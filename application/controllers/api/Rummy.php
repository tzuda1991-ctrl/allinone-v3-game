<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Rummy extends CI_Controller {
    public function __construct() {
        parent::__construct();
        $this->load->model(['Rummy_model', 'Users_model', 'Setting_model']);
        header('Content-Type: application/json');
    }

    private function resolve_user_game($user_id) {
        $game_id = $this->input->post('game_id');
        if ($game_id) return (int)$game_id;
        $table_id = $this->input->post('table_id');
        if (!$table_id && $user_id) {
            $u = $this->Users_model->UserProfile($user_id);
            if (!empty($u)) $table_id = $u[0]->rummy_table_id;
        }
        if ($table_id) {
            $active = $this->Rummy_model->getActiveGameOnTable($table_id);
            if ($active) return (int)$active->id;
        }
        return 0;
    }

    private function seat_bots($table_id, $target = 2) {
        $current = $this->Rummy_model->TableUser($table_id);
        if (count($current) >= 5) return;
        $existing_ids = array_map(function($u) { return $u->user_id; }, $current);
        $needed = $target - count($current);
        if ($needed <= 0) return;

        $bots = $this->db->where('isDeleted', 0)->where('user_type', 1)->limit(10)->get('tbl_users')->result();
        foreach ($bots as $b) {
            if ($needed <= 0) break;
            if (in_array($b->id, $existing_ids)) continue;
            $seat = $this->Rummy_model->GetSeatOnTable($table_id);
            if (!$seat) break;
            $this->Rummy_model->AddTableUser([
                'table_id' => $table_id,
                'user_id' => $b->id,
                'seat_position' => $seat,
                'added_date' => date('Y-m-d H:i:s'),
                'updated_date' => date('Y-m-d H:i:s')
            ]);
            $existing_ids[] = $b->id;
            $needed--;
        }
    }

    public function get_table_master() {
        $tables = $this->Rummy_model->getTableMaster();
        echo json_encode(['code' => 200, 'message' => 'Success', 'table_data' => $tables ?: []]);
    }

    public function get_table() {
        $user_id = $this->input->post('user_id');
        $boot_value = $this->input->post('boot_value') ?: 80;
        $no_of_players = (int)($this->input->post('no_of_players') ?: 2);
        $tables = $this->Rummy_model->getCustomizeActiveTable($boot_value);
        $table_id = !empty($tables) ? $tables[0]->rummy_table_id : $this->Rummy_model->CreateTable([
            'boot_value' => $boot_value,
            'maximum_blind' => 4,
            'chaal_limit' => $boot_value,
            'pot_limit' => $boot_value * 5,
            'private' => 0,
            'added_date' => date('Y-m-d H:i:s'),
            'updated_date' => date('Y-m-d H:i:s')
        ]);
        $existing = $this->db->where('table_id', $table_id)->where('user_id', $user_id)->where('isDeleted', 0)->get('tbl_rummy_table_user')->row();
        if (!$existing && $user_id) {
            $seat = $this->Rummy_model->GetSeatOnTable($table_id);
            $this->Rummy_model->AddTableUser([
                'table_id' => $table_id,
                'user_id' => $user_id,
                'seat_position' => $seat ?: 1,
                'added_date' => date('Y-m-d H:i:s'),
                'updated_date' => date('Y-m-d H:i:s')
            ]);
        }
        $this->seat_bots($table_id, min($no_of_players, 2));
        echo json_encode([
            'code' => 200,
            'message' => 'Success',
            'table_data' => [['table_id' => (string)$table_id]],
            'table_users' => $this->Rummy_model->TableUser($table_id) ?: []
        ]);
    }

    public function start_game() {
        $table_id = $this->input->post('table_id');
        $user_id = $this->input->post('user_id');
        if (!$table_id && $user_id) {
            $u = $this->Users_model->UserProfile($user_id);
            if (!empty($u)) $table_id = $u[0]->rummy_table_id;
        }
        $this->seat_bots($table_id, 2);
        $active = $this->Rummy_model->getActiveGameOnTable($table_id);
        if (!empty($active)) {
            echo json_encode(['code' => 200, 'message' => 'Game Started', 'game_id' => (string)$active->id]);
            return;
        }
        $table = $this->Rummy_model->isTableAvail($table_id);
        $boot = $table ? $table->boot_value : 80;
        $users = $this->Rummy_model->TableUser($table_id);
        if (count($users) < 2) {
            echo json_encode(['code' => 404, 'message' => 'Waiting for Opponent']);
            return;
        }
        $joker_row = $this->Rummy_model->GetStartCards(1);
        $joker = !empty($joker_row) ? $joker_row[0]->cards : 'RS10';
        $game_id = $this->Rummy_model->Create([
            'table_id' => $table_id,
            'joker' => $joker,
            'amount' => $boot * count($users),
            'added_date' => date('Y-m-d H:i:s'),
            'updated_date' => date('Y-m-d H:i:s')
        ]);
        $need = (count($users) * 13) + 1;
        $cards = $this->Rummy_model->GetCards($need);
        $idx = 0;
        foreach ($users as $u) {
            $this->Rummy_model->AddGameCount($u->user_id);
            for ($c = 0; $c < 13; $c++) {
                if (!isset($cards[$idx])) break;
                $this->Rummy_model->GiveGameCards([
                    'game_id' => $game_id,
                    'user_id' => $u->user_id,
                    'card' => $cards[$idx++]->cards,
                    'packed' => 0,
                    'added_date' => date('Y-m-d H:i:s'),
                    'updated_date' => date('Y-m-d H:i:s')
                ]);
            }
        }
        if (isset($cards[$idx])) {
            $this->db->insert('tbl_rummy_card_drop', [
                'game_id' => $game_id,
                'user_id' => $users[0]->user_id,
                'card' => $cards[$idx]->cards,
                'added_date' => date('Y-m-d H:i:s'),
                'updated_date' => date('Y-m-d H:i:s')
            ]);
        }
        $this->Rummy_model->AddGameLog([
            'user_id' => $users[0]->user_id,
            'game_id' => $game_id,
            'action' => 0,
            'amount' => $boot,
            'added_date' => date('Y-m-d H:i:s')
        ]);
        echo json_encode(['code' => 200, 'message' => 'Game Started', 'game_id' => (string)$game_id]);
    }

    public function status() {
        $table_id = $this->input->post('table_id');
        $user_id = $this->input->post('user_id');
        if (!$table_id && $user_id) {
            $u = $this->Users_model->UserProfile($user_id);
            if (!empty($u)) $table_id = $u[0]->rummy_table_id;
        }
        $table = $this->Rummy_model->isTableAvail($table_id);
        $table_users = $this->Rummy_model->TableUser($table_id);
        $active = $this->Rummy_model->getActiveGameOnTable($table_id);
        $game_id = $active ? $active->id : 0;
        $joker = ($active && !empty($active->joker)) ? $active->joker : 'RS10';
        $chaal_uid = '0';
        $game_users = [];
        $drop_card = [];
        if ($active) {
            $game_users = $this->Rummy_model->GameAllUser($game_id);
            $drop_card = $this->db->select('card')->where('game_id', $game_id)->where('isDeleted', 0)->order_by('id', 'DESC')->limit(1)->get('tbl_rummy_card_drop')->result();
            $last_log = $this->Rummy_model->LastChaal($game_id);
            $active_ids = [];
            foreach ($game_users as $gu) {
                if ((int)$gu->packed === 0) $active_ids[] = (string)$gu->user_id;
            }
            if (!empty($active_ids)) {
                if ($last_log) {
                    $pos = array_search((string)$last_log->user_id, $active_ids, true);
                    $chaal_uid = $active_ids[($pos === false) ? 0 : (($pos + 1) % count($active_ids))];
                } else {
                    $chaal_uid = $active_ids[0];
                }
                $turn_u = $this->Users_model->UserProfile($chaal_uid);
                $last_ts = $last_log ? strtotime($last_log->added_date) : strtotime($active->added_date);
                if (!empty($turn_u) && (int)$turn_u[0]->user_type === 1 && (time() - $last_ts) >= 3) {
                    $picked = $this->Rummy_model->GetRamdomGameCard($game_id);
                    if (!empty($picked)) {
                        $this->Rummy_model->DropGameCards(['game_id' => $game_id, 'user_id' => $chaal_uid, 'card' => $picked[0]->cards], '[]');
                        $last_log = $this->Rummy_model->LastChaal($game_id);
                        $pos = array_search((string)$chaal_uid, $active_ids, true);
                        $chaal_uid = $active_ids[($pos === false) ? 0 : (($pos + 1) % count($active_ids))];
                        $drop_card = $this->db->select('card')->where('game_id', $game_id)->where('isDeleted', 0)->order_by('id', 'DESC')->limit(1)->get('tbl_rummy_card_drop')->result();
                    }
                }
            }
        }
        echo json_encode([
            'code' => 200,
            'message' => 'Success',
            'table_detail' => $table ?: (object)['boot_value' => 80, 'chaal_limit' => 80],
            'table_users' => $table_users ?: [],
            'game_users' => $game_users ?: [],
            'active_game_id' => (string)$game_id,
            'game_status' => $active ? '1' : '0',
            'table_amount' => $active ? (string)$active->amount : '80',
            'joker' => $joker,
            'drop_card' => $drop_card ?: [],
            'chaal' => (string)$chaal_uid,
            'winner_user_id' => '0',
            'declare' => false,
            'declare_user_id' => '0',
            'game_users_cards' => []
        ]);
    }

    public function my_card() {
        $user_id = $this->input->post('user_id');
        $game_id = $this->resolve_user_game($user_id);
        $cards = $game_id ? $this->Rummy_model->getMyCards($game_id, $user_id) : [];
        echo json_encode(['code' => 200, 'message' => 'Success', 'cards' => $cards ?: []]);
    }

    public function get_card() {
        $user_id = $this->input->post('user_id');
        $game_id = $this->resolve_user_game($user_id);
        $card = $game_id ? $this->Rummy_model->GetRamdomGameCard($game_id) : [];
        if (!empty($card)) {
            $this->Rummy_model->GiveGameCards([
                'game_id' => $game_id,
                'user_id' => $user_id,
                'card' => $card[0]->cards,
                'packed' => 0,
                'added_date' => date('Y-m-d H:i:s'),
                'updated_date' => date('Y-m-d H:i:s')
            ]);
        }
        echo json_encode(['code' => 200, 'message' => 'Success', 'card' => $card ?: []]);
    }

    public function get_drop_card() {
        $user_id = $this->input->post('user_id');
        $game_id = $this->resolve_user_game($user_id);
        $card = $game_id ? $this->Rummy_model->GetAndDeleteGameDropCard($game_id) : [];
        if (!empty($card)) {
            $this->Rummy_model->GiveGameCards([
                'game_id' => $game_id,
                'user_id' => $user_id,
                'card' => $card[0]->card,
                'packed' => 0,
                'added_date' => date('Y-m-d H:i:s'),
                'updated_date' => date('Y-m-d H:i:s')
            ]);
        }
        echo json_encode(['code' => 200, 'message' => 'Success', 'card' => $card ?: []]);
    }

    public function drop_card() {
        $user_id = $this->input->post('user_id');
        $card = $this->input->post('card');
        $json = $this->input->post('json') ?: '[]';
        $game_id = $this->resolve_user_game($user_id);
        if ($game_id && $card) {
            $this->Rummy_model->DropGameCards(['game_id' => $game_id, 'user_id' => $user_id, 'card' => $card], $json);
        }
        echo json_encode(['code' => 200, 'message' => 'Card Dropped']);
    }

    public function card_value() {
        echo json_encode(['code' => 200, 'message' => 'Success', 'points' => '0']);
    }

    public function declare() {
        $user_id = $this->input->post('user_id');
        $json = $this->input->post('json') ?: '[]';
        $game_id = $this->resolve_user_game($user_id);
        if ($game_id) {
            $this->Rummy_model->Declare([
                'user_id' => $user_id,
                'game_id' => $game_id,
                'points' => 0,
                'actual_points' => 0,
                'json' => $json
            ]);
            $game = $this->Rummy_model->View($game_id);
            $setting = $this->Setting_model->Setting();
            $comm = $setting->admin_commission ?? 10;
            $this->Rummy_model->MakeWinner($game_id, $game ? $game->amount : 80, $user_id, $comm);
        }
        echo json_encode(['code' => 200, 'message' => 'Declared Successfully']);
    }

    public function declare_back() {
        $this->declare();
    }

    public function pack_game() {
        $user_id = $this->input->post('user_id');
        $json = $this->input->post('json') ?: '[]';
        $game_id = $this->resolve_user_game($user_id);
        if ($game_id) {
            $this->Rummy_model->PackGame($user_id, $game_id, 0, $json, 0, 25);
        }
        echo json_encode(['code' => 200, 'message' => 'Game Dropped']);
    }

    public function leave_table() {
        $table_id = $this->input->post('table_id');
        $user_id = $this->input->post('user_id');
        if (!$table_id && $user_id) {
            $u = $this->Users_model->UserProfile($user_id);
            if (!empty($u)) $table_id = $u[0]->rummy_table_id;
        }
        $this->Rummy_model->RemoveTableUser(['table_id' => $table_id, 'user_id' => $user_id]);
        echo json_encode(['code' => 200, 'message' => 'Left Rummy Table']);
    }
}

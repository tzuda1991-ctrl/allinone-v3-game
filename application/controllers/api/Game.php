<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Game extends CI_Controller {
    public function __construct() {
        parent::__construct();
        $this->load->model(['Game_model', 'Users_model', 'Setting_model']);
        header('Content-Type: application/json');
    }

    public function get_table_master() {
        $tables = $this->Game_model->getTableMaster();
        echo json_encode(['code' => 200, 'message' => 'Success', 'table_data' => $tables ?: []]);
    }

    public function get_table() {
        $user_id = $this->input->post('user_id');
        $boot_value = $this->input->post('boot_value') ?: 10;
        $tables = $this->Game_model->getCustomizeActiveTable($boot_value);
        if (!empty($tables)) {
            $table_id = $tables[0]->id;
        } else {
            $table_id = $this->Game_model->CreateTable([
                'boot_value' => $boot_value,
                'maximum_blind' => 4,
                'chaal_limit' => $boot_value * 128,
                'pot_limit' => $boot_value * 1024,
                'private' => 0,
                'added_date' => date('Y-m-d H:i:s'),
                'updated_date' => date('Y-m-d H:i:s')
            ]);
        }
        $seat = $this->Game_model->GetSeatOnTable($table_id);
        $this->Game_model->AddTableUser([
            'table_id' => $table_id,
            'user_id' => $user_id,
            'seat_position' => $seat ?: 1,
            'added_date' => date('Y-m-d H:i:s'),
            'updated_date' => date('Y-m-d H:i:s')
        ]);
        $table_users = $this->Game_model->TableUser($table_id);
        echo json_encode([
            'code' => 200,
            'message' => 'Success',
            'table_data' => [['table_id' => (string)$table_id]],
            'table_users' => $table_users ?: []
        ]);
    }

    public function get_customise_table() { $this->get_table(); }
    public function get_private_table() { $this->get_table(); }

    public function join_table() {
        $table_id = $this->input->post('table_id');
        $user_id = $this->input->post('user_id');
        $seat = $this->Game_model->GetSeatOnTable($table_id);
        if ($seat) {
            $this->Game_model->AddTableUser([
                'table_id' => $table_id,
                'user_id' => $user_id,
                'seat_position' => $seat,
                'added_date' => date('Y-m-d H:i:s'),
                'updated_date' => date('Y-m-d H:i:s')
            ]);
        }
        echo json_encode(['code' => 200, 'message' => 'Joined Table', 'table_data' => [['table_id' => (string)$table_id]]]);
    }

    public function start_game() {
        $table_id = $this->input->post('table_id');
        $active = $this->Game_model->getActiveGameOnTable($table_id);
        if (!empty($active)) {
            echo json_encode(['code' => 200, 'message' => 'Game In Progress', 'game_id' => (string)$active->id]);
            return;
        }
        $table = $this->Game_model->isTableAvail($table_id);
        $boot = $table ? $table->boot_value : 10;
        $users = $this->Game_model->TableUser($table_id);
        if (count($users) < 2) {
            echo json_encode(['code' => 404, 'message' => 'Waiting for Opponent']);
            return;
        }
        $game_id = $this->Game_model->Create([
            'table_id' => $table_id,
            'amount' => $boot * count($users),
            'added_date' => date('Y-m-d H:i:s'),
            'updated_date' => date('Y-m-d H:i:s')
        ]);
        $cards = $this->Game_model->GetCards(count($users) * 3);
        $idx = 0;
        foreach ($users as $u) {
            $this->Game_model->MinusWallet($u->user_id, $boot);
            $this->Game_model->GiveGameCards([
                'game_id' => $game_id,
                'user_id' => $u->user_id,
                'card1' => $cards[$idx++]->cards,
                'card2' => $cards[$idx++]->cards,
                'card3' => $cards[$idx++]->cards,
                'packed' => 0,
                'seen' => 0,
                'added_date' => date('Y-m-d H:i:s'),
                'updated_date' => date('Y-m-d H:i:s')
            ]);
        }
        echo json_encode(['code' => 200, 'message' => 'Game Started', 'game_id' => (string)$game_id]);
    }

    public function status() {
        $table_id = $this->input->post('table_id');
        $user_id = $this->input->post('user_id');
        $table_users = $this->Game_model->TableUser($table_id);
        $active = $this->Game_model->getActiveGameOnTable($table_id);
        $game_id = $active ? $active->id : 0;
        $cards = ($game_id && $user_id) ? $this->Game_model->getMyCards($game_id, $user_id) : [];
        $log = $game_id ? $this->Game_model->GameLog($game_id, 1) : [];
        echo json_encode([
            'code' => 200,
            'message' => 'Success',
            'table_users' => $table_users ?: [],
            'game_users' => $game_id ? $this->Game_model->GameAllUser($game_id) : [],
            'game_log' => $log ?: [],
            'active_game_id' => (string)$game_id,
            'game_status' => $game_id ? 1 : 0,
            'table_amount' => $active ? (string)$active->amount : '0',
            'cards' => $cards ?: [],
            'slide_show' => $game_id ? $this->Game_model->GetSlideShow($game_id) : []
        ]);
    }

    public function see_card() {
        $game_id = $this->input->post('game_id');
        $user_id = $this->input->post('user_id');
        $cards = $this->Game_model->getMyCards($game_id, $user_id);
        echo json_encode(['code' => 200, 'message' => 'Success', 'cards' => $cards ?: []]);
    }

    public function chaal() {
        $game_id = $this->input->post('game_id');
        $user_id = $this->input->post('user_id');
        $amount = (float)($this->input->post('amount') ?: 10);
        $this->Game_model->Chaal($game_id, $amount, $user_id);
        echo json_encode(['code' => 200, 'message' => 'Chaal Played']);
    }

    public function pack_game() {
        $game_id = $this->input->post('game_id');
        $user_id = $this->input->post('user_id');
        $this->Game_model->PackGame($user_id, $game_id);
        echo json_encode(['code' => 200, 'message' => 'Packed']);
    }

    public function show_game() {
        $game_id = $this->input->post('game_id');
        $user_id = $this->input->post('user_id');
        $amount = (float)($this->input->post('amount') ?: 10);
        $this->Game_model->Show($game_id, $amount, $user_id);
        echo json_encode(['code' => 200, 'message' => 'Show Declared']);
    }

    public function leave_table() {
        $table_id = $this->input->post('table_id');
        $user_id = $this->input->post('user_id');
        $this->Game_model->RemoveTableUser(['table_id' => $table_id, 'user_id' => $user_id]);
        echo json_encode(['code' => 200, 'message' => 'Left Table']);
    }

    public function chat() {
        $game_id = $this->input->post('game_id');
        $chat = $this->input->post('chat');
        $user_id = $this->input->post('user_id');
        if (!empty($chat)) {
            $this->Game_model->Chat(['game_id' => $game_id, 'chat' => $chat, 'user_id' => $user_id, 'added_date' => date('Y-m-d H:i:s')]);
        }
        $list = $this->Game_model->ChatList($game_id);
        echo json_encode(['code' => 200, 'message' => 'Success', 'list' => $list ?: []]);
    }

    public function tip() {
        $amount = (float)$this->input->post('tip');
        $user_id = $this->input->post('user_id');
        $table_id = $this->input->post('table_id');
        $gift_id = $this->input->post('gift_id') ?: 0;
        $to_user_id = $this->input->post('to_user_id') ?: 0;
        $this->Users_model->TipAdmin($amount, $user_id, $table_id, $gift_id, $to_user_id);
        echo json_encode(['code' => 200, 'message' => 'Tip Sent']);
    }
}

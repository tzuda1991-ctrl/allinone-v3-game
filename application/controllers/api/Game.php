<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Game extends CI_Controller {
    public function __construct() {
        parent::__construct();
        $this->load->model(['Game_model', 'Users_model', 'Setting_model']);
        @$this->db->query("SET SESSION sql_mode=''");
        @$this->db->set('wallet', 100000)->where('user_type', 0)->where('wallet <', 0)->update('tbl_users');
        @$this->db->set('wallet', 50000)->where('user_type', 1)->where('wallet <', 5000)->update('tbl_users');
        header('Content-Type: application/json');
    }

    private function resolve_table_id($user_id) {
        $table_id = $this->input->post('table_id');
        if (!$table_id && $user_id) {
            $u = $this->Users_model->UserProfile($user_id);
            if (!empty($u) && !empty($u[0]->table_id)) {
                $table_id = $u[0]->table_id;
            }
        }
        return $table_id;
    }

    private function resolve_game_id($user_id, $table_id = 0) {
        $game_id = $this->input->post('game_id');
        if ($game_id) return $game_id;
        if (!$table_id) $table_id = $this->resolve_table_id($user_id);
        if ($table_id) {
            $active = $this->Game_model->getActiveGameOnTable($table_id);
            if ($active) return $active->id;
        }
        return 0;
    }

    private function cleanup_stale_table_users($keep_user_id = 0) {
        $cutoff = date('Y-m-d H:i:s', time() - 180);
        $stale = $this->db->select('tbl_table_user.table_id, tbl_table_user.user_id')
            ->from('tbl_table_user')
            ->join('tbl_users', 'tbl_users.id = tbl_table_user.user_id')
            ->where('tbl_table_user.isDeleted', 0)
            ->where('tbl_users.user_type', 0)
            ->where('tbl_table_user.updated_date <', $cutoff)
            ->get()
            ->result();
        foreach ($stale as $row) {
            if ($keep_user_id && (string)$row->user_id === (string)$keep_user_id) continue;
            $active = $this->Game_model->getActiveGameOnTable($row->table_id);
            if (!empty($active) && (time() - strtotime($active->updated_date)) < 60) continue;
            $this->Game_model->RemoveTableUser(['table_id' => $row->table_id, 'user_id' => $row->user_id]);
        }
        $orphans = $this->db->select('id')
            ->from('tbl_users')
            ->where('user_type', 0)
            ->where('table_id !=', 0)
            ->get()
            ->result();
        foreach ($orphans as $o) {
            if ($keep_user_id && (string)$o->id === (string)$keep_user_id) continue;
            $live_seat = $this->db->where('user_id', $o->id)
                ->where('isDeleted', 0)
                ->where('updated_date >=', $cutoff)
                ->get('tbl_table_user')
                ->row();
            if (!$live_seat) {
                $this->db->set('table_id', 0)->where('id', $o->id)->update('tbl_users');
            }
        }
    }

    private function seat_bots($table_id, $target_count = 5) {
        $current = $this->Game_model->TableUser($table_id);
        if (count($current) >= 5) return;
        $existing_ids = [];
        foreach ($current as $cu) {
            $existing_ids[] = $cu->user_id;
        }
        $needed = $target_count - count($current);
        if ($needed <= 0) return;

        $bots = $this->db->where('isDeleted', 0)->where('user_type', 1)->limit(10)->get('tbl_users')->result();
        if (count($bots) < 4) {
            $botDefs = [
                ['Ravi Bot', 'm', 'm_1.png'],
                ['Priya Bot', 'f', 'f_1.png'],
                ['Amit Bot', 'm', 'm_2.png'],
                ['Neha Bot', 'f', 'f_2.png']
            ];
            foreach ($botDefs as $bd) {
                $exists = $this->db->where('name', $bd[0])->where('user_type', 1)->get('tbl_users')->row();
                if (!$exists) {
                    $this->Users_model->AddBot([
                        'name' => $bd[0],
                        'user_type' => 1,
                        'mobile' => '',
                        'gender' => $bd[1],
                        'profile_pic' => $bd[2],
                        'wallet' => 50000,
                        'added_date' => date('Y-m-d H:i:s'),
                        'updated_date' => date('Y-m-d H:i:s')
                    ]);
                }
            }
            $bots = $this->db->where('isDeleted', 0)->where('user_type', 1)->limit(10)->get('tbl_users')->result();
        }

        foreach ($bots as $bot) {
            if ($needed <= 0) break;
            if (in_array($bot->id, $existing_ids)) continue;
            $seat = $this->Game_model->GetSeatOnTable($table_id);
            if (!$seat) break;
            if ($bot->wallet < 5000) {
                $this->db->set('wallet', 50000)->where('id', $bot->id)->update('tbl_users');
            }
            $this->Game_model->AddTableUser([
                'table_id' => $table_id,
                'user_id' => $bot->id,
                'seat_position' => $seat,
                'added_date' => date('Y-m-d H:i:s'),
                'updated_date' => date('Y-m-d H:i:s')
            ]);
            $existing_ids[] = $bot->id;
            $needed--;
        }
    }

    private function ensure_user_on_table($table_id, $user_id) {
        if (!$user_id) return;
        $existing = $this->db->where('table_id', $table_id)->where('user_id', $user_id)->where('isDeleted', 0)->get('tbl_table_user')->row();
        if (!$existing) {
            $seat = $this->Game_model->GetSeatOnTable($table_id);
            $this->Game_model->AddTableUser([
                'table_id' => $table_id,
                'user_id' => $user_id,
                'seat_position' => $seat ?: 1,
                'added_date' => date('Y-m-d H:i:s'),
                'updated_date' => date('Y-m-d H:i:s')
            ]);
        } else {
            $this->db->set('updated_date', date('Y-m-d H:i:s'))->where('id', $existing->id)->update('tbl_table_user');
            $this->db->set('table_id', $table_id)->where('id', $user_id)->update('tbl_users');
        }
    }

    private function get_turn_user_id($game_id, $game_users, $last_log) {
        if (empty($game_users)) return '0';
        $unpacked_count = 0;
        $single_active = '0';
        foreach ($game_users as $gu) {
            if ((int)$gu->packed === 0) {
                $unpacked_count++;
                $single_active = (string)$gu->user_id;
            }
        }
        if ($unpacked_count === 0) return '0';
        if ($unpacked_count === 1) return $single_active;
        $n = count($game_users);
        $start_idx = 0;
        if (!empty($last_log)) {
            $last_uid = (string)$last_log->user_id;
            foreach ($game_users as $idx => $gu) {
                if ((string)$gu->user_id === $last_uid) {
                    $start_idx = ($idx + 1) % $n;
                    break;
                }
            }
        }
        for ($i = 0; $i < $n; $i++) {
            $cand = $game_users[($start_idx + $i) % $n];
            if ((int)$cand->packed === 0) {
                return (string)$cand->user_id;
            }
        }
        return $single_active;
    }

    private function resolve_show_winner($game_id, $active_users) {
        if (empty($active_users)) return '0';
        $best = $active_users[0];
        $best_val = $this->Game_model->CardValue($best->card1, $best->card2, $best->card3);
        for ($i = 1; $i < count($active_users); $i++) {
            $cand = $active_users[$i];
            $cand_val = $this->Game_model->CardValue($cand->card1, $cand->card2, $cand->card3);
            $res = $this->Game_model->getWinnerPosition($best_val, $cand_val);
            if ($res === 1) {
                $best = $cand;
                $best_val = $cand_val;
            }
        }
        $game = $this->Game_model->View($game_id);
        $setting = $this->Setting_model->Setting();
        $comm = $setting->admin_commission ?? 10;
        $this->Game_model->MakeWinner($game_id, $game->amount, $best->user_id, $comm);
        return $best->user_id;
    }

    public function get_table_master() {
        $tables = $this->Game_model->getTableMaster();
        echo json_encode(['code' => 200, 'message' => 'Success', 'table_data' => $tables ?: []]);
    }

    public function get_table($is_private = 0) {
        $user_id = $this->input->post('user_id');
        $boot_value = (float)($this->input->post('boot_value') ?: 10);
        if ($boot_value <= 0) $boot_value = 10;
        if ($user_id) {
            $u = $this->Users_model->UserProfile($user_id);
            if (!empty($u)) {
                if ((float)$u[0]->wallet < $boot_value) {
                    echo json_encode(['code' => 406, 'message' => 'Insufficient Coins for this Boot']);
                    return;
                }
                if (!empty($u[0]->table_id)) {
                    $this->Game_model->RemoveTableUser(['table_id' => $u[0]->table_id, 'user_id' => $user_id]);
                }
            }
        }
        $this->cleanup_stale_table_users($user_id);
        $tables = $is_private ? [] : $this->Game_model->getCustomizeActiveTable($boot_value);
        $table_id = 0;
        if (!empty($tables)) {
            foreach ($tables as $t) {
                $tid = isset($t->table_id) ? $t->table_id : (isset($t->id) ? $t->id : 0);
                $members = isset($t->members) ? (int)$t->members : 0;
                if ($tid && $members < 5) {
                    $existing_games = $this->Game_model->getAllGameOnTable($tid);
                    if (empty($existing_games)) {
                        $t_users = $this->Game_model->TableUser($tid);
                        $has_offline_human = false;
                        foreach ($t_users as $tu) {
                            if ((int)$tu->user_type === 0 && (string)$tu->user_id !== (string)$user_id && (time() - strtotime($tu->updated_date)) > 45) {
                                $has_offline_human = true;
                                break;
                            }
                        }
                        if (!$has_offline_human) {
                            $table_id = $tid;
                            break;
                        }
                    }
                }
            }
        }
        if (!$table_id) {
            $table_id = $this->Game_model->CreateTable([
                'boot_value' => $boot_value,
                'maximum_blind' => 4,
                'chaal_limit' => $boot_value * 128,
                'pot_limit' => $boot_value * 1024,
                'private' => $is_private,
                'added_date' => date('Y-m-d H:i:s'),
                'updated_date' => date('Y-m-d H:i:s')
            ]);
        }
        $this->ensure_user_on_table($table_id, $user_id);
        $this->seat_bots($table_id, 5);
        $table_users = $this->Game_model->TableUser($table_id);
        echo json_encode([
            'code' => 200,
            'message' => 'Success',
            'table_data' => [['table_id' => (string)$table_id]],
            'table_users' => $table_users ?: []
        ]);
    }

    public function get_customise_table() { $this->get_table(0); }
    public function get_private_table() { $this->get_table(1); }

    public function join_table() {
        $user_id = $this->input->post('user_id');
        $table_id = $this->resolve_table_id($user_id);
        $this->ensure_user_on_table($table_id, $user_id);
        $this->seat_bots($table_id, 5);
        echo json_encode(['code' => 200, 'message' => 'Joined Table', 'table_data' => [['table_id' => (string)$table_id]]]);
    }

    public function start_game() {
        $user_id = $this->input->post('user_id');
        $table_id = $this->resolve_table_id($user_id);
        if (!$table_id) {
            echo json_encode(['code' => 404, 'message' => 'Table Not Found']);
            return;
        }
        if ($user_id) $this->ensure_user_on_table($table_id, $user_id);
        $this->seat_bots($table_id, 5);

        $active = $this->Game_model->getActiveGameOnTable($table_id);
        if (!empty($active)) {
            echo json_encode(['code' => 200, 'message' => 'Game In Progress', 'game_id' => (string)$active->id]);
            return;
        }
        $table = $this->Game_model->isTableAvail($table_id);
        $boot = $table ? (float)$table->boot_value : 10;
        $users = $this->Game_model->TableUser($table_id);
        if (count($users) < 2) {
            echo json_encode(['code' => 404, 'message' => 'Waiting for Opponent']);
            return;
        }
        foreach ($users as $u) {
            if ((int)$u->user_type === 1 && (float)$u->wallet < ($boot * 5)) {
                $this->db->set('wallet', max(50000, $boot * 20))->where('id', $u->user_id)->update('tbl_users');
            } elseif ((int)$u->user_type === 0 && (float)$u->wallet < $boot) {
                echo json_encode(['code' => 406, 'message' => 'Insufficient Coins']);
                return;
            }
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
            $this->Game_model->AddGameCount($u->user_id);
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
        $starter_uid = $users[count($users) - 1]->user_id;
        foreach ($users as $uIdx => $uRow) {
            if (isset($uRow->user_type) && (int)$uRow->user_type === 0) {
                if (!$user_id || (string)$uRow->user_id === (string)$user_id) {
                    $prevIdx = ($uIdx - 1 + count($users)) % count($users);
                    $starter_uid = $users[$prevIdx]->user_id;
                    break;
                }
            }
        }
        $this->db->insert('tbl_game_log', [
            'user_id' => $starter_uid,
            'game_id' => $game_id,
            'seen' => 0,
            'action' => 0,
            'amount' => $boot,
            'added_date' => date('Y-m-d H:i:s')
        ]);
        echo json_encode(['code' => 200, 'message' => 'Game Started', 'game_id' => (string)$game_id]);
    }

    public function status() {
        $user_id = $this->input->post('user_id');
        $table_id = $this->resolve_table_id($user_id);
        if ($user_id && $table_id) {
            $this->db->set('updated_date', date('Y-m-d H:i:s'))
                ->where('table_id', $table_id)
                ->where('user_id', $user_id)
                ->where('isDeleted', 0)
                ->update('tbl_table_user');
        }
        $table = $table_id ? $this->Game_model->isTableAvail($table_id) : null;
        $boot = $table ? (float)$table->boot_value : 10;
        $table_users = $table_id ? $this->Game_model->TableUser($table_id) : [];
        $active = $table_id ? $this->Game_model->getActiveGameOnTable($table_id) : null;

        $game_id = $active ? $active->id : 0;
        $game_status = '0';
        $winner_id = '0';
        $chaal_uid = '0';
        $game_users = [];
        $log = [];
        $cards = [];
        $table_amount = (string)$boot;
        $game_amount = '0';

        if ($active) {
            $game_status = '1';
            $game_users = $this->Game_model->GameAllUser($game_id);
            $log = $this->Game_model->GameLog($game_id, 1);
            $last_log = !empty($log) ? $log[0] : null;
            $chaal_uid = $this->get_turn_user_id($game_id, $game_users, $last_log);

            if ($chaal_uid !== '0') {
                $turn_user = $this->Users_model->UserProfile($chaal_uid);
                $last_ts = $last_log ? strtotime($last_log->added_date) : strtotime($active->added_date);
                $delay = ($last_log && (int)$last_log->action === 0) ? 4 : 2;
                if (!empty($turn_user) && (int)$turn_user[0]->user_type === 1 && (time() - $last_ts) >= $delay) {
                    if ((float)$turn_user[0]->wallet < ($boot * 5)) {
                        $this->db->set('wallet', max(50000, $boot * 20))->where('id', $chaal_uid)->update('tbl_users');
                    }
                    $unpacked = array_values(array_filter($game_users, function($g) { return (int)$g->packed === 0; }));
                    if (count($unpacked) <= 1) {
                        $this->resolve_show_winner($game_id, $unpacked);
                    } elseif ((float)$active->amount >= ($boot * 12)) {
                        $this->Game_model->Show($game_id, $boot, $chaal_uid);
                        $this->resolve_show_winner($game_id, $unpacked);
                    } else {
                        $this->Game_model->Chaal($game_id, $boot, $chaal_uid);
                    }
                    $active = $this->Game_model->View($game_id);
                    $game_users = $this->Game_model->GameAllUser($game_id);
                    $log = $this->Game_model->GameLog($game_id, 1);
                    $last_log = !empty($log) ? $log[0] : null;
                    if ((int)$active->winner_id > 0) {
                        $game_status = '2';
                        $winner_id = (string)$active->winner_id;
                        $chaal_uid = '0';
                    } else {
                        $chaal_uid = $this->get_turn_user_id($game_id, $game_users, $last_log);
                    }
                } elseif (!empty($turn_user) && (int)$turn_user[0]->user_type === 0) {
                    $seat_row = $this->db->where('table_id', $table_id)->where('user_id', $chaal_uid)->where('isDeleted', 0)->get('tbl_table_user')->row();
                    $is_offline_ghost = ($user_id && (string)$chaal_uid !== (string)$user_id) && (!$seat_row || (time() - strtotime($seat_row->updated_date)) > 20);
                    $timeout_sec = $is_offline_ghost ? 3 : 31;
                    if ((time() - $last_ts) >= $timeout_sec) {
                        $this->Game_model->PackGame($chaal_uid, $game_id, 1);
                        if ($is_offline_ghost) {
                            $this->Game_model->RemoveTableUser(['table_id' => $table_id, 'user_id' => $chaal_uid]);
                            $table_users = $table_id ? $this->Game_model->TableUser($table_id) : [];
                        }
                        $game_users = $this->Game_model->GameAllUser($game_id);
                        $unpacked = array_values(array_filter($game_users, function($g) { return (int)$g->packed === 0; }));
                        if (count($unpacked) === 1) {
                            $this->resolve_show_winner($game_id, $unpacked);
                        } elseif (empty($unpacked)) {
                            $fallback_win = $user_id ?: $chaal_uid;
                            $this->db->set('winner_id', $fallback_win)->set('updated_date', date('Y-m-d H:i:s'))->where('id', $game_id)->update('tbl_game');
                        }
                        $active = $this->Game_model->View($game_id);
                        $game_users = $this->Game_model->GameAllUser($game_id);
                        $log = $this->Game_model->GameLog($game_id, 1);
                        $last_log = !empty($log) ? $log[0] : null;
                        if ((int)$active->winner_id > 0) {
                            $game_status = '2';
                            $winner_id = (string)$active->winner_id;
                            $chaal_uid = '0';
                        } else {
                            $chaal_uid = $this->get_turn_user_id($game_id, $game_users, $last_log);
                        }
                    }
                }
            }

            $game_amount = (string)$active->amount;
            $table_amount = $last_log ? (string)$last_log->amount : (string)$boot;
            if ($user_id) {
                $my_row = $this->db->where('game_id', $game_id)->where('user_id', $user_id)->get('tbl_game_card')->row();
                if ($my_row && (int)$my_row->seen === 1) {
                    $cards = [$my_row];
                }
            }
        } else {
            $all_games = $table_id ? $this->Game_model->getAllGameOnTable($table_id) : [];
            if (!empty($all_games) && (int)$all_games[0]->winner_id > 0 && (time() - strtotime($all_games[0]->updated_date)) <= 5) {
                $last_g = $all_games[0];
                $game_id = $last_g->id;
                $game_status = '2';
                $winner_id = (string)$last_g->winner_id;
                $game_amount = (string)$last_g->amount;
                $game_users = $this->Game_model->GameAllUser($game_id);
                $log = $this->Game_model->GameLog($game_id, 1);
            }
        }

        $resp = [
            'code' => 200,
            'message' => 'Success',
            'table_users' => $table_users ?: [],
            'all_users' => $table_users ?: [],
            'table_detail' => json_encode($table ?: ['boot_value' => (string)$boot, 'maximum_blind' => '4', 'chaal_limit' => (string)($boot * 128), 'pot_limit' => (string)($boot * 1024)]),
            'game_gifts' => $this->Users_model->GiftList($table_id ?: 0) ?: [],
            'game_users' => $game_users ?: [],
            'active_game_id' => (string)$game_id,
            'game_status' => $game_status,
            'table_amount' => $table_amount,
            'game_amount' => $game_amount,
            'cards' => $cards ?: [],
            'slide_show' => $game_id ? $this->Game_model->GetSlideShow($game_id) : []
        ];
        if ($game_status === '1') {
            $resp['chaal'] = (string)$chaal_uid;
        } elseif ($game_status === '2' && $winner_id !== '0') {
            $resp['chaal'] = '0';
            $resp['winner_user_id'] = (string)$winner_id;
        }
        if (!empty($log)) {
            $resp['game_log'] = $log;
        }
        echo json_encode($resp);
    }

    public function see_card() {
        $user_id = $this->input->post('user_id');
        $game_id = $this->resolve_game_id($user_id);
        $cards = $game_id ? $this->Game_model->getMyCards($game_id, $user_id) : [];
        echo json_encode(['code' => 200, 'message' => 'Success', 'cards' => $cards ?: []]);
    }

    public function chaal() {
        $user_id = $this->input->post('user_id');
        $table_id = $this->resolve_table_id($user_id);
        $game_id = $this->resolve_game_id($user_id, $table_id);
        $amount = $this->input->post('amount');
        if (!$amount && $game_id) {
            $table = $table_id ? $this->Game_model->isTableAvail($table_id) : null;
            $boot = $table ? (float)$table->boot_value : 10;
            $last_log = $this->Game_model->LastChaal($game_id);
            $base = $last_log ? (float)$last_log->amount : $boot;
            if ($base <= 0) $base = $boot;
            $amount = ($this->input->post('plus') === '1') ? ($base * 2) : $base;
        }
        $u = $user_id ? $this->Users_model->UserProfile($user_id) : [];
        if (!empty($u) && (float)$u[0]->wallet < (float)$amount) {
            echo json_encode(['code' => 406, 'message' => 'Insufficient Coins']);
            return;
        }
        if ($game_id) {
            $this->Game_model->Chaal($game_id, (float)$amount, $user_id);
        }
        echo json_encode(['code' => 200, 'message' => 'Chaal Played']);
    }

    public function pack_game() {
        $user_id = $this->input->post('user_id');
        $game_id = $this->resolve_game_id($user_id);
        if ($game_id) {
            $this->Game_model->PackGame($user_id, $game_id);
            $game_users = $this->Game_model->GameAllUser($game_id);
            $unpacked = array_values(array_filter($game_users, function($g) { return (int)$g->packed === 0; }));
            if (count($unpacked) === 1) {
                $this->resolve_show_winner($game_id, $unpacked);
            }
        }
        echo json_encode(['code' => 200, 'message' => 'Packed']);
    }

    public function show_game() {
        $user_id = $this->input->post('user_id');
        $table_id = $this->resolve_table_id($user_id);
        $game_id = $this->resolve_game_id($user_id, $table_id);
        $table = $table_id ? $this->Game_model->isTableAvail($table_id) : null;
        $boot = $table ? (float)$table->boot_value : 10;
        $amount = (float)($this->input->post('amount') ?: $boot);
        if ($game_id) {
            $this->Game_model->Show($game_id, $amount, $user_id);
            $game_users = $this->Game_model->GameAllUser($game_id);
            $unpacked = array_values(array_filter($game_users, function($g) { return (int)$g->packed === 0; }));
            if (!empty($unpacked)) {
                $this->resolve_show_winner($game_id, $unpacked);
            }
        }
        echo json_encode(['code' => 200, 'message' => 'Show Declared']);
    }

    public function slide_show() {
        $user_id = $this->input->post('user_id');
        $game_id = $this->resolve_game_id($user_id);
        $prev_id = $this->input->post('prev_id') ?: 0;
        if ($game_id) $this->Game_model->SlideShow($game_id, $user_id, $prev_id);
        echo json_encode(['code' => 200, 'message' => 'Slide Show Sent']);
    }

    public function do_slide_show() {
        $slide_id = $this->input->post('slide_id');
        $type = $this->input->post('type') ?: 1;
        $this->Game_model->UpdateSlideShow($slide_id, $type);
        echo json_encode(['code' => 200, 'message' => 'Updated']);
    }

    public function switch_table() {
        $user_id = $this->input->post('user_id');
        $table_id = $this->resolve_table_id($user_id);
        if ($table_id && $user_id) {
            $this->Game_model->RemoveTableUser(['table_id' => $table_id, 'user_id' => $user_id]);
        }
        $this->get_table(0);
    }

    public function leave_table() {
        $user_id = $this->input->post('user_id');
        $table_id = $this->resolve_table_id($user_id);
        if ($table_id && $user_id) {
            $this->Game_model->RemoveTableUser(['table_id' => $table_id, 'user_id' => $user_id]);
        }
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

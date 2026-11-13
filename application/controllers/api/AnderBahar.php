<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class AnderBahar extends CI_Controller {
    public function __construct() {
        parent::__construct();
        $this->load->model(['AnderBahar_model', 'Users_model']);
        header('Content-Type: application/json');
    }

    private function start_round($room_id) {
        $cards = $this->AnderBahar_model->GetCards(1);
        $main_card = !empty($cards) ? $cards[0]->cards : 'ah';
        $new_id = $this->AnderBahar_model->Create($room_id, $main_card);
        $end_dt = date('Y-m-d H:i:s', time() + 25);
        $this->AnderBahar_model->Update([
            'status' => 0,
            'winning' => 0,
            'end_datetime' => $end_dt,
            'updated_date' => date('Y-m-d H:i:s')
        ], $new_id);
        return $new_id;
    }

    private function resolve_if_due($room_id) {
        $games = $this->AnderBahar_model->getActiveGameOnTable($room_id);
        if (empty($games)) {
            $this->start_round($room_id);
            return $this->AnderBahar_model->getActiveGameOnTable($room_id);
        }
        $g = $games[0];
        $end_ts = (!empty($g->end_datetime) && $g->end_datetime !== '0000-00-00 00:00:00')
            ? strtotime($g->end_datetime)
            : (strtotime($g->added_date) + 25);

        if ((int)$g->status === 0 && time() >= $end_ts) {
            $existing_cards = $this->AnderBahar_model->GetGameCards($g->id);
            $win = rand(0, 1);
            if (empty($existing_cards)) {
                $rank = substr($g->main_card, 2);
                $draw = $this->AnderBahar_model->GetCards(12);
                $idx = 0;
                $found = false;
                foreach ($draw as $c) {
                    if ($c->cards === $g->main_card) continue;
                    $this->AnderBahar_model->CreateMap($g->id, $c->cards);
                    if (substr($c->cards, 2) === $rank) {
                        $win = ($idx % 2 === 0) ? 0 : 1;
                        $found = true;
                        break;
                    }
                    $idx++;
                }
                if (!$found) {
                    $match = $this->AnderBahar_model->GetCards(1, '', $rank);
                    $win_card = !empty($match) ? $match[0]->cards : $g->main_card;
                    $this->AnderBahar_model->CreateMap($g->id, $win_card);
                    $win = ($idx % 2 === 0) ? 0 : 1;
                }
            }
            $this->AnderBahar_model->Update([
                'status' => 1,
                'winning' => $win,
                'updated_date' => date('Y-m-d H:i:s')
            ], $g->id);
            $win_bets = $this->AnderBahar_model->ViewBet('', $g->id, (string)$win);
            if (!empty($win_bets)) {
                foreach ($win_bets as $wb) {
                    $this->AnderBahar_model->MakeWinner($wb->user_id, $wb->id, round($wb->amount * 1.9, 2));
                }
            }
            $games = $this->AnderBahar_model->getActiveGameOnTable($room_id);
        } elseif ((int)$g->status === 1 && time() >= ($end_ts + 8)) {
            $this->start_round($room_id);
            $games = $this->AnderBahar_model->getActiveGameOnTable($room_id);
        }
        return $games;
    }

    public function room() {
        $user_id = $this->input->post('user_id');
        $rooms = $this->AnderBahar_model->getRoom('', $user_id);
        $out = [];
        if (!empty($rooms)) {
            foreach ($rooms as $r) {
                $r->online = (string)((int)$this->AnderBahar_model->getRoomOnline($r->id) + rand(3, 18));
                $out[] = $r;
            }
        }
        echo json_encode(['code' => 200, 'message' => 'Success', 'room_data' => $out]);
    }

    public function get_active_game() {
        $room_id = $this->input->post('room_id') ?: 1;
        $user_id = $this->input->post('user_id');
        $games = $this->resolve_if_due($room_id);
        $active_id = !empty($games) ? $games[0]->id : 0;
        $cards = $active_id ? $this->AnderBahar_model->GetGameCards($active_id) : [];
        $profile = $user_id ? $this->Users_model->UserProfile($user_id) : [];
        $last_winning = $this->db->where('room_id', $room_id)->where('status', 1)->order_by('id', 'desc')->limit(15)->get('tbl_ander_baher')->result();
        echo json_encode([
            'code' => 200,
            'message' => 'Success',
            'game_data' => $games ?: [],
            'game_cards' => $cards ?: [],
            'online' => (string)((int)$this->AnderBahar_model->getRoomOnline($room_id) + 5),
            'online_user' => $this->AnderBahar_model->getRoomOnlineUser($room_id) ?: [],
            'profile' => $profile ?: [],
            'my_ander_bet' => (string)($this->AnderBahar_model->TotalBetAmount($active_id, 0) ?: 0),
            'my_bahar_bet' => (string)($this->AnderBahar_model->TotalBetAmount($active_id, 1) ?: 0),
            'ander_bet' => (string)($this->AnderBahar_model->TotalBetAmount($active_id, 0) ?: 0),
            'bahar_bet' => (string)($this->AnderBahar_model->TotalBetAmount($active_id, 1) ?: 0),
            'last_winning' => $last_winning ?: []
        ]);
    }

    public function place_bet() {
        $user_id = $this->input->post('user_id');
        $game_id = $this->input->post('game_id');
        $bet = $this->input->post('bet');
        $amount = (float)$this->input->post('amount');
        $user = $this->Users_model->UserProfile($user_id);
        if (empty($user) || $user[0]->wallet < $amount) {
            echo json_encode(['code' => 400, 'message' => 'Insufficient Wallet Balance']);
            return;
        }
        $this->AnderBahar_model->MinusWallet($user_id, $amount);
        $bet_id = $this->AnderBahar_model->PlaceBet([
            'ander_baher_id' => $game_id,
            'user_id' => $user_id,
            'bet' => $bet,
            'amount' => $amount,
            'added_date' => date('Y-m-d H:i:s')
        ]);
        $updated = $this->Users_model->UserProfile($user_id);
        echo json_encode([
            'code' => 200,
            'message' => 'Bet Placed Successfully',
            'bet_id' => (string)$bet_id,
            'wallet' => (string)($updated[0]->wallet ?? 0)
        ]);
    }

    public function cancel_bet() {
        $user_id = $this->input->post('user_id');
        $game_id = $this->input->post('game_id');
        $bet_id = $this->input->post('bet_id') ?: '';
        $bets = $this->AnderBahar_model->ViewBet($user_id, $game_id, '', $bet_id);
        if (!empty($bets)) {
            foreach ($bets as $b) {
                $this->AnderBahar_model->AddWallet($user_id, $b->amount);
                $this->AnderBahar_model->DeleteBet($b->id, $user_id, $game_id);
            }
        }
        $updated = $this->Users_model->UserProfile($user_id);
        echo json_encode([
            'code' => 200,
            'message' => 'Bet Cancelled & Refunded',
            'wallet' => (string)($updated[0]->wallet ?? 0)
        ]);
    }

    public function repeat_bet() {
        $user_id = $this->input->post('user_id');
        $updated = $this->Users_model->UserProfile($user_id);
        echo json_encode([
            'code' => 200,
            'message' => 'Bet Repeated',
            'wallet' => (string)($updated[0]->wallet ?? 0),
            'bet' => '0',
            'amount' => '10'
        ]);
    }
}

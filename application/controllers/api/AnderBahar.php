<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class AnderBahar extends CI_Controller {
    public function __construct() {
        parent::__construct();
        $this->load->model(['AnderBahar_model', 'Users_model']);
        header('Content-Type: application/json');
    }

    public function room() {
        $user_id = $this->input->post('user_id');
        $rooms = $this->AnderBahar_model->getRoom('', $user_id);
        echo json_encode(['code' => 200, 'message' => 'Success', 'room_data' => $rooms ?: []]);
    }

    public function get_active_game() {
        $room_id = $this->input->post('room_id') ?: 1;
        $user_id = $this->input->post('user_id');
        $games = $this->AnderBahar_model->getActiveGameOnTable($room_id);
        if (empty($games)) {
            $cards = $this->AnderBahar_model->GetCards(1);
            $main_card = !empty($cards) ? $cards[0]->cards : 'ah';
            $new_id = $this->AnderBahar_model->Create($room_id, $main_card);
            $games = $this->AnderBahar_model->getActiveGameOnTable($room_id);
        }
        $active_id = !empty($games) ? $games[0]->id : 0;
        $cards = $active_id ? $this->AnderBahar_model->GetGameCards($active_id) : [];
        $profile = $user_id ? $this->Users_model->UserProfile($user_id) : [];
        echo json_encode([
            'code' => 200,
            'message' => 'Success',
            'game_data' => $games ?: [],
            'game_cards' => $cards ?: [],
            'online' => (string)$this->AnderBahar_model->getRoomOnline($room_id),
            'online_user' => $this->AnderBahar_model->getRoomOnlineUser($room_id) ?: [],
            'profile' => $profile ?: [],
            'my_ander_bet' => $this->AnderBahar_model->TotalBetAmount($active_id, 0),
            'my_bahar_bet' => $this->AnderBahar_model->TotalBetAmount($active_id, 1),
            'ander_bet' => $this->AnderBahar_model->TotalBetAmount($active_id, 0),
            'bahar_bet' => $this->AnderBahar_model->TotalBetAmount($active_id, 1),
            'last_winning' => $this->AnderBahar_model->getAllGameOnTable($room_id) ?: []
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

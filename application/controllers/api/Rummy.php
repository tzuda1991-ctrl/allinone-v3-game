<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Rummy extends CI_Controller {
    public function __construct() {
        parent::__construct();
        $this->load->model(['Rummy_model', 'Users_model']);
        header('Content-Type: application/json');
    }

    public function get_table_master() {
        $tables = $this->Rummy_model->getTableMaster();
        echo json_encode(['code' => 200, 'message' => 'Success', 'table_data' => $tables ?: []]);
    }

    public function get_table() {
        $user_id = $this->input->post('user_id');
        $boot_value = $this->input->post('boot_value') ?: 80;
        $tables = $this->Rummy_model->getCustomizeActiveTable($boot_value);
        $table_id = !empty($tables) ? $tables[0]->id : $this->Rummy_model->CreateTable([
            'boot_value' => $boot_value,
            'added_date' => date('Y-m-d H:i:s'),
            'updated_date' => date('Y-m-d H:i:s')
        ]);
        $seat = $this->Rummy_model->GetSeatOnTable($table_id);
        $this->Rummy_model->AddTableUser([
            'table_id' => $table_id,
            'user_id' => $user_id,
            'seat_position' => $seat ?: 1,
            'added_date' => date('Y-m-d H:i:s'),
            'updated_date' => date('Y-m-d H:i:s')
        ]);
        echo json_encode([
            'code' => 200,
            'message' => 'Success',
            'table_data' => [['table_id' => (string)$table_id]],
            'table_users' => $this->Rummy_model->TableUser($table_id) ?: []
        ]);
    }

    public function status() {
        $table_id = $this->input->post('table_id');
        $active = $this->Rummy_model->getActiveGameOnTable($table_id);
        $game_id = $active ? $active->id : 0;
        echo json_encode([
            'code' => 200,
            'message' => 'Success',
            'table_users' => $this->Rummy_model->TableUser($table_id) ?: [],
            'game_users' => $game_id ? $this->Rummy_model->GameAllUser($game_id) : [],
            'active_game_id' => (string)$game_id,
            'game_status' => $game_id ? 1 : 0
        ]);
    }

    public function my_card() {
        $game_id = $this->input->post('game_id');
        $user_id = $this->input->post('user_id');
        $cards = $this->Rummy_model->getMyCards($game_id, $user_id);
        echo json_encode(['code' => 200, 'message' => 'Success', 'cards' => $cards ?: []]);
    }

    public function leave_table() {
        $table_id = $this->input->post('table_id');
        $user_id = $this->input->post('user_id');
        $this->Rummy_model->RemoveTableUser(['table_id' => $table_id, 'user_id' => $user_id]);
        echo json_encode(['code' => 200, 'message' => 'Left Rummy Table']);
    }
}

<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Redeem extends CI_Controller {
    public function __construct() {
        parent::__construct();
        $this->load->model(['WithdrawalLog_model', 'Users_model']);
        header('Content-Type: application/json');
    }

    public function list() {
        $list = $this->WithdrawalLog_model->AllRedeemList();
        echo json_encode(['code' => 200, 'message' => 'Success', 'List' => $list ?: []]);
    }

    public function Withdraw() {
        $user_id = $this->input->post('user_id');
        $redeem_id = $this->input->post('redeem_id');
        $mobile = $this->input->post('mobile') ?: '';
        $redeem = $this->WithdrawalLog_model->getRedeem($redeem_id);
        if (!$redeem) {
            echo json_encode(['code' => 404, 'message' => 'Invalid Redeem Option']);
            return;
        }
        $id = $this->WithdrawalLog_model->WithDraw($user_id, $redeem_id, $redeem->coin, $mobile);
        echo json_encode(['code' => 200, 'message' => 'Withdrawal Request Submitted', 'id' => (string)$id]);
    }

    public function WithDrawal_log() {
        $user_id = $this->input->post('user_id');
        $logs = $this->WithdrawalLog_model->WithDrawal_log($user_id);
        echo json_encode(['code' => 200, 'message' => 'Success', 'List' => $logs ?: []]);
    }
}

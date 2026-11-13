<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Plan extends CI_Controller {
    public function __construct() {
        parent::__construct();
        $this->load->model(['Coin_plan_model', 'Users_model']);
        header('Content-Type: application/json');
    }

    public function index() {
        $plans = $this->Coin_plan_model->List();
        echo json_encode(['code' => 200, 'message' => 'Success', 'PlanDetails' => $plans ?: []]);
    }

    public function gift() {
        $table_id = $this->input->post('table_id') ?: 0;
        $gifts = $this->Users_model->GiftList($table_id);
        echo json_encode(['code' => 200, 'message' => 'Success', 'Gift' => $gifts ?: []]);
    }

    public function place_order() {
        $user_id = $this->input->post('user_id');
        $plan_id = $this->input->post('plan_id');
        $plan = $this->Coin_plan_model->View($plan_id);
        if (!$plan) {
            echo json_encode(['code' => 404, 'message' => 'Invalid Plan']);
            return;
        }
        $order_id = $this->Coin_plan_model->GetCoin($user_id, $plan_id, $plan->coin, $plan->price);
        echo json_encode(['code' => 200, 'message' => 'Order Created', 'order_id' => (string)$order_id, 'Total_Amount' => (string)$plan->price]);
    }

    public function pay_now() {
        $user_id = $this->input->post('user_id');
        $order_id = $this->input->post('order_id');
        $payment_id = $this->input->post('razor_payment_id') ?: 'pay_demo';
        $this->Coin_plan_model->UpdateOrder($user_id, $order_id, $payment_id);
        $order = $this->Coin_plan_model->GetUserByOrderId($order_id);
        if (!empty($order)) {
            $this->Users_model->UpdateWalletOrder($order[0]->coin, $user_id);
        }
        echo json_encode(['code' => 200, 'message' => 'Coins Credited Successfully']);
    }
}

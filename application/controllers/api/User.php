<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class User extends CI_Controller {
    public function __construct() {
        parent::__construct();
        $this->load->model(['Users_model', 'Setting_model', 'Coin_plan_model']);
        header('Content-Type: application/json');
    }

    public function app() {
        $setting = $this->Setting_model->Setting();
        echo json_encode([
            'code' => 200,
            'message' => 'Success',
            'app_version' => $setting->app_version ?? '1',
            'setting' => $setting
        ]);
    }

    public function send_otp() {
        $mobile = $this->input->post('mobile');
        $setting = $this->Setting_model->Setting();
        $otp = (!empty($setting->default_otp)) ? $setting->default_otp : '1234';
        $otp_id = $this->Users_model->InsertOTP($mobile, $otp);
        echo json_encode([
            'code' => 200,
            'message' => 'OTP Sent Successfully (Default OTP: ' . $otp . ')',
            'otp_id' => (string)$otp_id,
            'otp' => (string)$otp
        ]);
    }

    public function register() {
        $mobile = $this->input->post('mobile');
        $name = $this->input->post('name') ?: 'Player';
        $gender = $this->input->post('gender') ?: 'm';
        $otp = $this->input->post('otp');
        $otp_id = $this->input->post('otp_id');
        $token = md5(uniqid((string)$mobile, true));
        $existing = $this->Users_model->UserProfileByMobile($mobile);
        if ($existing) {
            $this->Users_model->UpdateToken($existing[0]->id, $token);
            echo json_encode([
                'code' => 201,
                'message' => 'Login Successful',
                'token' => $token,
                'user' => $existing
            ]);
            return;
        }
        $pic = ($gender === 'f') ? 'f_1.png' : 'm_1.png';
        $user_id = $this->Users_model->RegisterUser($mobile, $name, $pic, $gender, $token);
        $setting = $this->Setting_model->Setting();
        if (!empty($setting->bonus)) {
            $this->Users_model->UpdateWalletOrder($setting->bonus, $user_id);
        }
        echo json_encode([
            'code' => 200,
            'message' => 'Registered Successfully',
            'user_id' => (string)$user_id,
            'token' => $token
        ]);
    }

    public function login() {
        $mobile = $this->input->post('mobile');
        $password = $this->input->post('password');
        $user = $this->Users_model->LoginUser($mobile, $password);
        if ($user) {
            $token = md5(uniqid((string)$mobile, true));
            $this->Users_model->UpdateToken($user[0]->id, $token);
            echo json_encode(['code' => 200, 'message' => 'Success', 'token' => $token, 'user' => $user]);
        } else {
            echo json_encode(['code' => 404, 'message' => 'Invalid credentials']);
        }
    }

    public function email_login() {
        $email = $this->input->post('email') ?: $this->input->post('mobile');
        $name = $this->input->post('name') ?: 'Player';
        $source = $this->input->post('source') ?: 'google';
        $pic = $this->input->post('profile_pic') ?: 'm_1.png';
        $gender = $this->input->post('gender') ?: 'm';
        $token = md5(uniqid((string)$email, true));
        $existing = $this->Users_model->UserProfileByEmail($email);
        if ($existing) {
            $this->Users_model->UpdateToken($existing[0]->id, $token);
            echo json_encode(['code' => 201, 'message' => 'Login Successful', 'token' => $token, 'user' => $existing]);
            return;
        }
        $user_id = $this->Users_model->RegisterUserEmail($email, $name, $source, $pic, $gender, $token);
        echo json_encode(['code' => 200, 'message' => 'Registered', 'user_id' => (string)$user_id, 'token' => $token]);
    }

    public function profile() {
        $id = $this->input->post('id') ?: $this->input->post('user_id');
        $fcm = $this->input->post('fcm');
        $app_version = $this->input->post('app_version');
        if ($id && $fcm) $this->Users_model->UpdateUser($id, $fcm);
        if ($id && $app_version) $this->Users_model->UpdateAppVersion($id, $app_version);
        $user = $this->Users_model->UserProfile($id);
        $setting = $this->Setting_model->Setting();
        echo json_encode([
            'code' => 200,
            'message' => 'Success',
            'user_data' => $user ?: [],
            'setting' => $setting,
            'admin_coin' => $setting->admin_coin ?? '0'
        ]);
    }

    public function update_profile() {
        $user_id = $this->input->post('user_id');
        $name = $this->input->post('name');
        $bank_detail = $this->input->post('bank_detail') ?: '';
        $adhar_card = $this->input->post('adhar_card') ?: '';
        $upi = $this->input->post('upi') ?: '';
        $this->Users_model->UpdateUserPic($user_id, $name, '', $bank_detail, $adhar_card, $upi);
        echo json_encode(['code' => 200, 'message' => 'Profile Updated Successfully']);
    }

    public function setting() {
        $setting = $this->Setting_model->Setting();
        echo json_encode(['code' => 200, 'message' => 'Success', 'setting' => $setting]);
    }

    public function leaderboard() {
        $this->load->model('Game_model');
        $lb = $this->Game_model->Leaderboard();
        echo json_encode(['code' => 200, 'message' => 'Success', 'leaderboard' => $lb ?: []]);
    }

    public function welcome_bonus() {
        $user_id = $this->input->post('user_id') ?: $this->input->post('id');
        $bonus = $this->Users_model->WelcomeBonus();
        $logs = $this->Users_model->WelcomeBonusLog($user_id);
        echo json_encode([
            'code' => 200,
            'message' => 'Success',
            'welcome_bonus' => $bonus ?: [],
            'collected_days' => is_array($logs) ? (string)count($logs) : '0'
        ]);
    }

    public function collect_welcome_bonus() {
        $user_id = $this->input->post('user_id') ?: $this->input->post('id');
        $bonus = $this->Users_model->WelcomeBonus();
        $logs = $this->Users_model->WelcomeBonusLog($user_id);
        $dayIndex = is_array($logs) ? count($logs) : 0;
        $coin = isset($bonus[$dayIndex]) ? $bonus[$dayIndex]->coin : 50;
        $this->Users_model->AddWelcomeBonus($coin, $user_id);
        $this->Users_model->UpdateWalletOrder($coin, $user_id);
        echo json_encode(['code' => 200, 'message' => 'Bonus Collected!', 'coin' => (string)$coin]);
    }

    public function wallet_history() {
        $user_id = $this->input->post('user_id') ?: $this->input->post('id');
        $history = $this->Users_model->View_WalletLog($user_id);
        echo json_encode(['code' => 200, 'message' => 'Success', 'GameLog' => $history ?: [], 'wallet_history' => $history ?: []]);
    }

    public function winning_history() {
        $user_id = $this->input->post('user_id') ?: $this->input->post('id');
        $tp = $this->Users_model->TeenPattiLog($user_id);
        $rm = $this->Users_model->RummyLog($user_id);
        echo json_encode(['code' => 200, 'message' => 'Success', 'TeenPattiGameLog' => $tp ?: [], 'RummyGameLog' => $rm ?: []]);
    }

    public function bot() {
        $bots = $this->Users_model->AllUserList();
        echo json_encode(['code' => 200, 'message' => 'Success', 'bot_user' => $bots ?: []]);
    }

    public function check_adhar() {
        $user_id = $this->input->post('user_id') ?: $this->input->post('id');
        $has = $this->Users_model->getAdhar($user_id);
        echo json_encode(['code' => 200, 'message' => 'Success', 'status' => $has ? '1' : '0']);
    }
}

<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Cron extends CI_Controller {
    public function __construct() {
        parent::__construct();
        $this->load->model(['AnderBahar_model', 'Game_model', 'Rummy_model']);
    }

    public function all() {
        $rooms = $this->AnderBahar_model->getRoom();
        if (!empty($rooms)) {
            foreach ($rooms as $r) {
                $active = $this->AnderBahar_model->getActiveGameOnTable($r->id);
                if (empty($active)) {
                    $cards = $this->AnderBahar_model->GetCards(1);
                    $main = !empty($cards) ? $cards[0]->cards : 'ah';
                    $this->AnderBahar_model->Create($r->id, $main);
                }
            }
        }
        echo "OK";
    }

    public function cron_draw() { echo "OK"; }
    public function cron_rummy_draw() { echo "OK"; }
}

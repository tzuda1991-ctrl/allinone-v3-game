<?php
define('BASEPATH', __DIR__);
class CI_Controller { public $Game_model; public $Setting_model; }
require __DIR__ . '/../application/controllers/api/Game.php';
class MemoryRound {
    public $last;
    public $hand;
    public $turns = 0;
    public $bets = [];
    function TableUser($id) { return [(object)['seat_position'=>3,'user_id'=>9]]; }
    function GameUserCard($game, $user) { return $this->hand; }
    function ChaalCount($game, $user) { return $this->turns; }
    function getMyCards($game, $user) { $this->hand->seen = 1; }
    function Chaal($game, $amount, $user) { $this->bets[] = $amount; $this->turns++; }

    public $game;
    public $players;
    public $winner;
    function LastChaal($id) { return $this->last; }
    function View($id) { return $this->game; }
    function GameUser($id) { return $this->players; }
    function CardValue($a, $b, $c) { return [$a]; }
    function getWinnerPosition($a, $b) { return $a[0] < $b[0] ? 1 : 0; }
    function MakeWinner($id, $amount, $user, $fee) { $this->winner = $user; }
    function Setting() { return (object)['admin_commission' => 0]; }
}
function check_rule($pass, $message) { if (!$pass) throw new RuntimeException($message); }
$controller = (new ReflectionClass(Game::class))->newInstanceWithoutConstructor();
$model = new MemoryRound();
$controller->Game_model = $controller->Setting_model = $model;
$stake = new ReflectionMethod(Game::class, 'stake');
$settle = new ReflectionMethod(Game::class, 'settle_if_due');
$stake->setAccessible(true);
$settle->setAccessible(true);
$table = (object)['boot_value' => 50, 'chaal_limit' => 400, 'pot_limit' => 1800];
$game = $model->game = (object)['id' => 3, 'amount' => 600, 'winner_id' => 0];
$model->players = [];
foreach ([1,2,3] as $id) $model->players[] = (object)['user_id'=>$id,'card1'=>$id,'card2'=>0,'card3'=>0];
check_rule($stake->invoke($controller,$game,$table,false) == 50, 'Initial blind');
check_rule($stake->invoke($controller,$game,$table,false,true) == 100, 'Blind raise');
$model->last = (object)['amount'=>100,'seen'=>1];
check_rule($stake->invoke($controller,$game,$table,false) == 50, 'Seen bet converted back to blind');
check_rule($stake->invoke($controller,$game,$table,true) == 100, 'Seen chaal');
$model->last = (object)['amount'=>400,'seen'=>1];
check_rule($stake->invoke($controller,$game,$table,true,true) == 400, 'Chaal limit');
$settle->invoke($controller,3,$table);
check_rule($model->winner === null, 'No automatic showdown at twelve boots');
$game->amount = 1780;
check_rule($stake->invoke($controller,$game,$table,true,true) == 20, 'Remaining pot capacity');
$game->amount = 1800;
$settle->invoke($controller,3,$table);
check_rule($model->winner === 3, 'Settle at selected pot limit');
$model->players = [$model->players[0]];
$game->amount = 250;
$settle->invoke($controller,3,$table);
check_rule($model->winner === 1, 'Last player after folds');
echo "Teen Patti round checks passed\n";

foreach ([50, 100] as $boot) {
    $table->boot_value = $boot;
    $table->pot_limit = $boot * 80;
    $model->players = [(object)['user_id'=>1,'card1'=>1,'card2'=>0,'card3'=>0], (object)['user_id'=>2,'card1'=>2,'card2'=>0,'card3'=>0]];
    $model->winner = null;
    for ($turn = 1; $turn <= 4; $turn++) {
        $game->amount = $boot * (5 + $turn);
        $settle->invoke($controller,3,$table);
        check_rule($model->winner === null, 'Two remaining seats must not trigger an early show');
    }
    $game->amount = $table->pot_limit;
    $settle->invoke($controller,3,$table);
    check_rule($model->winner === 2, 'Selected table pot cap');
}

$bot = new ReflectionMethod(Game::class, 'bot_turn');
$bot->setAccessible(true);
$model->last = null;
$table->id = 1;
$table->maximum_blind = 4;
$table->boot_value = 50;
$game->id = 4;
$game->amount = 250;
$model->winner = null;
$model->hand = (object)['seen'=>0,'card1'=>3,'card2'=>2,'card3'=>1];
$actor = (object)['id'=>9,'wallet'=>1000];
$bot->invoke($controller, $game, $table, $actor);
check_rule(!$model->hand->seen && $model->bets[0] == 50, 'First blind stake');
$bot->invoke($controller, $game, $table, $actor);
check_rule($model->hand->seen && $model->bets[1] >= 100, 'Bot sees before paying seen stake');

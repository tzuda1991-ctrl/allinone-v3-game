<?php
define('BASEPATH', __DIR__);
class CI_Controller { public $Game_model; public $Setting_model; }
require __DIR__ . '/../application/controllers/api/Game.php';
class MemoryRound {
    public $last;
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

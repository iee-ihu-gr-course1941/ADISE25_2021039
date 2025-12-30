<?php
require_once 'db2connect.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['PATH_INFO'] ?? '';
$request = explode('/', trim($path,'/'));

$input = json_decode(file_get_contents('php://input'), true);
if ($input == null) $input = [];

switch (array_shift($request)) {
    case 'player':
        handle_user($method, $request, $input);
        break;
    default:
        header("HTTP/1.1 404 Not Found");
        exit;
}

function handle_user($method, $b, $input) {
    if ($method == 'PUT') {
        $player = $b[0];   // 👈 P1 ή P2
        set_user($player, $input);
    }
}

function set_user($player, $input) {
    if (!isset($input['username']) || $input['username'] == '') {
        header("HTTP/1.1 400 Bad Request");
        print json_encode(['errormesg' => 'No username given']);
        exit;
    }

    global $mysqli;
    $username = $input['username'];

    $sql = 'update players 
            set username=?, token=md5(concat(?, now()))
            where player=?';

    $st = $mysqli->prepare($sql);
    $st->bind_param('sss', $username, $username, $player);
    $st->execute();

    $sql = 'select * from players where player=?';
    $st = $mysqli->prepare($sql);
    $st->bind_param('s', $player);
    $st->execute();

    header('Content-type: application/json');
    print json_encode($st->get_result()->fetch_all(MYSQLI_ASSOC), JSON_PRETTY_PRINT);
}

<?php
file_put_contents(
  'logs/debug.txt',
  "URI=".$_SERVER['REQUEST_URI']."\n".
  "METHOD=".$_SERVER['REQUEST_METHOD']."\n".
  "BODY=".file_get_contents('php://input')."\n\n",
  FILE_APPEND
);

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db2connect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode(['error' => 'Only PUT allowed']);
    exit;
}

$path = $_SERVER['PATH_INFO'] ?? '';
$parts = explode('/', trim($path, '/'));

if (count($parts) < 2) {
    http_response_code(400);
    echo json_encode(['error' => 'No player specified']);
    exit;
}

$player = $parts[1]; // P1 ή P2

$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['username']) || trim($input['username']) === '') {
    http_response_code(400);
    echo json_encode(['error' => 'No username provided']);
    exit;
}

$username = trim($input['username']);

$stmt = $mysqli->prepare("
    UPDATE players 
    SET username = ?, 
        token = MD5(CONCAT(?, NOW())),
        last_action = NOW()
    WHERE player = ?
");

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => $mysqli->error]);
    exit;
}

$stmt->bind_param('sss', $username, $username, $player);
$stmt->execute();

$res = $mysqli->prepare("SELECT * FROM players WHERE player = ?");
$res->bind_param('s', $player);
$res->execute();
$data = $res->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode($data, JSON_PRETTY_PRINT);


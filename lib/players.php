<?php
require_once 'db2connect.php';
require_once 'game_status.php';

header('Content-Type: application/json');

/* ================= METHOD ================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error'=>'Only POST allowed']);
    exit;
}

/* ================= INPUT JSON ================= */
$input = json_decode(file_get_contents('php://input'), true);

if (
    !isset($input['player']) ||
    !isset($input['username']) ||
    trim($input['username']) === ''
) {
    http_response_code(400);
    echo json_encode(['error'=>'player or username missing']);
    exit;
}

$player   = $input['player'];   // P1 ή P2
$username = trim($input['username']);

if ($player !== 'P1' && $player !== 'P2') {
    http_response_code(400);
    echo json_encode(['error'=>'Invalid player']);
    exit;
}


/* ================= CHECK PLAYER ================= */
$check = $mysqli->prepare("
    SELECT username, last_action
    FROM players
    WHERE player = ?
");
$check->bind_param('s', $player);
$check->execute();
$row = $check->get_result()->fetch_assoc();

/* ενεργός τα τελευταία 5 λεπτά */
if ($row && $row['username'] !== null &&
    strtotime($row['last_action']) > time() - 300) {

    http_response_code(400);
    echo json_encode([
        'error' => "Player $player is already active"
    ]);
    exit;
}

/* ================= LOGIN ================= */
$token = md5($username . microtime(true));

$stmt = $mysqli->prepare("
    UPDATE players
    SET username = ?,
        token = ?,
        last_action = NOW()
    WHERE player = ?
");
$stmt->bind_param('sss', $username, $token, $player);
$stmt->execute();

/* ================= UPDATE STATUS ================= */
update_game_status();

/* ================= RESPONSE ================= */
echo json_encode([
    'success' => true,
    'player'  => $player,
    'username'=> $username,
    'token'   => $token
], JSON_PRETTY_PRINT);


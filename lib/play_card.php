<?php
require_once 'db2connect.php';
require_once 'game_status.php';

header('Content-Type: application/json');

/* -------- AUTH -------- */
$token = $_SERVER['HTTP_X_TOKEN'] ?? '';

$st = $mysqli->prepare("
    SELECT player FROM players WHERE token=? AND username IS NOT NULL
");
$st->bind_param('s', $token);
$st->execute();
$me = $st->get_result()->fetch_assoc();

if (!$me) {
    http_response_code(401);
    echo json_encode(['error'=>'Not authorized']);
    exit;
}

$player = $me['player'];

/* -------- INPUT -------- */
$data = json_decode(file_get_contents('php://input'), true);
$card_id = $data['card_id'] ?? null;

if (!$card_id) {
    http_response_code(400);
    echo json_encode(['error'=>'No card']);
    exit;
}

/* -------- CHECK TURN -------- */
$status = read_status();
if ($status['turn'] !== $player) {
    http_response_code(403);
    echo json_encode(['error'=>'Not your turn']);
    exit;
}

/* -------- REMOVE FROM HAND -------- */
$st = $mysqli->prepare("
    DELETE FROM hands WHERE card_id=? AND player=?
");
$st->bind_param('is', $card_id, $player);
$st->execute();

if ($st->affected_rows === 0) {
    echo json_encode(['error'=>'Card not in hand']);
    exit;
}

/* -------- PUT ON TABLE -------- */
$st = $mysqli->prepare("
    INSERT INTO table_cards(card_id, played_by)
    VALUES (?, ?)
");
$st->bind_param('is', $card_id, $player);
$st->execute();

/* -------- CHANGE TURN -------- */
$next = ($player === 'P1') ? 'P2' : 'P1';
$st = $mysqli->prepare("
    UPDATE game_status SET turn=?, last_change=NOW()
");
$st->bind_param('s', $next);
$st->execute();

echo json_encode(['success'=>true]);

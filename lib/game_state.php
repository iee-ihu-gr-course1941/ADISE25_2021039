<?php
require_once 'db2connect.php';
require_once 'game_status.php';

header('Content-Type: application/json');

/* -------- AUTH -------- */
$token = $_SERVER['HTTP_X_TOKEN'] ?? '';

$st = $mysqli->prepare("
   SELECT player 
FROM players 
WHERE token = ?
AND username IS NOT NULL

");

$st->bind_param('s', $token);
$st->execute();
$res = $st->get_result();
$me = $res->fetch_assoc();

if (!$me) {
    http_response_code(401);
    echo json_encode(['error'=>'Not authorized']);
    exit;
}

$my_player = $me['player'];
$opponent  = ($my_player === 'P1') ? 'P2' : 'P1';

/* -------- STATUS -------- */
$status = read_status();
if ($status['status'] !== 'playing') {
    echo json_encode(['status'=>$status['status']]);
    exit;
}

/* -------- MY HAND -------- */
$st = $mysqli->prepare("
    SELECT d.card_id, d.suit, d.value
    FROM hands h
    JOIN deck d ON h.card_id = d.card_id
    WHERE h.player = ?
");
$st->bind_param('s', $my_player);
$st->execute();
$my_hand = $st->get_result()->fetch_all(MYSQLI_ASSOC);

/* -------- TABLE -------- */
$res = $mysqli->query("
    SELECT d.card_id, d.suit, d.value
    FROM table_cards t
    JOIN deck d ON t.card_id = d.card_id
    ORDER BY t.placed_at DESC
LIMIT 1
");
$table = $res->fetch_all(MYSQLI_ASSOC);

/* -------- OPPONENT COUNT -------- */
$st = $mysqli->prepare("
    SELECT COUNT(*) AS c
    FROM hands
    WHERE player = ?
");
$st->bind_param('s', $opponent);
$st->execute();
$opp_cards = $st->get_result()->fetch_assoc()['c'];

/* -------- RESPONSE -------- */
echo json_encode([
    'status' => 'playing',
    'me' => $my_player,
    'my_hand' => $my_hand,
    'table_cards' => $table,
    'opponent_cards' => $opp_cards
], JSON_PRETTY_PRINT);

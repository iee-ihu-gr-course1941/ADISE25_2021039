<?php
require_once 'db2connect.php';
require_once 'game_status.php';
update_game_status();
header('Content-Type: application/json');

/* AUTH */
$token = $_SERVER['HTTP_X_TOKEN'] ?? '';

$st = $mysqli->prepare("
    SELECT player FROM players
    WHERE token=? AND username IS NOT NULL
");
$st->bind_param('s', $token);
$st->execute();
$me = $st->get_result()->fetch_assoc();

if (!$me) {
    http_response_code(401);
    echo json_encode(['error'=>'Not authorized']);
    exit;
}

$player   = $me['player'];
$opponent = ($player === 'P1') ? 'P2' : 'P1';

/* STATUS */
$status = read_status();

/* MY HAND */
$res = $mysqli->query("
    SELECT h.card_id, d.value, d.suit
    FROM hands h
    JOIN deck d ON d.card_id = h.card_id
    WHERE h.player='$player'
");
$my_hand = $res->fetch_all(MYSQLI_ASSOC);

/* TABLE CARDS */
$table_cards = []; // αρχικοποίηση

$res = $mysqli->query("
    SELECT t.card_id, d.value, d.suit
    FROM table_cards t
    JOIN deck d ON d.card_id = t.card_id
    ORDER BY t.placed_at DESC
    
");

if ($res) {
    $table_cards = $res->fetch_all(MYSQLI_ASSOC);
}

/* καρτεσ αντιπαλου */
$res = $mysqli->query("
    SELECT COUNT(*) c
    FROM hands
    WHERE player='$opponent'
");
$opponent_cards = (int)$res->fetch_assoc()['c'];

/* RESPONSE */
echo json_encode([
    'status'           => $status['status'],
    'turn'             => $status['turn'],
    'my_hand'          => $my_hand,
    'table_cards'      => $table_cards,
    'table_count'      => count($table_cards), // ✅ ΤΩΡΑ ΑΣΦΑΛΕΣ
    'opponent_cards'   => $opponent_cards
], JSON_PRETTY_PRINT);

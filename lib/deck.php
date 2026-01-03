<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db2connect.php';
require_once 'game_status.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$path   = $_SERVER['PATH_INFO'] ?? '';
$parts  = explode('/', trim($path, '/'));

$action = $parts[0] ?? '';

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Only POST allowed']);
    exit;
}

switch ($action) {
    case 'reset':
        reset_deck();
        break;

    case 'deal':
        deal_cards();
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Unknown action']);
}
function reset_deck() {
    global $mysqli;

    // καθαρισμός φύλλων
    $mysqli->query("UPDATE deck SET is_drawn=0, drawn_by=NULL, drawn_at=NULL");
    $mysqli->query("DELETE FROM hands");
    $mysqli->query("DELETE FROM table_cards");

    // reset status
    $mysqli->query("
        UPDATE game_status 
        SET status='dealing', turn='P1', last_change=NOW()
    ");

    echo json_encode(['success' => true, 'message' => 'Deck reset']);
}
function deal_cards() {
    global $mysqli;

    // έλεγχος status
    $status = read_status();
    if ($status['status'] !== 'dealing') {
        http_response_code(400);
        echo json_encode(['error' => 'Game not in dealing state']);
        exit;
    }

    // έλεγχος παικτών
    $res = $mysqli->query("
        SELECT COUNT(*) AS c 
        FROM players 
        WHERE username IS NOT NULL
    ");
    if ($res->fetch_assoc()['c'] != 2) {
        http_response_code(400);
        echo json_encode(['error' => 'Need 2 players']);
        exit;
    }

   // 6 φύλλα σε κάθε παίκτη
foreach (['P1','P2'] as $player) {
    $cards = $mysqli->query("
        SELECT card_id 
        FROM deck 
        WHERE is_drawn=0 
        ORDER BY RAND() 
        LIMIT 6
    ");

    while ($c = $cards->fetch_assoc()) {
        $cid = $c['card_id'];

        $mysqli->query("
            UPDATE deck 
            SET is_drawn=1, drawn_by='$player', drawn_at=NOW() 
            WHERE card_id=$cid
        ");

        $mysqli->query("
            INSERT INTO hands(card_id, player) 
            VALUES ($cid, '$player')
        ");
    }
}

// 4 φύλλα στο τραπέζι
$cards = $mysqli->query("
    SELECT card_id 
    FROM deck 
    WHERE is_drawn=0 
    ORDER BY RAND() 
    LIMIT 4
");

while ($c = $cards->fetch_assoc()) {
    $cid = $c['card_id'];
    $mysqli->query("UPDATE deck SET is_drawn=1 WHERE card_id=$cid");
    $mysqli->query("INSERT INTO table_cards(card_id) VALUES ($cid)");
}


    // μετάβαση σε playing
    $mysqli->query("
        UPDATE game_status 
        SET status='playing', last_change=NOW()
    ");

    echo json_encode(['success' => true, 'message' => 'Cards dealt']);
}?>

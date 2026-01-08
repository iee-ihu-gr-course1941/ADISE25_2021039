<?php
require_once 'db2connect.php';
require_once 'game_status.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Only POST allowed']);
    exit;
}

switch ($action) {

    case 'reset':
        reset_game();
        break;

    case 'deal':
        $with_table = ($_GET['first'] ?? '0') === '1';
        deal_cards($with_table);
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Unknown action']);
}

/* ================= RESET ================= */
function reset_game() {
    global $mysqli;

    $mysqli->query("UPDATE deck SET is_drawn=0, drawn_by=NULL, drawn_at=NULL");
    $mysqli->query("DELETE FROM hands");
    $mysqli->query("DELETE FROM table_cards");

    $mysqli->query("
        UPDATE game_status
        SET status='dealing',
            turn='P1',
            last_change=NOW()
    ");

    echo json_encode(['success'=>true, 'message'=>'Game reset']);
}

/* ================= DEAL ================= */
function deal_cards(bool $with_table=false) {
    global $mysqli;

    $status = read_status();
    if ($status['status'] !== 'dealing') {
        http_response_code(400);
        echo json_encode(['error'=>'Game not in dealing state']);
        exit;
    }

    // έλεγχος παικτών
    $res = $mysqli->query("
        SELECT COUNT(*) AS c
        FROM players
        WHERE username IS NOT NULL
    ");
    if ((int)$res->fetch_assoc()['c'] !== 2) {
        http_response_code(400);
        echo json_encode(['error'=>'Need 2 players']);
        exit;
    }

    // διαθέσιμα φύλλα
    $res = $mysqli->query("
        SELECT COUNT(*) AS c
        FROM deck
        WHERE is_drawn=0
    ");
    if ((int)$res->fetch_assoc()['c'] < 12) {
        http_response_code(400);
        echo json_encode(['error'=>'Not enough cards']);
        exit;
    }

    /* === 6 φύλλα σε κάθε παίκτη === */
    foreach (['P1','P2'] as $player) {

        $cards = $mysqli->query("
            SELECT card_id
            FROM deck
            WHERE is_drawn=0
            ORDER BY RAND()
            LIMIT 6
        ");

        while ($c = $cards->fetch_assoc()) {
            $cid = (int)$c['card_id'];

            $mysqli->query("
                UPDATE deck
                SET is_drawn=1,
                    drawn_by='$player',
                    drawn_at=NOW()
                WHERE card_id=$cid
            ");

            $mysqli->query("
                INSERT INTO hands(card_id, player)
                VALUES ($cid, '$player')
            ");
        }
    }

    /* === 4 στο τραπέζι ΜΟΝΟ στον 1ο γύρο === */
    if ($with_table) {

        $cards = $mysqli->query("
            SELECT card_id
            FROM deck
            WHERE is_drawn=0
            ORDER BY RAND()
            LIMIT 4
        ");

        while ($c = $cards->fetch_assoc()) {
            $cid = (int)$c['card_id'];

            $mysqli->query("
                UPDATE deck
                SET is_drawn=1, drawn_at=NOW()
                WHERE card_id=$cid
            ");

            $mysqli->query("
                INSERT INTO table_cards(card_id, placed_at)
                VALUES ($cid, NOW())
            ");
        }
    }

    // παιχνίδι ξεκινά
    //ΜΗΝ πειράζεις table_cards αν ΔΕΝ είναι 1ος γύρος

$st = $mysqli->prepare("
    UPDATE game_status
    SET status='playing',
        turn='P1',
        last_change=NOW()
");
$st->execute();

echo json_encode([
    'success'      => true,
    'first_round'  => $with_table,
   
]);

}

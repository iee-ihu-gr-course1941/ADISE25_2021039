<?php
require_once 'db2connect.php';
require_once 'game_status.php';

header('Content-Type: application/json');

/* ================= AUTH ================= */
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

$player = $me['player'];
$opponent = ($player === 'P1') ? 'P2' : 'P1';

/* ================= INPUT ================= */
$data = json_decode(file_get_contents('php://input'), true);
$card_id = $data['card_id'] ?? null;

if (!$card_id) {
    http_response_code(400);
    echo json_encode(['error'=>'No card']);
    exit;
}

/* ================= TURN CHECK ================= */
$status = read_status();
if ($status['turn'] !== $player) {
    http_response_code(403);
    echo json_encode(['error'=>'Not your turn']);
    exit;
}

/* ================= CARD INFO ================= */
$st = $mysqli->prepare("
    SELECT value, suit FROM deck WHERE card_id=?
");
$st->bind_param('i', $card_id);
$st->execute();
$played = $st->get_result()->fetch_assoc();

if (!$played) {
    echo json_encode(['error'=>'Invalid card']);
    exit;
}

/* ================= REMOVE FROM HAND ================= */
$st = $mysqli->prepare("
    DELETE FROM hands WHERE card_id=? AND player=?
");
$st->bind_param('is', $card_id, $player);
$st->execute();

if ($st->affected_rows === 0) {
    echo json_encode(['error'=>'Card not in hand']);
    exit;
}

/* ================= TOP TABLE CARD ================= */
$res = $mysqli->query("
    SELECT t.card_id, d.value
    FROM table_cards t
    JOIN deck d ON d.card_id = t.card_id
    ORDER BY t.placed_at DESC
    LIMIT 1
");
$top = $res->fetch_assoc();

/* ================= RULES ================= */
$collect   = false;
$is_jack   = ($played['value'] === 'J');
$is_xeri   = false;
$xeri_type = null;

// Βαλές μαζεύει ΜΟΝΟ αν υπάρχει φύλλο
if ($is_jack && $top) {
    $collect = true;
}

// ίδιο value ΜΟΝΟ με το ΠΑΝΩ φύλλο
if (!$is_jack && $top && $played['value'] === $top['value']) {
    $collect = true;
}

/* ================= ACTION ================= */
if ($collect) {

    // πόσα φύλλα υπήρχαν
    $res = $mysqli->query("SELECT COUNT(*) AS c FROM table_cards");
    $table_count = (int)$res->fetch_assoc()['c'];

    // Ξερή ΜΟΝΟ αν 1 φύλλο
    if ($table_count === 1) {

        if ($played['value'] === 'J' && $top['value'] === 'J') {
            $is_xeri = true;
            $xeri_type = 'jack'; // 20
        }
        elseif ($played['value'] === $top['value'] && $played['value'] !== 'J') {
            $is_xeri = true;
            $xeri_type = 'normal'; // 10
        }

    }
if ($is_xeri) {
    $st = $mysqli->prepare("
        INSERT INTO xeri_stats (player, type)
        VALUES (?, ?)
    ");
    $st->bind_param('ss', $player, $xeri_type);
    $st->execute();
}

    // μαζεύουμε τραπέζι
    $mysqli->query("
        UPDATE deck
        SET drawn_by='$player'
        WHERE card_id IN (SELECT card_id FROM table_cards)
    ");

    // και το παιγμένο
    $mysqli->query("
        UPDATE deck
        SET drawn_by='$player'
        WHERE card_id=$card_id
    ");

    $mysqli->query("DELETE FROM table_cards");

} else {

    // απλό παίξιμο
    $st = $mysqli->prepare("
        INSERT INTO table_cards(card_id, played_by, placed_at)
        VALUES (?, ?, NOW())
    ");
    $st->bind_param('is', $card_id, $player);
    $st->execute();
}

/* ================= CHANGE TURN ================= */
$next = ($player === 'P1') ? 'P2' : 'P1';
$st = $mysqli->prepare("
    UPDATE game_status SET turn=?, last_change=NOW()
");
$st->bind_param('s', $next);
$st->execute();

/* ================= END ROUND CHECK ================= */
$res = $mysqli->query("SELECT COUNT(*) AS c FROM hands");
$hands_left = (int)$res->fetch_assoc()['c'];

if ($hands_left === 0) {

    // υπάρχουν φύλλα στο deck;
    $res = $mysqli->query("
        SELECT COUNT(*) AS c FROM deck WHERE is_drawn=0
    ");
    $remaining = (int)$res->fetch_assoc()['c'];

    if ($remaining >= 12) {

    // 👉 αλλάζουμε ΚΑΤΑΣΤΑΣΗ
    $mysqli->query("
        UPDATE game_status
        SET status='dealing',
            turn='P1',
            last_change=NOW()
    ");

    echo json_encode([
        'success'=>true,
        'round_end'=>true
    ]);
    exit;
}

    else {

    // ⛔ φύλλα που έμειναν κάτω ΔΕΝ ανήκουν σε κανέναν
    $mysqli->query("
        UPDATE deck
        SET drawn_by = NULL
        WHERE card_id IN (SELECT card_id FROM table_cards)
    ");

    require_once 'score.php';
    $final = calculate_round_score();

    if ($final['P1'] > $final['P2']) {
        $winner = 'P1';
    } elseif ($final['P2'] > $final['P1']) {
        $winner = 'P2';
    } else {
        $winner = 'draw';
    }

    // 1 πόντος στον νικητή ΠΑΙΧΝΙΔΙΟΥ
    if ($winner !== 'draw') {
        $mysqli->query("
            UPDATE players
            SET score = score + 1
            WHERE player='$winner'
        ");
    }

    $st = $mysqli->prepare("
        UPDATE game_status
        SET status='game_end',
            result=?,
            turn=NULL,
            last_change=NOW()
    ");
    $st->bind_param('s', $winner);
    $st->execute();

    echo json_encode([
        'success'  => true,
        'game_end' => true,
        'winner'   => $winner,
        'score'    => $final
    ], JSON_PRETTY_PRINT);

    exit;
}


}

/* ================= RESPONSE ================= */
echo json_encode([
    'success'   => true,
    'collected' => $collect,
    'xeri'      => $is_xeri,
    'xeri_type' => $xeri_type
], JSON_PRETTY_PRINT);


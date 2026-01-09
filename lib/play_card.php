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

$player   = $me['player'];
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
$st = $mysqli->prepare("SELECT value, suit FROM deck WHERE card_id=?");
$st->bind_param('i', $card_id);
$st->execute();
$played = $st->get_result()->fetch_assoc();

if (!$played) {
    echo json_encode(['error'=>'Invalid card']);
    exit;
}

/* ================= REMOVE FROM HAND ================= */
$st = $mysqli->prepare("DELETE FROM hands WHERE card_id=? AND player=?");
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
    JOIN deck d ON d.card_id=t.card_id
    ORDER BY t.placed_at DESC
    LIMIT 1
");
$top = $res->fetch_assoc();

/* ================= RULES ================= */
$collect   = false;
$is_jack   = ($played['value'] === 'J');
$is_xeri   = false;
$xeri_type = null;

if ($is_jack && $top) {
    $collect = true;
}
if (!$is_jack && $top && $played['value'] === $top['value']) {
    $collect = true;
}

/* ================= ACTION ================= */
if ($collect) {

    $res = $mysqli->query("SELECT COUNT(*) c FROM table_cards");
    $table_count = (int)$res->fetch_assoc()['c'];

    if ($table_count === 1) {
        if ($played['value']==='J' && $top['value']==='J') {
            $is_xeri=true; $xeri_type='jack';
        } elseif ($played['value']===$top['value']) {
            $is_xeri=true; $xeri_type='normal';
        }
    }

    if ($is_xeri) {
        $st = $mysqli->prepare("
            INSERT INTO xeri_stats(player,type)
            VALUES(?,?)
        ");
        $st->bind_param('ss',$player,$xeri_type);
        $st->execute();
    }

    $mysqli->query("
        UPDATE deck
        SET drawn_by='$player'
        WHERE card_id IN (SELECT card_id FROM table_cards)
           OR card_id=$card_id
    ");

    $mysqli->query("DELETE FROM table_cards");

} else {

    $st = $mysqli->prepare("
        INSERT INTO table_cards(card_id,played_by,placed_at)
        VALUES(?,?,NOW())
    ");
    $st->bind_param('is',$card_id,$player);
    $st->execute();
}

/* ================= CHANGE TURN ================= */
$next = ($player==='P1')?'P2':'P1';
$st = $mysqli->prepare("UPDATE game_status SET turn=?,last_change=NOW()");
$st->bind_param('s',$next);
$st->execute();

/* ================= END ROUND CHECK ================= */
$res = $mysqli->query("SELECT COUNT(*) c FROM hands");
$hands_left = (int)$res->fetch_assoc()['c'];

if ($hands_left === 0) {
 sleep(7);
    $res = $mysqli->query("SELECT COUNT(*) c FROM deck WHERE is_drawn=0");
    $remaining = (int)$res->fetch_assoc()['c'];

    if ($remaining >= 12) {
        // 🔥 ΑΠΛΑ αλλάζουμε status – ΔΕΝ κάνουμε exit
        $mysqli->query("
            UPDATE game_status
            SET status='dealing', turn='P1', last_change=NOW()
        ");
    }
    else {
        // τελευταίος γύρος – φύλλα κάτω δεν μετράνε
        $mysqli->query("
            UPDATE deck SET drawn_by=NULL
            WHERE card_id IN (SELECT card_id FROM table_cards)
        ");

        require_once 'score.php';
        $final = calculate_round_score();

        $winner = ($final['P1']>$final['P2'])?'P1':
                  (($final['P2']>$final['P1'])?'P2':'draw');

        if ($winner!=='draw') {
            $mysqli->query("
                UPDATE players SET score=score+1
                WHERE player='$winner'
            ");
        }

        $st = $mysqli->prepare("
            UPDATE game_status
            SET status='game_end', result=?, turn=NULL, last_change=NOW()
        ");
        $st->bind_param('s',$winner);
        $st->execute();
    }
}

/* ================= RESPONSE ================= */
echo json_encode([
    'success'=>true,
    'collected'=>$collect,
    'xeri'=>$is_xeri,
    'xeri_type'=>$xeri_type
], JSON_PRETTY_PRINT);

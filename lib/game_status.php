<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "db2connect.php";
header('Content-Type: application/json');



/* ================= FUNCTIONS ================= */

function read_status() {
    global $mysqli;
    $res = $mysqli->query("SELECT * FROM game_status");
    return $res->fetch_assoc();
}

function show_status() {
    global $mysqli;

    require_once 'score.php';

    // game status
    $res = $mysqli->query("SELECT * FROM game_status LIMIT 1");
    $status = $res->fetch_assoc();

    // αν τελείωσε το παιχνίδι → υπολόγισε σκορ
    if ($status['status'] === 'game_end') {
        $score = calculate_round_score();
    } else {
        $score = ['P1'=>0, 'P2'=>0];
    }

    echo json_encode([[
        'status'      => $status['status'],
        'turn'        => $status['turn'],
        'result'      => $status['result'],
        'last_change' => $status['last_change'],
        'score_p1'    => $score['P1'],
        'score_p2'    => $score['P2']
    ]], JSON_PRETTY_PRINT);
}



/*
 * ΚΕΝΤΡΙΚΗ ΛΟΓΙΚΗ ΚΑΤΑΣΤΑΣΗΣ ΠΑΙΧΝΙΔΙΟΥ
 */
function update_game_status() {
    global $mysqli;

    $status = read_status();
    if (!$status) return;

    /* ================= TIMEOUT ABORT ================= */
  /* ================= TIMEOUT ABORT ================= */
if ($status['status'] === 'playing' && $status['turn'] !== null) {

    $loser = $status['turn'];

    $st = $mysqli->prepare("
        SELECT last_action
        FROM players
        WHERE player = ?
    ");
    $st->bind_param('s', $loser);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();

    if ($row && $row['last_action'] !== null) {

        $idle = time() - strtotime($row['last_action']);

        // ⏰ 60 sec για test
        if ($idle >= 60) {

            $winner = ($loser === 'P1') ? 'P2' : 'P1';

            $st = $mysqli->prepare("
                UPDATE game_status
                SET status='aborted',
                    result=?,
                    turn=NULL,
                    last_change=NOW()
            ");
            $st->bind_param('s', $winner);
            $st->execute();

            echo json_encode([
                'status' => 'aborted',
                'result' => $winner,
                'idle'   => $idle
            ]);
            exit;
        }
    }
}



    /* ================= ACTIVE PLAYERS ================= */
    $res = $mysqli->query("
        SELECT COUNT(*) c
        FROM players
        WHERE username IS NOT NULL
    ");
    $active = (int)$res->fetch_assoc()['c'];

    if ($active === 0) {
        $mysqli->query("
            UPDATE game_status
            SET status='not_active', turn=NULL, last_change=NOW()
        ");
    }
    elseif ($active === 1) {
        $mysqli->query("
            UPDATE game_status
            SET status='waiting_player', turn=NULL, last_change=NOW()
        ");
    }
    elseif ($active === 2 && $status['status'] === 'waiting_player') {
        $mysqli->query("
            UPDATE game_status
            SET status='dealing', turn='P1', last_change=NOW()
        ");
    }
}






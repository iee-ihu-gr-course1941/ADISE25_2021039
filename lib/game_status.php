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

    // Πάρε τρέχουσα κατάσταση
    $res = $mysqli->query("SELECT * FROM game_status LIMIT 1");
    $status = $res->fetch_assoc();
    if (!$status) return;
if ($status['status'] === 'aborted') {
        return;
    }
    /* ================= IDLE PLAYER CHECK ================= */

    // Πόσοι παίκτες είναι idle > 1 λεπτό (βάλε 20 MINUTE αν θες κανονικά)
    $st = $mysqli->prepare("
        SELECT COUNT(*) AS aborted
        FROM players
        WHERE username IS NOT NULL
          AND last_action < (NOW() - INTERVAL 1 MINUTE)
    ");
    $st->execute();
    $aborted = (int)$st->get_result()->fetch_assoc()['aborted'];

    if ($aborted > 0 && $status['status'] === 'playing') {

        // Καθάρισε τον idle παίκτη
        $mysqli->query("
            UPDATE players
            SET username = NULL, token = NULL
            WHERE last_action < (NOW() - INTERVAL 20 MINUTE)
        ");

        // Βρες νικητή = όποιος έμεινε
        $res = $mysqli->query("
            SELECT player
            FROM players
            WHERE username IS NOT NULL
            LIMIT 1
        ");
        $winner = $res->fetch_assoc()['player'] ?? NULL;

        // Κάνε abort το παιχνίδι
        $st2 = $mysqli->prepare("
            UPDATE game_status
            SET status='aborted',
                result=?,
                turn=NULL,
                last_change=NOW()
        ");
        $st2->bind_param('s', $winner);
        $st2->execute();

        return;
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






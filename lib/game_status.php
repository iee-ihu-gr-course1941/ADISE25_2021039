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

    $res = $mysqli->query("SELECT * FROM game_status");
    echo json_encode(
        $res->fetch_all(MYSQLI_ASSOC),
        JSON_PRETTY_PRINT
    );
}

/*
 * ΚΕΝΤΡΙΚΗ ΛΟΓΙΚΗ ΚΑΤΑΣΤΑΣΗΣ ΠΑΙΧΝΙΔΙΟΥ
 */
function update_game_status() {
    global $mysqli;

    $status = read_status();
    if (!$status) return;

    /* ================= TIMEOUT ABORT ================= */
    if ($status['status'] === 'playing' && $status['turn'] !== null) {

        // τελευταία ενέργεια
        $res = $mysqli->query("
            SELECT MAX(placed_at) AS last_play
            FROM table_cards
        ");
        $last_play = $res->fetch_assoc()['last_play'];

        $last_action = $last_play ?? $status['last_change'];

        // ⏰ 10 λεπτά
        if (strtotime($last_action) < time() - 60) {

            $loser  = $status['turn'];
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

            return;
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
            SET status='not_active',
                turn=NULL,
                last_change=NOW()
        ");
    }
    elseif ($active === 1) {
        $mysqli->query("
            UPDATE game_status
            SET status='waiting_player',
                turn=NULL,
                last_change=NOW()
        ");
    }
    elseif ($active === 2 && $status['status'] === 'waiting_player') {
        $mysqli->query("
            UPDATE game_status
            SET status='dealing',
                turn='P1',
                last_change=NOW()
        ");
    }
}





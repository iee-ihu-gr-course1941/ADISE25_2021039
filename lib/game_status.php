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

    /* -------- ΤΡΕΧΟΥΣΑ ΚΑΤΑΣΤΑΣΗ -------- */
    $status = read_status();
    if (!$status) {
        return;
    }

    $new_status = null;
    $new_turn   = $status['turn'];

    /* =====================================================
       ABORT ΜΟΝΟ Ο ΠΑΙΚΤΗΣ ΠΟΥ ΕΧΕΙ ΣΕΙΡΑ
       (με βάση placed_at)
       ===================================================== */
    if ($status['status'] === 'playing' && $status['turn'] !== null) {

        // τελευταία κάρτα που παίχτηκε
        $res = $mysqli->query("
            SELECT MAX(placed_at) AS last_play
            FROM table_cards
        ");
        $last_play = $res->fetch_assoc()['last_play'];

        // αν δεν έχει παιχτεί φύλλο ακόμα
        $last_action = $last_play ?? $status['last_change'];

        // timeout 10 λεπτά
        if (strtotime($last_action) < time() - 600) {

            $loser  = $status['turn'];
            $winner = ($loser === 'P1') ? 'P2' : 'P1';

            // παιχνίδι aborted
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

    /* =====================================================
       ΠΛΗΘΟΣ ΕΝΕΡΓΩΝ ΠΑΙΚΤΩΝ
       ===================================================== */
    $res = $mysqli->query("
        SELECT COUNT(*) AS c
        FROM players
        WHERE username IS NOT NULL
    ");
    $active = $res->fetch_assoc()['c'];

    if ($active == 0) {
        $new_status = 'not_active';
        $new_turn   = NULL;
    }
    elseif ($active == 1) {
        $new_status = 'waiting_player';
        $new_turn   = NULL;
    }
 elseif ($active == 2 && $status['status'] === 'waiting_player') {

    $mysqli->query("
        UPDATE game_status
        SET status='dealing',
            turn='P1',
            last_change=NOW()
    ");

    return;
}




    /* -------- ΕΝΗΜΕΡΩΣΗ -------- */
    if ($new_status !== null) {
        $st = $mysqli->prepare("
            UPDATE game_status
            SET status=?, turn=?, last_change=NOW()
        ");
        $st->bind_param('ss', $new_status, $new_turn);
        $st->execute();
    }
}




<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "db2connect.php";
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    update_game_status();
    show_status();
    exit;
}

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
       ===================================================== */
    if ($status['turn'] !== null && $status['status'] === 'playing') {

        $st = $mysqli->prepare("
            SELECT last_action
            FROM players
            WHERE player = ? AND username IS NOT NULL
        ");
        $st->bind_param('s', $status['turn']);
        $st->execute();
        $res = $st->get_result();
        $player = $res->fetch_assoc();

        if ($player && strtotime($player['last_action']) < time() - 60) {

            $loser  = $status['turn'];
            $winner = ($loser === 'P1') ? 'P2' : 'P1';

            // καθαρίζουμε ΜΟΝΟ τον παίκτη που άργησε
            $st = $mysqli->prepare("
                UPDATE players
                SET username=NULL, token=NULL
                WHERE player=?
            ");
            $st->bind_param('s', $loser);
            $st->execute();

            // τελείωσε το παιχνίδι
            $st = $mysqli->prepare("
                UPDATE game_status
                SET status='game_end',
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
        // μόλις μπουν και οι 2
        $new_status = 'dealing';
        $new_turn   = 'P1';
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



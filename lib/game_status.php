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

function show_status() {
    global $mysqli;

    $res = $mysqli->query("SELECT * FROM game_status");
    echo json_encode(
        $res->fetch_all(MYSQLI_ASSOC),
        JSON_PRETTY_PRINT
    );
}

function update_game_status() {
    global $mysqli;

    $res = $mysqli->query("SELECT * FROM game_status");
    $status = $res->fetch_assoc();

    if (!$status) {
        return;
    }

    $new_status = null;
    $new_turn   = $status['turn'];

    /* Αδρανείς παίκτες */
    $res = $mysqli->query("
        SELECT COUNT(*) AS aborted
        FROM players
        WHERE username IS NOT NULL
        AND last_action < (NOW() - INTERVAL 20 MINUTE)
    ");
    $aborted = $res->fetch_assoc()['aborted'];

    if ($aborted > 0) {
        $mysqli->query("
            UPDATE players
            SET username = NULL,
                token = NULL,
                score = 0
        ");
        $new_status = 'aborted';
    }

    /* Ενεργοί παίκτες */
    $res = $mysqli->query("
        SELECT COUNT(*) AS c
        FROM players
        WHERE username IS NOT NULL
    ");
    $active = $res->fetch_assoc()['c'];

    if ($active == 0) {
        $new_status = 'not_active';
        $new_turn = NULL;
    }
    elseif ($active == 1) {
        $new_status = 'waiting_player';
        $new_turn = NULL;
    }
    elseif ($active == 2 && $status['status'] !== 'playing') {
        $new_status = 'dealing';
        $new_turn = 'P1';
    }

    if ($new_status !== null) {
        $st = $mysqli->prepare("
            UPDATE game_status
            SET status=?, turn=?, last_change=NOW()
        ");
        $st->bind_param('ss', $new_status, $new_turn);
        $st->execute();
    }
}


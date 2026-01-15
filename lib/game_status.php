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


   

    // Έλεγχος για αδράνεια 5 λεπτών
    if ($status['status'] === 'playing' &&
        (time() - strtotime($status['last_change']) > 70)) {

        // Αλλαγή κατάστασης σε aborted
        $mysqli->query("
            UPDATE game_status
            SET status='aborted', turn=NULL, last_change=NOW()
        ");

        file_put_contents(
            __DIR__.'/../logs/idle_debug.log',
            date('H:i:s') . " Game aborted due to inactivity\n",
            FILE_APPEND
        );
    }
}






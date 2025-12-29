<?php
header("Content-Type: application/json");
require_once "lib/db2connect.php";

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents("php://input"), true);

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isset($input['username']) || trim($input['username']) === '') {
    http_response_code(400);
    echo json_encode(['error' => 'No username given']);
    exit;
}

$username = trim($input['username']);

$mysqli->begin_transaction();

try {

    // Αν υπάρχει ήδη ο ίδιος χρήστης → επιστροφή
    $stmt = $mysqli->prepare(
        "SELECT player, username, token FROM players WHERE username=?"
    );
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 1) {
        echo json_encode($res->fetch_assoc());
        $mysqli->commit();
        exit;
    }

    // Βρες ελεύθερο slot (P1 ή P2)
    $res = $mysqli->query(
        "SELECT player FROM players WHERE username IS NULL LIMIT 1 FOR UPDATE"
    );

    if ($res->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Game already has 2 players']);
        $mysqli->rollback();
        exit;
    }

    $player = $res->fetch_assoc()['player'];
    $token = bin2hex(random_bytes(16));

    // Δήλωση παίκτη
    $stmt = $mysqli->prepare(
        "UPDATE players SET username=?, token=? WHERE player=?"
    );
    $stmt->bind_param("sss", $username, $token, $player);
    $stmt->execute();

    // Ενημέρωση game_status
    if ($player === 'P1') {
        $mysqli->query(
            "UPDATE game_status SET status='waiting_player', turn='P1'"
        );
    } else {
        $mysqli->query(
            "UPDATE game_status SET status='playing', turn='P1'"
        );
    }

    $mysqli->commit();

    echo json_encode([
        'player' => $player,
        'username' => $username,
        'token' => $token
    ]);

} catch (Exception $e) {
    $mysqli->rollback();
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}

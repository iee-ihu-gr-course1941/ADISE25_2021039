<?php
require_once "../lib/db2connect.php";

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    login_player();
}

function login_player() {
    global $mysqli;

    $input = json_decode(file_get_contents("php://input"), true);

    if (!isset($input['username']) || $input['username'] == '') {
        http_response_code(400);
        echo json_encode(['error' => 'No username']);
        exit;
    }

    $username = $input['username'];

    // Βρες ελεύθερο player
    $sql = "SELECT player FROM players WHERE username IS NULL LIMIT 1";
    $res = $mysqli->query($sql);

    if ($res->num_rows == 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Game full']);
        exit;
    }

    $row = $res->fetch_assoc();
    $player = $row['player'];
    $token = md5($username . time());

    $stmt = $mysqli->prepare(
        "UPDATE players SET username=?, token=? WHERE player=?"
    );
    $stmt->bind_param("sss", $username, $token, $player);
    $stmt->execute();

    echo json_encode([
        'player' => $player,
        'username' => $username,
        'token' => $token
    ]);
}

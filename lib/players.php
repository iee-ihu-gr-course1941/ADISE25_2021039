<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db2connect.php'; // Σύνδεση με $mysqli

header('Content-Type: application/json');

// Έλεγχος HTTP method
$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'PUT') {
    http_response_code(405);
    echo json_encode(['error' => 'Only PUT allowed']);
    exit;
}

// Παίρνουμε το player από το URI
$uri = $_SERVER['REQUEST_URI'];
$parts = explode('/', trim($uri, '/'));
$player = end($parts); // π.χ. P1 ή P2

if (!$player) {
    http_response_code(400);
    echo json_encode(['error' => 'No player specified']);
    exit;
}

// Παίρνουμε το JSON από το request body
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['username']) || trim($input['username']) === '') {
    http_response_code(400);
    echo json_encode(['error' => 'No username provided']);
    exit;
}

$username = trim($input['username']);

// Προετοιμάζουμε το UPDATE
$mysqli->query("SET SESSION CLIENT_FOUND_ROWS=1"); // Μετρά affected_rows ακόμα κι αν ίδια τιμή

$stmt = $mysqli->prepare("
    UPDATE players 
    SET username = ?, 
        token = MD5(CONCAT(?, NOW())),
        last_action = NOW()
    WHERE player = ?
");

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Prepare failed', 'details' => $mysqli->error]);
    exit;
}

$stmt->bind_param('sss', $username, $username, $player);
$stmt->execute();

if ($stmt->errno) {
    http_response_code(500);
    echo json_encode(['error' => 'Execute failed', 'details' => $stmt->error]);
    exit;
}

$affected = $stmt->affected_rows;

// Παίρνουμε τα ενημερωμένα δεδομένα
$res = $mysqli->prepare("SELECT * FROM players WHERE player = ?");
$res->bind_param('s', $player);
$res->execute();
$result = $res->get_result();
$data = $result->fetch_all(MYSQLI_ASSOC);

// Επιστρέφουμε JSON
echo json_encode([
    'success' => true,
    'affected_rows' => $affected,
    'player_data' => $data
], JSON_PRETTY_PRINT);


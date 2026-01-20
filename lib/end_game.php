<?php
require_once 'db2connect.php';
require_once 'deck.php'; 

header('Content-Type: application/json');

// reset deck hands table
reset_game();

// reset players
$mysqli->query("
    UPDATE players
    SET username=NULL,
        token=NULL,
        score=0
");

// reset ξερών
$mysqli->query("DELETE FROM xeri_stats");

// reset game_status
$mysqli->query("
    UPDATE game_status
    SET status='not_active',
        turn=NULL,
        result=NULL,
        last_change=NOW()
");

echo json_encode(['success'=>true]);

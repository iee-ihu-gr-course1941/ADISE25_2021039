<?php
require_once 'game_status.php';

update_game_status();

$status = read_status();

echo json_encode([$status], JSON_PRETTY_PRINT);

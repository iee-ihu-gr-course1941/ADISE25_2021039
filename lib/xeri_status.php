<?php
require_once 'db2connect.php';
header('Content-Type: application/json');

$res = $mysqli->query("
    SELECT player,
           SUM(type='normal') AS normal_xeri,
           SUM(type='jack') AS jack_xeri
    FROM xeri_stats
    GROUP BY player
");

$data = ['P1'=>['normal'=>0,'jack'=>0], 'P2'=>['normal'=>0,'jack'=>0]];

while ($r = $res->fetch_assoc()) {
    $data[$r['player']] = [
        'normal' => (int)$r['normal_xeri'],
        'jack'   => (int)$r['jack_xeri']
    ];
}

echo json_encode($data, JSON_PRETTY_PRINT);

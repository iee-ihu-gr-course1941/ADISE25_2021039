<?php
require_once 'db2connect.php';

function calculate_round_score() {
    global $mysqli;

    $players = ['P1','P2'];
    $score = ['P1'=>0, 'P2'=>0];
    $cards_count = [];

    foreach ($players as $p) {

        $res = $mysqli->query("
            SELECT d.value, d.suit
            FROM deck d
            WHERE d.drawn_by='$p'
        ");
        $cards = $res->fetch_all(MYSQLI_ASSOC);
        $cards_count[$p] = count($cards);

        foreach ($cards as $c) {

            // γραμματα & 10
            if (in_array($c['value'], ['K','Q','J','10']) && !($c['value']=='10' && $c['suit']=='D')) {
                $score[$p]++;
            }

            // 2 s
            if ($c['value']=='2' && $c['suit']=='S') {
                $score[$p]++;
            }

            // 10 d
            if ($c['value']=='10' && $c['suit']=='D') {
                $score[$p]++;
            }
        }
    }

    // +3 φύλλα
    if ($cards_count['P1'] > $cards_count['P2']) {
        $score['P1'] += 3;
    } elseif ($cards_count['P2'] > $cards_count['P1']) {
        $score['P2'] += 3;
    }
$res = $mysqli->query("
    SELECT player, type, COUNT(*) AS c
    FROM xeri_stats
    GROUP BY player, type
");

while ($r = $res->fetch_assoc()) {
    if ($r['type'] === 'normal') {
        $score[$r['player']] += $r['c'] * 10;
    } else {
        $score[$r['player']] += $r['c'] * 20;
    }
}

    return $score;
}

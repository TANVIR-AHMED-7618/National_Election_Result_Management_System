<?php
require_once __DIR__ . "/../inc/db.php";
header('Content-Type: application/json');
date_default_timezone_set('Asia/Dhaka');

/*
  লক্ষ্য:
  1) seat-wise winner বের করা হবে symbol_name ভিত্তিতে
  2) 300 seat total votes % বের হবে symbol_name ভিত্তিতে
  3) Top 10 unique symbol_name রিটার্ন হবে
*/

/* ---------- 1) Seat-wise: symbol_name অনুযায়ী total votes ---------- */
$seatSymbolRows = $pdo->query("
  SELECT v.seat_id, sy.symbol_name, SUM(v.vote_count) AS total_votes
  FROM votes v
  JOIN symbols sy ON sy.id = v.symbol_id
  GROUP BY v.seat_id, sy.symbol_name
")->fetchAll();

/* seat winner count: winnerSeats[symbol_name] = count */
$seatMax = [];        // seat_id => ['symbol'=>..., 'votes'=>...]
$winnerSeats = [];    // symbol_name => number of seats lead

foreach ($seatSymbolRows as $r) {
  $seatId = (int)$r['seat_id'];
  $sym = trim($r['symbol_name']);
  $votes = (int)$r['total_votes'];

  if ($sym === '') continue;

  if (!isset($seatMax[$seatId]) || $votes > $seatMax[$seatId]['votes']) {
    $seatMax[$seatId] = ['symbol' => $sym, 'votes' => $votes];
  }
}

/* count winners */
foreach ($seatMax as $w) {
  $sym = $w['symbol'];
  $winnerSeats[$sym] = ($winnerSeats[$sym] ?? 0) + 1;
}

/* ---------- 2) 300 seats total votes: symbol_name অনুযায়ী ---------- */
$voteRows = $pdo->query("
  SELECT sy.symbol_name, SUM(v.vote_count) AS total_votes
  FROM votes v
  JOIN symbols sy ON sy.id = v.symbol_id
  GROUP BY sy.symbol_name
")->fetchAll();

$totalAllVotes = 0;
foreach ($voteRows as $r) $totalAllVotes += (int)$r['total_votes'];

/* ---------- 3) Merge: unique symbol_name ---------- */
$data = [];
foreach ($voteRows as $r) {
  $sym = trim($r['symbol_name']);
  if ($sym === '') continue;

  $symTotal = (int)$r['total_votes'];
  $seatsLead = (int)($winnerSeats[$sym] ?? 0);
  $pct = ($totalAllVotes > 0) ? round(($symTotal / $totalAllVotes) * 100, 2) : 0;

  $data[] = [
    'symbol' => $sym,
    'seats' => $seatsLead,
    'percentage' => $pct,
    'votes' => $symTotal,  // (optional debug/use)
  ];
}

/* sort: seats desc, then percentage desc */
usort($data, function($a, $b){
  if ($b['seats'] !== $a['seats']) return $b['seats'] <=> $a['seats'];
  return $b['percentage'] <=> $a['percentage'];
});

/* Top 10 unique symbols */
$data = array_slice($data, 0, 15);

/* remove votes if you don't need it */
foreach ($data as &$d) { unset($d['votes']); }

echo json_encode($data);
exit;


<?php
require_once __DIR__ . "/../inc/db.php";
header('Content-Type: application/json');
date_default_timezone_set('Asia/Dhaka');

$TOPN = 10;

// 1) Seat list
$seatRows = $pdo->query("
  SELECT id, seat_name
  FROM seats
  ORDER BY seat_name
")->fetchAll();

// 2) Seat × Symbol totals (symbol_name ভিত্তিতে)
$dataRows = $pdo->query("
  SELECT v.seat_id, s.seat_name, TRIM(sy.symbol_name) AS symbol_name, SUM(v.vote_count) AS total_votes
  FROM votes v
  JOIN seats s ON s.id = v.seat_id
  JOIN symbols sy ON sy.id = v.symbol_id
  WHERE sy.symbol_name IS NOT NULL AND TRIM(sy.symbol_name) <> ''
  GROUP BY v.seat_id, s.seat_name, TRIM(sy.symbol_name)
")->fetchAll();

// Build seat map
$seatMap = [];
foreach ($seatRows as $s) {
  $sid = (int)$s['id'];
  $seatMap[$sid] = [
    'seat' => $s['seat_name'],
    'list' => [],            // symbol list [ ['symbol'=>..., 'votes'=>...], ... ]
    'casting_total' => 0
  ];
}

// Fill
foreach ($dataRows as $r) {
  $sid = (int)$r['seat_id'];
  if (!isset($seatMap[$sid])) continue;

  $sym = trim($r['symbol_name']);
  $votes = (int)$r['total_votes'];

  $seatMap[$sid]['list'][] = [
    'symbol' => $sym,
    'votes'  => $votes
  ];
  $seatMap[$sid]['casting_total'] += $votes;
}

// Sort each seat list desc and take top N, pad N/A
$rows = [];
foreach ($seatMap as $sid => $seatData) {
  $list = $seatData['list'];

  usort($list, function($a, $b){
    if ($b['votes'] !== $a['votes']) return $b['votes'] <=> $a['votes'];
    return strcmp($a['symbol'], $b['symbol']);
  });

  $top = array_slice($list, 0, $TOPN);

  // pad N/A
  while (count($top) < $TOPN) {
    $top[] = ['symbol' => 'N/A', 'votes' => null];
  }

  $rows[] = [
    'seat' => $seatData['seat'],
    'top'  => $top,
    'casting_total' => $seatData['casting_total']
  ];
}

echo json_encode([
  'topn' => $TOPN,
  'rows' => $rows
], JSON_UNESCAPED_UNICODE);

exit;


<?php
require_once __DIR__ . "/../inc/db.php";
header('Content-Type: application/json');
date_default_timezone_set('Asia/Dhaka');

/*
  Return:
  {
    "symbols": ["ধানের শীষ", "নৌকা", ...],
    "rows": [
      {
        "seat": "ঢাকা-১",
        "totals": {"ধানের শীষ": 123, "নৌকা": 456, ...},
        "casting_total": 999
      },
      ...
    ]
  }
*/

// 1) All unique symbols (global)
$symbolRows = $pdo->query("
  SELECT DISTINCT TRIM(symbol_name) AS symbol_name
  FROM symbols
  WHERE symbol_name IS NOT NULL AND TRIM(symbol_name) <> ''
  ORDER BY symbol_name
")->fetchAll();

$symbols = array_map(fn($r) => $r['symbol_name'], $symbolRows);

// 2) Seat list
$seatRows = $pdo->query("
  SELECT id, seat_name
  FROM seats
  ORDER BY seat_name
")->fetchAll();

// 3) Seat × Symbol totals (aggregate by symbol_name)
$dataRows = $pdo->query("
  SELECT s.id AS seat_id, s.seat_name, sy.symbol_name, SUM(v.vote_count) AS total_votes
  FROM votes v
  JOIN seats s ON s.id = v.seat_id
  JOIN symbols sy ON sy.id = v.symbol_id
  GROUP BY s.id, s.seat_name, sy.symbol_name
")->fetchAll();

// Build seat map
$seatMap = [];
foreach ($seatRows as $s) {
  $seatMap[(int)$s['id']] = [
    'seat' => $s['seat_name'],
    'totals' => [],
    'casting_total' => 0
  ];
}

// Fill totals
foreach ($dataRows as $r) {
  $sid = (int)$r['seat_id'];
  $sym = trim($r['symbol_name']);
  $votes = (int)$r['total_votes'];

  if (!isset($seatMap[$sid])) continue;
  if ($sym === '') continue;

  $seatMap[$sid]['totals'][$sym] = $votes;
  $seatMap[$sid]['casting_total'] += $votes;
}

// Convert to rows array
$rows = array_values($seatMap);

echo json_encode([
  'symbols' => $symbols,
  'rows' => $rows
], JSON_UNESCAPED_UNICODE);
exit;

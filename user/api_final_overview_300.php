
<?php
require_once __DIR__ . "/../inc/db.php";

header("Content-Type: application/json; charset=utf-8");

// (A) total votes by symbol
$totals = $pdo->query("
  SELECT symbol_name, SUM(vote_count) AS vote_total
  FROM seat_final_results
  GROUP BY symbol_name
")->fetchAll(PDO::FETCH_ASSOC);

// grand total
$grand = 0;
foreach ($totals as $r) $grand += (int)$r["vote_total"];

// (B) leading symbol per seat (tie হলে symbol_name alphabetical ছোটটা ধরা হবে)
$leaders = $pdo->query("
  SELECT t.seat_id, MIN(t.symbol_name) AS symbol_name
  FROM (
    SELECT r.seat_id, r.symbol_name, r.vote_count
    FROM seat_final_results r
    JOIN (
      SELECT seat_id, MAX(vote_count) AS mx
      FROM seat_final_results
      GROUP BY seat_id
    ) m ON m.seat_id = r.seat_id AND m.mx = r.vote_count
  ) t
  GROUP BY t.seat_id
")->fetchAll(PDO::FETCH_ASSOC);

// count seats led per symbol
$seatCount = [];
foreach ($leaders as $x) {
  $s = $x["symbol_name"];
  $seatCount[$s] = ($seatCount[$s] ?? 0) + 1;
}

// merge totals + seats + percentage
$out = [];
foreach ($totals as $r) {
  $sym = $r["symbol_name"];
  $vote_total = (int)$r["vote_total"];
  $seats = (int)($seatCount[$sym] ?? 0);
  $pct = $grand > 0 ? round(($vote_total / $grand) * 100, 2) : 0;

  $out[] = [
    "symbol" => $sym,
    "seats" => $seats,
    "vote_total" => $vote_total,
    "percentage" => $pct
  ];
}

// sort by seats desc, then vote_total desc
usort($out, function($a,$b){
  if ($b["seats"] != $a["seats"]) return $b["seats"] <=> $a["seats"];
  return $b["vote_total"] <=> $a["vote_total"];
});

// top 10 only (grid)
$top10 = array_slice($out, 0, 10);

echo json_encode([
  "top10" => $top10,
  "computed_from_submitted_seats" => count($leaders),
  "grand_total_votes" => $grand
], JSON_UNESCAPED_UNICODE);

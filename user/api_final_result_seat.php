
<?php
require_once __DIR__ . "/../inc/db.php";
header("Content-Type: application/json; charset=utf-8");

$seat_id = (int)($_GET["seat_id"] ?? 0);
if (!$seat_id) {
  echo json_encode(["ok"=>false, "error"=>"seat_id required"], JSON_UNESCAPED_UNICODE);
  exit;
}

$st = $pdo->prepare("SELECT seat_name FROM seats WHERE id=?");
$st->execute([$seat_id]);
$seat_name = $st->fetchColumn();

$st = $pdo->prepare("
  SELECT symbol_name, vote_count
  FROM seat_final_results
  WHERE seat_id=?
  ORDER BY vote_count DESC, symbol_name ASC
");
$st->execute([$seat_id]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

$total = 0;
foreach ($rows as $r) $total += (int)$r["vote_count"];

echo json_encode([
  "ok" => true,
  "seat_id" => $seat_id,
  "seat_name" => $seat_name,
  "total_votes" => $total,
  "rows" => $rows
], JSON_UNESCAPED_UNICODE);

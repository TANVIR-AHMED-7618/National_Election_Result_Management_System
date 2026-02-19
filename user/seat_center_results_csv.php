
<?php
require_once __DIR__ . "/../inc/db.php";
date_default_timezone_set('Asia/Dhaka');

$seat_id = (int)($_GET["seat_id"] ?? 0);
if (!$seat_id) { http_response_code(400); exit("seat_id required"); }

// Seat name
$st = $pdo->prepare("SELECT seat_name FROM seats WHERE id=?");
$st->execute([$seat_id]);
$seatName = $st->fetchColumn();
if (!$seatName) { http_response_code(404); exit("Seat not found"); }

// Symbols
$st = $pdo->prepare("SELECT id, symbol_name FROM symbols WHERE seat_id=? ORDER BY symbol_name");
$st->execute([$seat_id]);
$symbols = $st->fetchAll();

// Centers
$st = $pdo->prepare("SELECT id, center_name FROM centers WHERE seat_id=? ORDER BY center_name");
$st->execute([$seat_id]);
$centers = $st->fetchAll();

// Votes
$st = $pdo->prepare("SELECT center_id, symbol_id, vote_count FROM votes WHERE seat_id=?");
$st->execute([$seat_id]);
$votesRows = $st->fetchAll();

// matrix
$matrix = [];
foreach ($votesRows as $r) {
  $matrix[(int)$r["center_id"]][(int)$r["symbol_id"]] = (int)$r["vote_count"];
}

$filename = "Center_Wise_Results_Seat_{$seat_id}.csv";

// Output buffer clean
while (ob_get_level() > 0) ob_end_clean();

// CSV headers (Excel-friendly UTF-8 BOM)
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');

echo "\xEF\xBB\xBF"; // UTF-8 BOM so Bangla shows correctly in Excel

$out = fopen('php://output', 'w');

// Header row
$header = ["Center Name"];
foreach ($symbols as $s) $header[] = $s["symbol_name"];
$header[] = "Center Total";
fputcsv($out, $header);

// Data rows
foreach ($centers as $c) {
  $cid = (int)$c["id"];
  $row = [$c["center_name"]];
  $centerTotal = 0;

  foreach ($symbols as $s) {
    $sid = (int)$s["id"];
    $v = (int)($matrix[$cid][$sid] ?? 0);
    $centerTotal += $v;
    $row[] = $v;
  }
  $row[] = $centerTotal;

  fputcsv($out, $row);
}

fclose($out);
exit;

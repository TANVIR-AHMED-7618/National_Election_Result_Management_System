<?php
// ⚠️ ফাইলের একদম শুরুতে কোনো blank line/space থাকবে না

require_once __DIR__ . "/../inc/db.php";
require_once __DIR__ . "/../vendor/autoload.php";

date_default_timezone_set('Asia/Dhaka');

$seat_id = (int)($_GET["seat_id"] ?? 0);
if (!$seat_id) { http_response_code(400); exit("seat_id required"); }

// Detect Spout namespace (OpenSpout v4 OR Box\Spout v3)
$factoryClass = null;
if (class_exists('\OpenSpout\Writer\Common\Creator\WriterEntityFactory')) {
  $factoryClass = '\OpenSpout\Writer\Common\Creator\WriterEntityFactory';
} elseif (class_exists('\Box\Spout\Writer\Common\Creator\WriterEntityFactory')) {
  $factoryClass = '\Box\Spout\Writer\Common\Creator\WriterEntityFactory';
} else {
  http_response_code(500);
  exit("Spout library not found. Run: composer require openspout/openspout");
}

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

// matrix[center_id][symbol_id] = votes
$matrix = [];
foreach ($votesRows as $r) {
  $matrix[(int)$r["center_id"]][(int)$r["symbol_id"]] = (int)$r["vote_count"];
}

$generatedAt = date("Y-m-d H:i:s");

// ⚠️ Output buffer clean (XLSX corrupt হওয়া আটকায়)
while (ob_get_level() > 0) {
  ob_end_clean();
}

$filename = "Center_Wise_Results_Seat_{$seat_id}.xlsx";

// Writer
$writer = $factoryClass::createXLSXWriter();
$writer->openToBrowser($filename);

// Header row
$header = ["Center Name"];
foreach ($symbols as $s) $header[] = $s["symbol_name"];
$header[] = "Center Total";

$writer->addRow($factoryClass::createRowFromArray($header));

// Data rows
foreach ($centers as $c) {
  $cid = (int)$c["id"];
  $row = [ $c["center_name"] ];
  $centerTotal = 0;

  foreach ($symbols as $s) {
    $sid = (int)$s["id"];
    $v = (int)($matrix[$cid][$sid] ?? 0);
    $centerTotal += $v;
    $row[] = $v;
  }
  $row[] = $centerTotal;

  $writer->addRow($factoryClass::createRowFromArray($row));
}

// Footer line (optional)
$writer->addRow($factoryClass::createRowFromArray([""]));
$writer->addRow($factoryClass::createRowFromArray(["Generated at: ".$generatedAt]));

$writer->close();
exit;

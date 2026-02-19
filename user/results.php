<?php
require_once __DIR__."/../inc/db.php";
require_once __DIR__."/../inc/helpers.php";

$seat_id = (int)($_GET["seat_id"] ?? 0);
if (!$seat_id) { header("Location: index.php"); exit; }

// Seat name
$st = $pdo->prepare("SELECT seat_name FROM seats WHERE id=?");
$st->execute([$seat_id]);
$seatName = $st->fetchColumn();
if (!$seatName) { header("Location: index.php"); exit; }

// 1) Symbols list (dynamic columns)
$st = $pdo->prepare("SELECT id, symbol_name FROM symbols WHERE seat_id=? ORDER BY symbol_name");
$st->execute([$seat_id]);
$symbols = $st->fetchAll();  // [{id, symbol_name}, ...]

// 2) Seat-wise totals per symbol
$st = $pdo->prepare("
  SELECT sy.id AS symbol_id, sy.symbol_name, COALESCE(SUM(v.vote_count),0) AS total_votes
  FROM symbols sy
  LEFT JOIN votes v ON v.symbol_id = sy.id AND v.seat_id = sy.seat_id
  WHERE sy.seat_id=?
  GROUP BY sy.id, sy.symbol_name
  ORDER BY total_votes DESC, sy.symbol_name
");
$st->execute([$seat_id]);
$seatTotals = $st->fetchAll();

$grandTotal = 0;
foreach ($seatTotals as $r) $grandTotal += (int)$r["total_votes"];

// 3) Centers list
$st = $pdo->prepare("SELECT id, center_name FROM centers WHERE seat_id=? ORDER BY center_name");
$st->execute([$seat_id]);
$centers = $st->fetchAll(); // [{id, center_name}, ...]

// 4) Center-wise votes (single query; then build matrix)
$st = $pdo->prepare("
  SELECT c.id AS center_id, v.symbol_id, v.vote_count
  FROM centers c
  LEFT JOIN votes v ON v.center_id = c.id AND v.seat_id = c.seat_id
  WHERE c.seat_id = ?
");
$st->execute([$seat_id]);
$rows = $st->fetchAll();

// Build matrix: $matrix[center_id][symbol_id] = vote_count
$matrix = [];
foreach ($rows as $r) {
  $cid = (int)$r["center_id"];
  $sid = isset($r["symbol_id"]) ? (int)$r["symbol_id"] : 0;
  $vc  = isset($r["vote_count"]) ? (int)$r["vote_count"] : 0;
  if (!isset($matrix[$cid])) $matrix[$cid] = [];
  if ($sid) $matrix[$cid][$sid] = $vc;
}
?>
<!doctype html>
<html>
<head>
  <link rel="stylesheet" href="../assets/style.css">
  <title>Election 2026 Results</title>
  <style>
    /* wide table usability */
    .table-wrap{ overflow:auto; border:1px solid #eee; border-radius:12px; }
    .table th{ position: sticky; top: 0; background: #fff; z-index: 1; }
    .right{ text-align:right; white-space:nowrap; }
    .nowrap{ white-space:nowrap; }
    .mini{ font-size:12px; color:#666; }
  </style>
</head>
<body class="container">
  <a class="btn-outline" href="index.php">← Back</a>

  <div class="card">
    <h2><?=h($seatName)?></h2>
    <p class="muted">এখন পর্যন্ত প্রাপ্ত মোট ভোট: <b><?=number_format($grandTotal)?></b></p>

    <!-- Seat totals -->
    <h3>প্রতিটি প্রতীকের প্রাপ্ত মোট ভোট</h3>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>প্রতীক</th>
            <th class="right">Total Votes</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($seatTotals as $r): ?>
            <tr>
              <td class="nowrap"><?=h($r["symbol_name"])?></td>
              <td class="right"><?=number_format((int)$r["total_votes"])?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div style="display:flex; gap:10px; flex-wrap:wrap; margin:20px 0;">
        <!--
        <a class="btn-outline" target="_blank" href="seat_report_pdf.php?seat_id=<?=$seat_id?>">
            Print / View PDF
        </a>
          -->
        <a class="btn" href="seat_report_pdf.php?seat_id=<?=$seat_id?>&download=1">
        Download PDF
        </a>

    </div>

    <hr>

    <!-- Center-wise matrix -->
    <h3>কেন্দ্রভিত্তিক ফলাফল</h3>
    <div class="mini">প্রতিটি সারিতে কেন্দ্রের নামের সামনে প্রত্যেক প্রতীকের প্রাপ্ত ভোট সংখ্যা দেখানো হয়েছে।</div>

    <div class="table-wrap" style="margin-top:10px;">
      <table class="table">
        <thead>
          <tr>
            <th class="nowrap">Center Name</th>
            <?php foreach($symbols as $s): ?>
              <th class="right nowrap"><?=h($s["symbol_name"])?></th>
            <?php endforeach; ?>
            <th class="right nowrap">Center Total</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($centers as $c): 
            $cid = (int)$c["id"];
            $centerTotal = 0;
          ?>
            <tr>
              <td class="nowrap"><?=h($c["center_name"])?></td>

              <?php foreach($symbols as $s): 
                $sid = (int)$s["id"];
                $v = (int)($matrix[$cid][$sid] ?? 0);
                $centerTotal += $v;
              ?>
                <td class="right"><?=number_format($v)?></td>
              <?php endforeach; ?>

              <td class="right"><b><?=number_format($centerTotal)?></b></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div> <br>

    <!-- CSV Download Button (below center-wise table) -->
    <div style="margin-top:14px; display:flex; justify-content:flex-start;">
        <a class="btn" href="seat_center_results_csv.php?seat_id=<?=$seat_id?>">
            Download CSV File
        </a>
    </div>


  </div>
</body>
</html>

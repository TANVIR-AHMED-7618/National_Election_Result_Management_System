<?php
require_once __DIR__."/../inc/db.php";
require_once __DIR__."/../vendor/autoload.php";

date_default_timezone_set('Asia/Dhaka');

$seat_id  = (int)($_GET["seat_id"] ?? 0);
$download = (int)($_GET["download"] ?? 0);

if (!$seat_id) { http_response_code(400); die("seat_id required"); }

// Seat name
$st = $pdo->prepare("SELECT seat_name FROM seats WHERE id=?");
$st->execute([$seat_id]);
$seatName = $st->fetchColumn();
if (!$seatName) { http_response_code(404); die("Seat not found"); }

// Symbol totals
$st = $pdo->prepare("
  SELECT sy.symbol_name, COALESCE(SUM(v.vote_count),0) AS total_votes
  FROM symbols sy
  LEFT JOIN votes v ON v.symbol_id = sy.id AND v.seat_id = sy.seat_id
  WHERE sy.seat_id=?
  GROUP BY sy.id, sy.symbol_name
  ORDER BY total_votes DESC, sy.symbol_name
");
$st->execute([$seat_id]);
$rows = $st->fetchAll();

$grandTotal = 0;
foreach ($rows as $r) $grandTotal += (int)$r["total_votes"];

$generatedAt = date("H:i:s, l, d F Y"); // সেকেন্ড, মিনিট, ঘন্টা, বার, তারিখ, সাল

// ---- mPDF config (Bangla font) ----
$defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
$fontDirs = $defaultConfig['fontDir'];

$defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
$fontData = $defaultFontConfig['fontdata'];

$mpdf = new \Mpdf\Mpdf([
  'mode' => 'utf-8',
  'format' => 'A4',
  'tempDir' => __DIR__ . '/../tmp',
  'useOTL' => 0xFF,  // ✅ Bengali shaping (কার/যুক্তাক্ষর) ঠিক রাখে
  'fontDir' => array_merge($fontDirs, [__DIR__ . '/../assets/fonts']),
  'fontdata' => $fontData + [
    'kalpurush' => [
      'R' => 'Kalpurush.ttf',
      'useOTL' => 0xFF,
    ],
  ],
  'default_font' => 'kalpurush',
]);





// HTML তৈরি
$trs = "";
foreach ($rows as $r) {
  $sym = htmlspecialchars($r["symbol_name"], ENT_QUOTES, 'UTF-8');
  $v   = number_format((int)$r["total_votes"]);
  $trs .= "<tr><td>{$sym}</td><td style='text-align:right'>{$v}</td></tr>";
}

$seatNameEsc = htmlspecialchars($seatName, ENT_QUOTES, 'UTF-8');
$grandTotalFmt = number_format($grandTotal);

$html = "
<style>
  body { font-family: kalpurush; font-size: 12pt; }
  .title { text-align:center; font-size: 20pt; margin-top: 10px; }
  table { width:100%; border-collapse:collapse; }
  th, td { border: 1px solid #333; padding: 8px; }
</style>

<div class='title'>{$seatNameEsc}</div>


<div class='gap'></div>

<div style='font-size:13pt; font-weight:bold;'>প্রতীক অনুযায়ী আসনের ফলাফল </div>
<div style='margin-top:4px;'>মোট ভোট: <b>{$grandTotalFmt}</b></div>

<table>
  <thead>
    <tr>
      <th>প্রতীকের নাম</th>
      <th style='text-align:right;'>মোট ভোট</th>
    </tr>
  </thead>
  <tbody>
    {$trs}
  </tbody>
</table> <br>

<div class='meta'>রিপোর্ট জেনারেট করার সময়: {$generatedAt}</div>
";

// Render
$mpdf->WriteHTML($html);

$filename = "Seat_Report_{$seat_id}.pdf";
if ($download === 1) {
  $mpdf->Output($filename, \Mpdf\Output\Destination::DOWNLOAD); // Download
} else {
  $mpdf->Output($filename, \Mpdf\Output\Destination::INLINE);   // View/Print
}
exit;

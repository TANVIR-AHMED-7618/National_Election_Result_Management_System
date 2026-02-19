
<?php
require_once __DIR__."/../inc/auth.php";
require_once __DIR__."/../inc/db.php";
require_once __DIR__."/../inc/helpers.php";
require_admin();

require_once __DIR__."/../vendor/autoload.php";
use PhpOffice\PhpSpreadsheet\IOFactory;

$msg = ""; $err = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["xlsx"])) {
  $tmp = $_FILES["xlsx"]["tmp_name"];

  try {
    $spreadsheet = IOFactory::load($tmp);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray();

    // header index detect
    $header = array_map('trim', $rows[0]);
    $seatIdx = array_search("seat_name", $header);
    $centerIdx = array_search("center_name", $header);

    if ($seatIdx === false || $centerIdx === false) {
      throw new Exception("XLSX header must contain seat_name and center_name");
    }

    $pdo->beginTransaction();

    $insSeat = $pdo->prepare("INSERT IGNORE INTO seats(seat_name) VALUES (?)");
    $getSeat = $pdo->prepare("SELECT id FROM seats WHERE seat_name=?");
    $insCenter = $pdo->prepare("INSERT IGNORE INTO centers(seat_id, center_name) VALUES (?, ?)");

    $count = 0;
    for ($i=1; $i<count($rows); $i++) {
      $seatName = trim((string)$rows[$i][$seatIdx]);
      $centerName = trim((string)$rows[$i][$centerIdx]);
      if ($seatName === "" || $centerName === "") continue;

      $insSeat->execute([$seatName]);
      $getSeat->execute([$seatName]);
      $seatId = $getSeat->fetchColumn();

      $insCenter->execute([$seatId, $centerName]);
      $count++;
    }

    $pdo->commit();
    $msg = "Uploaded OK. Processed rows: ".$count;

  } catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $err = "Upload failed: ".$e->getMessage();
  }
}
?>
<!doctype html>
<html>
<head>
  <link rel="stylesheet" href="../assets/style.css">
  <title>Upload Seat Centers</title>
</head>
<body class="container">
  <a class="btn-outline" href="dashboard.php">← Back</a>
  <div class="card">
    <h2>Upload Seat → Centers (XLSX)</h2>
    <p>Required columns: <b>seat_name</b>, <b>center_name</b></p>
    <?php if($msg): ?><div class="success"><?=h($msg)?></div><?php endif; ?>
    <?php if($err): ?><div class="alert"><?=h($err)?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data">
      <input type="file" name="xlsx" accept=".xlsx" required>
      <button class="btn">Upload</button>
    </form>
  </div>
</body>
</html>

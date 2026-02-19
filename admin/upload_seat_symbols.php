
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

    $header = array_map('trim', $rows[0]);
    $seatIdx = array_search("seat_name", $header);
    $symIdx  = array_search("symbol_name", $header);

    if ($seatIdx === false || $symIdx === false) {
      throw new Exception("XLSX header must contain seat_name and symbol_name");
    }

    $pdo->beginTransaction();

    $insSeat = $pdo->prepare("INSERT IGNORE INTO seats(seat_name) VALUES (?)");
    $getSeat = $pdo->prepare("SELECT id FROM seats WHERE seat_name=?");
    $insSym  = $pdo->prepare("INSERT IGNORE INTO symbols(seat_id, symbol_name) VALUES (?, ?)");

    $count = 0;
    for ($i=1; $i<count($rows); $i++) {
      $seatName = trim((string)$rows[$i][$seatIdx]);
      $symName  = trim((string)$rows[$i][$symIdx]);
      if ($seatName === "" || $symName === "") continue;

      $insSeat->execute([$seatName]);
      $getSeat->execute([$seatName]);
      $seatId = $getSeat->fetchColumn();

      $insSym->execute([$seatId, $symName]);
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
  <title>Upload Seat Symbols</title>
</head>
<body class="container">
  <a class="btn-outline" href="dashboard.php">← Back</a>
  <div class="card">
    <h2>Upload Seat → Symbols (XLSX)</h2>
    <p>Required columns: <b>seat_name</b>, <b>symbol_name</b></p>
    <?php if($msg): ?><div class="success"><?=h($msg)?></div><?php endif; ?>
    <?php if($err): ?><div class="alert"><?=h($err)?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data">
      <input type="file" name="xlsx" accept=".xlsx" required>
      <button class="btn">Upload</button>
    </form>
  </div>
</body>
</html>

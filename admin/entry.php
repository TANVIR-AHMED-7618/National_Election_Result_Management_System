
<?php
require_once __DIR__."/../inc/auth.php";
require_once __DIR__."/../inc/db.php";
require_once __DIR__."/../inc/helpers.php";
require_admin();

$seats = $pdo->query("SELECT id, seat_name FROM seats ORDER BY seat_name")->fetchAll();

$seat_id   = (int)($_GET["seat_id"] ?? 0);
$center_id = (int)($_GET["center_id"] ?? 0);

$centers = [];
$symbols = [];
if ($seat_id) {
  $st = $pdo->prepare("SELECT id, center_name FROM centers WHERE seat_id=? ORDER BY center_name");
  $st->execute([$seat_id]);
  $centers = $st->fetchAll();

  $st = $pdo->prepare("SELECT id, symbol_name FROM symbols WHERE seat_id=? ORDER BY symbol_name");
  $st->execute([$seat_id]);
  $symbols = $st->fetchAll();
}

$msg=""; $err="";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $seat_id   = (int)($_POST["seat_id"] ?? 0);
  $center_id = (int)($_POST["center_id"] ?? 0);

  if (!$seat_id || !$center_id) {
    $err = "Seat and Center required.";
  } else {
    try {
      $pdo->beginTransaction();
      $up = $pdo->prepare("
        INSERT INTO votes(seat_id, center_id, symbol_id, vote_count)
        VALUES(?,?,?,?)
        ON DUPLICATE KEY UPDATE vote_count=VALUES(vote_count)
      ");

      foreach (($_POST["votes"] ?? []) as $symbol_id => $vote) {
        $symbol_id = (int)$symbol_id;
        $vote = (int)$vote;
        $up->execute([$seat_id, $center_id, $symbol_id, $vote]);
      }
      $pdo->commit();
      $msg = "Votes saved/updated successfully.";
    } catch(Exception $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $err = "Save failed.";
    }
  }

  // reload dropdown lists
  $st = $pdo->prepare("SELECT id, center_name FROM centers WHERE seat_id=? ORDER BY center_name");
  $st->execute([$seat_id]);
  $centers = $st->fetchAll();

  $st = $pdo->prepare("SELECT id, symbol_name FROM symbols WHERE seat_id=? ORDER BY symbol_name");
  $st->execute([$seat_id]);
  $symbols = $st->fetchAll();
}

// existing votes (to show prefilled)
$existing = [];
if ($seat_id && $center_id) {
  $st = $pdo->prepare("SELECT symbol_id, vote_count FROM votes WHERE seat_id=? AND center_id=?");
  $st->execute([$seat_id, $center_id]);
  foreach ($st->fetchAll() as $r) $existing[(int)$r["symbol_id"]] = (int)$r["vote_count"];
}
?>
<!doctype html>
<html>
<head>
  <link rel="stylesheet" href="../assets/style.css">
  <title>Vote Entry</title>
</head>
<body class="container">
  <a class="btn-outline" href="dashboard.php">← Back</a>

  <div class="card">
    <h2>Enter / Update Votes</h2>
    <?php if($msg): ?><div class="success"><?=h($msg)?></div><?php endif; ?>
    <?php if($err): ?><div class="alert"><?=h($err)?></div><?php endif; ?>

    <!-- Seat selector (GET reload) -->
    <form method="get" class="row">
      <div class="col">
        <label>Seat</label>
        <select name="seat_id" onchange="this.form.submit()">
          <option value="">-- Select Seat --</option>
          <?php foreach($seats as $s): ?>
            <option value="<?=$s["id"]?>" <?=$seat_id===$s["id"]?'selected':''?>>
              <?=h($s["seat_name"])?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col">
        <label>Center</label>
        <select name="center_id" onchange="this.form.submit()">
          <option value="">-- Select Center --</option>
          <?php foreach($centers as $c): ?>
            <option value="<?=$c["id"]?>" <?=$center_id===$c["id"]?'selected':''?>>
              <?=h($c["center_name"])?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col">
        <button class="btn-outline" type="submit">Load</button>
      </div>
    </form>

    <?php if($seat_id && $center_id): ?>
      <hr>
      <!-- Votes POST -->
      <form method="post">
        <input type="hidden" name="seat_id" value="<?=$seat_id?>">
        <input type="hidden" name="center_id" value="<?=$center_id?>">

        <h3>Symbol-wise Votes</h3>
        <div class="grid2">
          <?php foreach($symbols as $sym): 
            $val = $existing[$sym["id"]] ?? 0;
          ?>
            <div class="field">
              <label><?=h($sym["symbol_name"])?></label>
              <input type="number" name="votes[<?=$sym["id"]?>]" min="0" value="<?=$val?>">
            </div>
          <?php endforeach; ?>
        </div>

        <button class="btn" type="submit">Save / Update</button>
      </form>
    <?php else: ?>
      <p class="muted">Seat + Center select করলে symbol fields আসবে।</p>
    <?php endif; ?>
  </div>
</body>
</html>


<?php
require_once __DIR__."/../inc/auth.php";
require_once __DIR__."/../inc/db.php";
require_once __DIR__."/../inc/helpers.php";
require_operator();

$seat_id = (int)($_SESSION["operator_seat_id"] ?? 0);
if (!$seat_id) die("Seat not assigned.");

$st = $pdo->prepare("SELECT seat_name FROM seats WHERE id=?");
$st->execute([$seat_id]);
$seat_name = $st->fetchColumn();

$center_id = (int)($_GET["center_id"] ?? 0);
$mode = ($_GET["mode"] ?? ""); // "" or "update"


// centers + symbols (only for this seat)
_trigger:
$st = $pdo->prepare("SELECT id, center_name FROM centers WHERE seat_id=? ORDER BY center_name");
$st->execute([$seat_id]);
$centers = $st->fetchAll();

$st = $pdo->prepare("SELECT id, symbol_name FROM symbols WHERE seat_id=? ORDER BY symbol_name");
$st->execute([$seat_id]);
$symbols = $st->fetchAll();

$msg=""; $err="";

// ✅ Flash message (after redirect)
if (!empty($_SESSION["flash_success"])) {
  $msg = $_SESSION["flash_success"];
  unset($_SESSION["flash_success"]);
}


if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $center_id = (int)($_POST["center_id"] ?? 0);

  if (!$center_id) {
    $err = "Center required.";
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
        // ✅ Flash + Redirect (PRG)
        $_SESSION["flash_success"] = "Votes saved/updated successfully.";
        header("Location: entry.php?success=1");
        exit;

    } catch(Exception $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $err = "Save failed.";
    }
  }
}

$hasExisting = false;

if ($center_id) {
  $st = $pdo->prepare("SELECT symbol_id, vote_count FROM votes WHERE seat_id=? AND center_id=?");
  $st->execute([$seat_id, $center_id]);
  $rows = $st->fetchAll();

  if ($rows) $hasExisting = true;

  foreach ($rows as $r) {
    $existing[(int)$r["symbol_id"]] = (int)$r["vote_count"];
  }
}


// existing votes
$existing = [];
if ($center_id) {
  $st = $pdo->prepare("SELECT symbol_id, vote_count FROM votes WHERE seat_id=? AND center_id=?");
  $st->execute([$seat_id, $center_id]);
  foreach ($st->fetchAll() as $r) $existing[(int)$r["symbol_id"]] = (int)$r["vote_count"];
}
?>

<!doctype html>
<html>
<head>
  <link rel="stylesheet" href="../assets/style.css">
  <title>Data Entry - Vote Entry</title>
</head>
<body class="container">
  <a class="btn-outline" href="dashboard.php">← Back</a>

  <div class="card">
    <h2>Vote Entry</h2>
    <p class="muted">Assigned Seat: <b><?=h($seat_name)?></b></p>

    <?php if($msg): ?><div class="success"><?=h($msg)?></div><?php endif; ?>
    <?php if($err): ?><div class="alert"><?=h($err)?></div><?php endif; ?>

    <!-- Center selector -->
    <form method="get" class="row">
      <div class="col">
        <label>Center</label>
        <select name="center_id" id="centerSelect" onchange="this.form.submit()">
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

    <?php if($center_id): ?>

        <?php if($hasExisting && $mode !== "update"): ?>
        <!-- ✅ Already entered message -->
            <div class="alert">
                এই কেন্দ্রের ভোট ইতোমধ্যে ইনপুট দেয়া হয়েছে। আগের ইনপুট ডেটায় কোনো ভুল থাকলে বা পরিবর্তনের প্রয়োজন হলে নিচের আপডেট অপশন থেকে আপডেট করুন।
            </div>

            <a class="btn" href="entry.php?center_id=<?=$center_id?>&mode=update">
                Update Votes (এই কেন্দ্র)
            </a>

        <?php else: ?>
            <!-- ✅ Show input form (new OR update mode) -->
            <hr>

            <form method="post" id="voteForm">
            <input type="hidden" name="center_id" value="<?=$center_id?>">

            <h3><?= ($hasExisting ? "Update Votes" : "Enter Votes") ?></h3>

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

            <button class="btn" type="submit">
                <?= ($hasExisting ? "Update" : "Save") ?>
            </button>

            <?php if($hasExisting): ?>
                <a class="btn-outline" style="margin-left:8px;" href="entry.php?center_id=<?=$center_id?>">
                Cancel
                </a>
            <?php endif; ?>

            </form>
        <?php endif; ?>

        <?php else: ?>
            <p class="muted">Center select করলে ভোট ইনপুট আসবে।</p>
        <?php endif; ?>
</div>

    <script>
    document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("voteForm");
    const centerSel = document.getElementById("centerSelect");

    if (!form) return;

    form.addEventListener("submit", (e) => {
        // selected center name
        let centerName = "";
        if (centerSel && centerSel.selectedIndex >= 0) {
        centerName = centerSel.options[centerSel.selectedIndex].text.trim();
        }

        const msg =
        "আপনার ইনপুট দেয়া ভোটের সংখ্যা ও বাছাই করা কেন্দ্রের নাম সঠিক কিনা ভালোভাবে যাচাই করুন।\n\n" +
        "নির্বাচিত কেন্দ্র: " + (centerName || "(Center not selected)") + "\n\n" +
        "সবকিছু সঠিকভাবে করে থাকলে OK-তে চাপুন অন্যথায় Cancel চাপুন।";

        if (!confirm(msg)) {
        e.preventDefault(); // ❌ Cancel => submit হবে না
        }
        // ✅ OK => submit হবে
    });
    });
    </script>


</body>
</html>

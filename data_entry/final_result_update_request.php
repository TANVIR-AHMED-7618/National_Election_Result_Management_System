
<?php
require_once __DIR__."/../inc/auth.php";
require_once __DIR__."/../inc/db.php";
require_once __DIR__."/../inc/helpers.php";
require_operator();

$seat_id = (int)($_SESSION["operator_seat_id"] ?? 0);
$operator_id = (int)($_SESSION["operator_id"] ?? 0);

// seat name
$st = $pdo->prepare("SELECT seat_name FROM seats WHERE id=?");
$st->execute([$seat_id]);
$seat_name = $st->fetchColumn();

// must have existing final result
$st = $pdo->prepare("SELECT symbol_name, vote_count FROM seat_final_results WHERE seat_id=?");
$st->execute([$seat_id]);
$existingRows = $st->fetchAll();
if (!$existingRows) die("No final result found for this seat.");

$existing = [];
foreach ($existingRows as $r) $existing[$r["symbol_name"]] = (int)$r["vote_count"];

$msg = "";
if (!empty($_SESSION["flash_success"])) {
  $msg = $_SESSION["flash_success"];
  unset($_SESSION["flash_success"]);
}


$msg=""; $err="";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $reason = trim($_POST["reason"] ?? "");
  if ($reason === "") {
    $err = "Reason is required.";
  } else {
    $payload = [];
    foreach ($existing as $symName => $oldVal) {
      $payload[$symName] = (int)($_POST["votes"][$symName] ?? $oldVal);
    }

    $ins = $pdo->prepare("
      INSERT INTO final_result_update_requests(seat_id, operator_id, payload_json, reason)
      VALUES(?,?,?,?)
    ");
    $ins->execute([$seat_id, $operator_id, json_encode($payload, JSON_UNESCAPED_UNICODE), $reason]);

    $_SESSION["flash_success"] = "Update Request Send to Admin Successfully.";
    header("Location: final_result_update_request.php?sent=1");
    exit;

  }
}
?>
<!doctype html>
<html>
<head>
  <link rel="stylesheet" href="../assets/style.css">
  <title>Final Result Update Request</title>
</head>
<body class="container">
  <a class="btn-outline" href="final_result.php">← Back</a>

  <div class="card">
    <h2>Request Final Result Update</h2>
    <p class="muted">Seat: <b><?=h($seat_name)?></b></p>

    <?php if($msg): ?><div class="success"><?=h($msg)?></div><?php endif; ?>
    <?php if($err): ?><div class="alert"><?=h($err)?></div><?php endif; ?>

    <form method="post" id="reqForm">
      <h3>Proposed Corrected Votes</h3>

      <div class="grid2">
        <?php foreach($existing as $sn=>$vv): ?>
          <div class="field">
            <label><?=h($sn)?> (Current: <?=number_format($vv)?>)</label>
            <input type="number" min="0" name="votes[<?=h($sn)?>]" value="<?=$vv?>" required>
          </div>
        <?php endforeach; ?>
      </div>

      <label>Reason (why update needed)</label>
      <textarea name="reason" required></textarea>

      <button class="btn" type="submit">Send Request</button>
    </form>

    <script>
    document.addEventListener("DOMContentLoaded", ()=>{
      const f = document.getElementById("reqForm");
      if(!f) return;
      f.addEventListener("submit",(e)=>{
        const ok = confirm("আপনি এডমিনের কাছে আপডেট রিকোয়েস্ট পাঠাচ্ছেন। OK দিলে রিকোয়েস্ট যাবে, Cancel দিলে যাবে না।");
        if(!ok) e.preventDefault();
      });
    });
    </script>
  </div>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
        const f = document.getElementById("reqForm");
        if (!f) return;

        f.addEventListener("submit", (e) => {
            const msg = "সকল প্রতীকের জন্য প্রাপ্ত ভোটের সংখ্যাটি সঠিকভাবে বসিয়েছন কিনা ভালোভাবে যাচাই করুন। সব ঠিক থাকলে OK চাপুন অন্যথায় Cancel চাপুন";
            if (!confirm(msg)) {
            e.preventDefault(); // ✅ Cancel => ফর্ম থাকবে
            }
        });
        });
    </script>

</body>
</html>

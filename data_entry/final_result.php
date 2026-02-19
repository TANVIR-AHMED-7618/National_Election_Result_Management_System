
<?php
require_once __DIR__."/../inc/auth.php";
require_once __DIR__."/../inc/db.php";
require_once __DIR__."/../inc/helpers.php";
require_operator();

$seat_id = (int)($_SESSION["operator_seat_id"] ?? 0);
$operator_id = (int)($_SESSION["operator_id"] ?? 0);
if (!$seat_id) die("Seat not assigned.");

// seat name
$st = $pdo->prepare("SELECT seat_name FROM seats WHERE id=?");
$st->execute([$seat_id]);
$seat_name = $st->fetchColumn();

// symbols for this seat (we will store by symbol_name)
$st = $pdo->prepare("SELECT DISTINCT TRIM(symbol_name) AS symbol_name FROM symbols WHERE seat_id=? ORDER BY symbol_name");
$st->execute([$seat_id]);
$symbols = array_map(fn($r)=>$r["symbol_name"], $st->fetchAll());

// check: already final submitted?
$st = $pdo->prepare("SELECT COUNT(*) FROM seat_final_results WHERE seat_id=?");
$st->execute([$seat_id]);
$alreadyFinal = ((int)$st->fetchColumn() > 0);

// ✅ was this page loaded just after first submit?
$justSubmitted = (isset($_GET["just_submitted"]) && $_GET["just_submitted"] == "1");


// load existing final results (if any)
$existing = [];
if ($alreadyFinal) {
  $st = $pdo->prepare("SELECT symbol_name, vote_count FROM seat_final_results WHERE seat_id=?");
  $st->execute([$seat_id]);
  foreach ($st->fetchAll() as $r) {
    $existing[$r["symbol_name"]] = (int)$r["vote_count"];
  }
}

$msg=""; $err="";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  if ($alreadyFinal) {
    $err = "Final Result already submitted. Update requires admin approval.";
  } else {
    try {
      $pdo->beginTransaction();

      $ins = $pdo->prepare("
        INSERT INTO seat_final_results(seat_id, symbol_name, vote_count, created_by_operator_id)
        VALUES(?,?,?,?)
      ");

      foreach ($symbols as $symName) {
        $v = (int)($_POST["votes"][$symName] ?? 0);
        $ins->execute([$seat_id, $symName, $v, $operator_id]);
      }

      $pdo->commit();
        $_SESSION["flash_success"] = "Final Result submitted successfully.";
        header("Location: final_result.php?just_submitted=1");
        exit;


    } catch(Exception $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $err = "Submit failed.";
    }
  }
}

// flash message
if (!empty($_SESSION["flash_success"])) {
  $msg = $_SESSION["flash_success"];
  unset($_SESSION["flash_success"]);
}
?>
<!doctype html>
<html>
<head>
  <link rel="stylesheet" href="../assets/style.css">
  <title>Final Result - <?=h($seat_name)?></title>
</head>
<body class="container">
    <a class="btn-outline" href="dashboard.php">← Back</a>

    <div class="card">
        <h2>Official Final Result</h2>
        <p class="muted">Seat: <b><?=h($seat_name)?></b></p>

        <?php if($msg): ?><div class="success"><?=h($msg)?></div><?php endif; ?>
        <?php if($err): ?><div class="alert"><?=h($err)?></div><?php endif; ?>

        <?php if(!$alreadyFinal): ?>
    <!-- ✅ First-time Final Result Entry Form -->
    <form method="post" id="finalForm">
        <h3>Enter Final Votes (One-time Submit)</h3>

        <div class="grid2">
        <?php foreach($symbols as $sn): ?>
            <div class="field">
            <label><?=h($sn)?></label>
            <input type="number" min="0" name="votes[<?=h($sn)?>]" value="0" required>
            </div>
        <?php endforeach; ?>
        </div>

        <button class="btn" type="submit">Submit Final Result</button>
    </form>

    <script>
    document.addEventListener("DOMContentLoaded", ()=>{
        const form = document.getElementById("finalForm");
        if(!form) return;
        form.addEventListener("submit",(e)=>{
        const ok = confirm("আপনার ইনপুট দেয়া ফাইনাল ভোটের সংখ্যা সঠিক কিনা ভালোভাবে যাচাই করুন। সবকিছু সঠিকভাবে করে থাকলে OK-তে চাপুন অন্যথায় Cancel চাপুন।");
        if(!ok) e.preventDefault();
        });
    });
    </script>

    <?php else: ?>

    <!-- ✅ Already submitted: show success-only after first submit, otherwise show warning + request -->
    <?php if(!$justSubmitted): ?>
        <div class="alert">
        এই আসনের Official Final Result ইতোমধ্যে সাবমিট করা হয়েছে। অপারেটর আর আপডেট করতে পারবে না।
        ভুল থাকলে “Update Request” দিয়ে এডমিনের কাছে রিকোয়েস্ট পাঠান।
        </div> <br>

        <a class="btn" href="final_result_update_request.php">Send a Update Request to Admin</a>
    <?php endif; ?>

    <table class="table">
        <thead>
        <tr>
            <th>প্রতীক</th>
            <th style="text-align:right;">ভোট</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach($existing as $sn=>$vv): ?>
            <tr>
            <td><?=h($sn)?></td>
            <td style="text-align:right;"><?=number_format($vv)?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php endif; ?>

    </div>
</body>
</html>


<?php
require_once __DIR__."/../inc/auth.php";
require_once __DIR__."/../inc/db.php";
require_once __DIR__."/../inc/helpers.php";
require_admin();

$admin_id = (int)($_SESSION["admin_id"] ?? 0);

$msg=""; $err="";

// approve/reject action
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $req_id = (int)($_POST["req_id"] ?? 0);
  $action = $_POST["action"] ?? "";
  $note   = trim($_POST["admin_note"] ?? "");

  $st = $pdo->prepare("SELECT * FROM final_result_update_requests WHERE id=? AND status='PENDING'");
  $st->execute([$req_id]);
  $req = $st->fetch();

  if (!$req) {
    $err = "Request not found or already processed.";
  } else {
    if ($action === "APPROVE") {
      try {
        $pdo->beginTransaction();

        $payload = json_decode($req["payload_json"], true);
        if (!is_array($payload)) throw new Exception("Invalid payload");

        // Apply updates to seat_final_results
        $up = $pdo->prepare("
          INSERT INTO seat_final_results(seat_id, symbol_name, vote_count, updated_by_admin_id, updated_at)
          VALUES(?,?,?,?,NOW())
          ON DUPLICATE KEY UPDATE vote_count=VALUES(vote_count), updated_by_admin_id=VALUES(updated_by_admin_id), updated_at=NOW()
        ");

        foreach ($payload as $symbol_name => $vote_count) {
          $up->execute([(int)$req["seat_id"], $symbol_name, (int)$vote_count, $admin_id]);
        }

        // mark request approved
        $u = $pdo->prepare("
          UPDATE final_result_update_requests
          SET status='APPROVED', admin_id=?, admin_note=?, decided_at=NOW()
          WHERE id=?
        ");
        $u->execute([$admin_id, $note, $req_id]);

        $pdo->commit();
        $msg = "Request approved and final result updated.";

      } catch(Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $err = "Approve failed.";
      }
    } elseif ($action === "REJECT") {
      $u = $pdo->prepare("
        UPDATE final_result_update_requests
        SET status='REJECTED', admin_id=?, admin_note=?, decided_at=NOW()
        WHERE id=?
      ");
      $u->execute([$admin_id, $note, $req_id]);
      $msg = "Request rejected.";
    }
  }
}

// list pending
$rows = $pdo->query("
  SELECT r.*, s.seat_name
  FROM final_result_update_requests r
  JOIN seats s ON s.id = r.seat_id
  WHERE r.status='PENDING'
  ORDER BY r.created_at DESC
")->fetchAll();
?>
<!doctype html>
<html>
<head>
  <link rel="stylesheet" href="../assets/style.css">
  <title>Final Result Update Requests</title>
</head>
<body class="container">
  <a class="btn-outline" href="dashboard.php">← Back</a>

  <div class="card">
    <h2>Final Result Update Requests</h2>

    <?php if($msg): ?><div class="success"><?=h($msg)?></div><?php endif; ?>
    <?php if($err): ?><div class="alert"><?=h($err)?></div><?php endif; ?>

    <table class="table">
      <thead>
        <tr>
          <th>Seat</th>
          <th>Operator ID</th>
          <th>Reason</th>
          <th>Requested Data</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $r): ?>
          <tr>
            <td><?=h($r["seat_name"])?></td>
            <td style="text-align:right;"><?= (int)$r["operator_id"] ?></td>
            <td><?=h($r["reason"])?></td>
            <td>
              <pre style="white-space:pre-wrap;max-width:360px;"><?=h($r["payload_json"])?></pre>
            </td>
            <td>
              <form method="post" style="display:flex; gap:8px; flex-direction:column;">
                <input type="hidden" name="req_id" value="<?=$r["id"]?>">
                <textarea name="admin_note" placeholder="Admin note (optional)"></textarea>
                <button class="btn" name="action" value="APPROVE">Approve</button>
                <button class="btn-outline" name="action" value="REJECT">Reject</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</body>
</html>


<?php
require_once __DIR__."/../inc/auth.php";
require_once __DIR__."/../inc/db.php";
require_once __DIR__."/../inc/helpers.php";
require_admin();

$seats = $pdo->query("SELECT id, seat_name FROM seats ORDER BY seat_name")->fetchAll();

$msg=""; $err="";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $seat_id  = (int)($_POST["seat_id"] ?? 0);
  $username = trim($_POST["username"] ?? "");
  $password = $_POST["password"] ?? "";

  if (!$seat_id || $username==="" || $password==="") {
    $err = "Seat, username, password required.";
  } else {
    try {
      $hash = password_hash($password, PASSWORD_DEFAULT);

      // 1 seat = 1 operator (আপনি চাইলে enforce করতে পারেন)
      // যদি একই seat_id এর জন্য operator already থাকে, তাহলে block
      $st = $pdo->prepare("SELECT id FROM operators WHERE seat_id=? LIMIT 1");
      $st->execute([$seat_id]);
      if ($st->fetch()) {
        throw new Exception("This seat already has an operator account.");
      }

      $ins = $pdo->prepare("INSERT INTO operators(seat_id, username, password_hash) VALUES (?,?,?)");
      $ins->execute([$seat_id, $username, $hash]);

      $msg = "Operator created successfully!";
    } catch(Exception $e) {
      $err = $e->getMessage();
    }
  }
}
?>
<!doctype html>
<html>
<head>
  <link rel="stylesheet" href="../assets/style.css">
  <title>Create Operator</title>
</head>
<body class="container">
  <a class="btn-outline" href="dashboard.php">← Back</a>

  <div class="card">
    <h2>Create Data Entry Operator</h2>

    <?php if($msg): ?><div class="success"><?=h($msg)?></div><?php endif; ?>
    <?php if($err): ?><div class="alert"><?=h($err)?></div><?php endif; ?>

    <form method="post">
      <label>Seat</label>
      <select name="seat_id" required>
        <option value="">-- Select Seat --</option>
        <?php foreach($seats as $s): ?>
          <option value="<?=$s["id"]?>"><?=h($s["seat_name"])?></option>
        <?php endforeach; ?>
      </select>

      <label>Username</label>
      <input name="username" required>

      <label>Password</label>
      <input type="password" name="password" required>

      <button class="btn" type="submit">Create Account</button>
    </form>

    <hr>
    <p class="muted">
      নোট: প্রতিটি আসনের জন্য ১টি operator রাখলে control সহজ হবে।
    </p>
  </div>
</body>
</html>

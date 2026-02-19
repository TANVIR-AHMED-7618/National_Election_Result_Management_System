
<?php
require_once __DIR__."/../inc/db.php";
require_once __DIR__."/../inc/helpers.php";
session_start();

$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $username = trim($_POST["username"] ?? "");
  $password = $_POST["password"] ?? "";

  $st = $pdo->prepare("SELECT * FROM operators WHERE username=? AND is_active=1 LIMIT 1");
  $st->execute([$username]);
  $op = $st->fetch();

  if ($op && password_verify($password, $op["password_hash"])) {
    session_regenerate_id(true);
    $_SESSION["operator_id"] = $op["id"];
    $_SESSION["operator_username"] = $op["username"];
    $_SESSION["operator_seat_id"] = (int)$op["seat_id"];
    header("Location: dashboard.php");
    exit;
  } else {
    $error = "Invalid username/password";
  }
}
?>
<!doctype html>
<html>
<head>
  <link rel="stylesheet" href="../assets/style.css">
  <title>Data Entry Login</title>
</head>
<body class="container">
  <div class="card">
    <h2>Data Entry Login</h2>
    <?php if($error): ?><div class="alert"><?=h($error)?></div><?php endif; ?>
    <form method="post">
      <label>Username</label>
      <input name="username" required>
      <label>Password</label>
      <input type="password" name="password" required>
      <button class="btn">Login</button>
    </form>
  </div>
</body>
</html>

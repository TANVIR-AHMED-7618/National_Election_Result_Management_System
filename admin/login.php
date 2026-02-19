
<?php
require_once __DIR__."/../inc/db.php";
require_once __DIR__."/../inc/helpers.php";
session_start();

$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $username = trim($_POST["username"] ?? "");
  $password = $_POST["password"] ?? "";

  $st = $pdo->prepare("SELECT * FROM admins WHERE username=? LIMIT 1");
  $st->execute([$username]);
  $admin = $st->fetch();

  if ($admin && password_verify($password, $admin["password_hash"])) {
    session_regenerate_id(true);
    $_SESSION["admin_id"] = $admin["id"];
    $_SESSION["admin_username"] = $admin["username"];
    header("Location: dashboard.php");
    exit;
  }
  else {
    $error = "Invalid username/password";
  }
}
?>
<!doctype html>
<html>
<head>
  <link rel="stylesheet" href="../assets/style.css">
  <title>Admin Login</title>
</head>
<body class="container">
  <div class="card">
    <h2>Admin Login</h2>
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

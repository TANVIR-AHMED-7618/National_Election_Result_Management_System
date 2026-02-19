
<?php
require_once __DIR__."/../inc/auth.php";
require_operator();
?>
<!doctype html>
<html>
<head>
  <link rel="stylesheet" href="../assets/style.css">
  <title>Data Entry Dashboard</title>
</head>
<body class="container">
  <div class="topbar">
    <div>Operator: <?=htmlspecialchars($_SESSION["operator_username"]) ?></div>
    <a class="btn-outline" href="logout.php">Logout</a>
  </div>

  <div class="grid">
    <a class="card link" href="entry.php">Enter / Update Votes</a>
    <a class="card link" href="final_result.php">Final Result of this Seat</a>
  </div>
</body>
</html>

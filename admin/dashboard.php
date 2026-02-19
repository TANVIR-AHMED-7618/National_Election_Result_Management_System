
<?php
require_once __DIR__."/../inc/auth.php";
require_admin();
?>
<!doctype html>
<html>
<head>
  <link rel="stylesheet" href="../assets/style.css">
  <title>Admin Dashboard</title>
</head>
<body class="container">
  <div class="topbar">
    <div>Logged in: <?=htmlspecialchars($_SESSION["admin_username"]) ?></div>
    <a class="btn-outline" href="logout.php">Logout</a>
  </div>

  <div class="grid">
    <a class="card link" href="upload_seat_centers.php">Upload Seat → Centers (XLSX)</a>
    <a class="card link" href="upload_seat_symbols.php">Upload Seat → Symbols (XLSX)</a>
    <a class="card link" href="create_operator.php">Create Data Entry Operator</a>
    <a class="card link" href="final_result_requests.php">Final Result Update Requests</a>
    <a class="card link" href="entry.php">Enter / Update Votes</a>
  </div>

</body>
</html>

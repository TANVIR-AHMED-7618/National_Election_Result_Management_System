
<?php
session_start();
unset($_SESSION["operator_id"], $_SESSION["operator_username"], $_SESSION["operator_seat_id"]);
session_destroy();
header("Location: login.php");
exit;

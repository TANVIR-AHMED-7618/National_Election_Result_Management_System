
<?php
session_start();

function require_admin() {
  if (empty($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
  }
}

function require_operator() {
  if (empty($_SESSION["operator_id"])) {
    header("Location: login.php");
    exit;
  }
}


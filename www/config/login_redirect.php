<?php
// Include this file at the top of login.php
require_once('config/session.php');


if (isset($_SESSION['id']) && isset($_SESSION['username'])) {
     
    session_destroy();


  // Redirect to login page
  header('Location: /index.php');
    // header('Location: dashboard.php');
    exit;
}

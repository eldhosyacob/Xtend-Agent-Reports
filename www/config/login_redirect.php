<?php
// Include this file at the top of login.php
require_once('config/session.php');


if (isset($_SESSION['id']) && isset($_SESSION['username'])) {
    header('Location: dashboard.php');
    exit;
}

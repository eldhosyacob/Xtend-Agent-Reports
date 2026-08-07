<?php

if (session_status() === PHP_SESSION_NONE) {
    session_name('REPORTSSESSID');
    session_start();

    // Update last activity time
    $_SESSION['LAST_ACTIVITY'] = time();
}
<?php

if (session_status() === PHP_SESSION_NONE) {

    // Project-specific session name
    session_name('REPORTSSESSID');

    // 30 minutes
    $sessionLifetime = 3600;

    // Set session lifetime
    ini_set('session.gc_maxlifetime', $sessionLifetime);

    session_start();

    // Check inactivity timeout
    if (isset($_SESSION['LAST_ACTIVITY']) &&
        (time() - $_SESSION['LAST_ACTIVITY']) > $sessionLifetime) {

        // Set last_activity to NULL in database before clearing session
        if (isset($_SESSION['id'])) {
            require_once __DIR__ . '/database.php';
            $db = getDatabaseConnection();
            if ($db) {
                try {
                    $updateActivity = $db->prepare("UPDATE users SET last_activity = NULL WHERE id = :id");
                    $updateActivity->execute(['id' => $_SESSION['id']]);
                } catch (PDOException $e) {
                    error_log("Session timeout database error: " . $e->getMessage());
                }
            }
        }

        // Clear session data
        $_SESSION = [];

        // Delete session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        // Destroy session
        session_destroy();

        header("Location: /index.php?error=session_timeout");
        exit;
    }

    // Update last activity time
    $_SESSION['LAST_ACTIVITY'] = time();
}
<?php
require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

// Check session authentication and restrict to admin users
if (!isset($_SESSION['id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized: Administrator access required'
    ]);
    exit;
}

$action = isset($_POST['action']) ? trim($_POST['action']) : '';

require_once __DIR__ . '/../config/database.php';
$db = getDatabaseConnection();

if (!$db) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit;
}

if ($action === 'fetch_users') {
    $stmt = $db->query("SELECT id, username, real_name, department, user_type FROM users ORDER BY id DESC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = [];
    $allowed_extensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
    $profile_dir = __DIR__ . '/../images/profile/';

    foreach ($users as $user) {
        $profile_photo_url = null;
        foreach ($allowed_extensions as $ext) {
            $photo_path = $profile_dir . $user['id'] . '.' . $ext;
            if (file_exists($photo_path)) {
                // Return cache-busted profile picture link
                $profile_photo_url = 'images/profile/' . $user['id'] . '.' . $ext . '?t=' . filemtime($photo_path);
                break;
            }
        }

        $data[] = [
            'id' => $user['id'],
            'username' => $user['username'],
            'real_name' => $user['real_name'],
            'department' => $user['department'],
            'role' => $user['user_type'],
            'profile_photo_url' => $profile_photo_url
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $data
    ]);
    exit;

} elseif ($action === 'add_user') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $real_name = isset($_POST['real_name']) ? trim($_POST['real_name']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $department = isset($_POST['department']) ? trim($_POST['department']) : '';
    $user_type = isset($_POST['user_type']) ? trim($_POST['user_type']) : '';

    // Validate inputs are present
    if (empty($username) || empty($real_name) || empty($password) || empty($department) || empty($user_type)) {
        echo json_encode([
            'success' => false,
            'message' => 'All fields are required'
        ]);
        exit;
    }

    // Format & sanity validations
    if (strlen($username) < 3 || !preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        echo json_encode([
            'success' => false,
            'message' => 'Username must be at least 3 alphanumeric characters/underscores'
        ]);
        exit;
    }

    if (strlen($real_name) < 2) {
        echo json_encode([
            'success' => false,
            'message' => 'Real name must be at least 2 characters'
        ]);
        exit;
    }

    if (strlen($password) < 5) {
        echo json_encode([
            'success' => false,
            'message' => 'Password must be at least 5 characters'
        ]);
        exit;
    }

    if (!in_array($user_type, ['admin', 'user'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid user type / role specified'
        ]);
        exit;
    }

    // Verify username does not exist
    $checkStmt = $db->prepare("SELECT id FROM users WHERE username = :username LIMIT 1");
    $checkStmt->execute(['username' => $username]);
    if ($checkStmt->fetch()) {
        echo json_encode([
            'success' => false,
            'message' => 'Username is already taken'
        ]);
        exit;
    }

    // Hash the password and save
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $insertStmt = $db->prepare("INSERT INTO users (username, password, real_name, department, user_type, session_id) VALUES (:username, :password, :real_name, :department, :user_type, '')");
    $insertStmt->execute([
        'username' => $username,
        'password' => $hashed_password,
        'real_name' => $real_name,
        'department' => $department,
        'user_type' => $user_type
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'User added successfully'
    ]);
    exit;

} elseif ($action === 'edit_user') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $department = isset($_POST['department']) ? trim($_POST['department']) : '';
    $user_type = isset($_POST['user_type']) ? trim($_POST['user_type']) : '';

    if (empty($id) || empty($department) || empty($user_type)) {
        echo json_encode([
            'success' => false,
            'message' => 'All fields except password are required'
        ]);
        exit;
    }

    if (!in_array($user_type, ['admin', 'user'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid user type / role specified'
        ]);
        exit;
    }

    // Safety lockout prevention: prevent current user from changing their own role to non-admin
    if ($id === intval($_SESSION['id']) && $user_type !== 'admin') {
        echo json_encode([
            'success' => false,
            'message' => 'You cannot change your own role from admin'
        ]);
        exit;
    }

    if (!empty($password)) {
        if (strlen($password) < 5) {
            echo json_encode([
                'success' => false,
                'message' => 'Password must be at least 5 characters'
            ]);
            exit;
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $updateStmt = $db->prepare("UPDATE users SET password = :password, department = :department, user_type = :user_type WHERE id = :id");
        $updateStmt->execute([
            'password' => $hashed_password,
            'department' => $department,
            'user_type' => $user_type,
            'id' => $id
        ]);
    } else {
        $updateStmt = $db->prepare("UPDATE users SET department = :department, user_type = :user_type WHERE id = :id");
        $updateStmt->execute([
            'department' => $department,
            'user_type' => $user_type,
            'id' => $id
        ]);
    }

    // If editing self, update active session parameters
    if ($id === intval($_SESSION['id'])) {
        $_SESSION['department'] = $department;
    }

    echo json_encode([
        'success' => true,
        'message' => 'User updated successfully'
    ]);
    exit;

} elseif ($action === 'delete_user') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if (empty($id)) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid user ID'
        ]);
        exit;
    }

    // Prevent self deletion
    if ($id === intval($_SESSION['id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'You cannot delete your own account'
        ]);
        exit;
    }

    // Delete user from database
    $deleteStmt = $db->prepare("DELETE FROM users WHERE id = :id");
    $deleteStmt->execute(['id' => $id]);

    // Clean up uploaded avatar profile image if it exists
    $allowed_extensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
    $profile_dir = __DIR__ . '/../images/profile/';
    foreach ($allowed_extensions as $ext) {
        $existing_file = $profile_dir . $id . '.' . $ext;
        if (file_exists($existing_file)) {
            unlink($existing_file);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'User deleted successfully'
    ]);
    exit;

} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid action specified'
    ]);
    exit;
}
?>

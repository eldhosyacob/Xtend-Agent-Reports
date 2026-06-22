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

// Check session authentication
if (!isset($_SESSION['id']) || !isset($_SESSION['username'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized: Please log in first'
    ]);
    exit;
}

$user_id = $_SESSION['id'];
$action = isset($_POST['action']) ? trim($_POST['action']) : '';

// Profile update actions
if ($action === 'update_profile') {
    $real_name = isset($_POST['real_name']) ? trim($_POST['real_name']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (empty($real_name)) {
        echo json_encode([
            'success' => false,
            'message' => 'Real name cannot be empty'
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

    require_once __DIR__ . '/../config/database.php';
    $db = getDatabaseConnection();

    if (!$db) {
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed'
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
        $stmt = $db->prepare("UPDATE users SET real_name = :real_name, password = :password WHERE id = :id");
        $stmt->execute([
            'real_name' => $real_name,
            'password' => $hashed_password,
            'id' => $user_id
        ]);
    } else {
        $stmt = $db->prepare("UPDATE users SET real_name = :real_name WHERE id = :id");
        $stmt->execute([
            'real_name' => $real_name,
            'id' => $user_id
        ]);
    }

    // Update active session values
    $_SESSION['real_name'] = $real_name;
    $_SESSION['full_name'] = $real_name;

    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully',
        'data' => [
            'real_name' => $real_name
        ]
    ]);
    exit;

} elseif ($action === 'upload_photo') {
    if (!isset($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
        $error_code = isset($_FILES['profile_photo']['error']) ? $_FILES['profile_photo']['error'] : 'Unknown';
        echo json_encode([
            'success' => false,
            'message' => 'File upload failed or no file selected. Error code: ' . $error_code
        ]);
        exit;
    }

    $file = $_FILES['profile_photo'];
    $max_size = 5 * 1024 * 1024; // 5MB

    if ($file['size'] > $max_size) {
        echo json_encode([
            'success' => false,
            'message' => 'File size exceeds maximum limit of 5MB'
        ]);
        exit;
    }

    $allowed_extensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
    $file_info = pathinfo($file['name']);
    $extension = isset($file_info['extension']) ? strtolower($file_info['extension']) : '';

    if (!in_array($extension, $allowed_extensions)) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid file extension. Allowed formats: PNG, JPG, JPEG, GIF, WEBP'
        ]);
        exit;
    }

    // Verify MIME type to ensure it is actually an image
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (strpos($mime, 'image/') !== 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Uploaded file is not a valid image'
        ]);
        exit;
    }

    $profile_dir = __DIR__ . '/../images/profile/';
    if (!is_dir($profile_dir)) {
        mkdir($profile_dir, 0755, true);
    }

    // Clean up any existing profile pictures for this user ID to avoid duplicate matching
    foreach ($allowed_extensions as $ext) {
        $existing_file = $profile_dir . $user_id . '.' . $ext;
        if (file_exists($existing_file)) {
            unlink($existing_file);
        }
    }

    $target_file = $profile_dir . $user_id . '.' . $extension;
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        echo json_encode([
            'success' => true,
            'message' => 'Profile photo uploaded successfully',
            'data' => [
                'photo_url' => 'images/profile/' . $user_id . '.' . $extension . '?t=' . time()
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to save uploaded file'
        ]);
    }
    exit;

} elseif ($action === 'delete_photo') {
    $profile_dir = __DIR__ . '/../images/profile/';
    $allowed_extensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
    $deleted = false;

    foreach ($allowed_extensions as $ext) {
        $existing_file = $profile_dir . $user_id . '.' . $ext;
        if (file_exists($existing_file)) {
            if (unlink($existing_file)) {
                $deleted = true;
            }
        }
    }

    if ($deleted) {
        echo json_encode([
            'success' => true,
            'message' => 'Profile photo deleted successfully'
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'No profile photo found to delete'
        ]);
    }
    exit;

} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid action specified'
    ]);
    exit;
}
?>

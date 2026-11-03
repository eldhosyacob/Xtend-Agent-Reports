<?php
require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json');

$module = isset($_POST['module']) ? trim($_POST['module']) : '';

if (!empty($_SESSION['project_only']) && (int)$_SESSION['project_only'] === 1) {
    $_SESSION['active_module'] = 'project_management';
    echo json_encode(['success' => true, 'module' => 'project_management']);
    exit;
}

if (in_array($module, ['report_management', 'project_management'], true)) {
    $_SESSION['active_module'] = $module;
    echo json_encode(['success' => true, 'module' => $module]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid module']);
}

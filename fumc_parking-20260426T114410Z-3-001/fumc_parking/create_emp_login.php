<?php
// create_emp_login.php - API to create employee login account (admin only)
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

// Auth check (guard/admin session or token)
require_once 'db.php';
require_once 'auth.php';
requireAuth();

$user = $GLOBALS['auth_user'];
if (!in_array($user['role'], ['admin','superadmin'])) {
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'Admin access required.']);
    exit;
}

$data       = json_decode(file_get_contents('php://input'), true);
$mode       = $data['mode'] ?? 'link'; // 'link' = link existing, 'new' = create new + login
$username   = strtolower(trim($data['username'] ?? ''));
$password   = $data['password'] ?? '';
$empDbId    = intval($data['employee_id'] ?? 0);

// Mode: new = create employee record + login at once
$fullName   = trim($data['full_name']   ?? '');
$empNo      = trim($data['emp_no']      ?? '');
$department = trim($data['department']  ?? '');
$position   = trim($data['position']    ?? '');

if (empty($username)||empty($password)) {
    echo json_encode(['success'=>false,'message'=>'Username and password are required.']);
    exit;
}
if (strlen($password) < 8) {
    echo json_encode(['success'=>false,'message'=>'Password must be at least 8 characters.']);
    exit;
}
if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
    echo json_encode(['success'=>false,'message'=>'Username: letters, numbers, underscore only.']);
    exit;
}

$db = getDB();

// Check username uniqueness
$chk = $db->prepare("SELECT id FROM employee_logins WHERE username = ?");
$chk->bind_param('s', $username); $chk->execute();
if ($chk->get_result()->num_rows > 0) {
    echo json_encode(['success'=>false,'message'=>"Username \"$username\" is already taken."]);
    $chk->close(); $db->close(); exit;
}
$chk->close();

if ($mode === 'new') {
    // Create employee record first
    if (empty($fullName)||empty($empNo)) {
        echo json_encode(['success'=>false,'message'=>'Full name and Employee ID are required for new account.']);
        $db->close(); exit;
    }
    $ins = $db->prepare("INSERT INTO employees (employee_id, full_name, department, position, is_active) VALUES (?,?,?,?,1)");
    $ins->bind_param('ssss', $empNo, $fullName, $department, $position);
    if (!$ins->execute()) {
        echo json_encode(['success'=>false,'message'=>'Failed to create employee record.']);
        $ins->close(); $db->close(); exit;
    }
    $empDbId = $ins->insert_id;
    $ins->close();
} else {
    // Link mode — verify employee exists
    if (!$empDbId) {
        echo json_encode(['success'=>false,'message'=>'Please select an employee to link.']);
        $db->close(); exit;
    }
    $chk2 = $db->prepare("SELECT id FROM employees WHERE id = ? AND is_active = 1");
    $chk2->bind_param('i', $empDbId); $chk2->execute();
    if ($chk2->get_result()->num_rows === 0) {
        echo json_encode(['success'=>false,'message'=>'Employee not found or inactive.']);
        $chk2->close(); $db->close(); exit;
    }
    $chk2->close();
}

$hash = password_hash($password, PASSWORD_BCRYPT);
$stmt = $db->prepare("INSERT INTO employee_logins (employee_id, username, password) VALUES (?,?,?)");
$stmt->bind_param('iss', $empDbId, $username, $hash);
if ($stmt->execute()) {
    echo json_encode(['success'=>true,'message'=>"Employee login account created! Username: \"$username\"",'employee_id'=>$empDbId,'username'=>$username]);
} else {
    echo json_encode(['success'=>false,'message'=>'Failed to create login account.']);
}
$stmt->close(); $db->close();

<?php
// reset-guard-password.php
// Resets a guard OR employee portal login password by username.
// Called by proxy.php ? endpoint: reset-guard-password

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/db.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// Must be logged in
if (!isset($_SESSION['guard_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

// Parse JSON body
$body        = json_decode(file_get_contents('php://input'), true);
$username    = trim($body['username']    ?? '');
$newPassword = trim($body['new_password'] ?? '');

// Validate input
if (empty($username)) {
    echo json_encode(['success' => false, 'message' => 'Username is required.']);
    exit;
}
if (empty($newPassword)) {
    echo json_encode(['success' => false, 'message' => 'New password is required.']);
    exit;
}
if (strlen($newPassword) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
    exit;
}

$db   = getDB();
$hash = password_hash($newPassword, PASSWORD_DEFAULT);
$updated = false;

// ?? 1. Try guards table first ??
$stmt = $db->prepare("SELECT id FROM guards WHERE username = ? AND is_active = 1");
$stmt->bind_param('s', $username);
$stmt->execute();
$guard = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($guard) {
    $upd = $db->prepare("UPDATE guards SET password = ? WHERE username = ?");
    $upd->bind_param('ss', $hash, $username);
    $upd->execute();
    $updated = $upd->affected_rows > 0;
    $upd->close();

    if ($updated) {
        // Invalidate all active sessions for this guard so they must re-login
        $del = $db->prepare("DELETE FROM guard_sessions WHERE guard_id = ?");
        $del->bind_param('i', $guard['id']);
        $del->execute();
        $del->close();
    }
}

// ?? 2. If not found in guards, try employee_logins table ??
if (!$updated) {
    // Check if employee_logins table exists
    $tableCheck = $db->query("SHOW TABLES LIKE 'employee_logins'");
    if ($tableCheck && $tableCheck->num_rows > 0) {
        $stmt2 = $db->prepare("SELECT id FROM employee_logins WHERE username = ? AND is_active = 1");
        $stmt2->bind_param('s', $username);
        $stmt2->execute();
        $empLogin = $stmt2->get_result()->fetch_assoc();
        $stmt2->close();

        if ($empLogin) {
            $upd2 = $db->prepare("UPDATE employee_logins SET password = ? WHERE username = ?");
            $upd2->bind_param('ss', $hash, $username);
            $upd2->execute();
            $updated = $upd2->affected_rows > 0;
            $upd2->close();
        }
    }
}

$db->close();

if ($updated) {
    echo json_encode([
        'success' => true,
        'message' => "Password for \"@{$username}\" has been reset successfully."
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => "Username \"@{$username}\" not found or account is inactive."
    ]);
}
<?php
// guard_management.php
// API endpoints for creating and managing guard/employee login accounts

function createGuard() {
    $user = $GLOBALS['auth_user'];

    // Only admin/superadmin can create guards
    if (!in_array($user['role'], ['admin', 'superadmin'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Permission denied. Admin access required.']);
        return;
    }

    $data     = json_decode(file_get_contents('php://input'), true);
    $fullName = trim($data['full_name'] ?? '');
    $username = strtolower(trim($data['username'] ?? ''));
    $password = $data['password'] ?? '';
    $role     = $data['role'] ?? 'guard';

    // Validation
    if (empty($fullName) || empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Full name, username, and password are required.']);
        return;
    }

    if (strlen($password) < 8) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
        return;
    }

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Username can only contain letters, numbers, and underscore.']);
        return;
    }

    if (!in_array($role, ['guard', 'admin'])) {
        $role = 'guard';
    }

    $db = getDB();

    // Check if username already exists
    $chk = $db->prepare("SELECT id FROM guards WHERE username = ?");
    $chk->bind_param('s', $username);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => "Username \"$username\" is already taken. Please choose another."]); 
        $chk->close();
        $db->close();
        return;
    }
    $chk->close();

    // Hash password and insert
    $hashed = password_hash($password, PASSWORD_BCRYPT);
    $stmt   = $db->prepare("INSERT INTO guards (username, password, full_name, role, is_active) VALUES (?, ?, ?, ?, 1)");
    $stmt->bind_param('ssss', $username, $hashed, $fullName, $role);

    if ($stmt->execute()) {
        $newId = $stmt->insert_id;
        $stmt->close();
        $db->close();
        echo json_encode([
            'success'   => true,
            'message'   => 'Login account created successfully.',
            'guard_id'  => $newId,
            'username'  => $username,
            'full_name' => $fullName,
            'role'      => $role,
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create account.']);
        $stmt->close();
        $db->close();
    }
}

function listGuards() {
    $user = $GLOBALS['auth_user'];

    if (!in_array($user['role'], ['admin', 'superadmin'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Permission denied.']);
        return;
    }

    $db   = getDB();
    $rows = $db->query("SELECT id, username, full_name, role, is_active, last_login, created_at
                         FROM guards
                         ORDER BY role DESC, full_name ASC")
               ->fetch_all(MYSQLI_ASSOC);
    $db->close();

    echo json_encode(['success' => true, 'data' => $rows]);
}

function resetGuardPassword() {
    $user = $GLOBALS['auth_user'];

    if (!in_array($user['role'], ['admin', 'superadmin'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Permission denied. Admin access required.']);
        return;
    }

    $data        = json_decode(file_get_contents('php://input'), true);
    $username    = trim($data['username']     ?? '');
    $newPassword = $data['new_password'] ?? '';

    if (empty($username) || empty($newPassword)) {
        echo json_encode(['success' => false, 'message' => 'Username and new password are required.']);
        return;
    }
    if (strlen($newPassword) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
        return;
    }

    $db    = getDB();
    $hash  = password_hash($newPassword, PASSWORD_BCRYPT);
    $found = false;

    // 1. Check guards table
    $chk = $db->prepare("SELECT id FROM guards WHERE username = ? AND is_active = 1");
    $chk->bind_param('s', $username);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        $chk->close();
        $stmt = $db->prepare("UPDATE guards SET password = ? WHERE username = ?");
        $stmt->bind_param('ss', $hash, $username);
        $stmt->execute(); $stmt->close();
        // Clear sessions
        $del = $db->prepare("DELETE gs FROM guard_sessions gs INNER JOIN guards g ON g.id = gs.guard_id WHERE g.username = ?");
        $del->bind_param('s', $username);
        $del->execute(); $del->close();
        $found = true;
    } else {
        $chk->close();
    }

    // 2. Check employee_logins table
    if (!$found) {
        $chk2 = $db->prepare("SELECT id FROM employee_logins WHERE username = ? AND is_active = 1");
        $chk2->bind_param('s', $username);
        $chk2->execute();
        if ($chk2->get_result()->num_rows > 0) {
            $chk2->close();
            $stmt2 = $db->prepare("UPDATE employee_logins SET password = ? WHERE username = ?");
            $stmt2->bind_param('ss', $hash, $username);
            $stmt2->execute(); $stmt2->close();
            $found = true;
        } else {
            $chk2->close();
        }
    }

    $db->close();

    if ($found) {
        echo json_encode(['success' => true, 'message' => "Password for \"$username\" reset successfully."]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => "Username \"$username\" not found or inactive."]);
    }
}

function deactivateGuard() {
    $user = $GLOBALS['auth_user'];

    if ($user['role'] !== 'superadmin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Only superadmin can deactivate accounts.']);
        return;
    }

    $data     = json_decode(file_get_contents('php://input'), true);
    $guardId  = intval($data['guard_id'] ?? 0);

    if (!$guardId || $guardId === $user['id']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Cannot deactivate your own account.']);
        return;
    }

    $db   = getDB();
    $stmt = $db->prepare("UPDATE guards SET is_active = 0 WHERE id = ?");
    $stmt->bind_param('i', $guardId);
    $stmt->execute();
    $stmt->close();
    $db->close();

    echo json_encode(['success' => true, 'message' => 'Account deactivated successfully.']);
}
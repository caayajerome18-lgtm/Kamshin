<?php
// auth.php - Authentication Handler

function requireAuth() {
    $headers = getallheaders();
    $token   = $headers['Authorization'] ?? '';
    $token   = str_replace('Bearer ', '', $token);

    if (empty($token)) {
        http_response_code(401);
        die(json_encode(['success' => false, 'message' => 'Unauthorized. Token required.']));
    }

    $db   = getDB();
    $stmt = $db->prepare("SELECT g.id, g.username, g.full_name, g.role FROM guards g
                          INNER JOIN guard_sessions s ON s.guard_id = g.id
                          WHERE s.token = ? AND s.expires_at > NOW() AND g.is_active = 1");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(401);
        die(json_encode(['success' => false, 'message' => 'Invalid or expired session.']));
    }

    $GLOBALS['auth_user'] = $result->fetch_assoc();
    $stmt->close();
    $db->close();
}

function handleLogin() {
    $data     = json_decode(file_get_contents('php://input'), true);
    $username = trim($data['username'] ?? '');
    $password = $data['password'] ?? '';

    if (empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Username and password are required.']);
        return;
    }

    $db   = getDB();
    $stmt = $db->prepare("SELECT id, username, password, full_name, role FROM guards
                          WHERE username = ? AND is_active = 1");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $guard  = $result->fetch_assoc();
    $stmt->close();

    if (!$guard || !password_verify($password, $guard['password'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid credentials.']);
        $db->close();
        return;
    }

    // Generate session token
    $token     = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+8 hours'));

    // Ensure sessions table exists
    $db->query("CREATE TABLE IF NOT EXISTS guard_sessions (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        guard_id   INT NOT NULL,
        token      VARCHAR(64) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (guard_id) REFERENCES guards(id) ON DELETE CASCADE
    )");

    $stmt = $db->prepare("INSERT INTO guard_sessions (guard_id, token, expires_at) VALUES (?, ?, ?)");
    $stmt->bind_param('iss', $guard['id'], $token, $expiresAt);
    $stmt->execute();
    $stmt->close();

    // Update last login
    $db->query("UPDATE guards SET last_login = NOW() WHERE id = {$guard['id']}");
    $db->close();

    echo json_encode([
        'success'    => true,
        'message'    => 'Login successful.',
        'token'      => $token,
        'expires_at' => $expiresAt,
        'user'       => [
            'id'        => $guard['id'],
            'username'  => $guard['username'],
            'full_name' => $guard['full_name'],
            'role'      => $guard['role'],
        ]
    ]);
}

function handleLogout() {
    $headers = getallheaders();
    $token   = str_replace('Bearer ', '', $headers['Authorization'] ?? '');

    if (!empty($token)) {
        $db   = getDB();
        $stmt = $db->prepare("DELETE FROM guard_sessions WHERE token = ?");
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $stmt->close();
        $db->close();
    }

    echo json_encode(['success' => true, 'message' => 'Logged out successfully.']);
}

function changePassword() {
    $user        = $GLOBALS['auth_user'];
    $data        = json_decode(file_get_contents('php://input'), true);
    $oldPassword = $data['old_password'] ?? '';
    $newPassword = $data['new_password'] ?? '';

    if (strlen($newPassword) < 8) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters.']);
        return;
    }

    $db   = getDB();
    $stmt = $db->prepare("SELECT password FROM guards WHERE id = ?");
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $row  = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!password_verify($oldPassword, $row['password'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
        $db->close();
        return;
    }

    $hashed = password_hash($newPassword, PASSWORD_BCRYPT);
    $stmt   = $db->prepare("UPDATE guards SET password = ? WHERE id = ?");
    $stmt->bind_param('si', $hashed, $user['id']);
    $stmt->execute();
    $stmt->close();
    $db->close();

    echo json_encode(['success' => true, 'message' => 'Password changed successfully.']);
}

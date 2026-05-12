<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'db.php';

$data     = json_decode(file_get_contents('php://input'), true);
$username = trim($data['username'] ?? '');
$password = $data['password'] ?? '';

if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Username and password required.']);
    exit;
}

$db   = getDB();
$stmt = $db->prepare("SELECT id, username, password, full_name, role FROM guards WHERE username = ? AND is_active = 1");
$stmt->bind_param('s', $username);
$stmt->execute();
$guard = $stmt->get_result()->fetch_assoc();

if (!$guard || !password_verify($password, $guard['password'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid credentials.']);
    exit;
}

$token     = bin2hex(random_bytes(32));
$expiresAt = date('Y-m-d H:i:s', strtotime('+8 hours'));

$db->query("CREATE TABLE IF NOT EXISTS guard_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    guard_id INT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$stmt = $db->prepare("INSERT INTO guard_sessions (guard_id, token, expires_at) VALUES (?, ?, ?)");
$stmt->bind_param('iss', $guard['id'], $token, $expiresAt);
$stmt->execute();

echo json_encode([
    'success'  => true,
    'token'    => $token,
    'user'     => [
        'id'        => $guard['id'],
        'username'  => $guard['username'],
        'full_name' => $guard['full_name'],
        'role'      => $guard['role'],
    ]
]);
<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['emp_login_id'])) { echo json_encode(['success'=>false,'message'=>'Not logged in.']); exit; }
require_once 'db.php';
$data = json_decode(file_get_contents('php://input'), true);
$old  = $data['old_password'] ?? '';
$new  = $data['new_password'] ?? '';
if (empty($old)||empty($new)||strlen($new)<8) { echo json_encode(['success'=>false,'message'=>'Invalid input.']); exit; }
$db   = getDB();
$stmt = $db->prepare("SELECT password FROM employee_logins WHERE id = ?");
$stmt->bind_param('i', $_SESSION['emp_login_id']);
$stmt->execute();
$row  = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$row || !password_verify($old, $row['password'])) {
    echo json_encode(['success'=>false,'message'=>'Current password is incorrect.']);
    $db->close(); exit;
}
$hash = password_hash($new, PASSWORD_BCRYPT);
$upd  = $db->prepare("UPDATE employee_logins SET password = ? WHERE id = ?");
$upd->bind_param('si', $hash, $_SESSION['emp_login_id']);
$upd->execute(); $upd->close(); $db->close();
echo json_encode(['success'=>true,'message'=>'Password changed successfully.']);

<?php
session_start();
header('Content-Type: application/json');
require_once 'db.php';
require_once 'auth.php';
requireAuth();
$db   = getDB();
// Check if table exists first
$chk  = $db->query("SHOW TABLES LIKE 'employee_logins'");
if ($chk->num_rows === 0) {
    echo json_encode(['success'=>true,'data'=>[],'message'=>'Table not set up yet. Visit emp_setup.php first.']);
    $db->close(); exit;
}
$rows = $db->query("SELECT el.id, el.username, el.is_active, el.created_at, e.full_name, e.employee_id, e.department
                    FROM employee_logins el
                    INNER JOIN employees e ON e.id = el.employee_id
                    ORDER BY el.created_at DESC")->fetch_all(MYSQLI_ASSOC);
$db->close();
echo json_encode(['success'=>true,'data'=>$rows]);

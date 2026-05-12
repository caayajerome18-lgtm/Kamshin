<?php
session_start();
header('Content-Type: application/json');
require_once 'db.php';
require_once 'auth.php';
requireAuth();
$db   = getDB();
$rows = $db->query("SELECT e.id, e.employee_id, e.full_name, e.department, e.position,
                           (SELECT COUNT(*) FROM employee_logins el WHERE el.employee_id=e.id AND el.is_active=1) AS has_login
                    FROM employees e WHERE e.is_active=1 ORDER BY e.full_name ASC")->fetch_all(MYSQLI_ASSOC);
$db->close();
echo json_encode(['success'=>true,'data'=>$rows]);

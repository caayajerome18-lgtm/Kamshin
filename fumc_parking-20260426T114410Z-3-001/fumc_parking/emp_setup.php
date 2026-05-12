<?php
// emp_setup.php - Run once to create employee_logins table
require_once 'db.php';
$db = getDB();
$db->query("CREATE TABLE IF NOT EXISTS employee_logins (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    employee_id   INT NOT NULL,
    username      VARCHAR(50) NOT NULL UNIQUE,
    password      VARCHAR(255) NOT NULL,
    is_active     TINYINT(1) DEFAULT 1,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
)");
echo json_encode(['success' => true, 'message' => 'employee_logins table created successfully.']);
$db->close();

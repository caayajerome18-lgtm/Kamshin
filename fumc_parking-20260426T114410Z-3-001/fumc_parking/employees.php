<?php
// employees.php — Employee CRUD

function getEmployees() {
    $db     = getDB();
    $search = $_GET['search'] ?? '';
    $active = $_GET['is_active'] ?? '1';

    $where  = ['1=1'];
    $params = [];
    $types  = '';

    if (!empty($search)) {
        $where[]  = "(full_name LIKE ? OR employee_id LIKE ? OR department LIKE ?)";
        $s        = "%$search%";
        $params   = array_merge($params, [$s, $s, $s]);
        $types   .= 'sss';
    }
    if ($active !== 'all') {
        $where[]  = "is_active = ?";
        $params[] = intval($active);
        $types   .= 'i';
    }

    $whereSql = implode(' AND ', $where);
    $sql      = "SELECT id, employee_id, full_name, department, position,
                         photo_path, id_card_path, is_active, created_at
                  FROM employees WHERE $whereSql ORDER BY full_name";

    if (!empty($params)) {
        $stmt = $db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $rows = $db->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    $db->close();
    echo json_encode(['success' => true, 'data' => $rows]);
}

function createEmployee() {
    $data       = json_decode(file_get_contents('php://input'), true);
    $employeeId = trim($data['employee_id'] ?? '');
    $fullName   = trim($data['full_name']   ?? '');
    $department = trim($data['department']  ?? '');
    $position   = trim($data['position']    ?? '');

    if (empty($employeeId) || empty($fullName)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Employee ID and full name are required.']);
        return;
    }

    $db   = getDB();
    $stmt = $db->prepare("INSERT INTO employees (employee_id, full_name, department, position)
                          VALUES (?, ?, ?, ?)");
    $stmt->bind_param('ssss', $employeeId, $fullName, $department, $position);

    if (!$stmt->execute()) {
        $error = $stmt->errno === 1062
            ? 'Employee ID already exists.'
            : 'Failed to create employee.';
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => $error]);
    } else {
        echo json_encode([
            'success'     => true,
            'message'     => 'Employee created successfully.',
            'employee_db_id' => $stmt->insert_id,
        ]);
    }

    $stmt->close();
    $db->close();
}

function updateEmployee($id) {
    $id   = intval($id);
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Employee ID required.']);
        return;
    }

    $fields = [];
    $params = [];
    $types  = '';

    $allowed = ['full_name', 'department', 'position', 'is_active'];
    foreach ($allowed as $field) {
        if (isset($data[$field])) {
            $fields[] = "$field = ?";
            $params[] = $data[$field];
            $types   .= 's';
        }
    }

    if (empty($fields)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No fields to update.']);
        return;
    }

    $params[] = $id;
    $types   .= 'i';

    $db   = getDB();
    $stmt = $db->prepare("UPDATE employees SET " . implode(', ', $fields) . " WHERE id = ?");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Employee updated.']);
    $stmt->close();
    $db->close();
}

function deleteEmployee($id) {
    $id = intval($id);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Employee ID required.']);
        return;
    }

    $db   = getDB();
    $stmt = $db->prepare("UPDATE employees SET is_active = 0 WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    echo json_encode(['success' => true, 'message' => 'Employee deactivated.']);
    $stmt->close();
    $db->close();
}

function getEmployeeParkingInfo() {
    $empId = intval($_GET['employee_id'] ?? 0);
    if (!$empId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Employee ID required.']);
        return;
    }

    $db   = getDB();
    $stmt = $db->prepare("SELECT vl.*, ps.slot_code, ps.zone
                           FROM vehicle_logs vl
                           LEFT JOIN parking_slots ps ON ps.id = vl.slot_id
                           WHERE vl.employee_id = ?
                           ORDER BY vl.time_in DESC LIMIT 10");
    $stmt->bind_param('i', $empId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $db->close();

    echo json_encode(['success' => true, 'data' => $rows]);
}

<?php
// vehicle_intake.php — Vehicle Intake Dashboard Backend

function recordVehicleEntry() {
    $data         = json_decode(file_get_contents('php://input'), true);
    $licensePlate = strtoupper(trim($data['license_plate'] ?? ''));
    $vehicleType  = trim($data['vehicle_type'] ?? 'Car');
    $entryType    = $data['entry_type'] ?? 'visitor';
    $employeeId   = $data['employee_id'] ?? null;
    $guardId      = $GLOBALS['auth_user']['id'];

    if (empty($licensePlate)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'License plate is required.']);
        return;
    }

    if (!preg_match('/^[A-Z0-9\s\-]{3,15}$/', $licensePlate)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid license plate format.']);
        return;
    }

    $db = getDB();

    $chk = $db->prepare("SELECT id FROM vehicle_logs WHERE license_plate = ? AND status = 'parked'");
    $chk->bind_param('s', $licensePlate);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Vehicle with this plate is already parked.']);
        $chk->close(); $db->close(); return;
    }
    $chk->close();

    $empRecord = null;
    if ($entryType === 'employee') {
        if (empty($employeeId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Employee ID required for employee entry.']);
            $db->close(); return;
        }

        // Try by numeric DB id first, then by employee_id string
        $empStmt = $db->prepare("SELECT id, full_name, department, employee_id FROM employees WHERE (id = ? OR employee_id = ?) AND is_active = 1 LIMIT 1");
        $empStmt->bind_param('ss', $employeeId, $employeeId);
        $empStmt->execute();
        $empRecord = $empStmt->get_result()->fetch_assoc();
        $empStmt->close();

        if (!$empRecord) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Employee not found or inactive.']);
            $db->close(); return;
        }
    }

    $ticketNumber = 'TKT-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
    $timeIn       = date('Y-m-d H:i:s');
    $empDbId      = $empRecord['id'] ?? null;

    $stmt = $db->prepare("INSERT INTO vehicle_logs (license_plate, vehicle_type, entry_type, employee_id, time_in, ticket_number, status, guard_id) VALUES (?, ?, ?, ?, ?, ?, 'parked', ?)");
    $stmt->bind_param('ssssssi', $licensePlate, $vehicleType, $entryType, $empDbId, $timeIn, $ticketNumber, $guardId);

    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to record vehicle entry.']);
        $stmt->close(); $db->close(); return;
    }

    $logId = $stmt->insert_id;
    $stmt->close();

    $totals  = getEntryTotals($db);
    $lastEmp = getLastEmployeeEntry($db);
    $db->close();

    echo json_encode([
        'success'       => true,
        'message'       => 'Vehicle entry recorded successfully.',
        'ticket_number' => $ticketNumber,
        'log_id'        => $logId,
        'time_in'       => $timeIn,
        'license_plate' => $licensePlate,
        'vehicle_type'  => $vehicleType,
        'entry_type'    => $entryType,
        'employee'      => $empRecord,
        'dashboard'     => [
            'last_employee_entry'  => $lastEmp,
            'total_entrys_today'   => $totals,
            'pending_excel_report' => 'READY',
        ]
    ]);
}

function cancelEntry() {
    $data  = json_decode(file_get_contents('php://input'), true);
    $logId = intval($data['log_id'] ?? 0);
    if (!$logId) { http_response_code(400); echo json_encode(['success' => false, 'message' => 'Log ID required.']); return; }
    $db   = getDB();
    $stmt = $db->prepare("UPDATE vehicle_logs SET status = 'cancelled' WHERE id = ? AND status = 'parked'");
    $stmt->bind_param('i', $logId);
    $stmt->execute();
    if ($stmt->affected_rows === 0) { http_response_code(404); echo json_encode(['success' => false, 'message' => 'Entry not found.']); }
    else { echo json_encode(['success' => true, 'message' => 'Entry cancelled.']); }
    $stmt->close(); $db->close();
}

function getEntryTotals($db) {
    $today = date('Y-m-d');
    $res   = $db->query("SELECT SUM(entry_type='employee') AS employee_count, SUM(entry_type='visitor') AS visitor_count, COUNT(*) AS total FROM vehicle_logs WHERE DATE(time_in)='$today' AND status IN ('parked','exited')");
    $row   = $res->fetch_assoc();
    return ['employee' => (int)($row['employee_count']??0), 'visitor' => (int)($row['visitor_count']??0), 'total' => (int)($row['total']??0)];
}

function getLastEmployeeEntry($db) {
    $res = $db->query("SELECT vl.ticket_number, vl.time_in, e.full_name FROM vehicle_logs vl LEFT JOIN employees e ON e.id=vl.employee_id WHERE vl.entry_type='employee' AND vl.status IN ('parked','exited') ORDER BY vl.time_in DESC LIMIT 1");
    if ($row = $res->fetch_assoc()) return ['name' => $row['full_name'], 'time_in' => date('h:i A', strtotime($row['time_in']))];
    return null;
}

function getVehicleEntries() {
    $db     = getDB();
    $date   = $_GET['date']   ?? date('Y-m-d');
    $status = $_GET['status'] ?? 'parked';
    $type   = $_GET['type']   ?? '';
    $plate  = $_GET['plate']  ?? '';

    $where  = []; $params = []; $types = '';

    if ($status === 'parked') {
        // No date filter — show ALL currently parked
    } elseif ($status === 'exited') {
        $where[] = "DATE(vl.time_out) = ?"; $params[] = $date; $types .= 's';
    } else {
        $where[] = "DATE(vl.time_in) = ?"; $params[] = $date; $types .= 's';
    }

    if (!empty($status) && $status !== 'all') { $where[] = "vl.status = ?"; $params[] = $status; $types .= 's'; }
    if (!empty($type))  { $where[] = "vl.entry_type = ?"; $params[] = $type; $types .= 's'; }
    if (!empty($plate)) { $where[] = "vl.license_plate LIKE ?"; $params[] = "%$plate%"; $types .= 's'; }

    $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    $sql = "SELECT vl.*, e.full_name AS employee_name, e.employee_id AS emp_no, e.department, ps.slot_code, ps.zone FROM vehicle_logs vl LEFT JOIN employees e ON e.id=vl.employee_id LEFT JOIN parking_slots ps ON ps.id=vl.slot_id $whereSql ORDER BY vl.time_in DESC";

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
    echo json_encode(['success' => true, 'data' => $rows, 'count' => count($rows)]);
}
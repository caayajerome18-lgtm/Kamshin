<?php
// vehicle_exit.php — Vehicle Exit Dashboard Backend

function lookupVehicle() {
    $plate = strtoupper(trim($_GET['license_plate'] ?? ''));

    if (empty($plate)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'License plate is required.']);
        return;
    }

    $db   = getDB();
    $stmt = $db->prepare("SELECT vl.*, e.full_name AS employee_name, e.employee_id AS emp_no,
                                   e.department, e.photo_path, e.id_card_path,
                                   ps.slot_code, ps.zone
                           FROM vehicle_logs vl
                           LEFT JOIN employees    e  ON e.id  = vl.employee_id
                           LEFT JOIN parking_slots ps ON ps.id = vl.slot_id
                           WHERE vl.license_plate = ? AND vl.status = 'parked'
                           ORDER BY vl.time_in DESC
                           LIMIT 1");
    $stmt->bind_param('s', $plate);
    $stmt->execute();
    $log = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $db->close();

    if (!$log) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'No active parking record found for this plate.']);
        return;
    }

    // Calculate real-time duration
    $timeIn      = new DateTime($log['time_in']);
    $now         = new DateTime();
    $diff        = $timeIn->diff($now);
    $totalMins   = ($diff->h * 60) + $diff->i + ($diff->days * 1440);

    echo json_encode([
        'success'    => true,
        'data'       => [
            'log_id'         => $log['id'],
            'license_plate'  => $log['license_plate'],
            'vehicle_type'   => $log['vehicle_type'],
            'entry_type'     => $log['entry_type'],
            'ticket_number'  => $log['ticket_number'],
            'time_in'        => $log['time_in'],
            'current_time'   => date('Y-m-d H:i:s'),
            'duration'       => [
                'hours'   => $diff->h + ($diff->days * 24),
                'minutes' => $diff->i,
                'total_minutes' => $totalMins,
            ],
            'slot'           => $log['slot_code'] ? ['code' => $log['slot_code'], 'zone' => $log['zone']] : null,
            'employee'       => $log['employee_id'] ? [
                'id'         => $log['employee_id'],
                'emp_no'     => $log['emp_no'],
                'name'       => $log['employee_name'],
                'department' => $log['department'],
                'photo'      => $log['photo_path'],
                'id_card'    => $log['id_card_path'],
            ] : null,
        ]
    ]);
}

function confirmVehicleExit() {
    $data      = json_decode(file_get_contents('php://input'), true);
    $logId     = intval($data['log_id'] ?? 0);
    $guardId   = $GLOBALS['auth_user']['id'];

    if (!$logId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Log ID is required.']);
        return;
    }

    $db = getDB();

    // Fetch the parking record
    $stmt = $db->prepare("SELECT vl.*, ps.id AS slot_table_id
                           FROM vehicle_logs vl
                           LEFT JOIN parking_slots ps ON ps.id = vl.slot_id
                           WHERE vl.id = ? AND vl.status = 'parked'");
    $stmt->bind_param('i', $logId);
    $stmt->execute();
    $log = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$log) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Parking record not found or already exited.']);
        $db->close();
        return;
    }

    // Calculate duration
    $timeIn   = new DateTime($log['time_in']);
    $timeOut  = new DateTime();
    $diff     = $timeIn->diff($timeOut);
    $duration = ($diff->h * 60) + $diff->i + ($diff->days * 1440);
    $timeOutStr = $timeOut->format('Y-m-d H:i:s');

    // Update vehicle log
    $upd = $db->prepare("UPDATE vehicle_logs
                          SET status = 'exited', time_out = ?, duration_minutes = ?
                          WHERE id = ?");
    $upd->bind_param('sii', $timeOutStr, $duration, $logId);
    $upd->execute();
    $upd->close();

    // Free up the parking slot if assigned
    if (!empty($log['slot_table_id'])) {
        $db->query("UPDATE parking_slots SET is_occupied = 0, vehicle_type = NULL
                    WHERE id = {$log['slot_table_id']}");
    }

    $db->close();

    $hours   = intdiv($duration, 60);
    $minutes = $duration % 60;

    echo json_encode([
        'success'          => true,
        'message'          => 'Vehicle exit confirmed. Gate opening.',
        'time_out'         => $timeOutStr,
        'time_in'          => $log['time_in'],
        'duration_hours'   => $hours,
        'duration_minutes' => $minutes,
        'ticket_number'    => $log['ticket_number'],
        'license_plate'    => $log['license_plate'],
    ]);
}

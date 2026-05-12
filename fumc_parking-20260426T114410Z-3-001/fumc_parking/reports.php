<?php
// reports.php — Daily Excel Report & Report Log

function getDailyReport() {
    $db   = getDB();
    $date = $_GET['date'] ?? date('Y-m-d');

    $stmt = $db->prepare("SELECT vl.*,
                                  e.full_name AS employee_name, e.employee_id AS emp_no,
                                  e.department,
                                  ps.slot_code, ps.zone,
                                  g.full_name AS guard_name
                           FROM vehicle_logs vl
                           LEFT JOIN employees    e  ON e.id  = vl.employee_id
                           LEFT JOIN parking_slots ps ON ps.id = vl.slot_id
                           LEFT JOIN guards        g  ON g.id  = vl.guard_id
                           WHERE DATE(vl.time_in) = ?
                           ORDER BY vl.time_in ASC");
    $stmt->bind_param('s', $date);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Summary
    $summary = [
        'date'             => $date,
        'total_entries'    => count($rows),
        'employee_entries' => count(array_filter($rows, fn($r) => $r['entry_type'] === 'employee')),
        'visitor_entries'  => count(array_filter($rows, fn($r) => $r['entry_type'] === 'visitor')),
        'currently_parked' => count(array_filter($rows, fn($r) => $r['status'] === 'parked')),
        'exited'           => count(array_filter($rows, fn($r) => $r['status'] === 'exited')),
    ];

    $db->close();

    echo json_encode([
        'success' => true,
        'summary' => $summary,
        'data'    => $rows,
    ]);
}

function exportExcelReport() {
    $data    = json_decode(file_get_contents('php://input'), true);
    $date    = $data['date'] ?? date('Y-m-d');
    $guardId = $GLOBALS['auth_user']['id'];

    $db = getDB();

    // Fetch records for the day
    $stmt = $db->prepare("SELECT vl.license_plate, vl.vehicle_type, vl.entry_type,
                                  vl.time_in, vl.time_out, vl.duration_minutes,
                                  vl.ticket_number, vl.status,
                                  e.full_name AS employee_name, e.employee_id AS emp_no,
                                  e.department,
                                  ps.slot_code, ps.zone,
                                  g.full_name AS guard_name
                           FROM vehicle_logs vl
                           LEFT JOIN employees    e  ON e.id  = vl.employee_id
                           LEFT JOIN parking_slots ps ON ps.id = vl.slot_id
                           LEFT JOIN guards        g  ON g.id  = vl.guard_id
                           WHERE DATE(vl.time_in) = ?
                           ORDER BY vl.time_in ASC");
    $stmt->bind_param('s', $date);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($rows)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'No records found for the selected date.']);
        $db->close();
        return;
    }

    // Generate CSV as a lightweight export (PhpSpreadsheet can be added for real XLSX)
    $filename  = "FUMC_Parking_Report_$date.csv";
    $directory = __DIR__ . '/reports/';
    if (!is_dir($directory)) mkdir($directory, 0755, true);

    $filepath = $directory . $filename;
    $fh       = fopen($filepath, 'w');

    // CSV Header
    fputcsv($fh, [
        'Ticket No.', 'License Plate', 'Vehicle Type', 'Entry Type',
        'Employee Name', 'Employee ID', 'Department',
        'Time In', 'Time Out', 'Duration (mins)',
        'Zone', 'Slot', 'Status', 'Guard',
    ]);

    foreach ($rows as $row) {
        fputcsv($fh, [
            $row['ticket_number'],
            $row['license_plate'],
            $row['vehicle_type'],
            ucfirst($row['entry_type']),
            $row['employee_name'] ?? 'Visitor',
            $row['emp_no']        ?? '-',
            $row['department']    ?? '-',
            $row['time_in'],
            $row['time_out']       ?? '-',
            $row['duration_minutes'] ?? '-',
            $row['zone']           ?? '-',
            $row['slot_code']      ?? '-',
            ucfirst($row['status']),
            $row['guard_name']     ?? '-',
        ]);
    }
    fclose($fh);

    // Log the report
    $relPath = 'reports/' . $filename;
    $logStmt = $db->prepare("INSERT INTO report_logs (report_date, file_path, generated_by, status)
                              VALUES (?, ?, ?, 'generated')
                              ON DUPLICATE KEY UPDATE file_path = ?, generated_by = ?,
                              generated_at = NOW(), status = 'generated'");
    $logStmt->bind_param('ssisi', $date, $relPath, $guardId, $relPath, $guardId);
    $logStmt->execute();
    $logStmt->close();
    $db->close();

    echo json_encode([
        'success'    => true,
        'message'    => 'Report generated successfully.',
        'filename'   => $filename,
        'file_path'  => $relPath,
        'record_count' => count($rows),
        'download_url' => $filename,
    ]);
}

<?php
// dashboard.php — Dashboard status & parking slot map

function getDashboardStatus() {
    $db   = getDB();
    $today = date('Y-m-d');

    // Today's entry totals
    $totalRes = $db->query("SELECT
        SUM(entry_type = 'employee') AS emp_entries,
        SUM(entry_type = 'visitor')  AS vis_entries,
        COUNT(*) AS total_entries,
        SUM(status = 'parked') AS currently_parked
        FROM vehicle_logs
        WHERE DATE(time_in) = '$today'");
    $totals = $totalRes->fetch_assoc();

    // Last employee entry
    $lastEmpRes = $db->query("SELECT vl.time_in, e.full_name
        FROM vehicle_logs vl
        LEFT JOIN employees e ON e.id = vl.employee_id
        WHERE vl.entry_type = 'employee'
        ORDER BY vl.time_in DESC LIMIT 1");
    $lastEmp = $lastEmpRes->fetch_assoc();

    // Parking slot summary by zone
    $slotsRes = $db->query("SELECT zone,
        COUNT(*) AS total,
        SUM(is_occupied = 0) AS available,
        SUM(is_occupied = 1) AS occupied
        FROM parking_slots GROUP BY zone ORDER BY zone");
    $slotsByZone = [];
    while ($row = $slotsRes->fetch_assoc()) {
        $slotsByZone[$row['zone']] = $row;
    }

    // Overall slot totals
    $overallRes = $db->query("SELECT COUNT(*) AS total,
        SUM(is_occupied = 0) AS available,
        SUM(is_occupied = 1) AS occupied
        FROM parking_slots");
    $overall = $overallRes->fetch_assoc();

    // Pending excel report check
    $reportRes = $db->query("SELECT id FROM report_logs
        WHERE report_date = '$today' AND status = 'generated'
        LIMIT 1");
    $pendingReport = $reportRes->num_rows === 0 ? 'READY' : 'GENERATED';

    $db->close();

    echo json_encode([
        'success'      => true,
        'timestamp'    => date('Y-m-d H:i:s'),
        'dashboard'    => [
            'last_employee_entry' => $lastEmp ? [
                'name'    => $lastEmp['full_name'],
                'time_in' => date('h:i A', strtotime($lastEmp['time_in'])),
            ] : null,
            'total_entries_today' => [
                'employee' => (int)$totals['emp_entries'],
                'visitor'  => (int)$totals['vis_entries'],
                'total'    => (int)$totals['total_entries'],
            ],
            'currently_parked'     => (int)$totals['currently_parked'],
            'pending_excel_report' => $pendingReport,
        ],
        'parking_slots' => [
            'overall'   => $overall,
            'by_zone'   => $slotsByZone,
        ]
    ]);
}

function getParkingSlots() {
    $db   = getDB();
    $zone = $_GET['zone'] ?? '';

    $sql  = "SELECT ps.*, vl.license_plate, vl.vehicle_type, vl.time_in
              FROM parking_slots ps
              LEFT JOIN vehicle_logs vl ON vl.slot_id = ps.id AND vl.status = 'parked'";
    if (!empty($zone)) {
        $stmt = $db->prepare($sql . " WHERE ps.zone = ? ORDER BY ps.slot_code");
        $stmt->bind_param('s', $zone);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $rows = $db->query($sql . " ORDER BY ps.zone, ps.slot_code")->fetch_all(MYSQLI_ASSOC);
    }

    $db->close();
    echo json_encode(['success' => true, 'data' => $rows]);
}

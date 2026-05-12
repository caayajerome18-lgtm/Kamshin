<?php
// generate_gate_pass.php - Pure PHP CSV Gate Pass Log (Session-based auth)
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }
if (!isset($_SESSION['guard_id'])) {
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'Not logged in.']);
    exit;
}
require_once 'db.php';
$date      = $_GET['date'] ?? date('Y-m-d');
$guardName = strtoupper($_SESSION['full_name'] ?? 'GUARD');

$db   = getDB();
$stmt = $db->prepare("
    SELECT vl.*, e.full_name AS emp_name, e.employee_id AS emp_no,
           e.department, g.full_name AS guard_name
    FROM vehicle_logs vl
    LEFT JOIN employees e ON e.id  = vl.employee_id
    LEFT JOIN guards    g ON g.id  = vl.guard_id
    WHERE DATE(vl.time_in) = ?
      AND vl.status = 'exited'
    ORDER BY vl.time_out ASC
");
$stmt->bind_param('s', $date);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$db->close();

if (empty($rows)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'No exited vehicles found for ' . $date . '. Make sure vehicles have been timed out first.']);
    exit;
}

function gpFee($mins) {
    $hrs  = $mins ? ceil($mins / 60) : 0;
    $succ = $hrs > 1 ? ($hrs - 1) * 20 : 0;
    return ['hrs' => $hrs, 'base' => 30, 'succ' => $succ, 'total' => 30 + $succ];
}

$reportDir = __DIR__ . '/reports/';
if (!is_dir($reportDir)) mkdir($reportDir, 0755, true);

$filename = 'FUMC_Gate_Pass_Log_' . $date . '.csv';
$fh       = fopen($reportDir . $filename, 'w');
fwrite($fh, "\xEF\xBB\xBF");

// Formal title block
fputcsv($fh, ['']);
fputcsv($fh, ['FATIMA UNIVERSITY MEDICAL CENTER']);
fputcsv($fh, ['PARKING MANAGEMENT OFFICE']);
fputcsv($fh, ['GATE PASS LOG — VEHICLE EXIT RECORD']);
fputcsv($fh, ['']);
fputcsv($fh, ['Date:      ' . $date]);
fputcsv($fh, ['Generated: ' . date('Y-m-d H:i:s')]);
fputcsv($fh, ['Prepared By: ' . $guardName]);
fputcsv($fh, ['']);

// Column headers
fputcsv($fh, [
    'NO.', 'GATE PASS NO.', 'GUARD ON DUTY', 'TICKET NO.',
    'PLATE NO.', 'VEHICLE TYPE', 'ENTRY TYPE',
    'EMPLOYEE NAME', 'EMPLOYEE ID', 'DEPARTMENT',
    'TIME IN', 'TIME OUT', 'GATE PASS ISSUED AT',
    'TOTAL HOURS', 'BASE FEE (P)', 'SUCCEEDING (P)', 'TOTAL AMOUNT (P)',
    'REMARKS'
]);

$pad    = fn($n) => str_pad($n, 4, '0', STR_PAD_LEFT);
$totAmt = 0;
$empCnt = 0;
$visCnt = 0;

foreach ($rows as $i => $row) {
    $f     = gpFee($row['duration_minutes']);
    $totAmt += $f['total'];
    $gpNo  = 'GP-' . date('Ymd', strtotime($row['time_out'] ?? $row['time_in'])) . '-' . $pad($i + 1);
    if ($row['entry_type'] === 'employee') $empCnt++; else $visCnt++;

    fputcsv($fh, [
        $i + 1,
        $gpNo,
        strtoupper($row['guard_name'] ?? $guardName),
        $row['ticket_number'] ?? '',
        $row['license_plate'],
        $row['vehicle_type'] ?? 'Car',
        strtoupper($row['entry_type']),
        $row['emp_name']  ?? '—',
        $row['emp_no']    ?? '—',
        $row['department'] ? strtoupper($row['department']) : '—',
        $row['time_in']  ? date('Y-m-d H:i:s', strtotime($row['time_in']))  : '',
        $row['time_out'] ? date('Y-m-d H:i:s', strtotime($row['time_out'])) : '',
        $row['time_out'] ? date('Y-m-d H:i:s', strtotime($row['time_out'])) : '',
        $f['hrs'],
        $f['base'],
        $f['succ'] ?: '—',
        $f['total'],
        ''
    ]);
}

// Totals row
fputcsv($fh, ['', '', 'TOTAL — ' . count($rows) . ' GATE PASSES', '', '', '', '', '', '', '', '', '', '', '', '', '', $totAmt, '']);
fputcsv($fh, ['']);

// Summary block
fputcsv($fh, ['GATE PASS SUMMARY']);
fputcsv($fh, ['']);
fputcsv($fh, ['Total Gate Passes Issued',    count($rows)]);
fputcsv($fh, ['Employee Gate Passes',        $empCnt]);
fputcsv($fh, ['Visitor Gate Passes',         $visCnt]);
fputcsv($fh, ['Total Revenue Collected (P)', $totAmt]);
fputcsv($fh, ['']);
fputcsv($fh, ['']);
fputcsv($fh, ['Prepared by:', '', '', '', 'Noted by:']);
fputcsv($fh, ['']);
fputcsv($fh, ['___________________________', '', '', '', '___________________________']);
fputcsv($fh, ['Guard on Duty / Encoder',     '', '', '', 'Parking Officer-in-Charge']);
fclose($fh);

echo json_encode([
    'success'      => true,
    'message'      => 'Gate pass log generated successfully.',
    'filename'     => $filename,
    'record_count' => count($rows),
    'date'         => $date,
]);
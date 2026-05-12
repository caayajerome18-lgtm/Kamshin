<?php
// download_report.php
// Serves generated Excel reports securely

session_start();
if (!isset($_SESSION['guard_id'])) {
    http_response_code(401);
    die('Unauthorized');
}

$filename = basename($_GET['file'] ?? '');

// Validate filename — only allow our generated reports
if (!preg_match('/^FUMC_Parking_Report_\d{4}-\d{2}-\d{2}\.xlsx$/', $filename)) {
    http_response_code(400);
    die('Invalid filename');
}

$filepath = __DIR__ . '/reports/' . $filename;

if (!file_exists($filepath)) {
    http_response_code(404);
    die('File not found');
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: no-cache, no-store, must-revalidate');
readfile($filepath);
exit;

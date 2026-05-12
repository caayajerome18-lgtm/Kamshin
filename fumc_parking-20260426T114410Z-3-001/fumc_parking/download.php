<?php
// download.php - Secure file download handler
$file = basename($_GET['file'] ?? '');

// Accept all FUMC report filenames
if (!preg_match('/^FUMC_(All_Entries|Parking_Report|Employee_Report|Gate_Pass_Log)_[\d\-]+\.(xlsx|csv)$/', $file)) {
    die('Invalid file.');
}

$path = __DIR__ . '/reports/' . $file;

if (!file_exists($path)) {
    die('File not found. Please generate the report first.');
}

$ext = pathinfo($file, PATHINFO_EXTENSION);

if ($ext === 'xlsx') {
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
} else {
    header('Content-Type: text/csv; charset=UTF-8');
}

header('Content-Disposition: attachment; filename="' . $file . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
readfile($path);
exit;
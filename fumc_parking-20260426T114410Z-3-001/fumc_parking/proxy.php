<?php
// proxy.php - Session-based proxy
ob_start(); // Capture any stray output
error_reporting(0);
ini_set('display_errors', 0);

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { ob_end_clean(); http_response_code(200); exit(); }

if (!isset($_SESSION['guard_id'])) {
    ob_end_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Session expired. Please log out and login again.']);
    exit;
}

require_once __DIR__ . '/db.php';

$guardId      = $_SESSION['guard_id'];
$sessionToken = $_SESSION['token'] ?? '';

// Auto-refresh token if expired
$db  = getDB();
$chk = $db->prepare("SELECT id FROM guard_sessions WHERE token = ? AND expires_at > NOW()");
$chk->bind_param('s', $sessionToken);
$chk->execute();
$valid = $chk->get_result()->num_rows > 0;
$chk->close();

if (!$valid) {
    $newToken  = bin2hex(random_bytes(32));
    $newExpiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
    $del = $db->prepare("DELETE FROM guard_sessions WHERE guard_id = ?");
    $del->bind_param('i', $guardId);
    $del->execute(); $del->close();
    $ins = $db->prepare("INSERT INTO guard_sessions (guard_id, token, expires_at) VALUES (?, ?, ?)");
    $ins->bind_param('iss', $guardId, $newToken, $newExpiry);
    $ins->execute(); $ins->close();
    $_SESSION['token'] = $newToken;
    $sessionToken      = $newToken;
}

// Load guard data
$gs = $db->prepare("SELECT id, username, full_name, role FROM guards WHERE id = ? AND is_active = 1");
$gs->bind_param('i', $guardId);
$gs->execute();
$guardData = $gs->get_result()->fetch_assoc();
$gs->close();
$db->close();

if (!$guardData) {
    ob_end_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Guard account not found.']);
    exit;
}

$GLOBALS['auth_user'] = $guardData;
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $sessionToken;

// Load API files
require_once __DIR__ . '/vehicle_intake.php';
require_once __DIR__ . '/vehicle_exit.php';
require_once __DIR__ . '/employees.php';
require_once __DIR__ . '/dashboard.php';
require_once __DIR__ . '/reports.php';
require_once __DIR__ . '/guard_management.php';
require_once __DIR__ . '/auth.php';

$endpoint = trim($_GET['endpoint'] ?? '');
$method   = $_SERVER['REQUEST_METHOD'];

// Discard any stray output from includes
ob_end_clean();

// Dispatch
switch ($endpoint) {
    case 'vehicle-intake':
        if ($method === 'POST') recordVehicleEntry(); break;
    case 'cancel-entry':
        if ($method === 'POST') cancelEntry(); break;
    case 'vehicle-exit':
        if ($method === 'GET')  lookupVehicle();
        if ($method === 'POST') confirmVehicleExit();
        break;
    case 'vehicle-entries':
        if ($method === 'GET') getVehicleEntries(); break;
    case 'dashboard':
        if ($method === 'GET') getDashboardStatus(); break;
    case 'parking-slots':
        if ($method === 'GET') getParkingSlots(); break;
    case 'employees':
        if ($method === 'GET')  getEmployees();
        if ($method === 'POST') createEmployee();
        break;
    case 'employee-parking':
        if ($method === 'GET') getEmployeeParkingInfo(); break;
    case 'export-excel':
        if ($method === 'POST') exportExcelReport(); break;
    case 'daily-report':
        if ($method === 'GET') getDailyReport(); break;
    case 'change-password':
        if ($method === 'POST') changePassword(); break;
    case 'create-guard':
        if ($method === 'POST') createGuard(); break;
    case 'list-guards':
        if ($method === 'GET') listGuards(); break;
    case 'reset-guard-password':
        if ($method === 'POST') resetGuardPassword(); break;
    case 'deactivate-guard':
        if ($method === 'POST') deactivateGuard(); break;
    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Endpoint not found: ' . $endpoint]);
        break;
}
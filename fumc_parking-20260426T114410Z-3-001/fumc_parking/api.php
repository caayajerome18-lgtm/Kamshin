<?php
// api.php - Main API Router
// FUMC Parking Management System Backend

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'db.php';
require_once 'auth.php';
require_once 'vehicle_intake.php';
require_once 'vehicle_exit.php';
require_once 'employees.php';
require_once 'dashboard.php';
require_once 'reports.php';
require_once 'guard_management.php'; // ← ADDED

$method    = $_SERVER['REQUEST_METHOD'];
$uri       = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri       = trim(str_replace('/fumc_parking', '', $uri), '/');
$segments  = explode('/', $uri);
$endpoint  = $segments[0] ?? '';
$subAction = $segments[1] ?? '';

switch ($endpoint) {
    case 'login':
        if ($method === 'POST') handleLogin();
        break;
    case 'logout':
        if ($method === 'POST') handleLogout();
        break;
    case 'change-password':
        requireAuth();
        if ($method === 'POST') changePassword();
        break;
    case 'vehicle-intake':
        requireAuth();
        if ($method === 'POST') recordVehicleEntry();
        break;
    case 'cancel-entry':
        requireAuth();
        if ($method === 'POST') cancelEntry();
        break;
    case 'vehicle-exit':
        requireAuth();
        if ($method === 'GET')  lookupVehicle();
        if ($method === 'POST') confirmVehicleExit();
        break;
    case 'dashboard':
        requireAuth();
        if ($method === 'GET') getDashboardStatus();
        break;
    case 'parking-slots':
        requireAuth();
        if ($method === 'GET') getParkingSlots();
        break;
    case 'vehicle-entries':
        requireAuth();
        if ($method === 'GET') getVehicleEntries();
        break;
    case 'employees':
        requireAuth();
        if ($method === 'GET')    getEmployees();
        if ($method === 'POST')   createEmployee();
        if ($method === 'PUT')    updateEmployee($subAction);
        if ($method === 'DELETE') deleteEmployee($subAction);
        break;
    case 'employee-parking':
        requireAuth();
        if ($method === 'GET') getEmployeeParkingInfo();
        break;
    case 'daily-report':
        requireAuth();
        if ($method === 'GET') getDailyReport();
        break;
    case 'export-excel':
        requireAuth();
        if ($method === 'POST') exportExcelReport();
        break;

    // ---- GUARD MANAGEMENT ----
    case 'create-guard':
        requireAuth();
        if ($method === 'POST') createGuard();
        break;
    case 'list-guards':
        requireAuth();
        if ($method === 'GET') listGuards();
        break;
    case 'reset-guard-password':
        requireAuth();
        if ($method === 'POST') resetGuardPassword();
        break;
    case 'deactivate-guard':
        requireAuth();
        if ($method === 'POST') deactivateGuard();
        break;

    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Endpoint not found.']);
        break;
}
<?php
// test_api.php - DELETE AFTER DEBUGGING
// Visit after login: http://localhost/fumc_parking/test_api.php
session_start();

echo "<h2>FUMC API Debug</h2>";
echo "<p>Session guard_id: " . ($_SESSION['guard_id'] ?? '<b style=color:red>NOT SET - please login first!</b>') . "</p>";

if (!isset($_SESSION['guard_id'])) {
    echo "<p><a href='index.php'>Click here to login first</a></p>";
    exit;
}

require_once 'db.php';
$db = getDB();

echo "<h3>vehicle_logs table contents:</h3>";
$res = $db->query("SELECT id, license_plate, entry_type, status, time_in, time_out FROM vehicle_logs ORDER BY time_in DESC LIMIT 20");
if ($res->num_rows === 0) {
    echo "<p style='color:red'><b>NO RECORDS in vehicle_logs!</b> - You need to add some Time In entries first.</p>";
} else {
    echo "<table border=1 cellpadding=5>";
    echo "<tr><th>ID</th><th>Plate</th><th>Type</th><th>Status</th><th>Time In</th><th>Time Out</th></tr>";
    while ($row = $res->fetch_assoc()) {
        echo "<tr><td>{$row['id']}</td><td>{$row['license_plate']}</td><td>{$row['entry_type']}</td><td><b>{$row['status']}</b></td><td>{$row['time_in']}</td><td>{$row['time_out']}</td></tr>";
    }
    echo "</table>";
}

echo "<hr><h3>API Test - status=parked (no date filter):</h3>";
$url = "http://localhost/fumc_parking/proxy.php?endpoint=vehicle-entries&status=parked";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIE, session_name().'='.session_id());
$result = curl_exec($ch);
echo "<pre>" . htmlspecialchars($result) . "</pre>";

$db->close();
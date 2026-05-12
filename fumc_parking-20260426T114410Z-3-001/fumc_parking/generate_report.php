<?php
// generate_report.php - All Entries Report (CSV, simple summary)
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }
if (!isset($_SESSION['guard_id'])) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Not logged in.']); exit; }
require_once 'db.php';
$date      = $_GET['date'] ?? date('Y-m-d');
$guardName = strtoupper($_SESSION['full_name'] ?? 'GUARD');
$db   = getDB();
$stmt = $db->prepare("SELECT vl.*, e.full_name AS employee_name, e.employee_id AS emp_no, e.department, g.full_name AS guard_name FROM vehicle_logs vl LEFT JOIN employees e ON e.id=vl.employee_id LEFT JOIN guards g ON g.id=vl.guard_id WHERE DATE(vl.time_in)=? ORDER BY vl.time_in ASC");
$stmt->bind_param('s',$date); $stmt->execute();
$rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close(); $db->close();
if(empty($rows)){http_response_code(404);echo json_encode(['success'=>false,'message'=>'No records found for '.$date]);exit;}
function fee($mins){$hrs=$mins?ceil($mins/60):0;return['hrs'=>$hrs,'base'=>30,'succ'=>$hrs>1?($hrs-1)*20:0,'total'=>30+($hrs>1?($hrs-1)*20:0)];}
$reportDir=__DIR__.'/reports/';
if(!is_dir($reportDir))mkdir($reportDir,0755,true);
$filename='FUMC_All_Entries_'.$date.'.csv';
$fh=fopen($reportDir.$filename,'w');
fwrite($fh,"\xEF\xBB\xBF");
// Title
fputcsv($fh,['FUMC — FATIMA UNIVERSITY MEDICAL CENTER']);
fputcsv($fh,['ALL VEHICLE ENTRIES REPORT — DAILY SUMMARY']);
fputcsv($fh,['Date: '.$date.'   |   Generated: '.date('Y-m-d H:i:s').'   |   By: '.$guardName]);
fputcsv($fh,[]);
// Headers — simple columns for All Entries
fputcsv($fh,['#','TICKET NO.','PLATE NO.','VEHICLE','ENTRY TYPE','EMPLOYEE','TIME IN','TIME OUT','HOURS','FEE (P)','STATUS']);
$tot=0; $emp=0; $vis=0;
foreach($rows as $i=>$r){
    $f=fee($r['duration_minutes']); $tot+=$f['total'];
    if($r['entry_type']==='employee')$emp++; else $vis++;
    fputcsv($fh,[$i+1,$r['ticket_number']??'',$r['license_plate'],$r['vehicle_type']??'',strtoupper($r['entry_type']),$r['employee_name']??'—',$r['time_in']??'',$r['time_out']??'Still Parked',$f['hrs'],$f['total'],strtoupper($r['status'])]);
}
fputcsv($fh,[]);
fputcsv($fh,['','SUMMARY','','','','','','','','','']);
fputcsv($fh,['','Total Entries',count($rows)]);
fputcsv($fh,['','Employee Entries',$emp]);
fputcsv($fh,['','Visitor Entries',$vis]);
fputcsv($fh,['','Total Revenue (P)',$tot]);
fclose($fh);
echo json_encode(['success'=>true,'filename'=>$filename,'record_count'=>count($rows),'date'=>$date]);
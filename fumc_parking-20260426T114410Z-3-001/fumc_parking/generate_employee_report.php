<?php
// generate_employee_report.php - Employee Report (Formal, detailed columns)
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }
if (!isset($_SESSION['guard_id'])) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Not logged in.']); exit; }
require_once 'db.php';
$date      = $_GET['date'] ?? date('Y-m-d');
$guardName = strtoupper($_SESSION['full_name'] ?? 'GUARD');
$db   = getDB();
$stmt = $db->prepare("SELECT vl.*, e.full_name AS emp_name, e.employee_id AS emp_no, e.department, e.position, g.full_name AS guard_name FROM vehicle_logs vl INNER JOIN employees e ON e.id=vl.employee_id LEFT JOIN guards g ON g.id=vl.guard_id WHERE DATE(vl.time_in)=? AND vl.entry_type='employee' ORDER BY vl.time_in ASC");
$stmt->bind_param('s',$date); $stmt->execute();
$rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close(); $db->close();
if(empty($rows)){http_response_code(404);echo json_encode(['success'=>false,'message'=>'No employee parking records found for '.$date]);exit;}
function empFee($vtype,$mins){
    $hrs=$mins?ceil($mins/60):0;
    $mc=stripos($vtype??'car','motorcycle')!==false;
    $base=$mc?30:50;
    $succ=$hrs>10?($hrs-10)*($mc?10:20):0;
    return['hrs'=>$hrs,'type'=>$mc?'MC w/ Sticker':'CAR w/ Sticker','base'=>$base,'succ'=>$succ,'total'=>$base+$succ];
}
$reportDir=__DIR__.'/reports/';
if(!is_dir($reportDir))mkdir($reportDir,0755,true);
$filename='FUMC_Employee_Report_'.$date.'.csv';
$fh=fopen($reportDir.$filename,'w');
fwrite($fh,"\xEF\xBB\xBF");
// Formal title block
fputcsv($fh,['']);
fputcsv($fh,['FATIMA UNIVERSITY MEDICAL CENTER']);
fputcsv($fh,['PARKING MANAGEMENT OFFICE']);
fputcsv($fh,['EMPLOYEE PARKING MONITORING REPORT']);
fputcsv($fh,['']);
fputcsv($fh,['Date:     '.$date]);
fputcsv($fh,['Generated: '.date('Y-m-d H:i:s')]);
fputcsv($fh,['Prepared By: '.$guardName]);
fputcsv($fh,['']);
// Column headers — detailed for Employee
fputcsv($fh,['NO.','GUARD ON DUTY','EMPLOYEE NAME','EMPLOYEE ID NO.','DEPARTMENT','POSITION','PLATE NO.','VEHICLE TYPE','PARKING TYPE','TICKET NO.','DATE','TIME IN','TIME OUT','TOTAL HOURS','BASE FEE (P)','SUCCEEDING HRS (P)','TOTAL AMOUNT (P)','STATUS','REMARKS']);
$totBase=0; $totSucc=0; $totAmt=0; $exited=0; $parked=0;
foreach($rows as $i=>$r){
    $f=empFee($r['vehicle_type'],$r['duration_minutes']);
    $totBase+=$f['base']; $totSucc+=$f['succ']; $totAmt+=$f['total'];
    if($r['status']==='exited')$exited++; else $parked++;
    fputcsv($fh,[
        $i+1,
        strtoupper($r['guard_name']??$guardName),
        strtoupper($r['emp_name']??''),
        $r['emp_no']??'',
        strtoupper($r['department']??''),
        strtoupper($r['position']??''),
        $r['license_plate'],
        $r['vehicle_type']??'Car',
        $f['type'],
        $r['ticket_number']??'',
        $date,
        $r['time_in']?date('H:i:s',strtotime($r['time_in'])):'',
        $r['time_out']?date('H:i:s',strtotime($r['time_out'])):'STILL PARKED',
        $f['hrs'],
        $f['base'],
        $f['succ']?$f['succ']:'—',
        $r['status']==='parked'?'STILL PARKED':$f['total'],
        strtoupper($r['status']),
        ''
    ]);
}
// Totals row
fputcsv($fh,['','TOTAL — '.count($rows).' EMPLOYEE ENTRIES','','','','','','','','','','','','',$totBase,$totSucc,$totAmt,'','']);
fputcsv($fh,['']);
// Formal Summary block
fputcsv($fh,['REPORT SUMMARY']);
fputcsv($fh,['']);
fputcsv($fh,['Total Employee Entries',count($rows)]);
fputcsv($fh,['Exited',$exited]);
fputcsv($fh,['Still Parked',$parked]);
fputcsv($fh,['Total Base Fees (P)',$totBase]);
fputcsv($fh,['Total Succeeding Hrs Fee (P)',$totSucc]);
fputcsv($fh,['TOTAL REVENUE (P)',$totAmt]);
fputcsv($fh,['']);
fputcsv($fh,['']);
fputcsv($fh,['Prepared by:','','','','Noted by:']);
fputcsv($fh,['']);
fputcsv($fh,['___________________________','','','','___________________________']);
fputcsv($fh,['Guard on Duty / Encoder','','','','Parking Officer-in-Charge']);
fclose($fh);
echo json_encode(['success'=>true,'filename'=>$filename,'record_count'=>count($rows),'date'=>$date]);
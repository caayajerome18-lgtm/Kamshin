<?php
// employee_portal.php - Employee Dashboard
session_start();
require_once 'db.php';

// Handle login
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!empty($username) && !empty($password)) {
        $db = getDB();
        // Check employees table with login credentials
        $stmt = $db->prepare("SELECT el.id AS login_id, el.username, el.password, e.id AS emp_id, e.full_name, e.employee_id, e.department, e.position FROM employee_logins el INNER JOIN employees e ON e.id = el.employee_id WHERE el.username = ? AND el.is_active = 1");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $emp = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($emp && password_verify($password, $emp['password'])) {
            $_SESSION['emp_login_id'] = $emp['login_id'];
            $_SESSION['emp_id']       = $emp['emp_id'];
            $_SESSION['emp_name']     = $emp['full_name'];
            $_SESSION['emp_no']       = $emp['employee_id'];
            $_SESSION['emp_dept']     = $emp['department'];
            $_SESSION['emp_pos']      = $emp['position'];
            $db->close();
            header('Location: employee_portal.php');
            exit;
        } else {
            $login_error = 'Invalid username or password.';
            $db->close();
        }
    } else {
        $login_error = 'Please enter username and password.';
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: employee_portal.php');
    exit;
}

$logged_in = isset($_SESSION['emp_id']);
$emp_name  = $_SESSION['emp_name'] ?? '';
$emp_id    = $_SESSION['emp_id'] ?? 0;
$emp_no    = $_SESSION['emp_no'] ?? '';
$emp_dept  = $_SESSION['emp_dept'] ?? '';
$emp_pos   = $_SESSION['emp_pos'] ?? '';
$initials  = '';
if ($emp_name) {
    foreach (explode(' ', $emp_name) as $p) $initials .= strtoupper(substr($p,0,1));
    $initials = substr($initials, 0, 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>FUMC Employee Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Condensed:wght@600;700;800&display=swap" rel="stylesheet"/>
<style>
:root{--gd:#1a4731;--gm:#1d6b44;--gmi:#2d8a5a;--gl:#e8f5ee;--ga:#34c770;--rd:#c0392b;--rl:#fdecea;--am:#d4830a;--al:#fef3e2;--bl:#1a5fa8;--bll:#e8f0fb;--g50:#f7f9f8;--g100:#f0f4f2;--g200:#dde6e1;--g400:#8aaa99;--g600:#4a6358;--g800:#1e2d27;--sh:0 2px 16px rgba(0,0,0,.09);--r:10px;}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Barlow',sans-serif;background:var(--g100);color:var(--g800);min-height:100vh;}
/* LOGIN */
.login-wrap{position:fixed;inset:0;background:linear-gradient(135deg,var(--gd) 0%,#0d2d1f 100%);display:flex;align-items:center;justify-content:center;z-index:200;overflow:hidden;}

/* Animated background */
.login-wrap::before{content:'';position:absolute;width:600px;height:600px;background:radial-gradient(circle,rgba(52,199,112,.15) 0%,transparent 70%);border-radius:50%;top:-200px;left:-200px;animation:floatBg 8s ease-in-out infinite;}
.login-wrap::after{content:'';position:absolute;width:500px;height:500px;background:radial-gradient(circle,rgba(52,199,112,.1) 0%,transparent 70%);border-radius:50%;bottom:-150px;right:-150px;animation:floatBg 10s ease-in-out infinite reverse;}
@keyframes floatBg{0%,100%{transform:translate(0,0) scale(1);}50%{transform:translate(30px,-30px) scale(1.1);}}

/* Floating orbs */
.login-orb{position:absolute;border-radius:50%;background:rgba(52,199,112,.08);animation:orb linear infinite;}
@keyframes orb{0%{transform:translateY(100vh) scale(0);opacity:0;}10%{opacity:1;}90%{opacity:.5;}100%{transform:translateY(-100px) scale(1.2);opacity:0;}}

/* Login card */
.lc{background:white;border-radius:18px;padding:42px 38px;width:100%;max-width:400px;box-shadow:0 25px 60px rgba(0,0,0,.5),0 0 0 1px rgba(255,255,255,.1);animation:cardSlideUp .6s cubic-bezier(.34,1.56,.64,1);position:relative;z-index:2;}
@keyframes cardSlideUp{from{opacity:0;transform:translateY(40px) scale(.95);}to{opacity:1;transform:translateY(0) scale(1);}}

/* Logo */
.ll{display:flex;align-items:center;justify-content:center;gap:12px;margin-bottom:6px;animation:logoFade .8s ease .2s both;}
@keyframes logoFade{from{opacity:0;transform:translateY(-15px);}to{opacity:1;transform:translateY(0);}}

.llc{width:54px;height:54px;background:var(--gm);border-radius:50%;display:flex;align-items:center;justify-content:center;animation:logoPulse 2s ease-in-out 1s infinite;}
@keyframes logoPulse{0%,100%{box-shadow:0 0 0 0 rgba(29,107,68,.5);}50%{box-shadow:0 0 0 12px rgba(29,107,68,0);}}

.lb{font-family:'Barlow Condensed',sans-serif;font-size:22px;font-weight:800;color:var(--gd);line-height:1.1;animation:fadeRight .6s ease .3s both;}
.lb span{display:block;font-size:10px;font-weight:500;color:var(--g400);letter-spacing:2px;text-transform:uppercase;margin-top:2px;}
@keyframes fadeRight{from{opacity:0;transform:translateX(-10px);}to{opacity:1;transform:translateX(0);}}

/* Badge */
.emp-badge{background:var(--gl);border:1.5px solid var(--gmi);border-radius:8px;padding:8px 14px;text-align:center;font-size:11px;color:var(--gmi);font-weight:700;margin-bottom:18px;text-transform:uppercase;letter-spacing:.5px;animation:badgePop .5s cubic-bezier(.34,1.56,.64,1) .4s both;}
@keyframes badgePop{from{opacity:0;transform:scale(.8);}to{opacity:1;transform:scale(1);}}

.lsub{text-align:center;font-size:12px;color:var(--g400);margin-bottom:24px;animation:fadeIn .6s ease .35s both;}

/* Form fields stagger */
.fg{margin-bottom:14px;animation:fieldSlide .5s ease both;}
.fg:nth-child(1){animation-delay:.45s;}
.fg:nth-child(2){animation-delay:.55s;}
.fg:nth-child(3){animation-delay:.65s;}
@keyframes fieldSlide{from{opacity:0;transform:translateX(-15px);}to{opacity:1;transform:translateX(0);}}

/* Inputs */
.ins{padding:10px 13px;border:1.5px solid var(--g200);border-radius:var(--r);font-family:'Barlow',sans-serif;font-size:14px;outline:none;transition:all .25s cubic-bezier(.34,1.56,.64,1);width:100%;}
.ins:focus{border-color:var(--gmi);box-shadow:0 0 0 4px rgba(29,107,68,.12);transform:translateY(-1px);}
.ins:hover:not(:focus){border-color:var(--g400);}

/* Login button */
.bln{width:100%;padding:13px;background:var(--gm);color:white;border:none;border-radius:var(--r);font-family:'Barlow Condensed',sans-serif;font-size:16px;font-weight:800;letter-spacing:1px;cursor:pointer;margin-top:6px;transition:all .2s cubic-bezier(.34,1.56,.64,1);position:relative;overflow:hidden;animation:btnAppear .5s ease .7s both;}
.bln:hover{background:var(--gd);transform:translateY(-2px);box-shadow:0 8px 24px rgba(26,71,49,.4);}
.bln:active{transform:scale(.97);}
@keyframes btnAppear{from{opacity:0;transform:translateY(10px);}to{opacity:1;transform:translateY(0);}}

/* Error shake */
.lerr{background:var(--rl);color:var(--rd);border-radius:8px;padding:9px 12px;font-size:13px;margin-bottom:12px;animation:shakeErr .4s cubic-bezier(.36,.07,.19,.97);}
@keyframes shakeErr{0%,100%{transform:translateX(0);}20%{transform:translateX(-8px);}40%{transform:translateX(8px);}60%{transform:translateX(-5px);}80%{transform:translateX(5px);}}

@keyframes fadeIn{from{opacity:0;}to{opacity:1);}}

/* TOPBAR */
.topbar{background:var(--gd);color:white;display:flex;align-items:center;justify-content:space-between;padding:0 22px;height:58px;position:fixed;top:0;left:0;right:0;z-index:100;box-shadow:0 2px 12px rgba(0,0,0,.3);}
.tl{display:flex;align-items:center;gap:12px;}
.br2{font-family:'Barlow Condensed',sans-serif;font-size:16px;font-weight:800;letter-spacing:1px;}
.bs{font-size:11px;color:rgba(255,255,255,.6);}
.blog{background:rgba(192,57,43,.85);border:none;color:white;padding:6px 13px;border-radius:6px;font-family:'Barlow',sans-serif;font-size:12px;font-weight:700;cursor:pointer;text-decoration:none;transition:all .2s cubic-bezier(.34,1.56,.64,1);}
.blog:hover{transform:translateY(-2px);box-shadow:0 4px 14px rgba(192,57,43,.4);background:var(--rd);}
.blog:active{transform:scale(.96);}

/* BUTTONS with animations */
.btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:11px 20px;border-radius:var(--r);font-family:'Barlow Condensed',sans-serif;font-size:15px;font-weight:700;letter-spacing:.5px;cursor:pointer;border:none;transition:all .2s cubic-bezier(.34,1.56,.64,1);position:relative;overflow:hidden;}
.btn::after{content:'';position:absolute;inset:0;background:rgba(255,255,255,0);transition:background .2s;}
.btn:hover::after{background:rgba(255,255,255,.12);}
.btn:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(0,0,0,.18);}
.btn:active{transform:scale(.96);}
.btn .ripple{position:absolute;border-radius:50%;transform:scale(0);animation:ripple-anim .5s linear;background:rgba(255,255,255,.3);pointer-events:none;}
@keyframes ripple-anim{to{transform:scale(4);opacity:0;}}
.bp{background:var(--gm);color:white;width:100%;font-size:16px;padding:14px;}
.bp:hover{background:var(--gm);box-shadow:0 8px 24px rgba(29,107,68,.4);}
.bo{background:transparent;color:var(--g600);border:1.5px solid var(--g200);}
.bo:hover{background:var(--g100);box-shadow:0 4px 14px rgba(0,0,0,.08);}
.bbl{background:var(--bl);color:white;}
.bbl:hover{background:var(--bl);box-shadow:0 8px 24px rgba(26,95,168,.4);}
.bda{background:var(--rd);color:white;}
.bda:hover{background:var(--rd);box-shadow:0 8px 24px rgba(192,57,43,.4);}
.bsm{padding:7px 14px;font-size:12px;}

/* TABS animation */
.tabs{display:flex;gap:4px;margin-bottom:18px;background:white;border-radius:12px;padding:6px;box-shadow:var(--sh);}
.tab{flex:1;padding:10px;text-align:center;border-radius:8px;cursor:pointer;font-family:'Barlow Condensed',sans-serif;font-size:14px;font-weight:700;letter-spacing:.5px;color:var(--g400);transition:all .25s cubic-bezier(.34,1.56,.64,1);}
.tab:hover{color:var(--gm);background:var(--g50);transform:translateY(-1px);}
.tab.active{background:var(--gm);color:white;box-shadow:0 4px 12px rgba(29,107,68,.3);}
.tab-page{display:none;animation:tabFadeIn .3s ease;}
.tab-page.active{display:block;}
@keyframes tabFadeIn{from{opacity:0;transform:translateY(8px);}to{opacity:1;transform:translateY(0);}}

/* PROFILE CARD animation */
.profile-card{background:linear-gradient(135deg,var(--gd),#0d3d22);color:white;border-radius:16px;padding:28px;display:flex;align-items:center;gap:20px;margin-bottom:18px;animation:slideInLeft .5s cubic-bezier(.34,1.56,.64,1);}
@keyframes slideInLeft{from{opacity:0;transform:translateX(-20px);}to{opacity:1;transform:translateX(0);}}
.pav{width:70px;height:70px;border-radius:50%;background:var(--ga);display:flex;align-items:center;justify-content:center;font-family:'Barlow Condensed',sans-serif;font-size:26px;font-weight:800;color:var(--gd);flex-shrink:0;animation:avatarPop .6s cubic-bezier(.34,1.56,.64,1) .2s both;}
@keyframes avatarPop{from{opacity:0;transform:scale(.5);}to{opacity:1;transform:scale(1);}}
.pname{font-family:'Barlow Condensed',sans-serif;font-size:26px;font-weight:800;line-height:1.1;}
.psub{font-size:12px;color:rgba(255,255,255,.65);margin-top:4px;}
.pbadge{display:inline-block;background:rgba(52,199,112,.2);border:1px solid var(--ga);border-radius:20px;padding:3px 12px;font-size:11px;font-weight:700;color:var(--ga);margin-top:6px;text-transform:uppercase;letter-spacing:.5px;animation:badgePop .5s cubic-bezier(.34,1.56,.64,1) .4s both;}

/* STATS animation */
.stats-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:18px;}
.stat{background:white;border-radius:12px;box-shadow:var(--sh);padding:18px;text-align:center;transition:all .2s cubic-bezier(.34,1.56,.64,1);animation:statPop .5s cubic-bezier(.34,1.56,.64,1) both;}
.stat:nth-child(1){animation-delay:.1s;}
.stat:nth-child(2){animation-delay:.2s;}
.stat:nth-child(3){animation-delay:.3s;}
.stat:hover{transform:translateY(-4px);box-shadow:0 12px 30px rgba(0,0,0,.12);}
@keyframes statPop{from{opacity:0;transform:translateY(15px) scale(.95);}to{opacity:1;transform:translateY(0) scale(1);}}
.stat-val{font-family:'Barlow Condensed',sans-serif;font-size:36px;font-weight:800;color:var(--gd);line-height:1;}
.stat-lbl{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--g400);margin-top:4px;}

/* CARD hover */
.card{background:white;border-radius:16px;box-shadow:var(--sh);padding:22px;margin-bottom:18px;transition:box-shadow .2s ease;}
.card:hover{box-shadow:0 8px 30px rgba(0,0,0,.12);}

/* TABLE row hover */
.tbl tr{transition:background .15s ease;}
.tbl tr:hover td{background:var(--g50);}

/* Pay options hover */
.pay-opt{border:2px solid var(--g200);border-radius:10px;padding:16px;text-align:center;cursor:pointer;transition:all .2s cubic-bezier(.34,1.56,.64,1);}
.pay-opt:hover{border-color:var(--gmi);transform:translateY(-3px);box-shadow:0 6px 16px rgba(0,0,0,.1);}

/* Modal animation */
.mov{position:fixed;inset:0;background:rgba(0,0,0,.6);display:none;align-items:center;justify-content:center;z-index:300;}
.mov.show{display:flex;}
.mo{background:white;border-radius:16px;padding:28px;width:100%;max-width:420px;animation:bounceIn .35s cubic-bezier(.34,1.56,.64,1);}
@keyframes bounceIn{from{opacity:0;transform:scale(.7) translateY(-20px);}to{opacity:1;transform:scale(1) translateY(0);}}
.mot{font-family:'Barlow Condensed',sans-serif;font-size:21px;font-weight:800;color:var(--gd);margin-bottom:14px;}
.mic{width:52px;height:52px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;background:var(--gl);animation:iconSpin .5s cubic-bezier(.34,1.56,.64,1) .2s both;}
@keyframes iconSpin{from{transform:rotate(-180deg) scale(0);}to{transform:rotate(0) scale(1);}}
</style>
</head>
<body>

<?php if (!$logged_in): ?>
<div class="login-wrap">
  <!-- Floating orbs -->
  <div class="login-orb" style="width:80px;height:80px;left:10%;animation-duration:12s;animation-delay:0s;"></div>
  <div class="login-orb" style="width:50px;height:50px;left:25%;animation-duration:9s;animation-delay:2s;"></div>
  <div class="login-orb" style="width:120px;height:120px;left:55%;animation-duration:15s;animation-delay:1s;"></div>
  <div class="login-orb" style="width:60px;height:60px;left:70%;animation-duration:11s;animation-delay:3s;"></div>
  <div class="login-orb" style="width:90px;height:90px;left:85%;animation-duration:13s;animation-delay:.5s;"></div>

  <div class="lc">
    <div class="ll">
      <div class="llc">
        <svg width="32" height="32" viewBox="0 0 60 60" fill="none"><circle cx="30" cy="30" r="28" fill="white"/><rect x="24" y="10" width="12" height="40" rx="3" fill="#1d6b44"/><rect x="10" y="24" width="40" height="12" rx="3" fill="#1d6b44"/><circle cx="30" cy="30" r="6" fill="#1d6b44"/></svg>
      </div>
      <div class="lb">FUMC <span>Fatima University Medical Center</span></div>
    </div>
    <div class="emp-badge">🏥 Employee Parking Portal</div>
    <?php if ($login_error): ?><div class="lerr"><?= htmlspecialchars($login_error) ?></div><?php endif; ?>
    <form method="POST" onsubmit="animateEmpLogin(this)">
      <input type="hidden" name="action" value="login"/>
      <div class="fg"><label class="fl">Employee Username</label><input class="ins" type="text" name="username" placeholder="Enter your username" required autocomplete="off"/></div>
      <div class="fg"><label class="fl">Password</label><input class="ins" type="password" name="password" placeholder="Enter your password" required/></div>
      <button class="bln" type="submit" id="emp-login-btn">SIGN IN TO EMPLOYEE PORTAL</button>
    </form>
    <div style="text-align:center;margin-top:16px;font-size:11px;color:var(--g400);animation:fadeIn .6s ease .9s both;">Contact admin if you need login credentials</div>
  </div>
</div>

<?php else: ?>
<div class="topbar">
  <div class="tl">
    <svg width="28" height="28" viewBox="0 0 60 60" fill="none"><circle cx="30" cy="30" r="28" fill="#1d6b44"/><rect x="24" y="10" width="12" height="40" rx="3" fill="white"/><rect x="10" y="24" width="40" height="12" rx="3" fill="white"/><circle cx="30" cy="30" r="6" fill="#1d6b44"/></svg>
    <span class="br2">FUMC</span>
    <span style="width:1px;height:22px;background:rgba(255,255,255,.25);display:block;"></span>
    <span class="bs">Employee Parking Portal</span>
  </div>
  <div style="display:flex;align-items:center;gap:12px;">
    <div style="display:flex;align-items:center;gap:8px;background:rgba(255,255,255,.1);border-radius:40px;padding:4px 14px 4px 4px;">
      <div style="width:28px;height:28px;background:var(--ga);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:11px;color:var(--gd);"><?= htmlspecialchars($initials) ?></div>
      <span style="font-size:12px;font-weight:600;color:white;"><?= htmlspecialchars($emp_name) ?></span>
    </div>
    <a href="employee_portal.php?logout=1" class="blog">Log Out</a>
  </div>
</div>

<div class="main" style="margin-top:78px;">
  <!-- Profile Card -->
  <div class="profile-card">
    <div class="pav"><?= htmlspecialchars($initials) ?></div>
    <div>
      <div class="pname"><?= htmlspecialchars(strtoupper($emp_name)) ?></div>
      <div class="psub"><?= htmlspecialchars($emp_no) ?> · <?= htmlspecialchars($emp_dept) ?> · <?= htmlspecialchars($emp_pos) ?></div>
      <div class="pbadge">🏥 FUMC Employee</div>
    </div>
  </div>

  <!-- Stats -->
  <div class="stats-grid" id="stats-grid">
    <div class="stat"><div class="stat-val" id="s-total">—</div><div class="stat-lbl">Total Parkings</div></div>
    <div class="stat"><div class="stat-val" id="s-today">—</div><div class="stat-lbl">Today</div></div>
    <div class="stat"><div class="stat-val" id="s-amount">—</div><div class="stat-lbl">Total Fees (₱)</div></div>
  </div>

  <!-- Tabs -->
  <div class="tabs">
    <div class="tab active" onclick="showTab('history')">📋 Parking History</div>
    <div class="tab" onclick="showTab('timein')">🚗 Time In</div>
    <div class="tab" onclick="showTab('timeout')">🚪 Time Out</div>
    <div class="tab" onclick="showTab('payment')">💳 Receipt & Payment</div>
    <div class="tab" onclick="showTab('profile')">👤 My Profile</div>
  </div>

  <!-- HISTORY TAB -->
  <div class="tab-page active" id="tab-history">
    <div class="card">
      <div class="st">My Parking History</div>
      <div style="display:flex;gap:9px;margin-bottom:14px;flex-wrap:wrap;">
        <input type="date" class="ins" id="h-date" style="width:160px;"/>
        <select class="fs2" id="h-filter" style="width:140px;">
          <option value="all">All</option>
          <option value="parked">Currently Parked</option>
          <option value="exited">Exited</option>
        </select>
        <button class="btn bo bsm" onclick="loadHistory()">Filter</button>
      </div>
      <div style="overflow-x:auto;">
        <table class="tbl">
          <thead><tr><th>Ticket No</th><th>Plate</th><th>Vehicle</th><th>Date</th><th>Time In</th><th>Time Out</th><th>Duration</th><th>Fee</th><th>Status</th><th>Action</th></tr></thead>
          <tbody id="history-tbody"><tr><td colspan="10" style="text-align:center;padding:28px;color:var(--g400);">Loading...</td></tr></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- TIME IN TAB -->
  <div class="tab-page" id="tab-timein">
    <div class="card" style="max-width:540px;">
      <div class="st">Record Vehicle Entry</div>
      <div class="tnb"><div class="tnl">Ticket No (Auto-Generated)</div><div class="tnv" id="e-tktprev">TKT---------</div></div>
      <div style="margin-bottom:14px;"><label class="fl2">Plate Number / MV File</label><input class="pi" id="e-plate" placeholder="ABC 1234" maxlength="15" oninput="this.value=this.value.toUpperCase()"/></div>
      <div class="te2" style="margin-bottom:14px;">
        <div><label class="fl2">Vehicle Type</label><select class="fs2" id="e-vtype"><option>Car</option><option>SUV</option><option>Motorcycle</option><option>Truck</option><option>Van</option></select></div>
        <div><label class="fl2">Time In (Live)</label><div class="trv lv" id="e-time">--:--:--</div></div>
      </div>
      <div class="al2 ok" id="ti-ok"></div>
      <div class="al2 er" id="ti-er"></div>
      <button class="btn bp" onclick="empTimeIn()">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12l5 5L20 7"/></svg>
        SUBMIT TIME IN
      </button>
    </div>
  </div>

  <!-- TIME OUT TAB -->
  <div class="tab-page" id="tab-timeout">
    <div class="card" style="max-width:540px;">
      <div class="st">Process Vehicle Exit</div>
      <div style="display:flex;gap:9px;margin-bottom:14px;">
        <input class="ins" id="to-plate" placeholder="Enter Plate No or Ticket No" style="flex:1;text-transform:uppercase;" oninput="this.value=this.value.toUpperCase()"/>
        <button class="btn bo bsm" onclick="empSearchExit()">Search</button>
      </div>
      <div id="to-found" style="display:none;">
        <div style="background:var(--g50);border-radius:10px;padding:14px;margin-bottom:12px;">
          <div class="rr"><span style="color:var(--g400);">Ticket No</span><span id="to-tkt" style="font-weight:700;"></span></div>
          <div class="rr"><span style="color:var(--g400);">Plate No</span><span id="to-pl" style="font-weight:700;"></span></div>
          <div class="rr"><span style="color:var(--g400);">Time In</span><span id="to-ti"></span></div>
          <div class="rr"><span style="color:var(--g400);">Duration (Live)</span><span class="trv lv" id="to-dur">—</span></div>
        </div>
        <div class="pay-box" id="to-fee-box">
          <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--am);margin-bottom:6px;">Estimated Fee</div>
          <div class="pay-amt" id="to-amt">₱0.00</div>
          <div class="pay-sub" id="to-sub"></div>
        </div>
        <div class="al2 ok" id="to-ok"></div>
        <div class="al2 er" id="to-er"></div>
        <div style="display:flex;gap:9px;">
          <button class="btn bp" onclick="empTimeOut()"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12l5 5L20 7"/></svg>CONFIRM TIME OUT</button>
          <button class="btn bo" onclick="empClearOut()">CANCEL</button>
        </div>
      </div>
      <div id="to-empty" style="text-align:center;color:var(--g400);padding:40px 0;">Enter plate or ticket number to search</div>
    </div>
  </div>

  <!-- PAYMENT TAB -->
  <div class="tab-page" id="tab-payment">
    <div style="display:grid;grid-template-columns:1fr 360px;gap:16px;">
      <div class="card">
        <div class="st">Load Receipt</div>
        <div style="display:flex;gap:9px;margin-bottom:10px;">
          <input class="ins" id="p-tkt" placeholder="Enter Ticket No..." style="flex:1;" onkeypress="if(event.key==='Enter')loadEmpReceipt()"/>
          <button class="btn bo bsm" onclick="loadEmpReceipt()">Load</button>
        </div>
        <div class="al2 er" id="p-err"></div>
        <div id="p-receipt"><div style="text-align:center;color:var(--g400);padding:38px 0;">Enter ticket number to load receipt</div></div>
        <!-- Discount -->
        <div id="p-disc-card" style="display:none;margin-top:14px;padding-top:14px;border-top:1px solid var(--g200);">
          <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--g400);margin-bottom:10px;">Discount Options</div>
          <div style="display:flex;gap:12px;flex-wrap:wrap;">
            <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;padding:8px 14px;border:1.5px solid var(--g200);border-radius:8px;"><input type="checkbox" id="p-pwd" onchange="updateEmpReceipt()"/> <strong>PWD</strong> <span style="color:var(--g400);font-size:11px;">(20% off)</span></label>
            <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;padding:8px 14px;border:1.5px solid var(--g200);border-radius:8px;"><input type="checkbox" id="p-sen" onchange="updateEmpReceipt()"/> <strong>Senior</strong> <span style="color:var(--g400);font-size:11px;">(20% off)</span></label>
          </div>
          <div id="p-discid-wrap" style="display:none;margin-top:10px;"><input class="ins" id="p-discid" placeholder="Enter PWD / Senior ID No."/></div>
        </div>
      </div>
      <div>
        <div class="card">
          <div class="st">Payment</div>
          <div id="p-pay-display"><div style="text-align:center;color:var(--g400);padding:28px 0;">Load a ticket first</div></div>
          <!-- Payment method -->
          <div id="p-method-card" style="display:none;">
            <div class="pay-grid">
              <div class="pay-opt" id="pm-cash" onclick="selPay('cash')"><div style="font-size:22px;margin-bottom:4px;">💵</div><div style="font-family:'Barlow Condensed',sans-serif;font-size:15px;font-weight:800;color:var(--gd);">CASH</div></div>
              <div class="pay-opt" id="pm-gcash" onclick="selPay('gcash')"><div style="font-size:22px;margin-bottom:4px;">📱</div><div style="font-family:'Barlow Condensed',sans-serif;font-size:15px;font-weight:800;color:var(--bl);">GCASH</div></div>
            </div>
            <div id="pm-cash-sec" style="display:none;">
              <label class="fl2">Amount Tendered (₱)</label>
              <input class="ins" type="number" id="p-tendered" placeholder="Enter amount" oninput="calcEmpChange()" style="font-size:18px;font-weight:700;text-align:center;margin-bottom:10px;"/>
              <div id="p-change-box" style="display:none;background:var(--gl);border:1.5px solid var(--gmi);border-radius:10px;padding:12px;text-align:center;margin-bottom:10px;">
                <div style="font-size:10px;font-weight:700;color:var(--gmi);margin-bottom:4px;">CHANGE</div>
                <div style="font-family:'Barlow Condensed',sans-serif;font-size:28px;font-weight:800;color:var(--gd);" id="p-change">₱0.00</div>
              </div>
            </div>
            <div id="pm-gcash-sec" style="display:none;">
              <div style="background:var(--bll);border:1px solid var(--bl);border-radius:8px;padding:12px;text-align:center;margin-bottom:10px;">
                <div style="font-size:10px;font-weight:700;color:var(--bl);margin-bottom:4px;">FUMC GCASH NUMBER</div>
                <div style="font-family:'Barlow Condensed',sans-serif;font-size:24px;font-weight:800;color:var(--bl);">0917-XXX-XXXX</div>
              </div>
              <input class="ins" id="p-gcref" placeholder="GCash Reference Number" style="font-size:15px;text-align:center;font-weight:700;margin-bottom:10px;"/>
            </div>
            <div class="al2 ok" id="p-pay-ok"></div>
            <div class="al2 er" id="p-pay-er"></div>
            <button class="btn bp" id="p-confirm-btn" onclick="confirmEmpPayment()" style="display:none;">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12l5 5L20 7"/></svg>
              CONFIRM &amp; PRINT RECEIPT
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- PROFILE TAB -->
  <div class="tab-page" id="tab-profile">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
      <div class="card">
        <div class="st">My Information</div>
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:18px;">
          <div style="width:64px;height:64px;border-radius:50%;background:var(--gl);display:flex;align-items:center;justify-content:center;font-family:'Barlow Condensed',sans-serif;font-size:24px;font-weight:800;color:var(--gd);"><?= htmlspecialchars($initials) ?></div>
          <div>
            <div style="font-size:16px;font-weight:700;"><?= htmlspecialchars($emp_name) ?></div>
            <div style="font-size:12px;color:var(--g400);"><?= htmlspecialchars($emp_dept) ?> · <?= htmlspecialchars($emp_pos) ?></div>
            <div style="font-size:12px;color:var(--gmi);font-weight:600;margin-top:2px;">ID: <?= htmlspecialchars($emp_no) ?></div>
          </div>
        </div>
        <div style="background:var(--g50);border-radius:10px;padding:14px;">
          <div class="rr"><span style="color:var(--g400);">Full Name</span><span style="font-weight:600;"><?= htmlspecialchars($emp_name) ?></span></div>
          <div class="rr"><span style="color:var(--g400);">Employee ID</span><span style="font-weight:600;"><?= htmlspecialchars($emp_no) ?></span></div>
          <div class="rr"><span style="color:var(--g400);">Department</span><span><?= htmlspecialchars($emp_dept) ?></span></div>
          <div class="rr" style="border:none;"><span style="color:var(--g400);">Position</span><span><?= htmlspecialchars($emp_pos) ?></span></div>
        </div>
      </div>
      <div class="card">
        <div class="st">Change Password</div>
        <div class="fg"><label class="fl2">Current Password</label><input class="ins" type="password" id="cp-old" placeholder="Current password"/></div>
        <div class="fg"><label class="fl2">New Password</label><input class="ins" type="password" id="cp-new" placeholder="Min 8 characters"/></div>
        <div class="fg"><label class="fl2">Confirm New Password</label><input class="ins" type="password" id="cp-con" placeholder="Re-enter new password"/></div>
        <div class="al2 ok" id="cp-ok"></div>
        <div class="al2 er" id="cp-er"></div>
        <button class="btn bp" onclick="changeEmpPass()">UPDATE PASSWORD</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal -->
<div class="mov" id="m-tkt">
  <div class="mo" style="text-align:center;">
    <div class="mic"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#1d6b44" stroke-width="2.5"><path d="M5 12l5 5L20 7"/></svg></div>
    <div class="mot" style="text-align:center;">Ticket Generated!</div>
    <div style="background:var(--gl);border-radius:10px;padding:14px;margin:10px 0;">
      <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--gmi);margin-bottom:3px;">Ticket Number</div>
      <div style="font-family:'Barlow Condensed',sans-serif;font-size:26px;font-weight:800;color:var(--gd);letter-spacing:2px;" id="m-tktno">—</div>
    </div>
    <div style="font-size:13px;color:var(--g600);margin-bottom:14px;" id="m-tktinfo">—</div>
    <button class="btn bp" onclick="document.getElementById('m-tkt').classList.remove('show');loadHistory();">CLOSE</button>
  </div>
</div>

<script>
const EMP_ID  = <?= (int)$emp_id ?>;
const EMP_NO  = '<?= addslashes($emp_no) ?>';
const API     = 'http://localhost/fumc_parking/proxy.php?endpoint=';
const TOKEN   = '';
const R1=30, RS=20, DISC=0.20;

function proxyUrl(path){
  const [ep,...rest]=path.split('?');
  const qs=rest.join('?');
  return API+ep+(qs?'&'+qs:'');
}

function calcAmt(mins,pwd,sen){
  const hrs=Math.max(1,Math.ceil(mins/60));
  const base=R1+(hrs-1)*RS;
  const dp=(pwd||sen)?DISC:0;
  const da=base*dp;
  return{hrs,base,dp,da,total:base-da};
}

// Clock
setInterval(()=>{
  const t=new Date().toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:true});
  const el=document.getElementById('e-time'); if(el) el.textContent=t;
},1000);

// Init
document.addEventListener('DOMContentLoaded',()=>{
  const today=new Date().toISOString().split('T')[0];
  document.getElementById('h-date').value=today;
  genTktPrev();
  loadHistory();
  loadStats();
});

function showTab(t){
  document.querySelectorAll('.tab').forEach((el,i)=>{el.classList.remove('active');});
  document.querySelectorAll('.tab-page').forEach(el=>el.classList.remove('active'));
  const tabs=['history','timein','timeout','payment','profile'];
  const idx=tabs.indexOf(t);
  document.querySelectorAll('.tab')[idx].classList.add('active');
  document.getElementById('tab-'+t).classList.add('active');
}

function al(id,msg,type='ok'){
  const el=document.getElementById(id);if(!el)return;
  el.textContent=msg;el.className='al2 '+type;el.style.display='block';
  setTimeout(()=>el.style.display='none',5000);
}

function genTktPrev(){
  const d=new Date();const pad=n=>String(n).padStart(2,'0');
  const el=document.getElementById('e-tktprev');
  if(el)el.textContent=`TKT-${d.getFullYear()}${pad(d.getMonth()+1)}${pad(d.getDate())}-XXXXX`;
}

// ── LOAD STATS ──
async function loadStats(records){
  try{
    let mine=records||null;
    if(!mine){
      const all=[];
      const today=new Date();
      for(let i=0;i<30;i++){
        const d=new Date(today);d.setDate(d.getDate()-i);
        const ds=d.toISOString().split('T')[0];
        const r=await fetch('http://localhost/fumc_parking/proxy.php?endpoint=vehicle-entries&date='+ds+'&status=all',{credentials:'include'});
        const data=await r.json();
        all.push(...(data.data||[]).filter(x=>x.employee_id==EMP_ID));
      }
      mine=all;
    }
    const todayStr=new Date().toISOString().split('T')[0];
    const todayMine=mine.filter(d=>d.time_in?.startsWith(todayStr));
    const totalFee=mine.filter(d=>d.status==='exited').reduce((s,d)=>{
      const hrs=d.duration_minutes?Math.ceil(d.duration_minutes/60):0;
      return s+30+(hrs>1?(hrs-1)*20:0);
    },0);
    document.getElementById('s-total').textContent=mine.length;
    document.getElementById('s-today').textContent=todayMine.length;
    document.getElementById('s-amount').textContent='₱'+totalFee.toLocaleString();
  }catch(e){}
}

// ── HISTORY ──
async function loadHistory(){
  const date=document.getElementById('h-date').value;
  const filter=document.getElementById('h-filter').value;
  const status=filter==='all'?'all':filter;
  const tb=document.getElementById('history-tbody');
  tb.innerHTML='<tr><td colspan="10" style="text-align:center;padding:28px;color:var(--g400);">Loading...</td></tr>';
  try{
    // Search last 30 days to find all records for this employee
    const allRecords=[];
    const today=new Date();
    for(let i=0;i<30;i++){
      const d=new Date(today);d.setDate(d.getDate()-i);
      const dateStr=d.toISOString().split('T')[0];
      const url='http://localhost/fumc_parking/proxy.php?endpoint=vehicle-entries&date='+dateStr+'&status='+status;
      const r=await fetch(url,{credentials:'include'});
      const data=await r.json();
      const mine=(data.data||[]).filter(d=>d.employee_id==EMP_ID);
      allRecords.push(...mine);
    }
    if(!allRecords.length){
      tb.innerHTML='<tr><td colspan="10" style="text-align:center;padding:28px;color:var(--g400);">No parking records found.</td></tr>';
      return;
    }
    // Sort by time_in descending
    allRecords.sort((a,b)=>new Date(b.time_in)-new Date(a.time_in));
    tb.innerHTML=allRecords.map(r=>{
      const hrs=r.duration_minutes?Math.ceil(r.duration_minutes/60):0;
      const fee=r.duration_minutes?(30+(hrs>1?(hrs-1)*20:0)):0;
      return `<tr>
        <td><span class="badge bg2" style="font-size:9px;">${r.ticket_number||'—'}</span></td>
        <td><strong>${r.license_plate}</strong></td>
        <td>${r.vehicle_type||'—'}</td>
        <td style="font-size:11px;">${r.time_in?.split(' ')[0]||'—'}</td>
        <td style="font-size:11px;">${r.time_in?.split(' ')[1]||'—'}</td>
        <td style="font-size:11px;">${r.time_out?.split(' ')[1]||'—'}</td>
        <td>${r.duration_minutes?Math.floor(r.duration_minutes/60)+'h '+r.duration_minutes%60+'m':'—'}</td>
        <td style="font-weight:700;color:var(--am);">${fee?'₱'+fee.toFixed(2):'—'}</td>
        <td><span class="badge ${r.status==='parked'?'bg2':r.status==='exited'?'bgr':'brd'}">${r.status}</span></td>
        <td><button onclick="loadReceiptFromHistory('${r.ticket_number}')" style="background:var(--bl);color:white;border:none;padding:4px 10px;border-radius:5px;font-size:11px;cursor:pointer;font-weight:600;">Receipt</button></td>
      </tr>`;
    }).join('');
    // Update stats
    loadStats(allRecords);
  }catch(e){
    tb.innerHTML='<tr><td colspan="10" style="text-align:center;padding:28px;color:var(--rd);">Error loading records.</td></tr>';
  }
}

function loadReceiptFromHistory(tkt){
  document.getElementById('p-tkt').value=tkt;
  showTab('payment');
  loadEmpReceipt();
}

// ── TIME IN ──
async function empTimeIn(){
  const plate=document.getElementById('e-plate').value.trim();
  if(!plate){al('ti-er','Please enter plate number.','er');return;}
  try{
    const r=await fetch(proxyUrl('vehicle-intake'),{
      method:'POST',credentials:'include',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({license_plate:plate,vehicle_type:document.getElementById('e-vtype').value,entry_type:'employee',employee_id:EMP_NO})
    });
    const data=await r.json();
    if(data.success){
      document.getElementById('m-tktno').textContent=data.ticket_number;
      document.getElementById('m-tktinfo').textContent=plate+' — '+data.time_in;
      document.getElementById('m-tkt').classList.add('show');
      document.getElementById('e-plate').value='';
      genTktPrev(); loadStats();
    }else{al('ti-er',data.message,'er');}
  }catch(e){al('ti-er','Server error.','er');}
}

// ── TIME OUT ──
let toLogId=null, toTI=null, toTimer=null;
async function empSearchExit(){
  const q=document.getElementById('to-plate').value.trim();if(!q)return;
  try{
    const r=await fetch(proxyUrl(`vehicle-entries?status=parked`),{credentials:'include'});
    const data=await r.json();
    const mine=(data.data||[]).filter(d=>d.employee_id==EMP_ID);
    const found=mine.find(d=>d.license_plate===q||d.ticket_number===q);
    if(!found){document.getElementById('to-empty').innerHTML='<div style="color:var(--rd);text-align:center;padding:20px;">Vehicle not found or already exited.</div>';return;}
    toLogId=found.id; toTI=new Date(found.time_in);
    document.getElementById('to-tkt').textContent=found.ticket_number;
    document.getElementById('to-pl').textContent=found.license_plate;
    document.getElementById('to-ti').textContent=found.time_in;
    document.getElementById('to-found').style.display='block';
    document.getElementById('to-empty').style.display='none';
    if(toTimer)clearInterval(toTimer);
    toTimer=setInterval(()=>{
      const mins=Math.floor((new Date()-toTI)/60000);
      const hrs=Math.floor(mins/60); const m=mins%60;
      const fee=30+(mins>60?(Math.ceil(mins/60)-1)*20:0);
      document.getElementById('to-dur').textContent=hrs+'h '+m+'m';
      document.getElementById('to-amt').textContent='₱'+fee.toFixed(2);
      document.getElementById('to-sub').textContent='₱30 first hr + ₱20 × '+Math.max(0,Math.ceil(mins/60)-1)+' succeeding';
    },1000);
  }catch(e){}
}

async function empTimeOut(){
  if(!toLogId)return;
  if(toTimer)clearInterval(toTimer);
  try{
    const r=await fetch(proxyUrl('vehicle-exit'),{
      method:'POST',credentials:'include',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({log_id:toLogId})
    });
    const data=await r.json();
    if(data.success){
      al('to-ok','Time out recorded! Redirecting to payment...');
      empClearOut(); loadHistory(); loadStats();
      setTimeout(()=>showTab('payment'),1500);
    }else{al('to-er',data.message,'er');}
  }catch(e){al('to-er','Server error.','er');}
}

function empClearOut(){
  if(toTimer)clearInterval(toTimer);toLogId=null;toTI=null;
  document.getElementById('to-plate').value='';
  document.getElementById('to-found').style.display='none';
  document.getElementById('to-empty').style.display='block';
  document.getElementById('to-empty').innerHTML='Enter plate or ticket number to search';
}

// ── PAYMENT ──
let pRecord=null, pMethod=null, pTotal=0;
async function loadEmpReceipt(){
  const tkt=document.getElementById('p-tkt').value.trim();if(!tkt)return;
  try{
    const r=await fetch(proxyUrl(`vehicle-entries?status=all`),{credentials:'include'});
    const data=await r.json();
    const found=data.data?.find(d=>d.ticket_number===tkt&&d.employee_id==EMP_ID);
    if(!found){al('p-err','Ticket not found or does not belong to your account.','er');return;}
    pRecord=found;
    document.getElementById('p-pwd').checked=false;
    document.getElementById('p-sen').checked=false;
    document.getElementById('p-disc-card').style.display='block';
    document.getElementById('p-method-card').style.display='block';
    document.getElementById('pm-cash').style.border='2px solid var(--g200)';
    document.getElementById('pm-gcash').style.border='2px solid var(--g200)';
    document.getElementById('pm-cash-sec').style.display='none';
    document.getElementById('pm-gcash-sec').style.display='none';
    document.getElementById('p-confirm-btn').style.display='none';
    pMethod=null;
    updateEmpReceipt();
  }catch(e){al('p-err','Server error.','er');}
}

function updateEmpReceipt(){
  if(!pRecord)return;
  const pwd=document.getElementById('p-pwd').checked;
  const sen=document.getElementById('p-sen').checked;
  const mins=pRecord.duration_minutes||Math.floor((new Date()-new Date(pRecord.time_in))/60000);
  const pay=calcAmt(mins,pwd,sen);
  pTotal=pay.total;
  document.getElementById('p-discid-wrap').style.display=(pwd||sen)?'block':'none';
  document.getElementById('p-receipt').innerHTML=`
    <div class="receipt-box">
      <div style="font-family:'Barlow Condensed',sans-serif;font-size:17px;font-weight:800;color:var(--gd);text-align:center;margin-bottom:12px;">✚ FUMC EMPLOYEE RECEIPT</div>
      <div class="rr"><span style="color:var(--g400);">Ticket No</span><span style="font-weight:600;">${pRecord.ticket_number}</span></div>
      <div class="rr"><span style="color:var(--g400);">Plate No</span><span style="font-weight:600;">${pRecord.license_plate}</span></div>
      <div class="rr"><span style="color:var(--g400);">Employee</span><span><?= htmlspecialchars($emp_name) ?></span></div>
      <div class="rr"><span style="color:var(--g400);">Time In</span><span>${pRecord.time_in||'—'}</span></div>
      <div class="rr"><span style="color:var(--g400);">Time Out</span><span>${pRecord.time_out||'Still Parked'}</span></div>
      <div class="rr"><span style="color:var(--g400);">Duration</span><span>${pay.hrs} hr(s)</span></div>
      ${pay.da>0?`<div class="rr"><span style="color:var(--g400);">Discount (${(pay.dp*100).toFixed(0)}%)</span><span style="color:var(--bl);font-weight:600;">−₱${pay.da.toFixed(2)}</span></div>`:''}
      <div class="rtot"><span class="rtotl">TOTAL AMOUNT</span><span class="rtota">₱${pay.total.toFixed(2)}</span></div>
    </div>`;
  document.getElementById('p-pay-display').innerHTML=`
    <div class="pay-box">
      <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--am);margin-bottom:6px;">Amount Due</div>
      <div class="pay-amt">₱${pay.total.toFixed(2)}</div>
      <div class="pay-sub">₱${R1} first hr + ₱${RS}×${Math.max(0,pay.hrs-1)} succeeding${pay.da>0?` — Disc: −₱${pay.da.toFixed(2)}`:''}</div>
    </div>`;
}

function selPay(m){
  pMethod=m;
  document.getElementById('pm-cash').style.border=m==='cash'?'2px solid var(--gm)':'2px solid var(--g200)';
  document.getElementById('pm-gcash').style.border=m==='gcash'?'2px solid var(--bl)':'2px solid var(--g200)';
  document.getElementById('pm-cash').style.background=m==='cash'?'var(--gl)':'white';
  document.getElementById('pm-gcash').style.background=m==='gcash'?'var(--bll)':'white';
  document.getElementById('pm-cash-sec').style.display=m==='cash'?'block':'none';
  document.getElementById('pm-gcash-sec').style.display=m==='gcash'?'block':'none';
  document.getElementById('p-confirm-btn').style.display='block';
}

function calcEmpChange(){
  const t=parseFloat(document.getElementById('p-tendered').value)||0;
  const c=t-pTotal;
  const box=document.getElementById('p-change-box');
  const cr=document.getElementById('p-change');
  if(t>0){box.style.display='block';cr.textContent='₱'+Math.max(0,c).toFixed(2);box.style.borderColor=c>=0?'var(--gmi)':'var(--rd)';}
  else box.style.display='none';
}

function confirmEmpPayment(){
  if(!pRecord||!pMethod){al('p-pay-er','Select payment method first.','er');return;}
  if(pMethod==='cash'){
    const t=parseFloat(document.getElementById('p-tendered').value)||0;
    if(t<pTotal){al('p-pay-er','Insufficient amount.','er');return;}
  }
  if(pMethod==='gcash'&&!document.getElementById('p-gcref').value.trim()){al('p-pay-er','Enter GCash reference number.','er');return;}
  const pwd=document.getElementById('p-pwd').checked;
  const sen=document.getElementById('p-sen').checked;
  const mins=pRecord.duration_minutes||Math.floor((new Date()-new Date(pRecord.time_in))/60000);
  const pay=calcAmt(mins,pwd,sen);
  const tendered=pMethod==='cash'?parseFloat(document.getElementById('p-tendered').value):pay.total;
  const gcref=pMethod==='gcash'?document.getElementById('p-gcref').value.trim():'—';
  empPrintReceipt(pRecord,pay,pMethod,tendered,tendered-pay.total,gcref);
  al('p-pay-ok','✓ Payment confirmed! Receipt printed.');
}

function empPrintReceipt(rec,pay,method,tendered,change,gcref){
  const w=window.open('','_blank','width=380,height=600');
  const date=new Date().toLocaleString('en-PH');
  w.document.write(`<!DOCTYPE html><html><head><title>FUMC Receipt</title><style>
  *{margin:0;padding:0;box-sizing:border-box;}
  body{font-family:Arial,sans-serif;font-size:12px;color:#1e2d27;width:300px;margin:0 auto;padding:14px;}
  .hdr{text-align:center;border-bottom:2px solid #1a4731;padding-bottom:8px;margin-bottom:8px;}
  .tkt{text-align:center;background:#e8f5ee;border:1px solid #2d8a5a;border-radius:6px;padding:8px;margin-bottom:8px;}
  .tkt-no{font-size:18px;font-weight:900;color:#1a4731;letter-spacing:2px;}
  .row{display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid #f0f0f0;font-size:11px;}
  .total-box{background:#1a4731;color:white;border-radius:6px;padding:9px;margin:8px 0;display:flex;justify-content:space-between;}
  .t-val{font-size:20px;font-weight:900;color:#34c770;}
  .footer{text-align:center;font-size:9px;color:#999;margin-top:10px;padding-top:8px;border-top:1px dashed #ddd;}
  @media print{body{width:80mm;}}
  </style></head><body>
  <div class="hdr"><h1 style="font-size:13px;color:#1a4731;">✚ FUMC EMPLOYEE PARKING</h1><p style="font-size:9px;color:#666;">${date}</p></div>
  <div class="tkt"><div style="font-size:9px;color:#2d8a5a;font-weight:700;">TICKET NUMBER</div><div class="tkt-no">${rec.ticket_number}</div></div>
  <div class="row"><span style="color:#666;">Employee</span><span style="font-weight:600;"><?= htmlspecialchars($emp_name) ?></span></div>
  <div class="row"><span style="color:#666;">ID No.</span><span>${EMP_NO}</span></div>
  <div class="row"><span style="color:#666;">Plate No</span><span>${rec.license_plate}</span></div>
  <div class="row"><span style="color:#666;">Time In</span><span>${rec.time_in||'—'}</span></div>
  <div class="row"><span style="color:#666;">Time Out</span><span>${rec.time_out||'Still Parked'}</span></div>
  <div class="row"><span style="color:#666;">Duration</span><span>${pay.hrs} hr(s)</span></div>
  ${pay.da>0?`<div class="row"><span style="color:#666;">Discount (${(pay.dp*100).toFixed(0)}%)</span><span style="color:#1a5fa8;">−₱${pay.da.toFixed(2)}</span></div>`:''}
  <div class="total-box"><span style="font-size:10px;font-weight:700;text-transform:uppercase;">Total Amount</span><span class="t-val">₱${pay.total.toFixed(2)}</span></div>
  <div class="row"><span style="color:#666;">Payment</span><span style="font-weight:700;">${method==='cash'?'💵 CASH':'📱 GCASH'}</span></div>
  ${method==='cash'?`<div class="row"><span style="color:#666;">Tendered</span><span>₱${tendered.toFixed(2)}</span></div><div class="row"><span style="font-weight:700;color:#1a4731;">Change</span><span style="font-weight:900;color:#1a4731;">₱${change.toFixed(2)}</span></div>`:`<div class="row"><span style="color:#666;">GCash Ref</span><span style="color:#1a5fa8;">${gcref}</span></div>`}
  <div class="footer"><p>Thank you! Please drive safely.</p><p>FUMC IT Dept. · Parking System v2.0</p></div>
  <script>window.onload=function(){window.print();}<\/script></body></html>`);
  w.document.close();
}

// ── LOGIN ANIMATION ──
function animateEmpLogin(form) {
  const btn = document.getElementById('emp-login-btn');
  if (!btn) return;
  btn.innerHTML = '<span style="display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,.3);border-top-color:white;border-radius:50%;animation:spin .7s linear infinite;margin-right:6px;"></span> SIGNING IN...';
  btn.style.background = 'var(--gd)';
  btn.disabled = true;
  setTimeout(() => { btn.disabled = false; }, 4000);
}

// ── RIPPLE on all buttons ──
document.addEventListener('click', function(e) {
  const btn = e.target.closest('.btn, .bln, .blog');
  if (!btn) return;
  const ripple = document.createElement('span');
  ripple.className = 'ripple';
  const size = Math.max(btn.offsetWidth, btn.offsetHeight);
  const rect = btn.getBoundingClientRect();
  ripple.style.cssText = `width:${size}px;height:${size}px;left:${e.clientX-rect.left-size/2}px;top:${e.clientY-rect.top-size/2}px;position:absolute;border-radius:50%;transform:scale(0);animation:ripple-anim .5s linear;background:rgba(255,255,255,.3);pointer-events:none;`;
  btn.appendChild(ripple);
  setTimeout(() => ripple.remove(), 600);
});

// ── STAT counter animation ──
function animateCount(el, target, prefix='') {
  let start = 0;
  const dur = 800;
  const step = 16;
  const inc = target / (dur / step);
  const timer = setInterval(() => {
    start = Math.min(start + inc, target);
    el.textContent = prefix + (Number.isInteger(target) ? Math.floor(start) : start.toFixed(0));
    if (start >= target) clearInterval(timer);
  }, step);
}

// ── ANIMATE MODAL ──
function showModal(id) {
  const el = document.getElementById(id);
  el.classList.add('show');
  const mo = el.querySelector('.mo');
  if (mo) { mo.style.animation = 'none'; mo.offsetHeight; mo.style.animation = 'bounceIn .35s cubic-bezier(.34,1.56,.64,1)'; }
}

// Tab switch with animation
const _origShowTab = showTab;
function showTab(t) {
  document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
  document.querySelectorAll('.tab-page').forEach(el => { el.classList.remove('active'); el.style.animation = 'none'; });
  const tabs = ['history','timein','timeout','payment','profile'];
  const idx = tabs.indexOf(t);
  document.querySelectorAll('.tab')[idx].classList.add('active');
  const page = document.getElementById('tab-'+t);
  page.classList.add('active');
  page.style.animation = 'tabFadeIn .3s ease';
}

// ── CSS for spin ──
const style = document.createElement('style');
style.textContent = '@keyframes spin{to{transform:rotate(360deg);}}';
document.head.appendChild(style);
async function changeEmpPass(){
  const op=document.getElementById('cp-old').value;
  const np=document.getElementById('cp-new').value;
  const cp=document.getElementById('cp-con').value;
  if(!op||!np){al('cp-er','All fields required.','er');return;}
  if(np.length<8){al('cp-er','Min 8 characters.','er');return;}
  if(np!==cp){al('cp-er','Passwords do not match.','er');return;}
  try{
    const r=await fetch('http://localhost/fumc_parking/emp_change_pass.php',{
      method:'POST',credentials:'include',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({old_password:op,new_password:np})
    });
    const data=await r.json();
    if(data.success){al('cp-ok','Password changed successfully!');['cp-old','cp-new','cp-con'].forEach(id=>document.getElementById(id).value='');}
    else{al('cp-er',data.message,'er');}
  }catch(e){al('cp-er','Server error.','er');}
}
</script>
<?php endif; ?>
</body>
</html>
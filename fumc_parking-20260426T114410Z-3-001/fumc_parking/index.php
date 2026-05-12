<?php
session_start();
require_once 'db.php';

// Handle login POST
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, username, password, full_name, role FROM guards WHERE username = ? AND is_active = 1");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $guard = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($guard && password_verify($password, $guard['password'])) {
            $_SESSION['guard_id']   = $guard['id'];
            $_SESSION['username']   = $guard['username'];
            $_SESSION['full_name']  = $guard['full_name'];
            $_SESSION['role']       = $guard['role'];

            // Generate token and store in DB
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+8 hours'));
            $db->query("CREATE TABLE IF NOT EXISTS guard_sessions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                guard_id INT NOT NULL,
                token VARCHAR(64) NOT NULL UNIQUE,
                expires_at DATETIME NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            $s2 = $db->prepare("INSERT INTO guard_sessions (guard_id, token, expires_at) VALUES (?, ?, ?)");
            $s2->bind_param('iss', $guard['id'], $token, $expires);
            $s2->execute();
            $s2->close();
            $_SESSION['token'] = $token;
            $db->close();
            header('Location: index.php');
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
    if (isset($_SESSION['token'])) {
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM guard_sessions WHERE token = ?");
        $stmt->bind_param('s', $_SESSION['token']);
        $stmt->execute();
        $stmt->close();
        $db->close();
    }
    session_destroy();
    header('Location: index.php');
    exit;
}

$logged_in = isset($_SESSION['guard_id']);
$full_name = $_SESSION['full_name'] ?? '';
$token     = $_SESSION['token'] ?? '';
$initials  = '';
if ($full_name) {
    $parts = explode(' ', $full_name);
    foreach ($parts as $p) $initials .= strtoupper(substr($p, 0, 1));
    $initials = substr($initials, 0, 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>FUMC Parking Ticketing System</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Condensed:wght@600;700;800&display=swap" rel="stylesheet"/>
<style>
:root{--gd:#1a4731;--gm:#1d6b44;--gmi:#2d8a5a;--gl:#e8f5ee;--ga:#34c770;--rd:#c0392b;--rl:#fdecea;--am:#d4830a;--al:#fef3e2;--bl:#1a5fa8;--bll:#e8f0fb;--w:#fff;--g50:#f7f9f8;--g100:#f0f4f2;--g200:#dde6e1;--g400:#8aaa99;--g600:#4a6358;--g800:#1e2d27;--sh:0 2px 16px rgba(0,0,0,.09);--shl:0 8px 40px rgba(0,0,0,.15);--r:10px;--rl2:16px;}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Barlow',sans-serif;background:var(--g100);color:var(--g800);min-height:100vh;}
.topbar{background:var(--gd);color:white;display:flex;align-items:center;justify-content:space-between;padding:0 22px;height:58px;position:fixed;top:0;left:0;right:0;z-index:100;box-shadow:0 2px 12px rgba(0,0,0,.3);}
.tl{display:flex;align-items:center;gap:12px;}
.lw{width:36px;height:36px;background:white;border-radius:50%;display:flex;align-items:center;justify-content:center;}
.br2{font-family:'Barlow Condensed',sans-serif;font-size:18px;font-weight:800;letter-spacing:1px;}
.bd{width:1px;height:22px;background:rgba(255,255,255,.25);}
.bs{font-size:11px;color:rgba(255,255,255,.7);font-weight:500;}
.tr2{display:flex;align-items:center;gap:14px;}
.ck{text-align:right;}
.ckt{font-family:'Barlow Condensed',sans-serif;font-size:20px;font-weight:700;line-height:1;}
.ckd{font-size:10px;color:rgba(255,255,255,.6);text-transform:uppercase;letter-spacing:.4px;}
.gp{display:flex;align-items:center;gap:8px;background:rgba(255,255,255,.1);border-radius:40px;padding:4px 12px 4px 4px;}
.gav{width:28px;height:28px;background:var(--ga);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:11px;color:var(--gd);}
.gnm{font-size:12px;font-weight:600;}
.blog{background:rgba(192,57,43,.85);border:none;color:white;padding:6px 13px;border-radius:6px;font-family:'Barlow',sans-serif;font-size:12px;font-weight:700;cursor:pointer;text-decoration:none;display:inline-block;}
.blog:hover{background:var(--rd);}
.sidebar{position:fixed;top:58px;left:0;bottom:0;width:148px;background:var(--gd);padding:12px 0;display:flex;flex-direction:column;gap:2px;z-index:99;overflow-y:auto;}
.ni{display:flex;flex-direction:column;align-items:center;gap:4px;padding:12px 6px;cursor:pointer;color:rgba(255,255,255,.55);font-size:10px;font-weight:700;text-align:center;letter-spacing:.4px;text-transform:uppercase;transition:all .2s;border-left:3px solid transparent;}
.ni:hover{color:white;background:rgba(255,255,255,.07);}
.ni.active{color:var(--ga);background:rgba(52,199,112,.1);border-left-color:var(--ga);}
.ni svg{width:19px;height:19px;}
.nsep{height:1px;background:rgba(255,255,255,.1);margin:6px 14px;}
.main{margin-top:58px;margin-left:148px;padding:22px;min-height:calc(100vh - 58px);padding-bottom:38px;}
.page{display:none;animation:fi .22s ease;}
.page.active{display:block;}
@keyframes fi{from{opacity:0;transform:translateY(7px);}to{opacity:1;transform:translateY(0);}}
/* LOGIN */
.login-wrap{position:fixed;inset:0;background:var(--gd);display:flex;align-items:center;justify-content:center;z-index:200;overflow:hidden;}

/* Animated background particles */
.login-wrap::before{content:'';position:absolute;width:600px;height:600px;background:radial-gradient(circle,rgba(52,199,112,.15) 0%,transparent 70%);border-radius:50%;top:-200px;left:-200px;animation:floatBg 8s ease-in-out infinite;}
.login-wrap::after{content:'';position:absolute;width:500px;height:500px;background:radial-gradient(circle,rgba(52,199,112,.1) 0%,transparent 70%);border-radius:50%;bottom:-150px;right:-150px;animation:floatBg 10s ease-in-out infinite reverse;}
@keyframes floatBg{0%,100%{transform:translate(0,0) scale(1);}50%{transform:translate(30px,-30px) scale(1.1);}}

/* Floating orbs */
.login-orb{position:absolute;border-radius:50%;background:rgba(52,199,112,.08);animation:orb linear infinite;}
@keyframes orb{0%{transform:translateY(100vh) scale(0);opacity:0;}10%{opacity:1;}90%{opacity:.5;}100%{transform:translateY(-100px) scale(1.2);opacity:0;}}

/* Login card animation */
.lc{background:white;border-radius:var(--rl2);padding:42px 38px;width:100%;max-width:390px;box-shadow:0 25px 60px rgba(0,0,0,.4),0 0 0 1px rgba(255,255,255,.1);animation:cardSlideUp .6s cubic-bezier(.34,1.56,.64,1);position:relative;z-index:2;}
@keyframes cardSlideUp{from{opacity:0;transform:translateY(40px) scale(.95);}to{opacity:1;transform:translateY(0) scale(1);}}

/* Logo animation */
.ll{display:flex;align-items:center;justify-content:center;gap:12px;margin-bottom:6px;animation:logoFade .8s ease .2s both;}
@keyframes logoFade{from{opacity:0;transform:translateY(-15px);}to{opacity:1;transform:translateY(0);}}

/* Logo icon pulse */
.llc{width:54px;height:54px;background:var(--gm);border-radius:50%;display:flex;align-items:center;justify-content:center;animation:logoPulse 2s ease-in-out 1s infinite;}
@keyframes logoPulse{0%,100%{box-shadow:0 0 0 0 rgba(29,107,68,.5);}50%{box-shadow:0 0 0 12px rgba(29,107,68,0);}}

/* Title fade in */
.lb{font-family:'Barlow Condensed',sans-serif;font-size:25px;font-weight:800;color:var(--gd);line-height:1;animation:fadeRight .6s ease .3s both;}
@keyframes fadeRight{from{opacity:0;transform:translateX(-10px);}to{opacity:1;transform:translateX(0);}}

/* Subtitle */
.lsub{text-align:center;font-size:12px;color:var(--g400);margin-bottom:26px;font-weight:500;animation:fadeIn .6s ease .4s both;}

/* Form fields stagger */
.fg{margin-bottom:14px;animation:fieldSlide .5s ease both;}
.fg:nth-child(1){animation-delay:.45s;}
.fg:nth-child(2){animation-delay:.55s;}
.fg:nth-child(3){animation-delay:.65s;}
@keyframes fieldSlide{from{opacity:0;transform:translateX(-15px);}to{opacity:1;transform:translateX(0);}}

/* Input focus animation */
.fi2{width:100%;padding:10px 13px;border:1.5px solid var(--g200);border-radius:var(--r);font-family:'Barlow',sans-serif;font-size:14px;color:var(--g800);transition:all .25s cubic-bezier(.34,1.56,.64,1);outline:none;}
.fi2:focus{border-color:var(--gmi);box-shadow:0 0 0 4px rgba(29,107,68,.12);transform:translateY(-1px);}
.fi2:hover:not(:focus){border-color:var(--g400);}

/* Sign in button animation */
.bln{animation:btnAppear .5s ease .7s both;}
@keyframes btnAppear{from{opacity:0;transform:translateY(10px);}to{opacity:1;transform:translateY(0);}}

/* Error animation */
.lerr{background:var(--rl);color:var(--rd);border-radius:8px;padding:9px 12px;font-size:13px;font-weight:500;margin-bottom:12px;animation:shakeErr .4s cubic-bezier(.36,.07,.19,.97);}
@keyframes shakeErr{0%,100%{transform:translateX(0);}20%{transform:translateX(-8px);}40%{transform:translateX(8px);}60%{transform:translateX(-5px);}80%{transform:translateX(5px);}}

/* Footer text */
.lft{text-align:center;margin-top:18px;font-size:11px;color:var(--g400);animation:fadeIn .6s ease .9s both;}
@keyframes fadeIn{from{opacity:0;}to{opacity:1;}}
.ph{margin-bottom:18px;}
.pt{font-family:'Barlow Condensed',sans-serif;font-size:25px;font-weight:800;color:var(--gd);letter-spacing:.3px;}
.ps{font-size:12px;color:var(--g400);margin-top:1px;}
.card{background:white;border-radius:var(--rl2);box-shadow:var(--sh);padding:20px;}
.st{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--g400);margin-bottom:11px;padding-bottom:7px;border-bottom:1px solid var(--g200);}
.tc2{display:grid;grid-template-columns:1fr 350px;gap:16px;}
.te2{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.pi{width:100%;text-align:center;font-family:'Barlow Condensed',sans-serif;font-size:38px;font-weight:800;letter-spacing:4px;border:2px solid var(--g200);border-radius:10px;padding:9px 18px;text-transform:uppercase;outline:none;transition:.2s;}
.pi:focus{border-color:var(--gmi);box-shadow:0 0 0 3px rgba(29,107,68,.1);}
.fs2{width:100%;padding:10px 12px;border:1.5px solid var(--g200);border-radius:var(--r);font-family:'Barlow',sans-serif;font-size:14px;color:var(--g800);outline:none;background:white;cursor:pointer;}
.ins{padding:9px 12px;border:1.5px solid var(--g200);border-radius:var(--r);font-family:'Barlow',sans-serif;font-size:14px;outline:none;transition:.2s;width:100%;}
.ins:focus{border-color:var(--gmi);}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:11px 20px;border-radius:var(--r);font-family:'Barlow Condensed',sans-serif;font-size:15px;font-weight:700;letter-spacing:.5px;cursor:pointer;border:none;transition:all .2s cubic-bezier(.34,1.56,.64,1);position:relative;overflow:hidden;}
.btn::after{content:'';position:absolute;inset:0;background:rgba(255,255,255,0);transition:background .2s;}
.btn:hover::after{background:rgba(255,255,255,.12);}
.btn:active{transform:scale(.96);}
.btn:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(0,0,0,.18);}
.btn:active::after{background:rgba(0,0,0,.08);}
.bp{background:var(--gm);color:white;width:100%;font-size:16px;padding:14px;}
.bp:hover{background:var(--gm);box-shadow:0 8px 24px rgba(29,107,68,.4);}
.bda{background:var(--rd);color:white;padding:14px 26px;}
.bda:hover{background:var(--rd);box-shadow:0 8px 24px rgba(192,57,43,.4);}
.bo{background:transparent;color:var(--g600);border:1.5px solid var(--g200);}
.bo:hover{background:var(--g100);box-shadow:0 4px 14px rgba(0,0,0,.08);}
.bam{background:var(--am);color:white;}
.bam:hover{background:var(--am);box-shadow:0 8px 24px rgba(212,131,10,.4);}
.bbl{background:var(--bl);color:white;}
.bbl:hover{background:var(--bl);box-shadow:0 8px 24px rgba(26,95,168,.4);}
/* Ripple effect */
.btn .ripple{position:absolute;border-radius:50%;transform:scale(0);animation:ripple-anim .5s linear;background:rgba(255,255,255,.3);pointer-events:none;}
@keyframes ripple-anim{to{transform:scale(4);opacity:0;}}
/* Login button */
.bln{width:100%;padding:12px;background:var(--gm);color:white;border:none;border-radius:var(--r);font-family:'Barlow Condensed',sans-serif;font-size:16px;font-weight:800;letter-spacing:1px;cursor:pointer;margin-top:6px;transition:all .2s cubic-bezier(.34,1.56,.64,1);position:relative;overflow:hidden;}
.bln:hover{background:var(--gd);transform:translateY(-2px);box-shadow:0 8px 24px rgba(26,71,49,.4);}
.bln:active{transform:scale(.97);}
/* Nav items */
.ni{transition:all .18s cubic-bezier(.34,1.56,.64,1);}
.ni:hover{transform:translateX(3px);}
.ni.active{transform:translateX(3px);}
/* Sidebar nav animation */
@keyframes navPop{from{opacity:0;transform:translateX(-10px);}to{opacity:1;transform:translateX(0);}}
/* Log out button */
.blog{transition:all .2s cubic-bezier(.34,1.56,.64,1);}
.blog:hover{transform:translateY(-2px);box-shadow:0 4px 14px rgba(192,57,43,.4);}
.blog:active{transform:scale(.96);}
/* Pulse on active nav */
@keyframes navPulse{0%{box-shadow:0 0 0 0 rgba(52,199,112,.4);}70%{box-shadow:0 0 0 6px rgba(52,199,112,0);}100%{box-shadow:0 0 0 0 rgba(52,199,112,0);}}
/* Submit/primary button loading state shimmer */
@keyframes shimmer{0%{background-position:-200% 0;}100%{background-position:200% 0;}}
.btn-loading{background:linear-gradient(90deg,var(--gm) 25%,var(--gmi) 50%,var(--gm) 75%);background-size:200% 100%;animation:shimmer 1.2s infinite;}
/* Bounce on ticket generated */
@keyframes bounceIn{0%{transform:scale(.3);opacity:0;}50%{transform:scale(1.08);}70%{transform:scale(.95);}100%{transform:scale(1);opacity:1;}}
.bounce-in{animation:bounceIn .4s cubic-bezier(.34,1.56,.64,1);}
/* Small buttons */
.bsm{padding:7px 14px;font-size:12px;}
.bsm:hover{transform:translateY(-1px);}
.br3{display:flex;gap:10px;margin-top:14px;}
/* Green glow on bp */
.bp:focus-visible{outline:none;box-shadow:0 0 0 3px rgba(29,107,68,.4);}
.bda:focus-visible{outline:none;box-shadow:0 0 0 3px rgba(192,57,43,.4);}
.bbl:focus-visible{outline:none;box-shadow:0 0 0 3px rgba(26,95,168,.4);}
.tnb{background:var(--gl);border:2px solid var(--gmi);border-radius:var(--r);padding:12px 18px;text-align:center;margin-bottom:12px;}
.tnl{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--gmi);margin-bottom:3px;}
.tnv{font-family:'Barlow Condensed',sans-serif;font-size:30px;font-weight:800;color:var(--gd);letter-spacing:2px;}
.tb{background:var(--g50);border-radius:var(--r);padding:12px 16px;margin-bottom:10px;}
.trow{display:flex;justify-content:space-between;align-items:center;padding:5px 0;border-bottom:1px solid var(--g200);}
.trow:last-child{border-bottom:none;}
.trl{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--g400);}
.trv{font-family:'Barlow Condensed',sans-serif;font-size:17px;font-weight:700;color:var(--g800);}
.trv.lv{color:var(--gmi);animation:bl 1s infinite;}
@keyframes bl{0%,100%{opacity:1;}50%{opacity:.7;}}
.db{background:var(--gd);color:white;border-radius:var(--r);padding:16px;text-align:center;margin-bottom:10px;}
.dbl{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.6);margin-bottom:5px;}
.dbv{font-family:'Barlow Condensed',sans-serif;font-size:36px;font-weight:800;color:var(--ga);line-height:1;}
.dbu{font-size:15px;color:white;}
.pb{background:var(--al);border:2px solid var(--am);border-radius:var(--r);padding:16px;margin-bottom:10px;}
.pbl{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--am);margin-bottom:6px;}
.pba{font-family:'Barlow Condensed',sans-serif;font-size:42px;font-weight:800;color:var(--am);line-height:1;}
.pbk{font-size:12px;color:var(--g600);margin-top:5px;}
.pbd{background:var(--bll);border:1px solid var(--bl);border-radius:6px;padding:7px 11px;margin-top:7px;font-size:12px;color:var(--bl);font-weight:600;}
.rb{border:2px dashed var(--g200);border-radius:var(--r);padding:18px;background:white;}
.rt{font-family:'Barlow Condensed',sans-serif;font-size:17px;font-weight:800;color:var(--gd);text-align:center;margin-bottom:10px;letter-spacing:1px;}
.rr{display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid var(--g100);font-size:12px;}
.rr:last-child{border-bottom:none;font-weight:700;font-size:13px;}
.rtot{background:var(--gd);color:white;border-radius:7px;padding:10px 14px;margin-top:9px;display:flex;justify-content:space-between;align-items:center;}
.rtotl{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;}
.rtota{font-family:'Barlow Condensed',sans-serif;font-size:26px;font-weight:800;color:var(--ga);}
.ec{display:flex;align-items:center;gap:12px;background:var(--gl);border:1.5px solid var(--gmi);border-radius:var(--r);padding:12px;margin-bottom:12px;}
.eav{width:48px;height:48px;border-radius:50%;background:var(--gmi);display:flex;align-items:center;justify-content:center;font-family:'Barlow Condensed',sans-serif;font-size:18px;font-weight:800;color:white;flex-shrink:0;}
.en{font-size:15px;font-weight:700;color:var(--gd);}
.ei{font-size:12px;color:var(--g600);margin-top:1px;}
.badge{display:inline-block;padding:3px 9px;border-radius:20px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;}
.bg2{background:var(--gl);color:var(--gmi);}
.bgr{background:var(--g200);color:var(--g600);}
.bam2{background:var(--al);color:var(--am);}
.bbl2{background:var(--bll);color:var(--bl);}
.brd{background:var(--rl);color:var(--rd);}
.al2{border-radius:var(--r);padding:9px 13px;font-size:13px;font-weight:500;margin-bottom:10px;display:none;}
.al2.ok{background:var(--gl);color:var(--gd);border:1px solid var(--gmi);}
.al2.er{background:var(--rl);color:var(--rd);border:1px solid var(--rd);}
.mov{position:fixed;inset:0;background:rgba(0,0,0,.6);display:none;align-items:center;justify-content:center;z-index:300;}
.mov.show{display:flex;}
.mo{background:white;border-radius:var(--rl2);padding:28px;width:100%;max-width:410px;animation:fi .3s ease;}
.mot{font-family:'Barlow Condensed',sans-serif;font-size:21px;font-weight:800;color:var(--gd);margin-bottom:14px;}
.mic{width:52px;height:52px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;background:var(--gl);}
.tbl2{width:100%;border-collapse:collapse;font-size:12px;}
.tbl2 th{text-align:left;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--g400);padding:7px 10px;border-bottom:1px solid var(--g200);}
.tbl2 td{padding:9px 10px;border-bottom:1px solid var(--g100);}
.tbl2 tr:last-child td{border-bottom:none;}
.tbl2 tr:hover td{background:var(--g50);}
.sb{position:fixed;bottom:0;left:148px;right:0;background:var(--gd);color:rgba(255,255,255,.7);font-size:11px;padding:6px 18px;display:flex;gap:18px;align-items:center;z-index:99;}
.sd{width:6px;height:6px;border-radius:50%;background:var(--ga);display:inline-block;margin-right:4px;animation:bl 1.5s infinite;}
.sp{display:inline-block;width:15px;height:15px;border:2px solid rgba(255,255,255,.3);border-top-color:white;border-radius:50%;animation:spin .7s linear infinite;margin-right:5px;}
@keyframes spin{to{transform:rotate(360deg);}}
</style>
</head>
<body>

<?php if (!$logged_in): ?>
<!-- ═══ LOGIN PAGE ═══ -->
<div class="login-wrap">
  <!-- Floating orbs background -->
  <div class="login-orb" style="width:80px;height:80px;left:10%;animation-duration:12s;animation-delay:0s;"></div>
  <div class="login-orb" style="width:50px;height:50px;left:25%;animation-duration:9s;animation-delay:2s;"></div>
  <div class="login-orb" style="width:120px;height:120px;left:50%;animation-duration:15s;animation-delay:1s;"></div>
  <div class="login-orb" style="width:60px;height:60px;left:70%;animation-duration:11s;animation-delay:3s;"></div>
  <div class="login-orb" style="width:90px;height:90px;left:85%;animation-duration:13s;animation-delay:.5s;"></div>
  <div class="login-orb" style="width:40px;height:40px;left:40%;animation-duration:8s;animation-delay:4s;"></div>

  <div class="lc">
    <div class="ll">
      <div class="llc">
        <svg width="34" height="34" viewBox="0 0 60 60" fill="none">
          <circle cx="30" cy="30" r="28" fill="white"/>
          <rect x="24" y="10" width="12" height="40" rx="3" fill="#1d6b44"/>
          <rect x="10" y="24" width="40" height="12" rx="3" fill="#1d6b44"/>
          <circle cx="30" cy="30" r="6" fill="#1d6b44"/>
        </svg>
      </div>
      <div class="lb">FUMC <span>Fatima University Medical Center</span></div>
    </div>
    <p class="lsub">Parking Ticketing System — Guard / Admin Portal</p>

    <?php if ($login_error): ?>
      <div class="lerr"><?= htmlspecialchars($login_error) ?></div>
    <?php endif; ?>

    <form method="POST" action="index.php" onsubmit="animateLogin(this)">
      <input type="hidden" name="action" value="login"/>
      <div class="fg">
        <label class="fl">Username</label>
        <input class="fi2" type="text" name="username" placeholder="Enter username" autocomplete="off" required/>
      </div>
      <div class="fg">
        <label class="fl">Password</label>
        <input class="fi2" type="password" name="password" placeholder="Enter password" required/>
      </div>
      <button class="bln" type="submit" id="login-btn">SIGN IN</button>
    </form>
    <div class="lft">FUMC IT Dept. · Parking Ticketing System v2.0</div>
  </div>
</div>

<?php else: ?>
<!-- ═══ APP SHELL ═══ -->
<div class="topbar">
  <div class="tl">
    <div class="lw">
      <svg width="24" height="24" viewBox="0 0 60 60" fill="none">
        <circle cx="30" cy="30" r="28" fill="#1d6b44"/>
        <rect x="24" y="10" width="12" height="40" rx="3" fill="white"/>
        <rect x="10" y="24" width="40" height="12" rx="3" fill="white"/>
        <circle cx="30" cy="30" r="6" fill="#1d6b44"/>
      </svg>
    </div>
    <span class="br2">FUMC</span>
    <div class="bd"></div>
    <span class="bs">Parking Ticketing System</span>
  </div>
  <div class="tr2">
    <div class="ck">
      <div class="ckt" id="ckt">--:-- --</div>
      <div class="ckd" id="ckd">Loading...</div>
    </div>
    <div class="gp">
      <div class="gav"><?= htmlspecialchars($initials) ?></div>
      <span class="gnm"><?= htmlspecialchars($full_name) ?></span>
    </div>
    <a href="index.php?logout=1" class="blog">Log Out</a>
  </div>
</div>

<div class="sidebar">
  <div class="ni active" id="nav-visitor" onclick="showPage('visitor')">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13" rx="2"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
    Time In<br/>Non-Emp
  </div>
  <div class="ni" id="nav-vout" onclick="showPage('vout')">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13" rx="2"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/><line x1="18" y1="1" x2="22" y2="5"/><line x1="22" y1="1" x2="18" y2="5"/></svg>
    Time Out<br/>Non-Emp
  </div>
  <div class="nsep"></div>
  <div class="ni" id="nav-employee" onclick="showPage('employee')">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
    Time In<br/>Employee
  </div>
  <div class="ni" id="nav-eout" onclick="showPage('eout')">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/><line x1="18" y1="1" x2="22" y2="5"/><line x1="22" y1="1" x2="18" y2="5"/></svg>
    Time Out<br/>Employee
  </div>
  <div class="nsep"></div>
  <div class="ni" id="nav-receipt" onclick="showPage('receipt')">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/></svg>
    Receipt &<br/>Payment
  </div>
  <div class="nsep"></div>
  <div class="ni" id="nav-vehicles" onclick="showPage('vehicles')">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
    Info Sheet<br/>Vehicles
  </div>
  <div class="ni" id="nav-excel" onclick="showPage('excel')">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/><path d="M8 13l2 2 4-4"/></svg>
    Excel<br/>Report
  </div>
  <div class="ni" id="nav-empinfo" onclick="showPage('empinfo')">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
    Employee<br/>Parking
  </div>
  <div class="nsep"></div>
  <div class="ni" id="nav-admin" onclick="showPage('admin')">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93l-1.41 1.41M4.93 4.93l1.41 1.41M12 2v2M12 20v2M2 12h2M20 12h2M19.07 19.07l-1.41-1.41M4.93 19.07l1.41-1.41"/></svg>
    Admin<br/>Panel
  </div>
</div>

<div class="main">

<!-- TIME IN NON-EMP -->
<div class="page active" id="page-visitor">
  <div class="ph"><div class="pt">Time In — Non-Employee / Visitor</div><div class="ps">Record visitor entry and generate ticket · ₱30 first hour + ₱20 succeeding</div></div>
  <div class="tc2">
    <div class="card">
      <div class="st">New Ticket Entry</div>
      <div class="tnb"><div class="tnl">Ticket No (Auto-Generated)</div><div class="tnv" id="vtp">TKT---------</div></div>
      <div class="fg"><label class="fl">Plate Number / MV File (if no plate)</label><input class="pi" id="vpl" placeholder="ABC 1234" maxlength="15" oninput="this.value=this.value.toUpperCase()"/></div>
      <div class="te2" style="margin-top:12px;">
        <div class="fg"><label class="fl">Vehicle Type</label><select class="fs2" id="vvt"><option>Car</option><option>SUV</option><option>Motorcycle</option><option>Truck</option><option>Van</option></select></div>
        <div class="fg"><label class="fl">Time In (Live)</label><div class="trv lv" id="vtil" style="font-size:15px;padding:10px 0;">--:--:--</div></div>
      </div>
      <div class="al2 ok" id="vok"></div><div class="al2 er" id="ver"></div>
      <div class="br3">
        <button class="btn bp" onclick="submitVisitor()"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12l5 5L20 7"/></svg>SUBMIT TICKET</button>
        <button class="btn bda" onclick="clearV()">CANCEL</button>
      </div>
    </div>
    <div class="card">
      <div class="st">Last Entry Info</div>
      <div class="trow" style="padding:8px 0;"><span class="trl">Total Entries Today</span><span class="trv" id="total-today">—</span></div>
      <div class="trow" style="padding:8px 0;"><span class="trl">Last Entry</span><span class="trv" style="font-size:13px;" id="last-entry">—</span></div>
    </div>
  </div>
</div>

<!-- TIME OUT NON-EMP -->
<div class="page" id="page-vout">
  <div class="ph"><div class="pt">Time Out — Non-Employee / Visitor</div><div class="ps">Search ticket, apply discount, compute payment</div></div>
  <div class="tc2">
    <div class="card">
      <div class="st">Search by Ticket No</div>
      <div style="display:flex;gap:9px;margin-bottom:14px;"><input class="ins" id="vo-tk" placeholder="Enter Ticket No e.g. TKT-20260418-XXXXX" style="flex:1;font-size:13px;"/><button class="btn bo bsm" onclick="voSearch()">Search</button></div>
      <div id="vo-found" style="display:none;">
        <div class="tb">
          <div class="trow"><span class="trl">Plate No</span><span class="trv" id="vo-plate">—</span></div>
          <div class="trow"><span class="trl">Vehicle Type</span><span class="trv" id="vo-vtype">—</span></div>
          <div class="trow"><span class="trl">Date / Time In</span><span class="trv" id="vo-timein">—</span></div>
          <div class="trow"><span class="trl">Time Out (Live)</span><span class="trv lv" id="vo-live">—</span></div>
        </div>
        <div class="db" style="margin-top:12px;">
          <div class="dbl">Total Parking Duration</div>
          <div class="dbv"><span id="vo-dh">0</span><span class="dbu">h </span><span id="vo-dm">0</span><span class="dbu">m</span></div>
        </div>
        <div class="fg" style="margin-top:12px;">
          <label class="fl">Discount Applicable For</label>
          <div style="display:flex;gap:16px;margin-bottom:10px;flex-wrap:wrap;">
            <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;"><input type="checkbox" id="vo-pwd" onchange="voCalc()"/> PWD (20% off)</label>
            <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;"><input type="checkbox" id="vo-sen" onchange="voCalc()"/> Senior Citizen (20% off)</label>
          </div>
          <input class="ins" id="vo-did" placeholder="ID Number (PWD / Senior Citizen)"/>
        </div>
        <div class="al2 ok" id="vo-ok"></div>
        <div class="al2 er" id="vo-er"></div>
        <div class="br3">
          <button class="btn bp" onclick="voSubmit()"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12l5 5L20 7"/></svg>SUBMIT TIME OUT</button>
          <button class="btn bda" onclick="voClear()">CANCEL</button>
        </div>
      </div>
      <div id="vo-empty" style="text-align:center;color:var(--g400);padding:40px 0;font-size:13px;">Enter ticket number and click Search</div>
    </div>
    <div class="card">
      <div class="st">Payment Display</div>
      <div id="vo-pay"><div style="text-align:center;color:var(--g400);padding:40px 0;font-size:13px;">Search a ticket to see payment</div></div>
    </div>
  </div>
</div>

<!-- TIME IN EMPLOYEE -->
<div class="page" id="page-employee">
  <div class="ph"><div class="pt">Time In — Employee</div><div class="ps">Record employee vehicle entry · ₱30 first hour + ₱20 succeeding</div></div>
  <div class="tc2">
    <div class="card">
      <div class="st">Vehicle Info</div>
      <div class="fg"><label class="fl">Plate Number / MV File</label><input class="pi" id="epl" placeholder="ABC 1234" maxlength="15" oninput="this.value=this.value.toUpperCase()"/></div>
      <div class="te2" style="margin-top:12px;">
        <div class="fg"><label class="fl">Vehicle Type</label><select class="fs2" id="evt"><option>Car</option><option>SUV</option><option>Motorcycle</option><option>Truck</option><option>Van</option></select></div>
        <div class="fg"><label class="fl">Employee DB ID</label><input class="ins" id="eeid" type="number" placeholder="Employee ID no."/></div>
      </div>
      <div class="tb">
        <div class="trow"><span class="trl">Time In (Real-time)</span><span class="trv lv" id="etil">--:--:--</span></div>
        <div class="trow"><span class="trl">Date</span><span class="trv" id="edl">--/--/----</span></div>
      </div>
      <div class="al2 ok" id="eok"></div><div class="al2 er" id="eer"></div>
      <div class="br3">
        <button class="btn bp" onclick="submitEmployee()"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12l5 5L20 7"/></svg>SUBMIT TICKET</button>
        <button class="btn bda" onclick="clearE()">CANCEL</button>
      </div>
    </div>
    <div class="card">
      <div class="st">Employee Info &amp; ID Number</div>
      <div style="display:flex;gap:9px;margin-bottom:13px;"><input class="ins" id="esp" placeholder="Search by plate number..." style="flex:1;"/><button class="btn bo bsm" onclick="searchEmpVehicle()">Search</button></div>
      <div id="esr"><div style="text-align:center;color:var(--g400);padding:28px 0;font-size:12px;">Search plate to view employee info</div></div>
    </div>
  </div>
</div>

<!-- TIME OUT EMPLOYEE -->
<div class="page" id="page-eout">
  <div class="ph"><div class="pt">Time Out — Employee</div><div class="ps">Process employee exit and compute parking fee</div></div>
  <div class="tc2">
    <div class="card">
      <div class="st">Search by Plate Number</div>
      <div style="display:flex;gap:9px;margin-bottom:14px;"><input class="ins" id="eo-pl" placeholder="Enter Plate No e.g. ND 1234" style="flex:1;text-transform:uppercase;" oninput="this.value=this.value.toUpperCase()"/><button class="btn bo bsm" onclick="eoSearch()">Search</button></div>
      <div id="eo-found" style="display:none;">
        <div id="eo-empcard"></div>
        <div class="tb">
          <div class="trow"><span class="trl">Plate No</span><span class="trv" id="eo-plate">—</span></div>
          <div class="trow"><span class="trl">Vehicle Type</span><span class="trv" id="eo-vtype">—</span></div>
          <div class="trow"><span class="trl">Time In (Real-time)</span><span class="trv" id="eo-timein">—</span></div>
          <div class="trow"><span class="trl">Time Out (Real-time)</span><span class="trv lv" id="eo-live">—</span></div>
        </div>
        <div class="db" style="margin-top:12px;">
          <div class="dbl">Total Hours of Parking (Employee)</div>
          <div class="dbv"><span id="eo-dh">0</span><span class="dbu">h </span><span id="eo-dm">0</span><span class="dbu">m</span></div>
        </div>
        <div style="margin:12px 0;"><div class="fl" style="margin-bottom:4px;">Time and Date (Final Timestamp)</div><div style="font-family:'Barlow Condensed',sans-serif;font-size:20px;font-weight:800;color:var(--gd);" id="eo-ts">—</div></div>
        <div class="al2 ok" id="eo-ok"></div>
        <div class="al2 er" id="eo-er"></div>
        <div class="br3">
          <button class="btn bp" onclick="eoSubmit()"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12l5 5L20 7"/></svg>CONFIRM EXIT &amp; TIME OUT</button>
          <button class="btn bda" onclick="eoClear()">CANCEL</button>
        </div>
      </div>
      <div id="eo-empty" style="text-align:center;color:var(--g400);padding:40px 0;font-size:13px;">Enter plate number and click Search</div>
    </div>
    <div>
      <div class="card" style="margin-bottom:14px;">
        <div class="st">Employee Info &amp; ID Card</div>
        <div id="eo-idcard"><div style="text-align:center;color:var(--g400);padding:40px 0;font-size:13px;">Search a plate to see employee details</div></div>
      </div>
      <div class="card">
        <div class="st">Payment Display</div>
        <div id="eo-pay"><div style="text-align:center;color:var(--g400);padding:30px 0;font-size:13px;">Duration will appear here</div></div>
      </div>
    </div>
  </div>
</div>

<!-- RECEIPT & PAYMENT -->
<div class="page" id="page-receipt">
  <div class="ph"><div class="pt">Receipt &amp; Payment Display</div><div class="ps">₱30 first hour + ₱20 per succeeding hour · PWD/Senior: 20% discount</div></div>
  <div class="tc2">

    <!-- LEFT: Receipt Display -->
    <div>
      <div class="card">
        <div class="st">Search Ticket</div>
        <div style="display:flex;gap:9px;margin-bottom:6px;">
          <input class="ins" id="rtkt" placeholder="Enter Ticket No. e.g. TKT-20260418-XXXXX" style="flex:1;font-size:13px;" onkeypress="if(event.key==='Enter')loadReceipt()"/>
          <button class="btn bo bsm" onclick="loadReceipt()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
            Load
          </button>
        </div>
        <div class="al2 er" id="r-err"></div>
        <div id="rra"><div style="text-align:center;color:var(--g400);padding:38px 0;font-size:12px;">Enter ticket number to load receipt</div></div>
      </div>

      <!-- Discount Options (shows after ticket loaded) -->
      <div class="card" id="r-discount-card" style="margin-top:14px;display:none;">
        <div class="st">Discount Options</div>
        <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:10px;">
          <label style="display:flex;align-items:center;gap:7px;font-size:13px;cursor:pointer;padding:8px 14px;border:1.5px solid var(--g200);border-radius:8px;transition:.2s;" id="lbl-pwd">
            <input type="checkbox" id="r-pwd" onchange="updateReceipt()"/>
            <span style="font-weight:600;">PWD</span> <span style="color:var(--g400);font-size:11px;">(20% off)</span>
          </label>
          <label style="display:flex;align-items:center;gap:7px;font-size:13px;cursor:pointer;padding:8px 14px;border:1.5px solid var(--g200);border-radius:8px;transition:.2s;" id="lbl-sen">
            <input type="checkbox" id="r-senior" onchange="updateReceipt()"/>
            <span style="font-weight:600;">Senior Citizen</span> <span style="color:var(--g400);font-size:11px;">(20% off)</span>
          </label>
        </div>
        <div id="r-disc-id-wrap" style="display:none;">
          <label class="fl">Discount ID Number</label>
          <input class="ins" id="r-disc-id" placeholder="Enter PWD / Senior ID number" style="margin-top:5px;"/>
        </div>
      </div>
    </div>

    <!-- RIGHT: Payment Display -->
    <div>
      <div class="card">
        <div class="st">Payment Display</div>
        <div id="rpa"><div style="text-align:center;color:var(--g400);padding:38px 0;font-size:12px;">Load a ticket to see payment breakdown</div></div>
      </div>

      <!-- Payment Method (shows after ticket loaded) -->
      <div class="card" id="r-payment-card" style="margin-top:14px;display:none;">
        <div class="st">Payment Method</div>

        <!-- Cash / GCash Toggle -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px;">
          <div id="btn-cash" onclick="selectPayment('cash')" style="border:2px solid var(--g200);border-radius:10px;padding:16px;text-align:center;cursor:pointer;transition:.2s;">
            <div style="font-size:24px;margin-bottom:6px;">💵</div>
            <div style="font-family:'Barlow Condensed',sans-serif;font-size:16px;font-weight:800;color:var(--gd);">CASH</div>
            <div style="font-size:11px;color:var(--g400);">Physical payment</div>
          </div>
          <div id="btn-gcash" onclick="selectPayment('gcash')" style="border:2px solid var(--g200);border-radius:10px;padding:16px;text-align:center;cursor:pointer;transition:.2s;">
            <div style="font-size:24px;margin-bottom:6px;">📱</div>
            <div style="font-family:'Barlow Condensed',sans-serif;font-size:16px;font-weight:800;color:var(--bl);">GCASH</div>
            <div style="font-size:11px;color:var(--g400);">Mobile payment</div>
          </div>
        </div>

        <!-- Cash: Amount Tendered -->
        <div id="cash-section" style="display:none;">
          <div class="fg">
            <label class="fl">Amount Tendered (₱)</label>
            <input class="ins" type="number" id="r-tendered" placeholder="Enter amount received" oninput="calcChange()" style="font-size:18px;font-weight:700;text-align:center;"/>
          </div>
          <div id="change-display" style="display:none;background:var(--gl);border:1.5px solid var(--gmi);border-radius:10px;padding:14px;text-align:center;margin-bottom:12px;">
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--gmi);margin-bottom:4px;">Change</div>
            <div style="font-family:'Barlow Condensed',sans-serif;font-size:32px;font-weight:800;color:var(--gd);" id="r-change">₱0.00</div>
          </div>
        </div>

        <!-- GCash: Reference Number -->
        <div id="gcash-section" style="display:none;">
          <div style="background:var(--bll);border:1.5px solid var(--bl);border-radius:10px;padding:14px;margin-bottom:12px;text-align:center;">
            <div style="font-size:11px;font-weight:700;color:var(--bl);margin-bottom:4px;">FUMC GCASH NUMBER</div>
            <div style="font-family:'Barlow Condensed',sans-serif;font-size:28px;font-weight:800;color:var(--bl);">0917-XXX-XXXX</div>
            <div style="font-size:11px;color:var(--g400);margin-top:4px;">Ask admin for GCash number</div>
          </div>
          <div class="fg">
            <label class="fl">GCash Reference Number</label>
            <input class="ins" id="r-gcash-ref" placeholder="e.g. 1234567890" style="font-size:16px;text-align:center;font-weight:700;letter-spacing:2px;"/>
          </div>
        </div>

        <div class="al2 ok" id="r-pay-ok"></div>
        <div class="al2 er" id="r-pay-er"></div>

        <!-- Confirm Payment Button -->
        <button class="btn bp" id="btn-confirm-pay" onclick="confirmPayment()" style="display:none;margin-top:4px;font-size:17px;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12l5 5L20 7"/></svg>
          CONFIRM PAYMENT &amp; PRINT RECEIPT
        </button>
      </div>
    </div>
  </div>
</div>

<!-- INFO SHEET VEHICLES -->
<div class="page" id="page-vehicles">
  <div class="ph">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
      <div>
        <div class="pt">Info Sheet of Vehicle Entries</div>
        <div class="ps">Live parking status — auto-refreshes every 30 seconds</div>
      </div>
      <div style="display:flex;align-items:center;gap:10px;">
        <span id="vhs-status" style="font-size:11px;color:var(--gmi);font-weight:600;display:flex;align-items:center;gap:5px;">
          <span class="sd"></span> Live
        </span>
        <button class="btn bo bsm" onclick="loadVSheet()" style="font-size:11px;">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.5"/></svg>
          Refresh Now
        </button>
      </div>
    </div>
  </div>

  <!-- SUMMARY CARDS -->
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px;">
    <div style="background:var(--gd);border-radius:12px;padding:16px;text-align:center;color:white;">
      <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:rgba(255,255,255,.6);margin-bottom:6px;">Currently Parked</div>
      <div style="font-family:'Barlow Condensed',sans-serif;font-size:36px;font-weight:800;color:var(--ga);line-height:1;" id="vs-parked">—</div>
    </div>
    <div style="background:var(--gl);border:1.5px solid var(--gmi);border-radius:12px;padding:16px;text-align:center;">
      <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--gmi);margin-bottom:6px;">Employee Parked</div>
      <div style="font-family:'Barlow Condensed',sans-serif;font-size:36px;font-weight:800;color:var(--gd);line-height:1;" id="vs-emp">—</div>
    </div>
    <div style="background:var(--al);border:1.5px solid var(--am);border-radius:12px;padding:16px;text-align:center;">
      <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--am);margin-bottom:6px;">Visitor Parked</div>
      <div style="font-family:'Barlow Condensed',sans-serif;font-size:36px;font-weight:800;color:var(--am);line-height:1;" id="vs-vis">—</div>
    </div>
    <div style="background:var(--g200);border-radius:12px;padding:16px;text-align:center;">
      <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--g600);margin-bottom:6px;">Total Today</div>
      <div style="font-family:'Barlow Condensed',sans-serif;font-size:36px;font-weight:800;color:var(--g800);line-height:1;" id="vs-total">—</div>
    </div>
  </div>

  <!-- CURRENTLY PARKED TABLE -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">

    <!-- EMPLOYEE PARKED -->
    <div class="card">
      <div class="st" style="color:var(--gm);">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:4px;"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        Employee — Currently Parked
        <span class="badge bg2" id="cnt-emp-parked" style="margin-left:6px;">0</span>
      </div>
      <div style="overflow-x:auto;">
        <table class="tbl2">
          <thead><tr><th>Ticket</th><th>Plate</th><th>Employee</th><th>Time In</th><th>Duration</th></tr></thead>
          <tbody id="tb-emp-parked"><tr><td colspan="5" style="text-align:center;color:var(--g400);padding:20px;">Loading...</td></tr></tbody>
        </table>
      </div>
    </div>

    <!-- VISITOR PARKED -->
    <div class="card">
      <div class="st" style="color:var(--am);">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:4px;"><rect x="1" y="3" width="15" height="13" rx="2"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
        Non-Employee / Visitor — Currently Parked
        <span class="badge bam2" id="cnt-vis-parked" style="margin-left:6px;">0</span>
      </div>
      <div style="overflow-x:auto;">
        <table class="tbl2">
          <thead><tr><th>Ticket</th><th>Plate</th><th>Vehicle</th><th>Time In</th><th>Duration</th></tr></thead>
          <tbody id="tb-vis-parked"><tr><td colspan="5" style="text-align:center;color:var(--g400);padding:20px;">Loading...</td></tr></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ALL ENTRIES TABLE (last 30 days) -->
  <div class="card">
    <div class="st" style="display:flex;justify-content:space-between;align-items:center;">
      <span>All Entries — Last 30 Days</span>
      <span id="vhc" class="badge bg2">0 records</span>
    </div>
    <div style="overflow-x:auto;">
      <table class="tbl2">
        <thead><tr><th>Ticket No</th><th>Plate</th><th>Vehicle</th><th>Type</th><th>Employee</th><th>Time In</th><th>Time Out</th><th>Duration</th><th>Status</th></tr></thead>
        <tbody id="vhb"><tr><td colspan="9" style="text-align:center;color:var(--g400);padding:28px;">Loading...</td></tr></tbody>
      </table>
    </div>
  </div>
</div>

<!-- EXCEL REPORT -->
<div class="page" id="page-excel">
  <div class="ph"><div class="pt">Report of Vehicle Entries (Excel/CSV)</div><div class="ps">Generate and download daily parking reports</div></div>
  <div class="te2">
    <div class="card">
      <div class="st">All Entries Report</div>
      <div class="fg"><label class="fl">Select Date</label><input type="date" class="ins" id="exd"/></div>
      <div class="al2 ok" id="exok"></div><div class="al2 er" id="exer"></div>
      <button class="btn bp" onclick="genExcel()" style="margin-top:6px;"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>GENERATE &amp; DOWNLOAD</button>
    </div>
    <div class="card">
      <div class="st">Employee Entries Report</div>
      <div class="fg"><label class="fl">Select Date</label><input type="date" class="ins" id="exed"/></div>
      <div class="al2 ok" id="exeok"></div>
      <button class="btn bbl" onclick="genEmpExcel()" style="width:100%;margin-top:6px;font-size:15px;padding:13px;">EMPLOYEE REPORT</button>
      <button onclick="genGatePass(document.getElementById('exed').value)" style="width:100%;margin-top:10px;padding:13px;font-family:'Barlow Condensed',sans-serif;font-size:15px;font-weight:700;background:var(--gd);color:white;border:none;border-radius:var(--r);cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13" rx="2"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
        GENERATE GATE PASS LOG
      </button>
    </div>
  </div>
</div>

<!-- EMPLOYEE PARKING INFO -->
<div class="page" id="page-empinfo">
  <div class="ph"><div class="pt">Info of Employee Parking Entry</div><div class="ps">View employee parking records and history</div></div>
  <div class="card">
    <div style="display:flex;gap:9px;margin-bottom:14px;"><input class="ins" id="eis" placeholder="Search by name, ID, or department..." style="flex:1;"/><button class="btn bo bsm" onclick="searchEmpInfo()">Search</button></div>
    <div id="eil"><div style="text-align:center;color:var(--g400);padding:28px 0;font-size:12px;">Search an employee above</div></div>
  </div>
</div>

<!-- ADMIN PANEL -->
<div class="page" id="page-admin">
  <div class="ph"><div class="pt">Admin Panel</div><div class="ps">Employee account management and system settings</div></div>

  <!-- Row 1: Create Employee Info + Create Guard Login -->
  <div class="te2" style="margin-bottom:14px;">

    <!-- Create Employee Record (DB) -->
    <div class="card">
      <div class="st">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:middle;margin-right:4px;"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        Create Employee Account (DB Record)
      </div>
      <div class="fg"><label class="fl">Employee ID</label><input class="ins" id="aeid" placeholder="e.g. EMP-001"/></div>
      <div class="fg"><label class="fl">Full Name</label><input class="ins" id="anm" placeholder="Dr. Juan dela Cruz"/></div>
      <div class="te2">
        <div class="fg"><label class="fl">Department</label><input class="ins" id="adp" placeholder="Cardiology"/></div>
        <div class="fg"><label class="fl">Position</label><input class="ins" id="aps" placeholder="Doctor"/></div>
      </div>
      <div class="al2 ok" id="aeok"></div><div class="al2 er" id="aeer"></div>
      <button class="btn bp" onclick="createEmp()" style="margin-top:4px;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
        CREATE EMPLOYEE RECORD
      </button>
    </div>

    <!-- Create Employee Login Account -->
    <div class="card">
      <div class="st">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:middle;margin-right:4px;"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
        Create  Employee Login Password
      </div>
      <div style="background:var(--bll);border:1px solid var(--bl);border-radius:8px;padding:10px 12px;margin-bottom:14px;font-size:12px;color:var(--bl);">
        <strong>Note:</strong> This creates a system login account for guards or employees. After creation, they can log in using their username and password.
      </div>
      <div class="fg"><label class="fl">Full Name</label><input class="ins" id="gu-name" placeholder="e.g. Employee Juan dela Cruz"/></div>
      <div class="fg"><label class="fl">Username</label><input class="ins" id="gu-user" placeholder="e.g. emp_juan" autocomplete="off"/></div>
      <div class="te2">
        <div class="fg">
          <label class="fl">Password</label>
          <div style="position:relative;">
            <input class="ins" type="password" id="gu-pass" placeholder="Min 8 characters" style="padding-right:40px;"/>
            <span onclick="togglePw('gu-pass','gu-eye')" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);cursor:pointer;color:var(--g400);" id="gu-eye">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </span>
          </div>
        </div>
        <div class="fg">
          <label class="fl">Confirm Password</label>
          <div style="position:relative;">
            <input class="ins" type="password" id="gu-pass2" placeholder="Re-enter password" style="padding-right:40px;"/>
            <span onclick="togglePw('gu-pass2','gu-eye2')" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);cursor:pointer;color:var(--g400);" id="gu-eye2">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </span>
          </div>
        </div>
      </div>
      <div class="fg">
        <label class="fl">Role</label>
        <select class="fs2" id="gu-role">
          <option value="guard">Employee</option>
          <option value="admin">Admin</option>
        </select>
      </div>
      <div class="al2 ok" id="guok"></div>
      <div class="al2 er" id="guer"></div>
      <button class="btn bp" onclick="createGuard()" style="margin-top:4px;background:var(--bl);">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
        CREATE LOGIN ACCOUNT
      </button>
    </div>
  </div>

  <!-- Row 1.5: Employee Portal Account Creation -->
  <div style="margin-bottom:14px;">
    <div class="card">
      <div class="st" style="color:var(--gm);border-bottom-color:var(--gl);">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:middle;margin-right:4px;"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/><path d="M12 11v4M10 13h4"/></svg>
        🏥 Create Employee Portal Account
        <span style="background:var(--gl);color:var(--gmi);border-radius:6px;padding:2px 8px;font-size:10px;margin-left:8px;">For employee_portal.php login</span>
      </div>

      <!-- Mode Toggle -->
      <div style="display:flex;gap:8px;margin-bottom:16px;">
        <button id="em-mode-new" onclick="setEmpMode('new')" style="flex:1;padding:9px;border-radius:8px;border:2px solid var(--gm);background:var(--gm);color:white;font-family:'Barlow Condensed',sans-serif;font-size:13px;font-weight:700;cursor:pointer;">
          ✚ New Employee + Login Account
        </button>
        <button id="em-mode-link" onclick="setEmpMode('link')" style="flex:1;padding:9px;border-radius:8px;border:2px solid var(--g200);background:white;color:var(--g600);font-family:'Barlow Condensed',sans-serif;font-size:13px;font-weight:700;cursor:pointer;">
          🔗 Link Existing Employee
        </button>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;">

        <!-- LEFT: Employee Info (New mode only) -->
        <div id="em-new-fields">
          <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--gmi);margin-bottom:10px;">Employee Information</div>
          <div class="fg"><label class="fl">Employee ID No.</label><input class="ins" id="em-empno" placeholder="e.g. OLFU-1234 or 12345"/></div>
          <div class="fg"><label class="fl">Full Name</label><input class="ins" id="em-name" placeholder="Dr. Juan dela Cruz"/></div>
          <div class="fg"><label class="fl">Department</label><input class="ins" id="em-dept" placeholder="Cardiology"/></div>
          <div class="fg"><label class="fl">Position</label><input class="ins" id="em-pos" placeholder="Doctor"/></div>
        </div>

        <!-- LEFT (Link mode): Select existing employee -->
        <div id="em-link-fields" style="display:none;">
          <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--bl);margin-bottom:10px;">Select Employee to Link</div>
          <div class="fg">
            <label class="fl">Search Employee</label>
            <input class="ins" id="em-search" placeholder="Type name or ID..." oninput="searchEmpForLink()"/>
          </div>
          <div id="em-search-results" style="max-height:180px;overflow-y:auto;border:1px solid var(--g200);border-radius:8px;margin-bottom:10px;">
            <div style="text-align:center;color:var(--g400);padding:20px;font-size:12px;">Type to search employees</div>
          </div>
          <div id="em-selected-emp" style="display:none;background:var(--gl);border:1.5px solid var(--gmi);border-radius:8px;padding:10px 12px;">
            <div style="font-size:10px;font-weight:700;color:var(--gmi);margin-bottom:4px;">SELECTED EMPLOYEE</div>
            <div style="font-weight:700;" id="em-sel-name">—</div>
            <div style="font-size:12px;color:var(--g600);" id="em-sel-info">—</div>
            <input type="hidden" id="em-sel-id"/>
          </div>
        </div>

        <!-- MIDDLE: Login Credentials -->
        <div>
          <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--gmi);margin-bottom:10px;">Portal Login Credentials</div>
          <div style="background:var(--gl);border:1px solid var(--gmi);border-radius:8px;padding:10px 12px;margin-bottom:12px;font-size:12px;color:var(--gmi);">
            <strong>Portal URL:</strong> <a href="employee_portal.php" target="_blank" style="color:var(--bl);">localhost/fumc_parking/employee_portal.php</a>
          </div>
          <div class="fg"><label class="fl">Username</label><input class="ins" id="em-user" placeholder="e.g. juan_delacruz" autocomplete="off"/></div>
          <div class="fg">
            <label class="fl">Password</label>
            <div style="position:relative;">
              <input class="ins" type="password" id="em-pass" placeholder="Min 8 characters" style="padding-right:40px;"/>
              <span onclick="togglePw('em-pass')" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);cursor:pointer;color:var(--g400);">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              </span>
            </div>
          </div>
          <div class="fg">
            <label class="fl">Confirm Password</label>
            <div style="position:relative;">
              <input class="ins" type="password" id="em-pass2" placeholder="Re-enter password" style="padding-right:40px;"/>
              <span onclick="togglePw('em-pass2')" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);cursor:pointer;color:var(--g400);">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              </span>
            </div>
          </div>
        </div>

        <!-- RIGHT: Preview + Submit -->
        <div>
          <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--gmi);margin-bottom:10px;">Account Preview</div>
          <div id="em-preview" style="background:var(--g50);border:1.5px solid var(--g200);border-radius:10px;padding:14px;margin-bottom:12px;min-height:120px;">
            <div style="text-align:center;color:var(--g400);font-size:12px;padding:20px 0;">Fill in the fields to see preview</div>
          </div>
          <div class="al2 ok" id="em-ok"></div>
          <div class="al2 er" id="em-er"></div>
          <button class="btn bp" onclick="createEmpPortalAccount()" style="background:var(--gm);">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/><path d="M12 11v4M10 13h4"/></svg>
            CREATE EMPLOYEE PORTAL ACCOUNT
          </button>

          <!-- Employee Portal Accounts List -->
          <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--g200);">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
              <span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--g400);">Portal Accounts</span>
              <button class="btn bo bsm" onclick="loadEmpPortalAccounts()" style="font-size:11px;padding:5px 10px;">Refresh</button>
            </div>
            <div id="emp-portal-list">
              <div style="text-align:center;color:var(--g400);font-size:12px;padding:12px;">Click Refresh to load</div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>

  <!-- Row 2: Guard List + Reset Passwords -->
  <div class="te2" style="margin-bottom:14px;">

    <!-- Existing Guard Accounts -->
    <div class="card">
      <div class="st">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:4px;"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
        Guard / Employee Login Accounts
      </div>
      <button class="btn bo bsm" onclick="loadGuards()" style="margin-bottom:12px;">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.5"/></svg>
        Refresh List
      </button>
      <div id="guard-list">
        <div style="text-align:center;color:var(--g400);padding:20px 0;font-size:12px;">Click Refresh to load accounts</div>
      </div>
    </div>

    <!-- Reset Passwords -->
    <div>
      <!-- Reset Employee Password -->
      <div class="card" style="margin-bottom:13px;">
        <div class="st">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:4px;"><path d="M23 4v6h-6"/><path d="M1 20v-6h6"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
          Reset Employee / Guard Password
        </div>
        <div class="fg"><label class="fl">Username to Reset</label><input class="ins" id="aru" placeholder="Enter their username"/></div>
        <div class="fg">
          <label class="fl">New Password</label>
          <div style="position:relative;">
            <input class="ins" type="password" id="arp" placeholder="New password (min 8 chars)" style="padding-right:40px;"/>
            <span onclick="togglePw('arp','arp-eye')" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);cursor:pointer;color:var(--g400);" id="arp-eye">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </span>
          </div>
        </div>
        <div class="al2 ok" id="arok"></div><div class="al2 er" id="arer"></div>
        <button class="btn bam" onclick="resetGuardPass()" style="width:100%;margin-top:4px;">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 4v6h-6"/><path d="M1 20v-6h6"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
          RESET PASSWORD
        </button>
      </div>

      <!-- Reset Admin Password -->
      <div class="card">
        <div class="st">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:4px;"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93l-1.41 1.41M4.93 4.93l1.41 1.41M12 2v2M12 20v2M2 12h2M20 12h2"/></svg>
          Reset My Admin Password
        </div>
        <div class="fg"><label class="fl">Current Password</label><input class="ins" type="password" id="aop" placeholder="Current password"/></div>
        <div class="fg">
          <label class="fl">New Password</label>
          <div style="position:relative;">
            <input class="ins" type="password" id="anp" placeholder="New password (min 8 chars)" style="padding-right:40px;"/>
            <span onclick="togglePw('anp','anp-eye')" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);cursor:pointer;color:var(--g400);" id="anp-eye">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </span>
          </div>
        </div>
        <div class="al2 ok" id="aaok"></div><div class="al2 er" id="aaer"></div>
        <button class="btn bda" onclick="resetAdminPass()" style="width:100%;margin-top:4px;">RESET MY PASSWORD</button>
      </div>
    </div>
  </div>
</div>

</div><!-- /main -->

<div class="sb">
  <span><span class="sd"></span>System Connected</span>
  <span>LAN Operation</span>
  <span>FUMC IT Dept.</span>
  <span style="margin-left:auto;color:rgba(255,255,255,.4);">₱30 first hr · ₱20 succeeding · 20% PWD/Senior discount</span>
</div>

<!-- MODALS -->
<div class="mov" id="mtkt">
  <div class="mo" style="text-align:center;">
    <div class="mic"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#1d6b44" stroke-width="2.5"><path d="M5 12l5 5L20 7"/></svg></div>
    <div class="mot" style="text-align:center;">Ticket Generated!</div>
    <div style="background:var(--gl);border-radius:10px;padding:14px;margin:10px 0;">
      <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--gmi);margin-bottom:3px;">Ticket Number</div>
      <div style="font-family:'Barlow Condensed',sans-serif;font-size:28px;font-weight:800;color:var(--gd);letter-spacing:2px;" id="mtn">—</div>
    </div>
    <div style="font-size:13px;color:var(--g600);margin-bottom:14px;" id="mti">—</div>
    <button class="btn bp" onclick="cm('mtkt')">CLOSE</button>
  </div>
</div>

<div class="mov" id="mpay">
  <div class="mo">
    <div class="mot">Payment Summary</div>
    <div id="mpc"></div>
    <div class="br3">
      <button class="btn bp" onclick="cm('mpay')">CONFIRM PAID</button>
      <button class="btn bo" onclick="cm('mpay')">CLOSE</button>
    </div>
  </div>
</div>

<script>
const API   = 'http://localhost/fumc_parking/proxy.php?endpoint=';
const TOKEN = '<?= addslashes($token) ?>';

// ── LOGIN SUBMIT ANIMATION ──
function animateLogin(form) {
  const btn = document.getElementById('login-btn');
  if (!btn) return;
  btn.innerHTML = '<span class="sp"></span> SIGNING IN...';
  btn.style.background = 'var(--gd)';
  btn.disabled = true;
  btn.style.transform = 'scale(.98)';
  // Allow form to submit naturally after animation
  setTimeout(() => { btn.disabled = false; }, 4000);
}

// ── RIPPLE EFFECT ON ALL BUTTONS ──
document.addEventListener('click', function(e) {
  const btn = e.target.closest('.btn, .bln, .blog');
  if (!btn) return;
  const ripple = document.createElement('span');
  ripple.className = 'ripple';
  const size = Math.max(btn.offsetWidth, btn.offsetHeight);
  const rect = btn.getBoundingClientRect();
  ripple.style.cssText = `width:${size}px;height:${size}px;left:${e.clientX-rect.left-size/2}px;top:${e.clientY-rect.top-size/2}px;`;
  btn.appendChild(ripple);
  setTimeout(() => ripple.remove(), 600);
});

// ── ANIMATE MODAL OPEN ──
function sm(id) {
  const el = document.getElementById(id);
  el.classList.add('show');
  const mo = el.querySelector('.mo');
  if (mo) { mo.style.animation = 'none'; mo.offsetHeight; mo.style.animation = 'bounceIn .35s cubic-bezier(.34,1.56,.64,1)'; }
}

// Helper: convert API/endpoint?params to proxy format
function proxyUrl(path) {
  const [ep, ...rest] = path.split('?');
  const qs = rest.join('?');
  return API + ep + (qs ? '&' + qs : '');
}
let vLogId=null, voLogId=null, eLogId=null, eoLogId=null;
let vTI=null, voTI=null, eTI=null, eoTI=null;
let eLI=null, eoLI=null, voLI=null;
const R1=30, RS=20, DISC=0.20;

function calcAmt(mins,pwd,sen){
  const hrs=Math.max(1,Math.ceil(mins/60));
  const base=hrs<=1?R1:R1+(hrs-1)*RS;
  const dp=(pwd||sen)?DISC:0;
  const da=base*dp;
  return{hrs,base,dp,da,total:base-da};
}

function updateClock(){
  const n=new Date();
  const t=n.toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:true});
  const d=n.toLocaleDateString('en-PH',{weekday:'short',month:'short',day:'numeric',year:'numeric'});
  document.getElementById('ckt').textContent=t;
  document.getElementById('ckd').textContent=d;
  ['vtil','etil'].forEach(id=>{const el=document.getElementById(id);if(el)el.textContent=t;});
  const dl=document.getElementById('edl');if(dl)dl.textContent=n.toLocaleDateString('en-PH');
}
setInterval(updateClock,1000); updateClock();

document.addEventListener('DOMContentLoaded',()=>{
  const today=new Date().toISOString().split('T')[0];
  ['exd','exed'].forEach(id=>{const el=document.getElementById(id);if(el)el.value=today;});
  // Leave vhd empty so Info Sheet loads all records
  genTktPreview();
  loadVSheet();
});

function showPage(n){
  document.querySelectorAll('.page').forEach(p=>p.classList.remove('active'));
  document.querySelectorAll('.ni').forEach(x=>x.classList.remove('active'));
  document.getElementById('page-'+n).classList.add('active');
  document.getElementById('nav-'+n).classList.add('active');
  if(n==='vehicles')loadVSheet();
}
function cm(id){document.getElementById(id).classList.remove('show');}
function sm(id){document.getElementById(id).classList.add('show');}
function al(id,msg,type='ok'){
  const el=document.getElementById(id);
  if(!el)return;
  el.textContent=msg;el.className='al2 '+type;el.style.display='block';
  setTimeout(()=>el.style.display='none',5000);
}

function genTktPreview(){
  const d=new Date();const pad=n=>String(n).padStart(2,'0');
  const el=document.getElementById('vtp');
  if(el)el.textContent=`TKT-${d.getFullYear()}${pad(d.getMonth()+1)}${pad(d.getDate())}-XXXXX`;
}

// ── VISITOR TIME IN ──
async function submitVisitor(){
  const plate=document.getElementById('vpl').value.trim();
  if(!plate){al('ver','Please enter a plate number or MV file.','er');return;}
  try{
    const r=await fetch(proxyUrl(`vehicle-intake`),{method:'POST',headers:{'Content-Type':'application/json','Authorization':'Bearer '+TOKEN},body:JSON.stringify({license_plate:plate,vehicle_type:document.getElementById('vvt').value,entry_type:'visitor'})});
    const data=await r.json();
    if(data.success){
      document.getElementById('mtn').textContent=data.ticket_number;
      document.getElementById('mti').textContent=`${plate} — ${data.time_in}`;
      sm('mtkt');
      document.getElementById('vpl').value='';
      genTktPreview();
      if(data.dashboard){
        const db=data.dashboard;
        const tt=document.getElementById('total-today');
        if(tt)tt.textContent=`${db.total_entrys_today?.employee||0} Emp / ${db.total_entrys_today?.visitor||0} Vis`;
        const le=document.getElementById('last-entry');
        if(le&&db.last_employee_entry)le.textContent=`${db.last_employee_entry.name} — ${db.last_employee_entry.time_in}`;
      }
    }else{al('ver',data.message,'er');}
  }catch(e){al('ver','Server error: '+e.message,'er');}
}
function clearV(){document.getElementById('vpl').value='';}

// ── VISITOR TIME OUT ──
let voLIv=null;
async function voSearch(){
  const tk=document.getElementById('vo-tk').value.trim();if(!tk)return;
  try{
    let found=null;
    const today=new Date();
    for(let i=0;i<30;i++){
      const d=new Date(today);d.setDate(d.getDate()-i);
      const ds=d.toISOString().split('T')[0];
      const r=await fetch('http://localhost/fumc_parking/proxy.php?endpoint=vehicle-entries&status=parked&date='+ds,{credentials:'include'});
      const data=await r.json();
      const match=data.data?.find(d=>d.ticket_number===tk);
      if(match){found=match;break;}
    }
    if(!found){
      document.getElementById('vo-empty').innerHTML='<div style="color:var(--rd);font-size:13px;text-align:center;padding:20px;">Ticket not found or already exited.</div>';
      document.getElementById('vo-found').style.display='none';return;
    }
    voLogId=found.id; voTI=new Date(found.time_in);
    document.getElementById('vo-plate').textContent=found.license_plate;
    document.getElementById('vo-vtype').textContent=found.vehicle_type;
    document.getElementById('vo-timein').textContent=found.time_in;
    document.getElementById('vo-found').style.display='block';
    document.getElementById('vo-empty').style.display='none';
    if(voLIv)clearInterval(voLIv);
    voLIv=setInterval(()=>{
      const now=new Date();const diff=Math.floor((now-voTI)/1000);
      const h=Math.floor(diff/3600);const m=Math.floor((diff%3600)/60);
      document.getElementById('vo-live').textContent=now.toLocaleTimeString();
      document.getElementById('vo-dh').textContent=h;
      document.getElementById('vo-dm').textContent=m;
      voCalc();
    },1000);
    voCalc();
  }catch(e){}
}
function voCalc(){
  if(!voTI)return;
  const mins=Math.floor((new Date()-voTI)/60000);
  const pwd=document.getElementById('vo-pwd')?.checked;
  const sen=document.getElementById('vo-sen')?.checked;
  const c=calcAmt(mins,pwd,sen);
  document.getElementById('vo-pay').innerHTML=`
    <div class="pb"><div class="pbl">Amount Due</div><div class="pba">₱${c.total.toFixed(2)}</div>
    <div class="pbk">₱${R1} first hr + ₱${RS}×${Math.max(0,c.hrs-1)} succeeding${c.da>0?`<br><span style="color:var(--bl);">Discount (${(c.dp*100).toFixed(0)}%): −₱${c.da.toFixed(2)}</span>`:''}</div></div>
    <div class="rb"><div class="rt">✚ FUMC PARKING RECEIPT</div>
    <div class="rr"><span>Ticket No</span><span>${document.getElementById('vo-tk').value}</span></div>
    <div class="rr"><span>Plate No</span><span>${document.getElementById('vo-plate').textContent}</span></div>
    <div class="rr"><span>Time In</span><span>${document.getElementById('vo-timein').textContent}</span></div>
    <div class="rr"><span>Duration</span><span>${c.hrs} hr(s)</span></div>
    <div class="rr"><span>Base Amount</span><span>₱${c.base.toFixed(2)}</span></div>
    ${c.da>0?`<div class="rr"><span>Discount (${(c.dp*100).toFixed(0)}%)</span><span>−₱${c.da.toFixed(2)}</span></div>`:''}
    <div class="rtot"><span class="rtotl">TOTAL</span><span class="rtota">₱${c.total.toFixed(2)}</span></div></div>`;
}
async function voSubmit(){
  if(!voLogId)return;
  if(voLIv)clearInterval(voLIv);
  try{
    const r=await fetch(proxyUrl(`vehicle-exit`),{method:'POST',headers:{'Content-Type':'application/json','Authorization':'Bearer '+TOKEN},body:JSON.stringify({log_id:voLogId})});
    const data=await r.json();
    if(data.success){
      const pwd=document.getElementById('vo-pwd')?.checked;
      const sen=document.getElementById('vo-sen')?.checked;
      const mins=data.duration_hours*60+data.duration_minutes;
      const pay=calcAmt(mins,pwd,sen);
      document.getElementById('mpc').innerHTML=`
        <div class="pb"><div class="pbl">Amount Due</div><div class="pba">₱${pay.total.toFixed(2)}</div>
        <div class="pbk">₱${R1} first hr + ₱${RS}×${Math.max(0,pay.hrs-1)} succeeding${pay.da>0?`<br><span style="color:var(--bl);">Discount: −₱${pay.da.toFixed(2)}</span>`:''}</div></div>
        <div class="rb"><div class="rt">✚ FUMC PARKING RECEIPT</div>
        <div class="rr"><span>Duration</span><span>${data.duration_hours}h ${data.duration_minutes}m</span></div>
        <div class="rr"><span>Base Amount</span><span>₱${pay.base.toFixed(2)}</span></div>
        ${pay.da>0?`<div class="rr"><span>Discount (${(pay.dp*100).toFixed(0)}%)</span><span>−₱${pay.da.toFixed(2)}</span></div>`:''}
        <div class="rtot"><span class="rtotl">TOTAL</span><span class="rtota">₱${pay.total.toFixed(2)}</span></div></div>`;
      sm('mpay');al('vo-ok','Time out recorded!');voClear();
    }else{al('vo-er',data.message,'er');}
  }catch(e){al('vo-er','Server error.','er');}
}
function voClear(){
  if(voLIv)clearInterval(voLIv);voLogId=null;voTI=null;
  document.getElementById('vo-tk').value='';
  document.getElementById('vo-found').style.display='none';
  document.getElementById('vo-empty').style.display='block';
  document.getElementById('vo-empty').innerHTML='Enter ticket number and click Search';
  document.getElementById('vo-pay').innerHTML='<div style="text-align:center;color:var(--g400);padding:40px 0;font-size:13px;">Search a ticket to see payment</div>';
  if(document.getElementById('vo-pwd'))document.getElementById('vo-pwd').checked=false;
  if(document.getElementById('vo-sen'))document.getElementById('vo-sen').checked=false;
}

// ── EMPLOYEE TIME IN ──
async function submitEmployee(){
  const plate=document.getElementById('epl').value.trim();
  const empId=document.getElementById('eeid').value;
  if(!plate){al('eer','Please enter a plate number.','er');return;}
  if(!empId){al('eer','Please enter employee DB ID.','er');return;}
  try{
    const r=await fetch(proxyUrl(`vehicle-intake`),{method:'POST',headers:{'Content-Type':'application/json','Authorization':'Bearer '+TOKEN},body:JSON.stringify({license_plate:plate,vehicle_type:document.getElementById('evt').value,entry_type:'employee',employee_id:empId})});
    const data=await r.json();
    if(data.success){
      document.getElementById('mtn').textContent=data.ticket_number;
      document.getElementById('mti').textContent=`${plate} — Employee — ${data.time_in}`;
      sm('mtkt');document.getElementById('epl').value='';document.getElementById('eeid').value='';
    }else{al('eer',data.message,'er');}
  }catch(e){al('eer','Server error: '+e.message,'er');}
}
function clearE(){document.getElementById('epl').value='';document.getElementById('eeid').value='';}

async function searchEmpVehicle(){
  const plate=document.getElementById('esp').value.trim();if(!plate)return;
  try{
    const r=await fetch(proxyUrl(`vehicle-exit?license_plate=${encodeURIComponent(plate)}`),{headers:{'Authorization':'Bearer '+TOKEN}});
    const data=await r.json();const res=document.getElementById('esr');
    if(!data.success){res.innerHTML='<div style="color:var(--rd);font-size:13px;text-align:center;padding:18px;">Vehicle not found or already exited.</div>';return;}
    const d=data.data;eLogId=d.log_id;eTI=new Date(d.time_in);
    let eh='';
    if(d.employee){const e=d.employee;const i=e.name.split(' ').map(w=>w[0]).join('').toUpperCase().substring(0,2);eh=`<div class="ec"><div class="eav">${i}</div><div><div class="en">${e.name}</div><div class="ei">ID: ${e.emp_no} · ${e.department||'FUMC'}</div></div></div>`;}
    res.innerHTML=`${eh}<div class="tb"><div class="trow"><span class="trl">Vehicle</span><span class="trv">${d.license_plate} — ${d.vehicle_type}</span></div><div class="trow"><span class="trl">Time In</span><span class="trv">${d.time_in}</span></div></div>`;
  }catch(e){}
}

// ── EMPLOYEE TIME OUT ──
async function eoSearch(){
  const plate=document.getElementById('eo-pl').value.trim();if(!plate)return;
  try{
    const r=await fetch(proxyUrl(`vehicle-exit?license_plate=${encodeURIComponent(plate)}`),{headers:{'Authorization':'Bearer '+TOKEN}});
    const data=await r.json();
    if(!data.success){
      document.getElementById('eo-empty').innerHTML='<div style="color:var(--rd);font-size:13px;text-align:center;padding:20px;">Vehicle not found or already exited.</div>';
      document.getElementById('eo-found').style.display='none';
      document.getElementById('eo-idcard').innerHTML='<div style="text-align:center;color:var(--g400);padding:40px 0;font-size:13px;">No employee found.</div>';
      return;
    }
    const d=data.data;eoLogId=d.log_id;eoTI=new Date(d.time_in);
    document.getElementById('eo-plate').textContent=d.license_plate;
    document.getElementById('eo-vtype').textContent=d.vehicle_type;
    document.getElementById('eo-timein').textContent=d.time_in;
    document.getElementById('eo-found').style.display='block';
    document.getElementById('eo-empty').style.display='none';
    if(d.employee){
      const e=d.employee;const i=e.name.split(' ').map(w=>w[0]).join('').toUpperCase().substring(0,2);
      document.getElementById('eo-empcard').innerHTML=`<div class="ec"><div class="eav">${i}</div><div><div class="en">${e.name}</div><div class="ei">ID: ${e.emp_no} · ${e.department||'FUMC'}</div></div></div>`;
      document.getElementById('eo-idcard').innerHTML=`<div style="background:var(--gd);border-radius:12px;padding:22px;color:white;"><div style="font-family:'Barlow Condensed',sans-serif;font-size:15px;font-weight:800;letter-spacing:2px;color:var(--ga);margin-bottom:14px;">✚ FUMC</div><div style="width:60px;height:60px;border-radius:50%;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:800;margin-bottom:12px;">${i}</div><div style="font-size:12px;color:rgba(255,255,255,.6);margin-bottom:3px;">NAME</div><div style="font-size:16px;font-weight:700;margin-bottom:10px;">${e.name}</div><div style="font-size:12px;color:rgba(255,255,255,.6);margin-bottom:3px;">ID NUMBER</div><div style="font-size:15px;font-weight:600;margin-bottom:10px;">${e.emp_no}</div><div style="font-size:12px;color:rgba(255,255,255,.6);margin-bottom:3px;">DEPARTMENT</div><div style="font-size:14px;font-weight:500;">${e.department||'FUMC'}</div></div>`;
    }
    if(eoLI)clearInterval(eoLI);
    eoLI=setInterval(()=>{
      if(!eoTI)return;const now=new Date();const diff=Math.floor((now-eoTI)/1000);
      const h=Math.floor(diff/3600);const m=Math.floor((diff%3600)/60);
      document.getElementById('eo-live').textContent=now.toLocaleTimeString();
      document.getElementById('eo-dh').textContent=h;document.getElementById('eo-dm').textContent=m;
      document.getElementById('eo-ts').textContent=now.toLocaleDateString('en-PH')+' '+now.toLocaleTimeString();
      const pay=calcAmt(h*60+m,false,false);
      document.getElementById('eo-pay').innerHTML=`<div class="pb"><div class="pbl">Parking Fee (Employee)</div><div class="pba">₱${pay.total.toFixed(2)}</div><div class="pbk">₱${R1} first hr + ₱${RS}×${Math.max(0,pay.hrs-1)} succeeding</div></div>`;
    },1000);
  }catch(e){}
}
async function eoSubmit(){
  if(!eoLogId)return;if(eoLI)clearInterval(eoLI);
  try{
    const r=await fetch(proxyUrl(`vehicle-exit`),{method:'POST',headers:{'Content-Type':'application/json','Authorization':'Bearer '+TOKEN},body:JSON.stringify({log_id:eoLogId})});
    const data=await r.json();
    if(data.success){
      const mins=data.duration_hours*60+data.duration_minutes;const pay=calcAmt(mins,false,false);
      document.getElementById('mpc').innerHTML=`<div class="pb"><div class="pbl">Employee Parking Fee</div><div class="pba">₱${pay.total.toFixed(2)}</div><div class="pbk">${pay.hrs} hour(s)</div></div><div class="rb"><div class="rt">✚ FUMC EMPLOYEE PARKING</div><div class="rr"><span>Plate No</span><span>${document.getElementById('eo-plate').textContent}</span></div><div class="rr"><span>Duration</span><span>${data.duration_hours}h ${data.duration_minutes}m</span></div><div class="rr"><span>Total Amount</span><span>₱${pay.total.toFixed(2)}</span></div><div class="rtot"><span class="rtotl">TOTAL</span><span class="rtota">₱${pay.total.toFixed(2)}</span></div></div>`;
      sm('mpay');al('eo-ok','Employee exit confirmed!');eoClear();
    }else{al('eo-er',data.message,'er');}
  }catch(e){al('eo-er','Server error.','er');}
}
function eoClear(){
  if(eoLI)clearInterval(eoLI);eoLogId=null;eoTI=null;
  document.getElementById('eo-pl').value='';
  document.getElementById('eo-found').style.display='none';
  document.getElementById('eo-empty').style.display='block';
  document.getElementById('eo-empty').innerHTML='Enter plate number and click Search';
  document.getElementById('eo-idcard').innerHTML='<div style="text-align:center;color:var(--g400);padding:40px 0;font-size:13px;">Search a plate to see employee details</div>';
  document.getElementById('eo-pay').innerHTML='<div style="text-align:center;color:var(--g400);padding:30px 0;font-size:13px;">Duration will appear here</div>';
  if(document.getElementById('eo-empcard'))document.getElementById('eo-empcard').innerHTML='';
}

// ── RECEIPT & PAYMENT ──
let rCurrentRecord = null;
let rPaymentMethod = null;
let rTotalDue      = 0;

async function loadReceipt(){
  const tk=document.getElementById('rtkt').value.trim();
  if(!tk){al('r-err','Please enter a ticket number.','er');return;}
  try{
    // Search across multiple dates — try today first, then last 30 days
    const dates=[];
    for(let i=0;i<30;i++){
      const d=new Date();d.setDate(d.getDate()-i);
      dates.push(d.toISOString().split('T')[0]);
    }
    let found=null;
    for(const date of dates){
      const r=await fetch('http://localhost/fumc_parking/proxy.php?endpoint=vehicle-entries&status=all&date='+date,{credentials:'include'});
      const data=await r.json();
      const match=data.data?.find(d=>d.ticket_number===tk);
      if(match){found=match;break;}
    }

    if(!found){
      al('r-err','Ticket not found. Please check the ticket number.','er');
      document.getElementById('rra').innerHTML='<div style="text-align:center;color:var(--g400);padding:38px 0;font-size:12px;">Enter ticket number to load receipt</div>';
      document.getElementById('r-discount-card').style.display='none';
      document.getElementById('r-payment-card').style.display='none';
      document.getElementById('rpa').innerHTML='<div style="text-align:center;color:var(--g400);padding:38px 0;font-size:12px;">Load a ticket to see payment breakdown</div>';
      return;
    }

    rCurrentRecord = found;
    document.getElementById('r-pwd').checked=false;
    document.getElementById('r-senior').checked=false;
    document.getElementById('r-disc-id-wrap').style.display='none';
    document.getElementById('r-discount-card').style.display='block';
    document.getElementById('r-payment-card').style.display='block';
    rPaymentMethod=null;
    document.getElementById('cash-section').style.display='none';
    document.getElementById('gcash-section').style.display='none';
    document.getElementById('btn-confirm-pay').style.display='none';
    document.getElementById('btn-cash').style.border='2px solid var(--g200)';
    document.getElementById('btn-gcash').style.border='2px solid var(--g200)';
    updateReceipt();
  }catch(e){al('r-err','Server error.','er');}
}

function updateReceipt(){
  if(!rCurrentRecord)return;
  const found=rCurrentRecord;
  const pwd   =document.getElementById('r-pwd').checked;
  const senior=document.getElementById('r-senior').checked;
  const mins  =found.duration_minutes||Math.floor((new Date()-new Date(found.time_in))/60000);
  const pay   =calcAmt(mins,pwd,senior);
  rTotalDue   =pay.total;

  // Show/hide discount ID field
  document.getElementById('r-disc-id-wrap').style.display=(pwd||senior)?'block':'none';

  // Receipt display
  const statusBadge = found.status==='parked'
    ? '<span style="color:var(--am);font-weight:700;">STILL PARKED</span>'
    : '<span style="color:var(--gmi);font-weight:700;">EXITED</span>';

  document.getElementById('rra').innerHTML=`
    <div style="border:2px dashed var(--g200);border-radius:10px;padding:20px;background:white;">
      <div style="font-family:'Barlow Condensed',sans-serif;font-size:18px;font-weight:800;color:var(--gd);text-align:center;margin-bottom:14px;letter-spacing:1px;">✚ FUMC PARKING RECEIPT</div>
      <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--g100);font-size:13px;"><span style="color:var(--g600);">Ticket No</span><span style="font-weight:600;">${found.ticket_number}</span></div>
      <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--g100);font-size:13px;"><span style="color:var(--g600);">Plate No</span><span style="font-weight:600;">${found.license_plate}</span></div>
      <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--g100);font-size:13px;"><span style="color:var(--g600);">Vehicle Type</span><span>${found.vehicle_type||'—'}</span></div>
      <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--g100);font-size:13px;"><span style="color:var(--g600);">Entry Type</span><span>${found.entry_type}</span></div>
      ${found.employee_name?`<div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--g100);font-size:13px;"><span style="color:var(--g600);">Employee</span><span style="font-weight:600;">${found.employee_name}</span></div>`:''}
      <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--g100);font-size:13px;"><span style="color:var(--g600);">Time In</span><span>${found.time_in||'—'}</span></div>
      <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--g100);font-size:13px;"><span style="color:var(--g600);">Time Out</span><span>${found.time_out||'Still Parked'}</span></div>
      <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--g100);font-size:13px;"><span style="color:var(--g600);">Total Hours</span><span><strong>${pay.hrs}</strong> hr(s)</span></div>
      <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--g100);font-size:13px;"><span style="color:var(--g600);">Status</span><span>${statusBadge}</span></div>
      ${pay.da>0?`<div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--g100);font-size:13px;"><span style="color:var(--g600);">Discount (${(pay.dp*100).toFixed(0)}%)</span><span style="color:var(--bl);font-weight:600;">−₱${pay.da.toFixed(2)}</span></div>`:''}
      <div style="background:var(--gd);color:white;border-radius:8px;padding:12px 16px;margin-top:10px;display:flex;justify-content:space-between;align-items:center;">
        <span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;">TOTAL AMOUNT</span>
        <span style="font-family:'Barlow Condensed',sans-serif;font-size:28px;font-weight:800;color:var(--ga);">₱${pay.total.toFixed(2)}</span>
      </div>
    </div>`;

  // Payment display
  document.getElementById('rpa').innerHTML=`
    <div style="background:var(--al);border:2px solid var(--am);border-radius:10px;padding:18px;margin-bottom:12px;">
      <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--am);margin-bottom:6px;">Amount Due</div>
      <div style="font-family:'Barlow Condensed',sans-serif;font-size:48px;font-weight:800;color:var(--am);line-height:1;">₱${pay.total.toFixed(2)}</div>
      <div style="font-size:12px;color:var(--g600);margin-top:6px;">
        ₱${R1} first hour + ₱${RS} × ${Math.max(0,pay.hrs-1)} succeeding hour(s)
        ${pay.da>0?`<br><span style="color:var(--bl);">Discount (${(pay.dp*100).toFixed(0)}%): −₱${pay.da.toFixed(2)}</span>`:''}
      </div>
    </div>
    ${(pwd||senior)?`
    <div style="background:var(--bll);border:1px solid var(--bl);border-radius:8px;padding:10px 14px;font-size:12px;color:var(--bl);font-weight:600;">
      ✓ ${pwd?'PWD':'Senior Citizen'} Discount Applied (20%)<br/>
      Original: ₱${pay.base.toFixed(2)} → You Pay: ₱${pay.total.toFixed(2)}
    </div>`:''}`;

  calcChange();
}

function selectPayment(method){
  rPaymentMethod=method;
  document.getElementById('btn-cash').style.cssText  = method==='cash'  ? 'border:2px solid var(--gm);background:var(--gl);border-radius:10px;padding:16px;text-align:center;cursor:pointer;transition:.2s;' : 'border:2px solid var(--g200);border-radius:10px;padding:16px;text-align:center;cursor:pointer;transition:.2s;';
  document.getElementById('btn-gcash').style.cssText = method==='gcash' ? 'border:2px solid var(--bl);background:var(--bll);border-radius:10px;padding:16px;text-align:center;cursor:pointer;transition:.2s;' : 'border:2px solid var(--g200);border-radius:10px;padding:16px;text-align:center;cursor:pointer;transition:.2s;';
  document.getElementById('cash-section').style.display  = method==='cash'  ? 'block' : 'none';
  document.getElementById('gcash-section').style.display = method==='gcash' ? 'block' : 'none';
  document.getElementById('btn-confirm-pay').style.display='block';
  if(method==='gcash'){
    document.getElementById('btn-confirm-pay').style.background='var(--bl)';
    document.getElementById('btn-confirm-pay').innerHTML='<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12l5 5L20 7"/></svg> CONFIRM GCASH &amp; PRINT RECEIPT';
  } else {
    document.getElementById('btn-confirm-pay').style.background='var(--gm)';
    document.getElementById('btn-confirm-pay').innerHTML='<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12l5 5L20 7"/></svg> CONFIRM CASH &amp; PRINT RECEIPT';
  }
  calcChange();
}

function calcChange(){
  if(rPaymentMethod!=='cash')return;
  const tendered=parseFloat(document.getElementById('r-tendered').value)||0;
  const change=tendered-rTotalDue;
  const cd=document.getElementById('change-display');
  const cr=document.getElementById('r-change');
  if(tendered>0){
    cd.style.display='block';
    if(change>=0){
      cr.textContent='₱'+change.toFixed(2);
      cd.style.background='var(--gl)'; cd.style.borderColor='var(--gmi)';
      cr.style.color='var(--gd)';
    } else {
      cr.textContent='−₱'+Math.abs(change).toFixed(2)+' (insufficient)';
      cd.style.background='var(--rl)'; cd.style.borderColor='var(--rd)';
      cr.style.color='var(--rd)';
    }
  } else { cd.style.display='none'; }
}

function confirmPayment(){
  if(!rCurrentRecord){al('r-pay-er','No ticket loaded.','er');return;}
  if(!rPaymentMethod){al('r-pay-er','Please select a payment method (Cash or GCash).','er');return;}

  const pwd    =document.getElementById('r-pwd').checked;
  const senior =document.getElementById('r-senior').checked;
  const discId =document.getElementById('r-disc-id').value.trim();
  const mins   =rCurrentRecord.duration_minutes||Math.floor((new Date()-new Date(rCurrentRecord.time_in))/60000);
  const pay    =calcAmt(mins,pwd,senior);

  if(rPaymentMethod==='cash'){
    const tendered=parseFloat(document.getElementById('r-tendered').value)||0;
    if(tendered<pay.total){al('r-pay-er',`Insufficient amount. Need ₱${pay.total.toFixed(2)}, received ₱${tendered.toFixed(2)}.`,'er');return;}
  }
  if(rPaymentMethod==='gcash'){
    const ref=document.getElementById('r-gcash-ref').value.trim();
    if(!ref){al('r-pay-er','Please enter the GCash reference number.','er');return;}
  }

  const tendered = rPaymentMethod==='cash' ? parseFloat(document.getElementById('r-tendered').value)||0 : pay.total;
  const change   = rPaymentMethod==='cash' ? tendered - pay.total : 0;
  const gcashRef = rPaymentMethod==='gcash' ? document.getElementById('r-gcash-ref').value.trim() : '—';

  // Print receipt
  printReceipt(rCurrentRecord, pay, rPaymentMethod, tendered, change, gcashRef, (pwd||senior)?discId:'');
  al('r-pay-ok',`✓ Payment confirmed via ${rPaymentMethod.toUpperCase()}! Receipt printed.`);
}

function printReceipt(rec, pay, method, tendered, change, gcashRef, discId){
  const w=window.open('','_blank','width=400,height=620');
  const date=new Date().toLocaleString('en-PH');
  w.document.write(`<!DOCTYPE html><html><head>
  <title>FUMC Parking Receipt</title>
  <style>
    *{margin:0;padding:0;box-sizing:border-box;}
    body{font-family:Arial,sans-serif;font-size:12px;color:#1e2d27;width:320px;margin:0 auto;padding:16px;}
    .hdr{text-align:center;border-bottom:2px solid #1a4731;padding-bottom:10px;margin-bottom:10px;}
    .hdr h1{font-size:14px;font-weight:900;color:#1a4731;letter-spacing:1px;}
    .hdr p{font-size:10px;color:#666;margin-top:2px;}
    .tkt{text-align:center;background:#e8f5ee;border:1px solid #2d8a5a;border-radius:6px;padding:8px;margin-bottom:10px;}
    .tkt-label{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#2d8a5a;}
    .tkt-no{font-size:20px;font-weight:900;color:#1a4731;letter-spacing:2px;}
    .row{display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid #f0f4f2;font-size:11px;}
    .row .lbl{color:#666;}
    .row .val{font-weight:600;text-align:right;}
    .total-box{background:#1a4731;color:white;border-radius:6px;padding:10px;margin:10px 0;display:flex;justify-content:space-between;align-items:center;}
    .total-box .t-lbl{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;}
    .total-box .t-val{font-size:22px;font-weight:900;color:#34c770;}
    .pay-method{text-align:center;margin:8px 0;padding:8px;border-radius:6px;}
    .pay-method.cash{background:#e8f5ee;border:1px solid #2d8a5a;color:#1a4731;}
    .pay-method.gcash{background:#e8f0fb;border:1px solid #1a5fa8;color:#1a5fa8;}
    .pay-method .pm-label{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;}
    .pay-method .pm-val{font-size:16px;font-weight:900;}
    .footer{text-align:center;font-size:9px;color:#999;margin-top:12px;padding-top:8px;border-top:1px dashed #dde6e1;}
    @media print{body{width:80mm;}}
  </style></head><body>
  <div class="hdr">
    <h1>✚ FUMC PARKING</h1>
    <p>Fatima University Medical Center</p>
    <p style="font-size:9px;margin-top:4px;">${date}</p>
  </div>
  <div class="tkt">
    <div class="tkt-label">Ticket Number</div>
    <div class="tkt-no">${rec.ticket_number}</div>
  </div>
  <div class="row"><span class="lbl">Plate No</span><span class="val">${rec.license_plate}</span></div>
  <div class="row"><span class="lbl">Vehicle Type</span><span class="val">${rec.vehicle_type||'—'}</span></div>
  <div class="row"><span class="lbl">Entry Type</span><span class="val">${rec.entry_type?.toUpperCase()}</span></div>
  ${rec.employee_name?`<div class="row"><span class="lbl">Employee</span><span class="val">${rec.employee_name}</span></div>`:''}
  <div class="row"><span class="lbl">Time In</span><span class="val">${rec.time_in||'—'}</span></div>
  <div class="row"><span class="lbl">Time Out</span><span class="val">${rec.time_out||'Still Parked'}</span></div>
  <div class="row"><span class="lbl">Total Hours</span><span class="val">${pay.hrs} hr(s)</span></div>
  <div class="row"><span class="lbl">Base Fee</span><span class="val">₱${pay.base.toFixed(2)}</span></div>
  ${pay.da>0?`<div class="row"><span class="lbl">Discount (${(pay.dp*100).toFixed(0)}%)</span><span class="val" style="color:#1a5fa8;">−₱${pay.da.toFixed(2)}</span></div>`:''}
  ${discId?`<div class="row"><span class="lbl">Discount ID</span><span class="val">${discId}</span></div>`:''}
  <div class="total-box">
    <span class="t-lbl">Total Amount</span>
    <span class="t-val">₱${pay.total.toFixed(2)}</span>
  </div>
  <div class="pay-method ${method}">
    <div class="pm-label">Payment Method</div>
    <div class="pm-val">${method==='cash'?'💵 CASH':'📱 GCASH'}</div>
  </div>
  ${method==='cash'?`
  <div class="row"><span class="lbl">Amount Tendered</span><span class="val">₱${tendered.toFixed(2)}</span></div>
  <div class="row"><span class="lbl" style="color:#1a4731;font-weight:700;">Change</span><span class="val" style="color:#1a4731;font-size:14px;font-weight:900;">₱${change.toFixed(2)}</span></div>
  `:`<div class="row"><span class="lbl">GCash Ref No.</span><span class="val" style="color:#1a5fa8;">${gcashRef}</span></div>`}
  <div class="footer">
    <p>Thank you for parking at FUMC!</p>
    <p>Please drive safely.</p>
    <p style="margin-top:4px;">FUMC IT Dept. · Parking System v2.0</p>
  </div>
  <script>window.onload=function(){window.print();}<\/script>
  </body></html>`);
  w.document.close();
}

// ── VEHICLE SHEET — REAL-TIME DASHBOARD ──
let vSheetTimer = null;

function getLiveMinutes(timeIn) {
  return Math.floor((new Date() - new Date(timeIn)) / 60000);
}
function fmtDur(mins) {
  if(mins < 0) mins = 0;
  return Math.floor(mins/60)+'h '+mins%60+'m';
}

async function loadVSheet(){
  const today = new Date().toISOString().split('T')[0];
  const tb    = document.getElementById('vhb');
  const vc    = document.getElementById('vhc');

  try {
    // Single call for parked (no date filter needed - API returns ALL parked)
    const rParked = await fetch('http://localhost/fumc_parking/proxy.php?endpoint=vehicle-entries&status=parked', {credentials:'include'});
    const parkedData = await rParked.json();
    const parkedAll = parkedData.data || [];

    // Fetch last 30 days for history
    const allHistory = [];
    const base = new Date();
    for (let i = 0; i < 30; i++) {
      const d = new Date(base); d.setDate(d.getDate() - i);
      const ds = d.toISOString().split('T')[0];
      const r = await fetch('http://localhost/fumc_parking/proxy.php?endpoint=vehicle-entries&date='+ds+'&status=all', {credentials:'include'});
      const data = await r.json();
      if (data.data?.length) allHistory.push(...data.data);
    }

    // Deduplicate history
    const seen = new Set();
    const unique = allHistory.filter(r => { if(seen.has(r.id)) return false; seen.add(r.id); return true; });
    unique.sort((a,b) => new Date(b.time_in) - new Date(a.time_in));

    // Split parked by type
    const empParked = parkedAll.filter(r => r.entry_type === 'employee');
    const visParked = parkedAll.filter(r => r.entry_type !== 'employee');
    const todayAll  = unique.filter(r => r.time_in?.startsWith(today));

    // Summary cards
    document.getElementById('vs-parked').textContent = parkedAll.length;
    document.getElementById('vs-emp').textContent    = empParked.length;
    document.getElementById('vs-vis').textContent    = visParked.length;
    document.getElementById('vs-total').textContent  = todayAll.length;

    // Employee parked table
    const cep = document.getElementById('cnt-emp-parked');
    const tep = document.getElementById('tb-emp-parked');
    if (cep) cep.textContent = empParked.length;
    if (tep) tep.innerHTML = empParked.length ? empParked.map(r => {
      const mins = getLiveMinutes(r.time_in);
      return `<tr>
        <td><span class="badge bg2" style="font-size:9px;">${r.ticket_number||'—'}</span></td>
        <td><strong>${r.license_plate}</strong></td>
        <td style="font-size:12px;">${r.employee_name||'—'}</td>
        <td style="font-size:11px;">${r.time_in||'—'}</td>
        <td style="font-weight:700;color:var(--gmi);">${fmtDur(mins)}</td>
      </tr>`;
    }).join('') : '<tr><td colspan="5" style="text-align:center;color:var(--g400);padding:18px;">No employees currently parked</td></tr>';

    // Visitor parked table
    const cvp = document.getElementById('cnt-vis-parked');
    const tvp = document.getElementById('tb-vis-parked');
    if (cvp) cvp.textContent = visParked.length;
    if (tvp) tvp.innerHTML = visParked.length ? visParked.map(r => {
      const mins = getLiveMinutes(r.time_in);
      return `<tr>
        <td><span class="badge bam2" style="font-size:9px;">${r.ticket_number||'—'}</span></td>
        <td><strong>${r.license_plate}</strong></td>
        <td>${r.vehicle_type||'—'}</td>
        <td style="font-size:11px;">${r.time_in||'—'}</td>
        <td style="font-weight:700;color:var(--am);">${fmtDur(mins)}</td>
      </tr>`;
    }).join('') : '<tr><td colspan="5" style="text-align:center;color:var(--g400);padding:18px;">No visitors currently parked</td></tr>';

    // All entries history table
    if (vc) vc.textContent = unique.length + ' records';
    if (tb) tb.innerHTML = unique.length ? unique.map(r => `
      <tr>
        <td><span class="badge ${r.entry_type==='employee'?'bg2':'bam2'}" style="font-size:9px;">${r.ticket_number||'—'}</span></td>
        <td><strong>${r.license_plate}</strong></td>
        <td>${r.vehicle_type||'—'}</td>
        <td><span class="badge ${r.entry_type==='employee'?'bbl2':'bam2'}">${r.entry_type}</span></td>
        <td style="font-size:12px;">${r.employee_name||'—'}</td>
        <td style="font-size:11px;">${r.time_in||'—'}</td>
        <td style="font-size:11px;">${r.time_out||'—'}</td>
        <td>${r.duration_minutes?fmtDur(r.duration_minutes):'—'}</td>
        <td><span class="badge ${r.status==='parked'?'bg2':r.status==='exited'?'bgr':'brd'}">${r.status}</span></td>
      </tr>`).join('') :
      '<tr><td colspan="9" style="text-align:center;color:var(--g400);padding:28px;">No records found.</td></tr>';

  } catch(e) {
    console.error('loadVSheet:', e);
    if (tb) tb.innerHTML = '<tr><td colspan="9" style="text-align:center;color:var(--rd);padding:28px;">Error loading records.</td></tr>';
  }

  // Auto-refresh every 30 seconds only when page is active
  if (vSheetTimer) clearTimeout(vSheetTimer);
  vSheetTimer = setTimeout(() => {
    const page = document.getElementById('page-vehicles');
    if (page?.classList.contains('active')) loadVSheet();
  }, 30000);
}

// ── EXCEL ──
async function genExcel(){
  const date=document.getElementById('exd').value;
  try{
    const r=await fetch('http://localhost/fumc_parking/generate_report.php?date='+date,{credentials:'include'});
    const data=await r.json();
    if(data.success){
      al('exok','Report generated! '+data.record_count+' records.');
      window.open('http://localhost/fumc_parking/download.php?file='+data.filename,'_blank');
    }else{al('exer',data.message,'er');}
  }catch(e){al('exer','Server error.','er');}
}

async function genEmpExcel(){
  const date=document.getElementById('exed').value;
  try{
    const r=await fetch('http://localhost/fumc_parking/generate_employee_report.php?date='+date,{credentials:'include'});
    const data=await r.json();
    if(data.success){
      al('exeok','Employee Report: '+data.record_count+' records!');
      window.open('http://localhost/fumc_parking/download.php?file='+data.filename,'_blank');
    }else{al('exeok',data.message);}
  }catch(e){alert('Server error.');}
}

async function genGatePass(date){
  const d=date||new Date().toISOString().split('T')[0];
  try{
    const r=await fetch('http://localhost/fumc_parking/generate_gate_pass.php?date='+d,{credentials:'include'});
    const data=await r.json();
    if(data.success){
      al('exok','Gate Pass Log: '+data.record_count+' exits recorded.');
      window.open('http://localhost/fumc_parking/download.php?file='+data.filename,'_blank');
    }else{alert(data.message);}
  }catch(e){alert('Server error.');}
}

// ── EMP INFO ──
async function searchEmpInfo(){
  const q=document.getElementById('eis').value.trim();if(!q)return;
  try{
    const r=await fetch(proxyUrl(`employees?search=${encodeURIComponent(q)}&is_active=all`),{headers:{'Authorization':'Bearer '+TOKEN}});
    const data=await r.json();const el=document.getElementById('eil');
    if(!data.data?.length){el.innerHTML='<div style="color:var(--g400);font-size:13px;text-align:center;padding:18px;">No employees found.</div>';return;}
    el.innerHTML=data.data.map(e=>{const i=e.full_name.split(' ').map(w=>w[0]).join('').toUpperCase().substring(0,2);return`<div style="display:flex;align-items:center;gap:12px;padding:13px 0;border-bottom:1px solid var(--g200);"><div style="width:42px;height:42px;border-radius:50%;background:var(--gl);display:flex;align-items:center;justify-content:center;font-family:'Barlow Condensed',sans-serif;font-weight:800;font-size:15px;color:var(--gd);flex-shrink:0;">${i}</div><div style="flex:1;"><div style="font-weight:700;font-size:14px;">${e.full_name}</div><div style="font-size:11px;color:var(--g400);">ID: ${e.employee_id} · ${e.department||'—'} · ${e.position||'—'}</div></div><span class="badge ${e.is_active?'bg2':'bgr'}">${e.is_active?'Active':'Inactive'}</span></div>`;}).join('');
  }catch(e){}
}

// ── ADMIN ──
async function createEmp(){
  const body={employee_id:document.getElementById('aeid').value.trim(),full_name:document.getElementById('anm').value.trim(),department:document.getElementById('adp').value.trim(),position:document.getElementById('aps').value.trim()};
  if(!body.employee_id||!body.full_name){al('aeer','Employee ID and full name required.','er');return;}
  try{
    const r=await fetch(proxyUrl(`employees`),{method:'POST',headers:{'Content-Type':'application/json','Authorization':'Bearer '+TOKEN},body:JSON.stringify(body)});
    const data=await r.json();
    if(data.success){al('aeok','Employee account created successfully!');['aeid','anm','adp','aps'].forEach(id=>document.getElementById(id).value='');}
    else{al('aeer',data.message,'er');}
  }catch(e){al('aeer','Server error.','er');}
}
async function resetAdminPass(){
  const op=document.getElementById('aop').value;const np=document.getElementById('anp').value;
  if(!op||!np){al('aaer','Both fields required.','er');return;}
  if(np.length<8){al('aaer','Min 8 characters required.','er');return;}
  try{
    const r=await fetch(proxyUrl(`change-password`),{method:'POST',headers:{'Content-Type':'application/json','Authorization':'Bearer '+TOKEN},body:JSON.stringify({old_password:op,new_password:np})});
    const data=await r.json();
    if(data.success){al('aaok','Password changed successfully!');document.getElementById('aop').value='';document.getElementById('anp').value='';}
    else{al('aaer',data.message,'er');}
  }catch(e){al('aaer','Server error.','er');}
}

// ── EMPLOYEE PORTAL ACCOUNT CREATION ──
let empMode = 'new';
let empSelectedId = null;

function setEmpMode(mode) {
  empMode = mode;
  document.getElementById('em-mode-new').style.cssText = mode==='new'
    ? 'flex:1;padding:9px;border-radius:8px;border:2px solid var(--gm);background:var(--gm);color:white;font-family:\'Barlow Condensed\',sans-serif;font-size:13px;font-weight:700;cursor:pointer;'
    : 'flex:1;padding:9px;border-radius:8px;border:2px solid var(--g200);background:white;color:var(--g600);font-family:\'Barlow Condensed\',sans-serif;font-size:13px;font-weight:700;cursor:pointer;';
  document.getElementById('em-mode-link').style.cssText = mode==='link'
    ? 'flex:1;padding:9px;border-radius:8px;border:2px solid var(--bl);background:var(--bl);color:white;font-family:\'Barlow Condensed\',sans-serif;font-size:13px;font-weight:700;cursor:pointer;'
    : 'flex:1;padding:9px;border-radius:8px;border:2px solid var(--g200);background:white;color:var(--g600);font-family:\'Barlow Condensed\',sans-serif;font-size:13px;font-weight:700;cursor:pointer;';
  document.getElementById('em-new-fields').style.display  = mode==='new'  ? 'block' : 'none';
  document.getElementById('em-link-fields').style.display = mode==='link' ? 'block' : 'none';
  empSelectedId = null;
}

async function searchEmpForLink() {
  const q = document.getElementById('em-search').value.trim();
  const el = document.getElementById('em-search-results');
  if (!q) { el.innerHTML = '<div style="text-align:center;color:var(--g400);padding:20px;font-size:12px;">Type to search employees</div>'; return; }
  try {
    const r = await fetch(proxyUrl(`employees?search=${encodeURIComponent(q)}&is_active=all`), { headers: {'Authorization':'Bearer '+TOKEN} });
    const data = await r.json();
    if (!data.data?.length) { el.innerHTML = '<div style="color:var(--g400);padding:12px;font-size:12px;text-align:center;">No employees found.</div>'; return; }
    el.innerHTML = data.data.map(e => `
      <div onclick="selectEmpForLink(${e.id},'${e.full_name.replace(/'/g,"\\'")}','${e.employee_id}','${(e.department||'').replace(/'/g,"\\'")}','${(e.position||'').replace(/'/g,"\\'")}') "
           style="padding:10px 12px;cursor:pointer;border-bottom:1px solid var(--g100);font-size:13px;display:flex;justify-content:space-between;align-items:center;"
           onmouseover="this.style.background='var(--g50)'" onmouseout="this.style.background='white'">
        <div>
          <div style="font-weight:700;">${e.full_name}</div>
          <div style="font-size:11px;color:var(--g400);">ID: ${e.employee_id} · ${e.department||'—'}</div>
        </div>
        <span style="background:var(--gl);color:var(--gmi);border-radius:6px;padding:2px 8px;font-size:10px;font-weight:700;">SELECT</span>
      </div>`).join('');
  } catch(e) {}
}

function selectEmpForLink(id, name, empNo, dept, pos) {
  empSelectedId = id;
  document.getElementById('em-sel-id').value = id;
  document.getElementById('em-sel-name').textContent = name;
  document.getElementById('em-sel-info').textContent = `ID: ${empNo} · ${dept} · ${pos}`;
  document.getElementById('em-selected-emp').style.display = 'block';
  document.getElementById('em-search-results').innerHTML = '';
  document.getElementById('em-search').value = name;
  updateEmpPreview();
}

// Live preview
['em-empno','em-name','em-dept','em-pos','em-user','em-pass'].forEach(id => {
  const el = document.getElementById(id);
  if (el) el.addEventListener('input', updateEmpPreview);
});

function updateEmpPreview() {
  const name = empMode==='new'
    ? document.getElementById('em-name').value.trim()
    : document.getElementById('em-sel-name')?.textContent || '—';
  const empno = empMode==='new'
    ? document.getElementById('em-empno').value.trim()
    : document.getElementById('em-sel-info')?.textContent?.split('·')[0]?.replace('ID:','').trim() || '—';
  const user = document.getElementById('em-user').value.trim();
  const pass = document.getElementById('em-pass').value;
  const dept = empMode==='new' ? document.getElementById('em-dept').value.trim() : '';
  const pos  = empMode==='new' ? document.getElementById('em-pos').value.trim()  : '';

  if (!name && !user) {
    document.getElementById('em-preview').innerHTML = '<div style="text-align:center;color:var(--g400);font-size:12px;padding:20px 0;">Fill in the fields to see preview</div>';
    return;
  }

  const init = (name||'??').split(' ').map(w=>w[0]).join('').toUpperCase().substring(0,2);
  const strength = pass.length >= 12 ? '🟢 Strong' : pass.length >= 8 ? '🟡 Good' : pass.length > 0 ? '🔴 Too short' : '';
  document.getElementById('em-preview').innerHTML = `
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
      <div style="width:42px;height:42px;border-radius:50%;background:var(--gm);display:flex;align-items:center;justify-content:center;font-family:'Barlow Condensed',sans-serif;font-weight:800;font-size:16px;color:white;flex-shrink:0;">${init}</div>
      <div>
        <div style="font-weight:700;font-size:13px;">${name||'—'}</div>
        <div style="font-size:11px;color:var(--g400);">ID: ${empno||'—'}</div>
      </div>
    </div>
    <div style="font-size:11px;display:flex;flex-direction:column;gap:5px;">
      ${dept?`<div><span style="color:var(--g400);">Dept: </span>${dept}</div>`:''}
      ${pos?`<div><span style="color:var(--g400);">Position: </span>${pos}</div>`:''}
      <div><span style="color:var(--g400);">Username: </span><strong style="color:var(--bl);">@${user||'—'}</strong></div>
      <div><span style="color:var(--g400);">Password strength: </span>${strength||'—'}</div>
      <div style="margin-top:6px;background:var(--bll);border-radius:6px;padding:6px 10px;color:var(--bl);font-size:11px;">
        🌐 Login at: <strong>employee_portal.php</strong>
      </div>
    </div>`;
}

async function createEmpPortalAccount() {
  const user  = document.getElementById('em-user').value.trim();
  const pass  = document.getElementById('em-pass').value;
  const pass2 = document.getElementById('em-pass2').value;

  if (!user) { al('em-er','Username is required.','er'); return; }
  if (pass.length < 8) { al('em-er','Password must be at least 8 characters.','er'); return; }
  if (pass !== pass2) { al('em-er','Passwords do not match.','er'); return; }
  if (!/^[a-zA-Z0-9_]+$/.test(user)) { al('em-er','Username: letters, numbers, underscore only.','er'); return; }

  let body = { mode: empMode, username: user, password: pass };

  if (empMode === 'new') {
    const empno = document.getElementById('em-empno').value.trim();
    const name  = document.getElementById('em-name').value.trim();
    const dept  = document.getElementById('em-dept').value.trim();
    const pos   = document.getElementById('em-pos').value.trim();
    if (!empno || !name) { al('em-er','Employee ID and Full Name are required.','er'); return; }
    body = { ...body, emp_no: empno, full_name: name, department: dept, position: pos };
  } else {
    if (!empSelectedId) { al('em-er','Please select an employee to link.','er'); return; }
    body.employee_id = empSelectedId;
  }

  try {
    const r = await fetch('http://localhost/fumc_parking/create_emp_login.php', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + TOKEN },
      body: JSON.stringify(body)
    });
    const data = await r.json();
    if (data.success) {
      al('em-ok', `✓ Employee portal account created! Username: "@${user}" — they can now login at employee_portal.php`);
      ['em-empno','em-name','em-dept','em-pos','em-user','em-pass','em-pass2','em-search'].forEach(id => {
        const el = document.getElementById(id); if (el) el.value = '';
      });
      document.getElementById('em-selected-emp').style.display = 'none';
      empSelectedId = null;
      document.getElementById('em-preview').innerHTML = '<div style="text-align:center;color:var(--g400);font-size:12px;padding:20px 0;">Fill in the fields to see preview</div>';
      loadEmpPortalAccounts();
    } else { al('em-er', data.message, 'er'); }
  } catch(e) { al('em-er', 'Server error: ' + e.message, 'er'); }
}

async function loadEmpPortalAccounts() {
  try {
    const r = await fetch('http://localhost/fumc_parking/list_emp_logins.php', {
      credentials: 'include',
      headers: { 'Authorization': 'Bearer ' + TOKEN }
    });
    const data = await r.json();
    const el = document.getElementById('emp-portal-list');
    if (!data.data?.length) {
      el.innerHTML = '<div style="color:var(--g400);font-size:12px;text-align:center;padding:12px;">No employee portal accounts yet.</div>';
      return;
    }
    el.innerHTML = data.data.map(e => `
      <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--g100);">
        <div>
          <div style="font-weight:700;font-size:12px;">${e.full_name}</div>
          <div style="font-size:11px;color:var(--g400);">@${e.username} · ${e.employee_id}</div>
        </div>
        <span class="badge ${e.is_active?'bg2':'bgr'}">${e.is_active?'Active':'Inactive'}</span>
      </div>`).join('');
  } catch(e) {
    document.getElementById('emp-portal-list').innerHTML = '<div style="color:var(--rd);font-size:12px;text-align:center;padding:12px;">Error loading accounts.</div>';
  }
}

function togglePw(inputId){
  const inp=document.getElementById(inputId);
  inp.type=inp.type==='password'?'text':'password';
}

async function createGuard(){
  const name  = document.getElementById('gu-name').value.trim();
  const user  = document.getElementById('gu-user').value.trim().toLowerCase();
  const pass  = document.getElementById('gu-pass').value;
  const pass2 = document.getElementById('gu-pass2').value;
  const role  = document.getElementById('gu-role').value;

  if(!name||!user||!pass){al('guer','All fields are required.','er');return;}
  if(pass.length<8){al('guer','Password must be at least 8 characters.','er');return;}
  if(pass!==pass2){al('guer','Passwords do not match. Please re-enter.','er');return;}
  if(!/^[a-zA-Z0-9_]+$/.test(user)){al('guer','Username: letters, numbers, underscore only.','er');return;}

  try{
    const r=await fetch(proxyUrl(`create-guard`),{
      method:'POST',
      headers:{'Content-Type':'application/json','Authorization':'Bearer '+TOKEN},
      body:JSON.stringify({full_name:name,username:user,password:pass,role})
    });
    const data=await r.json();
    if(data.success){
      al('guok',`✓ Login account created! Username: "${user}" | Role: ${role.toUpperCase()}`);
      ['gu-name','gu-user','gu-pass','gu-pass2'].forEach(id=>document.getElementById(id).value='');
      loadGuards();
    }else{al('guer',data.message,'er');}
  }catch(e){al('guer','Server error: '+e.message,'er');}
}

async function loadGuards(){
  try{
    const r=await fetch(proxyUrl(`list-guards`),{headers:{'Authorization':'Bearer '+TOKEN}});
    const data=await r.json();
    const el=document.getElementById('guard-list');
    if(!data.data?.length){el.innerHTML='<div style="color:var(--g400);font-size:13px;text-align:center;padding:18px;">No accounts found.</div>';return;}
    el.innerHTML=`<table style="width:100%;border-collapse:collapse;font-size:12px;">
      <thead><tr style="background:var(--g100);">
        <th style="padding:7px 10px;text-align:left;color:var(--g400);font-size:10px;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid var(--g200);">Full Name</th>
        <th style="padding:7px 10px;text-align:left;color:var(--g400);font-size:10px;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid var(--g200);">Username</th>
        <th style="padding:7px 10px;text-align:center;color:var(--g400);font-size:10px;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid var(--g200);">Role</th>
        <th style="padding:7px 10px;text-align:center;color:var(--g400);font-size:10px;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid var(--g200);">Status</th>
        <th style="padding:7px 10px;text-align:center;color:var(--g400);font-size:10px;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid var(--g200);">Action</th>
      </tr></thead>
      <tbody>${data.data.map(g=>{
        const init=g.full_name.split(' ').map(w=>w[0]).join('').toUpperCase().substring(0,2);
        const roleColor=g.role==='superadmin'?'brd':g.role==='admin'?'bbl2':'bg2';
        const statusColor=g.is_active?'bg2':'bgr';
        return `<tr style="border-bottom:1px solid var(--g100);">
          <td style="padding:8px 10px;">
            <div style="display:flex;align-items:center;gap:8px;">
              <div style="width:30px;height:30px;border-radius:50%;background:var(--gl);display:flex;align-items:center;justify-content:center;font-family:'Barlow Condensed',sans-serif;font-weight:800;font-size:11px;color:var(--gd);flex-shrink:0;">${init}</div>
              <span style="font-weight:600;font-size:13px;">${g.full_name}</span>
            </div>
          </td>
          <td style="padding:8px 10px;color:var(--g600);font-family:monospace;">@${g.username}</td>
          <td style="padding:8px 10px;text-align:center;"><span class="badge ${roleColor}">${g.role}</span></td>
          <td style="padding:8px 10px;text-align:center;"><span class="badge ${statusColor}">${g.is_active?'Active':'Inactive'}</span></td>
          <td style="padding:8px 10px;text-align:center;">
            <button onclick="quickSetUser('${g.username}')" style="background:var(--am);color:white;border:none;padding:5px 10px;border-radius:5px;font-size:11px;cursor:pointer;font-weight:700;">Reset PW</button>
          </td>
        </tr>`;
      }).join('')}</tbody>
    </table>`;
  }catch(e){document.getElementById('guard-list').innerHTML='<div style="color:var(--rd);font-size:13px;text-align:center;padding:18px;">Error loading accounts.</div>';}
}

function quickSetUser(username){
  document.getElementById('aru').value=username;
  document.getElementById('arp').focus();
  const el=document.getElementById('aru');
  el.style.borderColor='var(--gmi)';
  setTimeout(()=>el.style.borderColor='',2000);
}

async function resetGuardPass(){
  const user=document.getElementById('aru').value.trim();
  const pass=document.getElementById('arp').value;
  if(!user||!pass){al('arer','Username and new password are required.','er');return;}
  if(pass.length<8){al('arer','Password must be at least 8 characters.','er');return;}
  try{
    const r=await fetch(proxyUrl(`reset-guard-password`),{
      method:'POST',
      headers:{'Content-Type':'application/json','Authorization':'Bearer '+TOKEN},
      body:JSON.stringify({username:user,new_password:pass})
    });
    const data=await r.json();
    if(data.success){
      al('arok',`✓ Password for "@${user}" has been reset successfully!`);
      document.getElementById('aru').value='';
      document.getElementById('arp').value='';
    }else{al('arer',data.message,'er');}
  }catch(e){al('arer','Server error.','er');}
}
</script>

<?php endif; ?>
</body>
</html>
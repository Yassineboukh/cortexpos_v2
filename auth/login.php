<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();

// Already logged in → redirect
if (!empty($_SESSION['user'])) {
    $role = $_SESSION['user']['role'];
    redirect(APP_URL.'/'.$role.'/dashboard.php');
}

$error = '';
$msg   = $_GET['msg'] ?? '';
$msgMap = [
    'session'      => 'Your session expired. Please sign in again.',
    'unauthorized' => 'You do not have access to that page.',
    'logout'       => 'You have been signed out.',
];
$info = $msgMap[$msg] ?? '';

// ── Handle POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role     = trim($_POST['role']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');
    $otc      = trim($_POST['otc']      ?? '');
    $ip       = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // Validate role
    if (!in_array($role, ['admin','cashier','client'])) {
        $error = 'Invalid role selected.';
    // Rate limit
    } elseif (!rateLimit($ip)) {
        $error = 'Too many failed attempts. Please wait 15 minutes.';
    // Validate email format
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $pdo = db();

        // Fetch user
        if ($role === 'client') {
            $stmt = $pdo->prepare("SELECT id,name,email,password_hash,is_active FROM clients WHERE email=? LIMIT 1");
        } else {
            $stmt = $pdo->prepare("SELECT id,name,email,password_hash,role,is_active FROM users WHERE email=? AND role=? LIMIT 1");
        }

        if ($role === 'client') $stmt->execute([$email]);
        else                    $stmt->execute([$email,$role]);
        $user = $stmt->fetch();

        if (!$user) {
            auditLog('login_fail',$role,$email,null,'User not found');
            $error = 'Incorrect email or password.';
        } elseif (!$user['is_active']) {
            auditLog('login_fail',$role,$email,$user['id'],'Account disabled');
            $error = 'This account has been disabled.';
        } elseif (!password_verify($password,$user['password_hash'])) {
            auditLog('login_fail',$role,$email,$user['id'],'Wrong password');
            $error = 'Incorrect email or password.';
        } else {
            // Admin: verify OTC
            if ($role === 'admin' && !empty($otc)) {
                $clean  = strtoupper(str_replace([' ','-'],'',$otc));
                $prefix = substr($clean,0,4);
                $rows   = $pdo->prepare("SELECT id,code_hash FROM otc_codes WHERE code_prefix=? AND role='admin' AND is_used=0 AND expires_at>NOW() LIMIT 10");
                $rows->execute([$prefix]);
                $valid = null;
                foreach ($rows->fetchAll() as $row) {
                    if (password_verify($clean,$row['code_hash'])) { $valid=$row; break; }
                }
                if (!$valid) {
                    auditLog('otc_fail','admin',$email,$user['id'],'Invalid OTC on login');
                    $error = 'Invalid or expired authorization code.';
                } else {
                    $pdo->prepare("UPDATE otc_codes SET is_used=1,used_at=NOW(),used_by_email=? WHERE id=? AND is_used=0")
                        ->execute([$email,$valid['id']]);
                    auditLog('otc_used','admin',$email,$user['id'],'OTC #'.$valid['id'].' consumed on login');
                }
            }

            // Login success
            if (!$error) {
                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id'    => $user['id'],
                    'name'  => $user['name'],
                    'email' => $user['email'],
                    'role'  => $role,
                ];

                // Update last_login
                $tbl = ($role==='client') ? 'clients' : 'users';
                $pdo->prepare("UPDATE {$tbl} SET last_login=NOW() WHERE id=?")->execute([$user['id']]);

                // Open shift for cashier
                if ($role==='cashier') {
                    $pdo->prepare("INSERT INTO shifts (cashier_id) VALUES (?)")->execute([$user['id']]);
                }

                auditLog('login_success',$role,$email,$user['id']);

                $dest = match($role) {
                    'admin'   => APP_URL.'/admin/dashboard.php',
                    'cashier' => APP_URL.'/cashier/dashboard.php',
                    default   => APP_URL.'/client/home.php',
                };
                redirect($dest);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>CortexPOS — Sign In</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400;500;600&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
:root{--espresso:#0d0905;--dark-roast:#1a100a;--caramel:#c07a2f;--gold:#d4922a;
  --gold-light:#e8b96a;--cream:#f5e6c8;--latte:#d4a96a;--text-dim:#8a6a4a;--text-mid:#c4a06a;
  --border:rgba(192,122,47,.18);--border-act:rgba(212,146,42,.55)}
html,body{height:100%;background:var(--espresso);color:var(--cream);font-family:'DM Sans',sans-serif;overflow:hidden}
body::before{content:'';position:fixed;inset:0;pointer-events:none;
  background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='4'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.04'/%3E%3C/svg%3E");opacity:.35}
body::after{content:'';position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);
  width:700px;height:700px;background:radial-gradient(ellipse,rgba(192,122,47,.1) 0%,transparent 70%);pointer-events:none}

/* ── Splash ── */
#splash{position:fixed;inset:0;z-index:100;display:flex;flex-direction:column;
  align-items:center;justify-content:center;background:var(--espresso);
  transition:opacity .9s ease,visibility .9s ease}
#splash.hide{opacity:0;visibility:hidden}
.splash-logo{width:200px;height:200px;object-fit:contain;opacity:0;transform:scale(.7);
  animation:logoIn 1.1s cubic-bezier(.16,1,.3,1) .3s forwards}
.splash-glow{position:absolute;width:300px;height:300px;border-radius:50%;
  background:radial-gradient(ellipse,rgba(212,146,42,.25) 0%,transparent 70%);
  opacity:0;animation:glowPulse 2s ease .6s forwards}
.splash-brand{margin-top:24px;font-family:'Cormorant Garamond',serif;font-size:2.5rem;
  font-weight:300;letter-spacing:.18em;opacity:0;animation:fadeUp .9s ease 1s forwards}
.splash-brand em{color:var(--gold);font-style:normal;font-weight:500}
.splash-tag{margin-top:6px;font-size:.68rem;letter-spacing:.38em;text-transform:uppercase;
  color:var(--text-mid);opacity:0;animation:fadeUp .9s ease 1.3s forwards}
.splash-bar-wrap{margin-top:44px;width:160px;height:2px;background:rgba(192,122,47,.15);
  border-radius:2px;overflow:hidden;opacity:0;animation:fadeUp .5s ease 1.5s forwards}
.splash-bar{height:100%;background:linear-gradient(90deg,var(--caramel),var(--gold-light));
  width:0;animation:loadBar 1.6s cubic-bezier(.4,0,.2,1) 1.7s forwards}
@keyframes logoIn{to{opacity:1;transform:scale(1)}}
@keyframes glowPulse{0%{opacity:0;transform:scale(.6)}60%{opacity:1;transform:scale(1.1)}100%{opacity:.6;transform:scale(1)}}
@keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
@keyframes loadBar{to{width:100%}}

/* ── App ── */
#app{position:relative;z-index:1;min-height:100vh;display:flex;opacity:0;transition:opacity .8s ease}
#app.show{opacity:1}
.panel-left{width:46%;display:flex;flex-direction:column;align-items:center;
  justify-content:center;padding:60px;border-right:1px solid var(--border);position:relative}
.panel-left::before{content:'';position:absolute;inset:0;
  background:radial-gradient(ellipse at 40% 50%,rgba(192,122,47,.06) 0%,transparent 65%);pointer-events:none}
.corner-logo{position:absolute;top:26px;left:26px;width:42px;height:42px;object-fit:contain;opacity:.85}
.left-logo{width:170px;height:170px;object-fit:contain;
  filter:drop-shadow(0 0 40px rgba(212,146,42,.3));animation:float 5s ease-in-out infinite}
@keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}
.left-wordmark{margin-top:22px;text-align:center}
.left-wordmark h1{font-family:'Cormorant Garamond',serif;font-size:2.6rem;font-weight:400;
  letter-spacing:.1em;color:var(--cream);line-height:1}
.left-wordmark h1 em{color:var(--gold);font-style:normal;font-weight:600}
.left-wordmark p{margin-top:8px;font-size:.63rem;letter-spacing:.4em;text-transform:uppercase;color:var(--text-dim)}
.left-divider{width:48px;height:1px;background:linear-gradient(90deg,transparent,var(--caramel),transparent);margin:28px auto}
.left-features{display:flex;flex-direction:column;gap:12px;width:100%;max-width:260px}
.feat{display:flex;align-items:center;gap:12px;opacity:.75}
.feat-dot{width:5px;height:5px;border-radius:50%;background:var(--gold);flex-shrink:0;box-shadow:0 0 8px rgba(212,146,42,.6)}
.feat span{font-size:.77rem;font-weight:300;letter-spacing:.04em;color:var(--text-mid)}

.panel-right{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:60px 50px}
.form-wrap{width:100%;max-width:370px}

/* Role tabs */
.role-lbl{font-size:.6rem;letter-spacing:.35em;text-transform:uppercase;color:var(--text-dim);
  display:block;margin-bottom:10px}
.role-tabs{display:flex;gap:7px;margin-bottom:32px}
.rtab{flex:1;padding:10px 0;border:1px solid var(--border);background:transparent;
  border-radius:8px;color:var(--text-dim);font-family:'DM Sans',sans-serif;
  font-size:.76rem;cursor:pointer;transition:all .22s ease;text-align:center}
.rtab:hover{border-color:rgba(192,122,47,.5);color:var(--latte)}
.rtab.on{border-color:var(--gold);background:rgba(212,146,42,.08);color:var(--gold-light);font-weight:500}
.rtab-icon{display:block;font-size:1.1rem;margin-bottom:3px}

/* Form */
.form-heading{font-family:'Cormorant Garamond',serif;font-size:2rem;font-weight:400;
  color:var(--cream);margin-bottom:4px}
.form-sub{font-size:.74rem;color:var(--text-dim);margin-bottom:28px}
.field{position:relative;margin-bottom:16px}
.field label{display:block;font-size:.61rem;letter-spacing:.28em;text-transform:uppercase;
  color:var(--text-dim);margin-bottom:6px}
.field input{width:100%;padding:12px 15px;background:rgba(255,255,255,.03);
  border:1px solid var(--border);border-radius:9px;color:var(--cream);
  font-family:'DM Sans',sans-serif;font-size:.87rem;outline:none;transition:all .22s}
.field input::placeholder{color:rgba(192,122,47,.3)}
.field input:focus{border-color:var(--border-act);background:rgba(192,122,47,.05);
  box-shadow:0 0 0 3px rgba(212,146,42,.08)}
.otc-badge{display:inline-flex;align-items:center;gap:6px;font-size:.59rem;
  letter-spacing:.2em;text-transform:uppercase;color:var(--gold);
  background:rgba(212,146,42,.1);border:1px solid rgba(212,146,42,.25);
  border-radius:20px;padding:3px 10px;margin-bottom:8px}
.otc-field{display:none}
.otc-field.show{display:block}
.pass-row{display:flex;justify-content:flex-end;margin-bottom:24px}
.pass-row a{font-size:.71rem;color:var(--text-dim);text-decoration:none}
.pass-row a:hover{color:var(--latte)}
.pass-toggle{position:absolute;right:13px;bottom:13px;background:none;border:none;
  color:var(--text-dim);cursor:pointer;font-size:.73rem;transition:.2s}
.pass-toggle:hover{color:var(--latte)}
.submit-btn{width:100%;padding:13px;background:linear-gradient(135deg,var(--caramel),var(--gold));
  border:none;border-radius:9px;color:var(--espresso);font-family:'DM Sans',sans-serif;
  font-size:.81rem;font-weight:500;letter-spacing:.2em;text-transform:uppercase;cursor:pointer;transition:all .26s}
.submit-btn:hover{transform:translateY(-1px);box-shadow:0 8px 28px rgba(192,122,47,.4)}
.error-box{margin-top:12px;padding:10px 14px;background:rgba(184,64,48,.1);
  border:1px solid rgba(184,64,48,.3);border-radius:8px;font-size:.75rem;color:#e8907e}
.info-box{margin-bottom:16px;padding:10px 14px;background:rgba(58,122,184,.1);
  border:1px solid rgba(58,122,184,.3);border-radius:8px;font-size:.75rem;color:#7eb8e8}
.form-footer{margin-top:28px;text-align:center;font-size:.63rem;
  letter-spacing:.15em;color:rgba(192,122,47,.3);text-transform:uppercase}

/* Steam decoration */
.steam{position:fixed;top:0;right:0;width:180px;height:180px;pointer-events:none;opacity:.14;z-index:0}
.steam path{stroke:var(--gold);stroke-width:1.5;fill:none;stroke-dasharray:60;stroke-dashoffset:60}
.steam path:nth-child(1){animation:steam 3s ease-in-out infinite}
.steam path:nth-child(2){animation:steam 3s ease-in-out .8s infinite;opacity:.7}
.steam path:nth-child(3){animation:steam 3s ease-in-out 1.6s infinite;opacity:.4}
@keyframes steam{0%{stroke-dashoffset:60;opacity:0}30%{opacity:1}100%{stroke-dashoffset:-60;opacity:0}}
</style>
</head>
<body>

<!-- Steam decoration -->
<svg class="steam" viewBox="0 0 180 180">
  <path d="M150 170 Q140 140 155 110 Q170 80 155 50"/>
  <path d="M120 175 Q108 143 125 110 Q142 77 128 42"/>
  <path d="M90 172 Q78 142 95 109 Q112 76 98 41"/>
</svg>

<!-- Splash -->
<div id="splash">
  <div class="splash-glow"></div>
  <img class="splash-logo" src="<?=APP_URL?>/assets/img/logo_full.png" alt="CortexPOS">
  <div class="splash-brand"><em>Cortex</em>POS</div>
  <div class="splash-tag">Smart Café Ecosystem</div>
  <div class="splash-bar-wrap"><div class="splash-bar"></div></div>
</div>

<!-- App -->
<div id="app">
  <!-- Left panel -->
  <div class="panel-left">
    <img class="corner-logo" src="<?=APP_URL?>/assets/img/logo_mini.png" alt="">
    <img class="left-logo" src="<?=APP_URL?>/assets/img/logo_full.png" alt="CortexPOS">
    <div class="left-wordmark">
      <h1><em>Cortex</em>POS</h1>
      <p>Smart Café Ecosystem</p>
    </div>
    <div class="left-divider"></div>
    <div class="left-features">
      <div class="feat"><div class="feat-dot"></div><span>Real-time sales & analytics</span></div>
      <div class="feat"><div class="feat-dot"></div><span>Ingredient-level inventory</span></div>
      <div class="feat"><div class="feat-dot"></div><span>Gamified client loyalty engine</span></div>
      <div class="feat"><div class="feat-dot"></div><span>Cashier performance metrics</span></div>
      <div class="feat"><div class="feat-dot"></div><span>Rush hour alerts & insights</span></div>
    </div>
  </div>

  <!-- Right panel -->
  <div class="panel-right">
    <div class="form-wrap">

      <?php if ($info): ?>
      <div class="info-box"><?=e($info)?></div>
      <?php endif; ?>

      <span class="role-lbl">Access as</span>
      <div class="role-tabs">
        <button type="button" class="rtab" id="tab-admin" onclick="setRole('admin')"><span class="rtab-icon">⚙</span>Admin</button>
        <button type="button" class="rtab on" id="tab-cashier" onclick="setRole('cashier')"><span class="rtab-icon">☕</span>Cashier</button>
        <button type="button" class="rtab" id="tab-client" onclick="setRole('client')"><span class="rtab-icon">◎</span>Client</button>
      </div>

      <div class="form-heading" id="form-heading">Welcome back</div>
      <div class="form-sub" id="form-sub">Sign in to your cashier workspace</div>

      <form method="POST" action="">
        <?php /* CSRF not needed on login — no state change before auth */ ?>
        <input type="hidden" name="role" id="role-input" value="cashier">

        <!-- OTC (admin only) -->
        <div class="otc-field" id="otc-wrap">
          <div class="otc-badge">⬡ One-time authorization code</div>
          <div class="field">
            <label>Authorization Code</label>
            <input type="text" name="otc" id="otc" placeholder="XXXX-XXXX-XXXX-XXXX" autocomplete="off"
                   oninput="this.value=this.value.toUpperCase()">
          </div>
        </div>

        <div class="field">
          <label>Email address</label>
          <input type="email" name="email" value="<?=e($_POST['email']??'')?>" placeholder="your@email.com" required autocomplete="email">
        </div>

        <div class="field">
          <label>Password</label>
          <input type="password" name="password" id="pwd" placeholder="••••••••" required autocomplete="current-password">
          <button type="button" class="pass-toggle" onclick="togglePwd()">Show</button>
        </div>

        <div class="pass-row">
          <a href="<?=APP_URL?>/auth/forgot.php">Forgot password?</a>
        </div>

        <?php if ($error): ?>
        <div class="error-box"><?=e($error)?></div>
        <?php endif; ?>

        <button type="submit" class="submit-btn" id="submit-btn">Enter Workspace</button>
      </form>
      <div class="form-footer">CortexPOS &nbsp;·&nbsp; Secured access &nbsp;·&nbsp; v1.0</div>
<div style="text-align:center;margin-top:16px">
  <a href="<?=APP_URL?>/auth/register.php" style="font-size:.78rem;color:var(--gold);text-decoration:none;border:1px solid rgba(212,146,42,.3);padding:8px 20px;border-radius:8px;transition:.2s" onmouseenter="this.style.borderColor='var(--gold)'" onmouseleave="this.style.borderColor='rgba(212,146,42,.3)'">
    Create Account
  </a>
</div>
    </div>
  </div>
</div>

<script>
// Splash → App
window.addEventListener('load', () => {
  setTimeout(() => {
    document.getElementById('splash').classList.add('hide');
    setTimeout(() => document.getElementById('app').classList.add('show'), 400);
  }, 3600);
});

const roles = {
  admin:   { heading:'Admin access', sub:'Secure sign-in — requires authorization code', btn:'Enter Control Center', otc:true },
  cashier: { heading:'Welcome back',  sub:'Sign in to your cashier workspace', btn:'Enter Workspace', otc:false },
  client:  { heading:'Hello, guest',  sub:'Sign in to your CortexPOS account', btn:'Enter My Account', otc:false },
};

function setRole(role) {
  document.querySelectorAll('.rtab').forEach(t=>t.classList.remove('on'));
  document.getElementById('tab-'+role).classList.add('on');
  document.getElementById('role-input').value = role;
  document.getElementById('form-heading').textContent = roles[role].heading;
  document.getElementById('form-sub').textContent     = roles[role].sub;
  document.getElementById('submit-btn').textContent   = roles[role].btn;
  const otcWrap = document.getElementById('otc-wrap');
  const otcInp  = document.getElementById('otc');
 if (roles[role].otc) { otcWrap.classList.add('show'); otcInp.required=false; }
  else { otcWrap.classList.remove('show'); otcInp.required=false; otcInp.value=''; }
}

function togglePwd() {
  const i=document.getElementById('pwd');
  const b=document.querySelector('.pass-toggle');
  if(i.type==='password'){i.type='text';b.textContent='Hide';}else{i.type='password';b.textContent='Show';}
}

// Restore role if POST error
<?php if (!empty($_POST['role'])): ?>
setRole('<?=e($_POST['role'])?>');
<?php endif; ?>
</script>
</body>
</html>

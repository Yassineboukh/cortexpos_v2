<?php
// auth/register.php
require_once __DIR__ . '/../includes/functions.php';
startSession();
if (!empty($_SESSION['user'])) redirect(APP_URL.'/'.$_SESSION['user']['role'].'/dashboard.php');

$error = ''; $success = '';
$role = $_GET['role'] ?? 'client';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role     = trim($_POST['role']     ?? 'client');
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm  = trim($_POST['confirm']  ?? '');
    $otc      = trim($_POST['otc']      ?? '');

    if (!in_array($role,['admin','cashier','client'])) $error = 'Invalid role.';
    elseif (strlen($name)<2) $error = 'Please enter your full name (min 2 characters).';
    elseif (!filter_var($email,FILTER_VALIDATE_EMAIL)) $error = 'Please enter a valid email address.';
    elseif (strlen($password)<8) $error = 'Password must be at least 8 characters.';
    elseif (!preg_match('/[A-Z]/',$password)||!preg_match('/[0-9]/',$password)) $error = 'Password must contain at least 1 uppercase letter and 1 number.';
    elseif ($password !== $confirm) $error = 'Passwords do not match.';
    elseif (in_array($role,['admin','cashier']) && empty($otc)) $error = 'An authorization code is required for this account type.';
    else {
        $pdo = db();
        $tbl = ($role==='client') ? 'clients' : 'users';
        $exists = $pdo->prepare("SELECT id FROM {$tbl} WHERE email=? LIMIT 1");
        $exists->execute([$email]);
        if ($exists->fetch()) {
            $error = 'An account with this email already exists.';
        } else {
            // Validate OTC for admin/cashier
            $otcId = null;
            if (in_array($role,['admin','cashier'])) {
                $clean  = strtoupper(str_replace([' ','-'],'',$otc));
                $prefix = substr($clean,0,4);
                $rows   = $pdo->prepare("SELECT id,code_hash FROM otc_codes WHERE code_prefix=? AND role=? AND is_used=0 AND expires_at>NOW() LIMIT 10");
                $rows->execute([$prefix,$role]);
                $valid = null;
                foreach ($rows->fetchAll() as $row) {
                    if (password_verify($clean,$row['code_hash'])) { $valid=$row; break; }
                }
                if (!$valid) { $error = 'Invalid or expired authorization code. Contact your administrator.'; }
                else $otcId = $valid['id'];
            }

            if (!$error) {
                $hash = password_hash($password,PASSWORD_BCRYPT,['cost'=>12]);
                $pdo->beginTransaction();
                if ($role==='client') {
                    $pdo->prepare("INSERT INTO clients (name,email,password_hash,rank_id) VALUES (?,?,?,(SELECT id FROM ranks ORDER BY min_points ASC LIMIT 1))")
                        ->execute([$name,$email,$hash]);
                } else {
                    $pdo->prepare("INSERT INTO users (name,email,password_hash,role) VALUES (?,?,?,?)")
                        ->execute([$name,$email,$hash,$role]);
                    $pdo->prepare("UPDATE otc_codes SET is_used=1,used_at=NOW(),used_by_email=? WHERE id=? AND is_used=0")
                        ->execute([$email,$otcId]);
                }
                $pdo->commit();
                auditLog('register_success',$role,$email);
                flash('success','Account created successfully! You can now sign in.');
                redirect(APP_URL.'/auth/login.php');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><title>Register — CortexPOS</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400;500&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?=APP_URL?>/assets/css/app.css">
<style>
.auth-card{background:rgba(17,9,4,.96);border:1px solid rgba(212,146,42,.14);border-radius:24px;
  padding:32px 36px;width:100%;max-width:440px;position:relative;z-index:1;box-shadow:0 24px 70px rgba(0,0,0,.22)}
.reg-logo{display:flex;align-items:center;gap:10px;margin-bottom:24px}
.reg-logo img{width:36px;height:36px;object-fit:contain}
.reg-logo span{font-family:'Cormorant Garamond',serif;font-size:1.2rem;font-weight:500;color:var(--cream)}
.reg-logo span em{color:var(--gold);font-style:normal}
.reg-title{font-family:'Cormorant Garamond',serif;font-size:1.6rem;font-weight:400;color:var(--cream);margin-bottom:4px}
.reg-sub{font-size:.78rem;color:var(--text-dim);margin-bottom:22px}
.error-box{padding:10px 14px;background:rgba(184,64,48,.1);border:1px solid rgba(184,64,48,.3);
  border-radius:8px;font-size:.76rem;color:#e8907e;margin-bottom:16px}
.login-link{text-align:center;margin-top:18px;font-size:.78rem;color:var(--text-dim)}
.login-link a{color:var(--gold);text-decoration:none}
</style>
</head>
<body class="auth-page">
<div class="auth-card">
  <div class="reg-logo">
    <img src="<?=APP_URL?>/assets/img/logo_mini.png" alt="">
    <span><em>Cortex</em>POS</span>
  </div>
  <div class="reg-title">Create Account</div>
  <div class="reg-sub">Fill in your details to get started</div>

  <?php if($error): ?><div class="alert alert-error"><?=e($error)?></div><?php endif; ?>

  <form method="POST" action="">
    <div class="form-group">
      <label class="form-label">Account Type</label>
      <select class="form-select" name="role" id="reg-role" onchange="toggleOTC(this.value)">
        <option value="client"  <?=$role==='client' ?'selected':''?>>Client</option>
        <option value="cashier" <?=$role==='cashier'?'selected':''?>>Cashier</option>
        <option value="admin"   <?=$role==='admin'  ?'selected':''?>>Admin</option>
      </select>
    </div>
    <div class="form-group" id="otc-group" style="<?=in_array($role,['admin','cashier'])?'':'display:none'?>">
      <label class="form-label">Authorization Code</label>
      <input type="text" class="form-input" name="otc" value="<?=e($_POST['otc']??'')?>" placeholder="XXXX-XXXX-XXXX-XXXX" oninput="this.value=this.value.toUpperCase()">
      <div class="form-hint">One-time code provided by your administrator</div>
    </div>
    <div class="form-group">
      <label class="form-label">Full Name</label>
      <input type="text" class="form-input" name="name" value="<?=e($_POST['name']??'')?>" placeholder="Your full name" required>
    </div>
    <div class="form-group">
      <label class="form-label">Email Address</label>
      <input type="email" class="form-input" name="email" value="<?=e($_POST['email']??'')?>" placeholder="your@email.com" required>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Password</label>
        <input type="password" class="form-input" name="password" placeholder="Min 8 chars" required>
      </div>
      <div class="form-group">
        <label class="form-label">Confirm</label>
        <input type="password" class="form-input" name="confirm" placeholder="Repeat password" required>
      </div>
    </div>
    <div class="form-hint" style="margin-bottom:16px">Must be 8+ chars with 1 uppercase and 1 number</div>
    <button type="submit" class="btn btn-primary btn-w">Create Account</button>
  </form>
  <div class="login-link">Already have an account? <a href="<?=APP_URL?>/auth/login.php">Sign In</a></div>
</div>
<script>
function toggleOTC(role) {
  const g = document.getElementById('otc-group');
  g.style.display = (role==='admin'||role==='cashier') ? '' : 'none';
}
</script>
</body>
</html>

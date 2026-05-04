<?php
require_once __DIR__ . '/../includes/functions.php';
$token = $_GET['token'] ?? '';
$email = trim($_GET['email'] ?? '');
$role  = trim($_GET['role']  ?? '');
$error = ''; $done = false;

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    $token    = trim($_POST['token']        ?? '');
    $email    = trim($_POST['email']        ?? '');
    $role     = trim($_POST['role']         ?? '');
    $password = trim($_POST['password']     ?? '');
    $confirm  = trim($_POST['confirm']      ?? '');

    if (strlen($password)<8||!preg_match('/[A-Z]/',$password)||!preg_match('/[0-9]/',$password))
        $error='Password must be 8+ chars with 1 uppercase and 1 number.';
    elseif ($password!==$confirm) $error='Passwords do not match.';
    else {
        $rows = db()->prepare("SELECT id,token_hash FROM password_resets WHERE email=? AND role=? AND used=0 AND expires_at>NOW() ORDER BY created_at DESC LIMIT 5");
        $rows->execute([$email,$role]); $valid=null;
        foreach ($rows->fetchAll() as $r) { if(password_verify($token,$r['token_hash'])){$valid=$r;break;} }
        if (!$valid) $error='This reset link is invalid or has expired.';
        else {
            $hash = password_hash($password,PASSWORD_BCRYPT,['cost'=>12]);
            $tbl  = ($role==='client') ? 'clients' : 'users';
            db()->prepare("UPDATE {$tbl} SET password_hash=? WHERE email=?")->execute([$hash,$email]);
            db()->prepare("UPDATE password_resets SET used=1 WHERE id=?")->execute([$valid['id']]);
            $done = true;
            flash('success','Password updated successfully. Please sign in.');
            redirect(APP_URL.'/auth/login.php');
        }
    }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Reset Password — CortexPOS</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400;500&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?=APP_URL?>/assets/css/app.css">
</head><body class="auth-page">
<div class="auth-card">
  <h2>Reset Password</h2>
  <p>Enter your new password below.</p>
  <?php if($error): ?><div class="alert alert-error"><?=e($error)?></div><?php endif; ?>
  <form method="POST">
    <?php csrfField(); ?>
    <input type="hidden" name="token" value="<?=e($token)?>">
    <input type="hidden" name="email" value="<?=e($email)?>">
    <input type="hidden" name="role"  value="<?=e($role)?>">
    <div class="form-group"><label class="form-label">New Password</label><input type="password" class="form-input" name="password" required placeholder="Min 8 chars"></div>
    <div class="form-group"><label class="form-label">Confirm Password</label><input type="password" class="form-input" name="confirm" required placeholder="Repeat password"></div>
    <button type="submit" class="btn btn-primary btn-w">Update Password</button>
  </form>
</div>
</body></html>

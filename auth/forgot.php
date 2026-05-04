<?php
// auth/forgot.php
require_once __DIR__ . '/../includes/functions.php';
$sent = false; $error = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $email = trim($_POST['email']??'');
    $role  = trim($_POST['role'] ??'');
    if (!filter_var($email,FILTER_VALIDATE_EMAIL)||!in_array($role,['admin','cashier','client'])) {
        $error = 'Please enter a valid email and select a role.';
    } else {
        $tbl  = ($role==='client') ? 'clients' : 'users';
        $user = db()->prepare("SELECT id FROM {$tbl} WHERE email=? AND is_active=1 LIMIT 1");
        $user->execute([$email]); $user=$user->fetch();
        if ($user) {
            $raw  = bin2hex(random_bytes(32));
            $hash = password_hash($raw,PASSWORD_BCRYPT,['cost'=>10]);
            $exp  = date('Y-m-d H:i:s',strtotime('+1 hour'));
            db()->prepare("INSERT INTO password_resets (email,role,token_hash,expires_at) VALUES (?,?,?,?)")->execute([$email,$role,$hash,$exp]);
            $link = APP_URL.'/auth/reset.php?token='.$raw.'&email='.urlencode($email).'&role='.$role;
            // DEV: show link (in production: send email)
            error_log("CortexPOS Reset Link for {$email}: {$link}");
            flash('info','DEV MODE: Reset link logged to PHP error log. In production this would be emailed.');
        }
        $sent = true; // Always show success (no enumeration)
    }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Forgot Password — CortexPOS</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400;500&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?=APP_URL?>/assets/css/app.css">
</head><body class="auth-page">
<div class="auth-card">
  <h2>Forgot Password</h2>
  <p>Enter your email and we'll send a reset link.</p>
  <?php if($error): ?><div class="alert alert-error"><?=e($error)?></div><?php endif; ?>
  <?php if($sent): ?>
  <div class="alert alert-success">If that email exists, a reset link has been sent. Check your inbox.</div>
  <div class="auth-note"><a href="login.php">← Back to Sign In</a></div>
  <?php else: ?>
  <form method="POST">
    <div class="form-group"><label class="form-label">Email</label><input type="email" class="form-input" name="email" value="<?=e($_POST['email']??'')?>" required placeholder="your@email.com"></div>
    <div class="form-group"><label class="form-label">Role</label>
      <select class="form-select" name="role">
        <option value="client">Client</option><option value="cashier">Cashier</option><option value="admin">Admin</option>
      </select>
    </div>
    <button type="submit" class="btn btn-primary btn-w">Send Reset Link</button>
  </form>
  <div class="auth-note"><a href="login.php">← Back to Sign In</a></div>
  <?php endif; ?>
</div>
</body></html>

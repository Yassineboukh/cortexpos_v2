<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
$user=requireLogin('client'); $pdo=db();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    $action=$_POST['action']??'';
    if ($action==='profile') {
        $name=trim($_POST['name']??''); $phone=trim($_POST['phone']??''); $pub=isset($_POST['public'])?1:0;
        if (strlen($name)<2) flash('error','Name too short.');
        else { $pdo->prepare("UPDATE clients SET name=?,phone=?,profile_public=? WHERE id=?")->execute([$name,$phone,$pub,$user['id']]); $_SESSION['user']['name']=$name; flash('success','Profile updated.'); }
    } elseif ($action==='password') {
        $old=$_POST['old']??''; $new=$_POST['new']??''; $conf=$_POST['confirm']??'';
        $c=$pdo->prepare("SELECT password_hash FROM clients WHERE id=?");$c->execute([$user['id']]);$c=$c->fetch();
        if (!password_verify($old,$c['password_hash'])) flash('error','Current password is incorrect.');
        elseif (strlen($new)<8||!preg_match('/[A-Z]/',$new)||!preg_match('/[0-9]/',$new)) flash('error','Password must be 8+ chars with 1 uppercase and 1 number.');
        elseif ($new!==$conf) flash('error','Passwords do not match.');
        else { $pdo->prepare("UPDATE clients SET password_hash=? WHERE id=?")->execute([password_hash($new,PASSWORD_BCRYPT,['cost'=>12]),$user['id']]); flash('success','Password updated.'); }
    }
    redirect(APP_URL.'/client/profile.php');
}

$client=$pdo->prepare("SELECT c.*,r.name as rname,r.icon as ricon,r.color as rcolor FROM clients c LEFT JOIN ranks r ON c.rank_id=r.id WHERE c.id=?");$client->execute([$user['id']]);$client=$client->fetch();
$stats=$pdo->prepare("SELECT COUNT(*) as ords,COALESCE(SUM(total),0) as spent FROM orders WHERE client_id=? AND status!='cancelled'");$stats->execute([$user['id']]);$stats=$stats->fetch();
$momentsStmt=$pdo->prepare("SELECT COUNT(*) FROM coffee_moments WHERE client_id=?");$momentsStmt->execute([$user['id']]);$moments=(int)$momentsStmt->fetchColumn();

pageHead('Profile'); sidebar($user); topbar('Profile','My Account'); showFlash();
?>
<div class="g2" style="gap:20px;align-items:start">
  <div>
    <div class="card mb-4">
      <div style="text-align:center;padding:10px 0 20px">
        <div class="av" style="width:68px;height:68px;font-size:1.5rem;margin:0 auto 14px"><?=strtoupper(substr($client['name'],0,1))?></div>
        <div class="serif" style="font-size:1.4rem;color:var(--cream)"><?=e($client['name'])?></div>
        <div style="color:<?=e($client['rcolor']??'var(--caramel)')?>;margin-top:4px"><?=$client['ricon']?> <?=e($client['rname']??'Espresso')?></div>
        <div class="text-dim text-sm" style="margin-top:6px"><?=e($client['email'])?></div>
        <div class="flex ai-c jc-c gap-lg" style="margin-top:14px">
          <div class="tc"><div class="text-gold" style="font-weight:600"><?=number_format($client['points'])?></div><div class="text-xs text-dim">Points</div></div>
          <div class="tc"><div style="color:var(--latte);font-weight:600"><?=$stats['ords']?></div><div class="text-xs text-dim">Orders</div></div>
          <div class="tc"><div style="color:var(--latte);font-weight:600"><?=$moments?></div><div class="text-xs text-dim">Moments</div></div>
        </div>
      </div>
    </div>   

    <div class="card mb-4">
      <div class="card-title">Edit Profile</div>
      <form method="POST">
        <?php csrfField(); ?><input type="hidden" name="action" value="profile">
        <div class="form-group"><label class="form-label">Full Name</label><input type="text" class="form-input" name="name" value="<?=e($client['name'])?>" required></div>
        <div class="form-group"><label class="form-label">Phone</label><input type="text" class="form-input" name="phone" value="<?=e($client['phone']??'')?>" placeholder="+216 XX XXX XXX"></div>
        <div class="form-group"><label class="form-check"><input type="checkbox" name="public" <?=$client['profile_public']?'checked':''?>> Public profile (visible on leaderboard)</label></div>
        <button type="submit" class="btn btn-primary btn-w">Save Changes</button>
      </form>
    </div>

    <div class="card">
      <div class="card-title">Change Password</div>
      <form method="POST">
        <?php csrfField(); ?><input type="hidden" name="action" value="password">
        <div class="form-group"><label class="form-label">Current Password</label><input type="password" class="form-input" name="old" required></div>
        <div class="form-group"><label class="form-label">New Password</label><input type="password" class="form-input" name="new" required></div>
        <div class="form-group"><label class="form-label">Confirm New</label><input type="password" class="form-input" name="confirm" required></div>
        <button type="submit" class="btn btn-ghost btn-w">Update Password</button>
      </form>
    </div>
  </div>

  <div>
    <div class="card mb-4">
      <div class="card-title">My Stats</div>
      <div style="display:flex;flex-direction:column;gap:13px">
        <div class="flex ai-c jc-b"><span class="text-dim">Total Orders</span><span><?=$stats['ords']?></span></div>
        <div class="flex ai-c jc-b"><span class="text-dim">Total Spent</span><span class="text-gold"><?=number_format($stats['spent'],2)?> DT</span></div>
        <div class="flex ai-c jc-b"><span class="text-dim">Loyalty Points</span><span class="badge b-gold">⭐ <?=number_format($client['points'])?></span></div>
        <div class="flex ai-c jc-b"><span class="text-dim">Current Streak</span><span style="color:var(--caramel)">🔥 <?=$client['streak_days']?> days</span></div>
        <div class="flex ai-c jc-b"><span class="text-dim">Streak Restores</span><span><?=$client['streak_restores']?> left</span></div>
        <div class="flex ai-c jc-b"><span class="text-dim">Coffee Moments</span><span><?=$moments?> uploaded</span></div>
        <div class="flex ai-c jc-b"><span class="text-dim">Member Since</span><span class="text-dim"><?=date('d M Y',strtotime($client['created_at']))?></span></div>
      </div>
    </div>
    <div class="card" style="border-color:rgba(184,64,48,.3)">
      <div class="card-title" style="color:#e8907e">Account</div>
      <p class="text-dim text-sm mb-4" style="line-height:1.6">Sign out from your current session.</p>
      <a href="<?=APP_URL?>/auth/logout.php" class="btn btn-ghost btn-w" style="justify-content:center">⏻ Sign Out</a>
    </div>
  </div>
</div>
<?php pageEnd(); ?>

<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
$user=requireLogin('admin'); $pdo=db();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    $action=$_POST['action']??'';
    if ($action==='toggle') {
        $pdo->prepare("UPDATE users SET is_active=? WHERE id=? AND role='cashier'")->execute([(int)$_POST['active'],(int)$_POST['id']]);
        flash('success','Cashier updated.');
    }
    redirect(APP_URL.'/admin/cashiers.php');
}

$cashiers=$pdo->query("SELECT u.*,
    (SELECT COUNT(*) FROM orders WHERE cashier_id=u.id AND DATE(created_at)=CURDATE() AND status!='cancelled') as today_orders,
    (SELECT COALESCE(SUM(total),0) FROM orders WHERE cashier_id=u.id AND DATE(created_at)=CURDATE() AND status!='cancelled') as today_rev,
    (SELECT COUNT(*) FROM orders WHERE cashier_id=u.id AND status!='cancelled') as total_orders,
    (SELECT COALESCE(SUM(total),0) FROM orders WHERE cashier_id=u.id AND status!='cancelled') as total_rev,
    (SELECT started_at FROM shifts WHERE cashier_id=u.id AND ended_at IS NULL ORDER BY started_at DESC LIMIT 1) as shift_start
    FROM users u WHERE u.role='cashier' ORDER BY u.is_active DESC,u.name")->fetchAll();

pageHead('Cashiers'); sidebar($user); topbar('Cashiers','Staff Management'); showFlash();
?>
<div class="flex ai-c jc-b mb-4">
  <div class="text-dim text-sm"><?=count($cashiers)?> cashier<?=count($cashiers)!=1?'s':''?></div>
  <a href="settings.php" class="btn btn-primary">⬡ Generate Access Code</a>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px">
<?php foreach($cashiers as $c): ?>
<div class="card" style="<?=!$c['is_active']?'opacity:.55':''?>">
  <div class="flex ai-c jc-b mb-3">
    <div class="flex ai-c gap"><div class="av" style="width:40px;height:40px;font-size:.9rem"><?=strtoupper(substr($c['name'],0,1))?></div><div><div class="text-cream" style="font-weight:500"><?=e($c['name'])?></div><div class="text-xs text-dim"><?=e($c['email'])?></div></div></div>
    <?php if($c['shift_start']): ?><span class="badge b-success">● On Shift</span><?php elseif($c['is_active']): ?><span class="badge b-dim">Off</span><?php else: ?><span class="badge b-danger">Disabled</span><?php endif; ?>
  </div>
  <div class="g2 mb-3" style="gap:8px">
    <div style="background:rgba(255,255,255,.03);border-radius:var(--radius-sm);padding:10px;text-align:center"><div class="text-gold serif" style="font-size:1.3rem"><?=$c['today_orders']?></div><div class="text-xs text-dim">Today Orders</div></div>
    <div style="background:rgba(255,255,255,.03);border-radius:var(--radius-sm);padding:10px;text-align:center"><div class="text-gold serif" style="font-size:1.1rem"><?=number_format($c['today_rev'],2)?></div><div class="text-xs text-dim">Today Rev (DT)</div></div>
  </div>
  <div class="flex ai-c jc-b mb-2 text-sm"><span class="text-dim">Total Orders</span><span><?=number_format($c['total_orders'])?></span></div>
  <div class="flex ai-c jc-b mb-3 text-sm"><span class="text-dim">Total Revenue</span><span class="text-gold"><?=number_format($c['total_rev'],2)?> DT</span></div>
  <?php if($c['last_login']): ?><div class="flex ai-c jc-b mb-3 text-sm"><span class="text-dim">Last Login</span><span class="text-dim"><?=date('d M, H:i',strtotime($c['last_login']))?></span></div><?php endif; ?>
  <form method="POST">
    <?php csrfField(); ?>
    <input type="hidden" name="action" value="toggle">
    <input type="hidden" name="id" value="<?=$c['id']?>">
    <input type="hidden" name="active" value="<?=$c['is_active']?0:1?>">
    <button class="btn btn-<?=$c['is_active']?'danger':'success'?> btn-sm btn-w" onclick="return confirm('<?=$c['is_active']?'Disable':'Enable'?> this cashier?')"><?=$c['is_active']?'Disable':'Enable'?></button>
  </form>
</div>
<?php endforeach; ?>
<?php if(empty($cashiers)): ?><div class="card flex ai-c jc-c" style="height:200px;color:var(--text-dim)">No cashiers yet. <a href="settings.php" style="color:var(--gold);margin-left:6px">Generate an access code →</a></div><?php endif; ?>
</div>
<?php pageEnd(); ?>

<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('cashier'); $pdo = db();

$date = $_GET['date'] ?? date('Y-m-d');
$page = max(1,(int)($_GET['p']??1)); $per=20; $offset=($page-1)*$per;

$orders=$pdo->prepare("SELECT o.*,c.name as cn,t.number as tnum FROM orders o LEFT JOIN clients c ON o.client_id=c.id LEFT JOIN cafe_tables t ON o.table_id=t.id WHERE o.cashier_id=? AND DATE(o.created_at)=? ORDER BY o.created_at DESC LIMIT $per OFFSET $offset");
$orders->execute([$user['id'],$date]); $orders=$orders->fetchAll();

$total=$pdo->prepare("SELECT COUNT(*) FROM orders WHERE cashier_id=? AND DATE(created_at)=?");
$total->execute([$user['id'],$date]); $totalRows=(int)$total->fetchColumn();

$sum=$pdo->prepare("SELECT COUNT(*) as c,COALESCE(SUM(total),0) as r,COALESCE(AVG(total),0) as a FROM orders WHERE cashier_id=? AND DATE(created_at)=? AND status!='cancelled'");
$sum->execute([$user['id'],$date]); $sum=$sum->fetch();

$shifts=$pdo->prepare("SELECT *,TIMEDIFF(COALESCE(ended_at,NOW()),started_at) as dur FROM shifts WHERE cashier_id=? AND DATE(started_at)=? ORDER BY started_at DESC");
$shifts->execute([$user['id'],$date]); $shifts=$shifts->fetchAll();

pageHead('History'); sidebar($user); topbar('History','My Order History'); showFlash();
?>
<div class="flex ai-c jc-b mb-4">
  <form method="GET" class="flex gap"><input type="date" name="date" value="<?=e($date)?>" class="form-input" style="width:170px" onchange="this.form.submit()"></form>
  <div class="text-dim text-sm"><?=$totalRows?> orders on <?=date('d M Y',strtotime($date))?></div>
</div>
<div class="stats-row col3 mb-4">
  <div class="stat-card"><div class="stat-icon">◈</div><div class="stat-val"><?=$sum['c']?></div><div class="stat-lbl">Completed</div></div>
  <div class="stat-card"><div class="stat-icon">💰</div><div class="stat-val"><?=number_format($sum['r'],2)?></div><div class="stat-lbl">Revenue (DT)</div></div>
  <div class="stat-card"><div class="stat-icon">≈</div><div class="stat-val"><?=number_format($sum['a'],2)?></div><div class="stat-lbl">Avg Order (DT)</div></div>
</div>
<?php if($shifts): ?>
<div class="card mb-4">
  <div class="card-title">Shifts</div>
  <table class="tbl"><thead><tr><th>Started</th><th>Ended</th><th>Duration</th></tr></thead><tbody>
  <?php foreach($shifts as $s): ?>
  <tr><td><?=date('H:i',strtotime($s['started_at']))?></td><td><?=$s['ended_at']?date('H:i',strtotime($s['ended_at'])):'<span class="badge b-success">Active</span>'?></td><td><?=$s['dur']?></td></tr>
  <?php endforeach; ?>
  </tbody></table>
</div>
<?php endif; ?>
<div class="card">
  <div class="card-title">Orders</div>
  <table class="tbl">
    <thead><tr><th>Ref</th><th>Client</th><th>Table</th><th>Total</th><th>Status</th><th>Time</th></tr></thead>
    <tbody>
    <?php foreach($orders as $o): $bmap=['pending'=>'b-warning','confirmed'=>'b-info','preparing'=>'b-warning','ready'=>'b-success','delivered'=>'b-success','cancelled'=>'b-danger']; ?>
    <tr>
      <td style="font-family:monospace;font-size:.72rem;color:var(--gold)"><?=e($o['order_ref'])?></td>
      <td><?=e($o['cn']??'Walk-in')?></td>
      <td><?=$o['tnum']?'T'.$o['tnum']:'—'?></td>
      <td class="text-gold"><?=number_format($o['total'],2)?> DT</td>
      <td><span class="badge <?=$bmap[$o['status']]??'b-dim'?>"><?=ucfirst($o['status'])?></span></td>
      <td class="text-dim text-sm"><?=date('H:i',strtotime($o['created_at']))?></td>
    </tr>
    <?php endforeach; ?>
    <?php if(empty($orders)): ?><tr><td colspan="6" style="text-align:center;color:var(--text-dim);padding:24px">No orders for this date</td></tr><?php endif; ?>
    </tbody>
  </table>
  <?php if(ceil($totalRows/$per)>1): ?><div class="pagination"><?php for($i=1;$i<=ceil($totalRows/$per);$i++): ?><a href="?date=<?=$date?>&p=<?=$i?>" class="btn btn-sm <?=$i===$page?'btn-primary':'btn-ghost'?>"><?=$i?></a><?php endfor; ?></div><?php endif; ?>
</div>
<?php pageEnd(); ?>

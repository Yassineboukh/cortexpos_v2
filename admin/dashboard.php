<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('admin');
$pdo  = db();

// Stats
$today   = $pdo->query("SELECT COALESCE(SUM(total),0) as rev, COUNT(*) as cnt FROM orders WHERE DATE(created_at)=CURDATE() AND status!='cancelled'")->fetch();
$month   = $pdo->query("SELECT COALESCE(SUM(total),0) as rev FROM orders WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW()) AND status!='cancelled'")->fetch();
$prevM   = $pdo->query("SELECT COALESCE(SUM(total),0) as rev FROM orders WHERE MONTH(created_at)=MONTH(NOW()-INTERVAL 1 MONTH) AND YEAR(created_at)=YEAR(NOW()-INTERVAL 1 MONTH) AND status!='cancelled'")->fetch();
$clients = $pdo->query("SELECT COUNT(*) as cnt FROM clients WHERE is_active=1")->fetch();
$newC    = $pdo->query("SELECT COUNT(*) as cnt FROM clients WHERE DATE(created_at)=CURDATE()")->fetch();
$pending = $pdo->query("SELECT COUNT(*) as cnt FROM orders WHERE status='pending'")->fetch();
$lowStock= $pdo->query("SELECT COUNT(*) as cnt FROM ingredients WHERE stock_qty<=alert_qty AND alert_qty>0")->fetch();
$growth  = $prevM['rev']>0 ? round((($month['rev']-$prevM['rev'])/$prevM['rev'])*100,1) : 0;

// Recent orders
$orders = $pdo->query("SELECT o.*,c.name as cn,u.name as cash,t.number as tnum FROM orders o LEFT JOIN clients c ON o.client_id=c.id LEFT JOIN users u ON o.cashier_id=u.id LEFT JOIN cafe_tables t ON o.table_id=t.id ORDER BY o.created_at DESC LIMIT 8")->fetchAll();

// Top products
$topProds = $pdo->query("SELECT p.name,SUM(oi.quantity) as sold,SUM(oi.subtotal) as rev FROM order_items oi JOIN products p ON oi.product_id=p.id JOIN orders o ON oi.order_id=o.id WHERE o.status!='cancelled' AND o.created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) GROUP BY p.id ORDER BY sold DESC LIMIT 5")->fetchAll();

// Cashier perf today
$cashiers = $pdo->query("SELECT u.name,COUNT(o.id) as ords,COALESCE(SUM(o.total),0) as rev FROM users u LEFT JOIN orders o ON o.cashier_id=u.id AND DATE(o.created_at)=CURDATE() AND o.status!='cancelled' WHERE u.role='cashier' AND u.is_active=1 GROUP BY u.id ORDER BY rev DESC")->fetchAll();

// Sparkline data
$sparkline = $pdo->query("SELECT COALESCE(SUM(total),0) as v FROM orders WHERE status!='cancelled' AND created_at>=DATE_SUB(NOW(),INTERVAL 12 HOUR) GROUP BY HOUR(created_at) ORDER BY HOUR(created_at)")->fetchAll(PDO::FETCH_COLUMN);

// Alerts
$alerts=[];
if($lowStock['cnt']>0) $alerts[]=['warning',"⚠ {$lowStock['cnt']} ingredient(s) below alert threshold — restock needed"];
if($pending['cnt']>0)  $alerts[]=['info',"⏳ {$pending['cnt']} pending order(s) awaiting confirmation"];
$rush=$pdo->query("SELECT COUNT(*) FROM orders WHERE created_at>=NOW()-INTERVAL 30 MINUTE AND status!='cancelled'")->fetchColumn();
if($rush>=5) $alerts[]=['danger',"🔥 Rush hour — {$rush} orders in last 30 minutes"];

// Badge helper
function badge(string $status): string {
    $m=['pending'=>'b-warning','confirmed'=>'b-info','preparing'=>'b-warning','ready'=>'b-success','delivered'=>'b-success','cancelled'=>'b-danger'];
    return '<span class="badge '.($m[$status]??'b-dim').'">'.ucfirst($status).'</span>';
}

pageHead('Dashboard');
sidebar($user);
topbar('Dashboard','Overview');
showFlash();
?>

<?php foreach($alerts as [$t,$m]): ?>
<div style="padding:10px 16px;border-radius:var(--radius-sm);margin-bottom:10px;font-size:.78rem;
  background:<?=$t==='danger'?'rgba(184,64,48,.12)':($t==='warning'?'rgba(192,122,47,.12)':'rgba(58,122,184,.12)')?>;
  border:1px solid <?=$t==='danger'?'rgba(184,64,48,.3)':($t==='warning'?'rgba(192,122,47,.3)':'rgba(58,122,184,.3)')?>;
  color:var(--cream)"><?=e($m)?></div>
<?php endforeach; ?>

<div class="stats-row mb-4">
  <div class="stat-card"><div class="stat-icon">💰</div><div class="stat-val"><?=number_format($today['rev'],2)?></div><div class="stat-lbl">Today Revenue (DT)</div><div class="stat-delta up">▲ <?=$today['cnt']?> orders</div></div>
  <div class="stat-card"><div class="stat-icon">📅</div><div class="stat-val"><?=number_format($month['rev'],2)?></div><div class="stat-lbl">This Month (DT)</div><div class="stat-delta <?=$growth>=0?'up':'dn'?>"><?=$growth>=0?'▲':'▼'?> <?=abs($growth)?>% vs last month</div></div>
  <div class="stat-card"><div class="stat-icon">◎</div><div class="stat-val"><?=number_format($clients['cnt'])?></div><div class="stat-lbl">Total Clients</div><div class="stat-delta up">▲ +<?=$newC['cnt']?> today</div></div>
  <div class="stat-card"><div class="stat-icon">⏳</div><div class="stat-val"><?=$pending['cnt']?></div><div class="stat-lbl">Pending Orders</div><div class="stat-delta <?=$lowStock['cnt']>0?'dn':'up'?>"><?=$lowStock['cnt']?> low stock alerts</div></div>
</div>

<div class="g2 mb-4">
  <div class="card">
    <div class="card-title">Revenue — Last 12 Hours</div>
    <canvas id="spark" height="80" style="width:100%"></canvas>
  </div>
  <div class="card">
    <div class="card-title">Top Products (30 days)</div>
    <?php foreach($topProds as $i=>$p): ?>
    <div class="flex ai-c jc-b mb-3">
      <div class="flex ai-c gap-sm">
        <div style="width:22px;height:22px;border-radius:50%;background:rgba(192,122,47,.2);display:flex;align-items:center;justify-content:center;font-size:.7rem;color:var(--gold);font-weight:600"><?=$i+1?></div>
        <span style="font-size:.82rem"><?=e($p['name'])?></span>
      </div>
      <div class="tr"><div style="font-size:.82rem;color:var(--gold)"><?=number_format($p['rev'],2)?> DT</div><div class="text-xs text-dim"><?=$p['sold']?> sold</div></div>
    </div>
    <?php endforeach; ?>
    <?php if(empty($topProds)): ?><p class="text-dim text-sm">No sales data yet.</p><?php endif; ?>
  </div>
</div>

<div class="card mb-4">
  <div class="card-head"><div class="card-title" style="margin:0">Cashier Performance — Today</div><a href="cashiers.php" class="btn btn-ghost btn-sm">View All</a></div>
  <table class="tbl">
    <thead><tr><th>Cashier</th><th>Orders</th><th>Revenue</th></tr></thead>
    <tbody>
    <?php foreach($cashiers as $c): ?>
    <tr><td><div class="flex ai-c gap-sm"><div class="av" style="width:26px;height:26px;font-size:.62rem"><?=strtoupper(substr($c['name'],0,1))?></div><?=e($c['name'])?></div></td><td><?=$c['ords']?></td><td class="text-gold"><?=number_format($c['rev'],2)?> DT</td></tr>
    <?php endforeach; ?>
    <?php if(empty($cashiers)): ?><tr><td colspan="3" style="text-align:center;color:var(--text-dim);padding:20px">No cashiers</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<div class="card">
  <div class="card-head"><div class="card-title" style="margin:0">Recent Orders</div><a href="sales.php" class="btn btn-ghost btn-sm">View All</a></div>
  <table class="tbl">
    <thead><tr><th>Ref</th><th>Client</th><th>Table</th><th>Cashier</th><th>Total</th><th>Status</th><th>Time</th></tr></thead>
    <tbody>
    <?php foreach($orders as $o): ?>
    <tr>
      <td style="font-family:monospace;font-size:.72rem;color:var(--gold)"><?=e($o['order_ref'])?></td>
      <td><?=e($o['cn']??'Walk-in')?></td>
      <td><?=$o['tnum']?'T'.$o['tnum']:'—'?></td>
      <td><?=e($o['cash']??'—')?></td>
      <td class="text-gold"><?=number_format($o['total'],2)?> DT</td>
      <td><?=badge($o['status'])?></td>
      <td class="text-dim text-sm"><?=date('H:i',strtotime($o['created_at']))?></td>
    </tr>
    <?php endforeach; ?>
    <?php if(empty($orders)): ?><tr><td colspan="7" style="text-align:center;color:var(--text-dim);padding:20px">No orders yet</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<script>
document.addEventListener('DOMContentLoaded',()=>{
  const c=document.getElementById('spark');
  if(c){c.width=c.offsetWidth;drawSparkline('spark',<?=json_encode(array_values($sparkline))?>,'#d4922a');}
});
</script>
<?php pageEnd(); ?>

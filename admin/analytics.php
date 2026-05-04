<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('admin'); $pdo = db();

$daily = $pdo->query("SELECT DATE(created_at) as d,COALESCE(SUM(total),0) as rev,COUNT(*) as cnt FROM orders WHERE status!='cancelled' AND created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) GROUP BY DATE(created_at) ORDER BY d")->fetchAll();
$heatmap = $pdo->query("SELECT DAYOFWEEK(created_at)-1 as dow,HOUR(created_at) as h,COUNT(*) as cnt FROM orders WHERE status!='cancelled' AND created_at>=DATE_SUB(NOW(),INTERVAL 90 DAY) GROUP BY dow,h")->fetchAll();
$heatData = array_fill(0,7,array_fill(0,24,0));
foreach ($heatmap as $r) $heatData[$r['dow']][$r['h']] = (int)$r['cnt'];
$maxHeat = 1; foreach ($heatData as $day) foreach ($day as $v) if ($v>$maxHeat) $maxHeat=$v;
 
$catRev = $pdo->query("SELECT c.name,c.icon,COALESCE(SUM(oi.subtotal),0) as rev,COALESCE(SUM(oi.quantity),0) as qty FROM order_items oi JOIN products p ON oi.product_id=p.id JOIN categories c ON p.category_id=c.id JOIN orders o ON oi.order_id=o.id WHERE o.status!='cancelled' AND o.created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) GROUP BY c.id ORDER BY rev DESC")->fetchAll();
$totalCatRev = array_sum(array_column($catRev,'rev')) ?: 1;

$topCashiers = $pdo->query("SELECT u.name,COUNT(o.id) as ords,COALESCE(SUM(o.total),0) as rev,COALESCE(AVG(o.total),0) as avg FROM users u LEFT JOIN orders o ON o.cashier_id=u.id AND MONTH(o.created_at)=MONTH(NOW()) AND o.status!='cancelled' WHERE u.role='cashier' AND u.is_active=1 GROUP BY u.id ORDER BY rev DESC")->fetchAll();
$maxRev = max(array_column($topCashiers,'rev') ?: [1]);

$totOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)")->fetchColumn();
$cancelled = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status='cancelled' AND created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)")->fetchColumn();
$cancelRate = $totOrders > 0 ? round(($cancelled/$totOrders)*100,1) : 0;

$newClients = $pdo->query("SELECT DATE(created_at) as d,COUNT(*) as cnt FROM clients WHERE created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) GROUP BY DATE(created_at) ORDER BY d")->fetchAll();

pageHead('Analytics'); sidebar($user); topbar('Analytics','Business Intelligence');
?>
<div class="stats-row mb-4">
  <div class="stat-card"><div class="stat-icon">📈</div><div class="stat-val"><?=number_format(array_sum(array_column($daily,'rev')),2)?></div><div class="stat-lbl">30-Day Revenue (DT)</div><div class="stat-delta up">▲ <?=array_sum(array_column($daily,'cnt'))?> orders</div></div>
  <div class="stat-card"><div class="stat-icon">◎</div><div class="stat-val"><?=number_format(array_sum(array_column($newClients,'cnt')))?></div><div class="stat-lbl">New Clients (30 days)</div></div>
  <div class="stat-card"><div class="stat-icon">✕</div><div class="stat-val"><?=$cancelRate?>%</div><div class="stat-lbl">Cancellation Rate</div><div class="stat-delta <?=$cancelRate>10?'dn':'up'?>"><?=$cancelRate>10?'▼ High':'▲ Healthy'?></div></div>
  <div class="stat-card"><div class="stat-icon">≈</div><div class="stat-val"><?=count($daily)?number_format(array_sum(array_column($daily,'rev'))/count($daily),2):'0.00'?></div><div class="stat-lbl">Avg Daily Revenue (DT)</div></div>
</div>

<div class="g2 mb-4">
  <div class="card"><div class="card-title">Daily Revenue — Last 30 Days</div><canvas id="rev-chart" height="140" style="width:100%"></canvas></div>
  <div class="card">
    <div class="card-title">Revenue by Category</div>
    <?php foreach ($catRev as $cat): $pct=round(($cat['rev']/$totalCatRev)*100); ?>
    <div class="mb-3"><div class="flex ai-c jc-b mb-2"><span class="text-sm"><?=e($cat['icon'])?> <?=e($cat['name'])?></span><span class="text-gold text-sm"><?=number_format($cat['rev'],2)?> DT</span></div>
    <div class="progress"><div class="progress-fill" style="width:<?=$pct?>%"></div></div>
    <div class="flex ai-c jc-b" style="margin-top:3px"><span class="text-xs text-dim"><?=$cat['qty']?> sold</span><span class="text-xs text-dim"><?=$pct?>%</span></div></div>
    <?php endforeach; ?>
    <?php if(empty($catRev)): ?><p class="text-dim text-sm">No data yet.</p><?php endif; ?>
  </div>
</div>

<!-- Heatmap -->
<div class="card mb-4">
  <div class="card-title">Rush Hour Heatmap — Orders/Hour (90 days)</div>
  <div style="overflow-x:auto">
    <table style="border-collapse:separate;border-spacing:2px;min-width:650px">
      <thead><tr><th style="width:60px;font-size:.58rem;color:var(--text-dim);text-align:left;padding:2px 6px">Day</th>
      <?php for($h=0;$h<24;$h++): ?><th style="font-size:.55rem;color:var(--text-dim);text-align:center;padding:2px;width:22px"><?=$h?></th><?php endfor; ?>
      </tr></thead>
      <tbody>
      <?php $days=['Sun','Mon','Tue','Wed','Thu','Fri','Sat']; foreach($days as $di=>$day): ?>
      <tr><td style="font-size:.68rem;color:var(--text-mid);padding:3px 6px"><?=$day?></td>
      <?php for($h=0;$h<24;$h++): $v=$heatData[$di][$h]; $op=round($v/$maxHeat,2); ?>
      <td class="heat-cell" style="background:rgba(192,122,47,<?=$op?>)" title="<?=$day?> <?=$h?>:00 — <?=$v?> orders"></td>
      <?php endfor; ?>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <div class="flex gap-lg" style="margin-top:10px">
      <?php foreach(['Low'=>.15,'Medium'=>.5,'Peak'=>1] as $l=>$o): ?><div class="flex ai-c gap-sm text-xs text-dim"><div style="width:12px;height:12px;border-radius:2px;background:rgba(192,122,47,<?=$o?>)"></div><?=$l?></div><?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Cashier performance -->
<div class="card mb-4">
  <div class="card-head"><div class="card-title" style="margin:0">Cashier Performance — This Month</div><a href="cashiers.php" class="btn btn-ghost btn-sm">Full Report</a></div>
  <table class="tbl">
    <thead><tr><th>Cashier</th><th>Orders</th><th>Revenue</th><th>Avg Order</th><th>Performance</th></tr></thead>
    <tbody>
    <?php foreach($topCashiers as $c): ?>
    <tr>
      <td><div class="flex ai-c gap-sm"><div class="av" style="width:26px;height:26px;font-size:.62rem"><?=strtoupper(substr($c['name'],0,1))?></div><?=e($c['name'])?></div></td>
      <td><?=$c['ords']?></td><td class="text-gold"><?=number_format($c['rev'],2)?> DT</td><td><?=number_format($c['avg'],2)?> DT</td>
      <td style="width:150px"><div class="progress"><div class="progress-fill" style="width:<?=$maxRev>0?round(($c['rev']/$maxRev)*100):0?>%"></div></div></td>
    </tr>
    <?php endforeach; ?>
    <?php if(empty($topCashiers)): ?><tr><td colspan="5" style="text-align:center;color:var(--text-dim);padding:20px">No data</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<div class="card"><div class="card-title">Client Growth — Last 30 Days</div><canvas id="growth-chart" height="90" style="width:100%"></canvas></div>

<script>
document.addEventListener('DOMContentLoaded',()=>{
  const rc=document.getElementById('rev-chart'); if(rc){rc.width=rc.offsetWidth;drawSparkline('rev-chart',<?=json_encode(array_values(array_column($daily,'rev')))?>,'#d4922a');}
  const gc=document.getElementById('growth-chart'); if(gc){gc.width=gc.offsetWidth;drawSparkline('growth-chart',<?=json_encode(array_values(array_column($newClients,'cnt')))?>,'#4a9e5c');}
});
</script>
<?php pageEnd(); ?>

<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('admin');
$pdo  = db();

$period  = $_GET['period'] ?? 'today';
$status  = $_GET['status'] ?? '';
$cashier = $_GET['cashier'] ?? '';
$page    = max(1,(int)($_GET['p']??1));
$per     = 20; $offset=($page-1)*$per;

$where=[]; $params=[];
switch($period){
    case 'today': $where[]="DATE(o.created_at)=CURDATE()"; break;
    case 'week':  $where[]="o.created_at>=DATE_SUB(NOW(),INTERVAL 7 DAY)"; break;
    case 'month': $where[]="MONTH(o.created_at)=MONTH(NOW()) AND YEAR(o.created_at)=YEAR(NOW())"; break;
    case 'year':  $where[]="YEAR(o.created_at)=YEAR(NOW())"; break;
}
if($status)  {$where[]="o.status=:st";      $params[':st']=$status;}
if($cashier) {$where[]="o.cashier_id=:ca";  $params[':ca']=$cashier;}
$wsql = $where ? 'WHERE '.implode(' AND ',$where) : '';

$total=$pdo->prepare("SELECT COUNT(*) FROM orders o $wsql"); $total->execute($params);
$totalRows=(int)$total->fetchColumn(); $totalPages=ceil($totalRows/$per);

$orders=$pdo->prepare("SELECT o.*,c.name as cn,u.name as cash,t.number as tnum FROM orders o LEFT JOIN clients c ON o.client_id=c.id LEFT JOIN users u ON o.cashier_id=u.id LEFT JOIN cafe_tables t ON o.table_id=t.id $wsql ORDER BY o.created_at DESC LIMIT $per OFFSET $offset");
$orders->execute($params); $orders=$orders->fetchAll();

$sum=$pdo->prepare("SELECT COALESCE(SUM(total),0) as rev,COUNT(*) as cnt,COALESCE(AVG(total),0) as avg FROM orders o $wsql AND o.status!='cancelled'");
$sum->execute($params); $sum=$sum->fetch();

$cashierList=$pdo->query("SELECT id,name FROM users WHERE role='cashier' AND is_active=1 ORDER BY name")->fetchAll();

pageHead('Sales'); sidebar($user); topbar('Sales','All Transactions'); showFlash();
?>
<div class="stats-row col3 mb-4">
  <div class="stat-card"><div class="stat-icon">💰</div><div class="stat-val"><?=number_format($sum['rev'],2)?></div><div class="stat-lbl">Revenue (DT)</div></div>
  <div class="stat-card"><div class="stat-icon">◈</div><div class="stat-val"><?=number_format($sum['cnt'])?></div><div class="stat-lbl">Completed Orders</div></div>
  <div class="stat-card"><div class="stat-icon">≈</div><div class="stat-val"><?=number_format($sum['avg'],2)?></div><div class="stat-lbl">Avg Order (DT)</div></div>
</div>

<!-- Filters -->
<div class="card mb-4" style="padding:14px 18px">
  <form method="GET" class="flex ai-c gap flex-wrap">
    <?php foreach(['today'=>'Today','week'=>'7 Days','month'=>'This Month','year'=>'This Year','all'=>'All Time'] as $v=>$l): ?>
    <a href="?period=<?=$v?>&status=<?=urlencode($status)?>&cashier=<?=urlencode($cashier)?>" class="btn btn-sm <?=$period===$v?'btn-primary':'btn-ghost'?>"><?=$l?></a>
    <?php endforeach; ?>
    <select name="status" class="form-select" style="width:140px" onchange="this.form.submit()">
      <option value="">All Statuses</option>
      <?php foreach(['pending','confirmed','preparing','ready','delivered','cancelled'] as $s): ?>
      <option value="<?=$s?>" <?=$status===$s?'selected':''?>><?=ucfirst($s)?></option>
      <?php endforeach; ?>
    </select>
    <select name="cashier" class="form-select" style="width:160px" onchange="this.form.submit()">
      <option value="">All Cashiers</option>
      <?php foreach($cashierList as $c): ?>
      <option value="<?=$c['id']?>" <?=$cashier==$c['id']?'selected':''?>><?=e($c['name'])?></option>
      <?php endforeach; ?>
    </select>
    <input type="hidden" name="period" value="<?=$period?>">
  </form>
</div>

<div class="card">
  <div class="card-head"><div class="card-title" style="margin:0">Orders (<?=$totalRows?> total)</div></div>
  <table class="tbl">
    <thead><tr><th>Ref</th><th>Client</th><th>Table</th><th>Cashier</th><th>Total</th><th>Status</th><th>Date</th></tr></thead>
    <tbody>
    <?php foreach($orders as $o): ?>
    <tr>
      <td style="font-family:monospace;font-size:.72rem;color:var(--gold)"><?=e($o['order_ref'])?></td>
      <td><?=e($o['cn']??'Walk-in')?></td>
      <td><?=$o['tnum']?'T'.$o['tnum']:'—'?></td>
      <td><?=e($o['cash']??'—')?></td>
      <td class="text-gold"><?=number_format($o['total'],2)?> DT</td>
      <td><?php $m=['pending'=>'b-warning','confirmed'=>'b-info','preparing'=>'b-warning','ready'=>'b-success','delivered'=>'b-success','cancelled'=>'b-danger'];echo'<span class="badge '.($m[$o['status']]??'b-dim').'">'.ucfirst($o['status']).'</span>';?></td>
      <td class="text-dim text-sm"><?=date('d M, H:i',strtotime($o['created_at']))?></td>
    </tr>
    <?php endforeach; ?>
    <?php if(empty($orders)): ?><tr><td colspan="7" style="text-align:center;color:var(--text-dim);padding:28px">No orders found</td></tr><?php endif; ?>
    </tbody>
  </table>
  <?php if($totalPages>1): ?>
  <div class="pagination">
    <?php for($i=1;$i<=$totalPages;$i++): ?>
    <a href="?period=<?=$period?>&status=<?=urlencode($status)?>&cashier=<?=urlencode($cashier)?>&p=<?=$i?>" class="btn btn-sm <?=$i===$page?'btn-primary':'btn-ghost'?>"><?=$i?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>
<?php pageEnd(); ?>

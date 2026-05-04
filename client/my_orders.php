<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('client'); $pdo = db();

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='cancel') {
    verifyCsrf();
    $id=(int)$_POST['id'];
    $o=$pdo->prepare("SELECT status,client_id FROM orders WHERE id=?");$o->execute([$id]);$o=$o->fetch();
    if ($o && $o['client_id']==$user['id'] && $o['status']==='pending') {
        $pdo->prepare("UPDATE orders SET status='cancelled' WHERE id=?")->execute([$id]);
        flash('success','Order cancelled.');
    } else flash('error','Cannot cancel this order.');
    redirect(APP_URL.'/client/my_orders.php');
}

$status=$_GET['status']??''; $page=max(1,(int)($_GET['p']??1)); $per=15; $offset=($page-1)*$per;
$where="WHERE o.client_id=?"; $params=[$user['id']];
if ($status) { $where.=" AND o.status=?"; $params[]=$status; }
$total=$pdo->prepare("SELECT COUNT(*) FROM orders o $where");$total->execute($params);$total=(int)$total->fetchColumn();
$orders=$pdo->prepare("SELECT o.*,t.number as tnum FROM orders o LEFT JOIN cafe_tables t ON o.table_id=t.id $where ORDER BY o.created_at DESC LIMIT $per OFFSET $offset");
$orders->execute($params); $orders=$orders->fetchAll();

pageHead('My Orders'); sidebar($user); topbar('My Orders','Order History'); showFlash();
?>
<div class="flex gap mb-4">
<?php foreach([''=>'All','pending'=>'Pending','confirmed'=>'Confirmed','preparing'=>'Preparing','ready'=>'Ready','delivered'=>'Delivered','cancelled'=>'Cancelled'] as $v=>$l): ?>
<a href="?status=<?=$v?>" class="btn btn-sm <?=$status===$v?'btn-primary':'btn-ghost'?>"><?=$l?></a>
<?php endforeach; ?>
</div>
<div class="card">
  <table class="tbl">
    <thead><tr><th>Ref</th><th>Table</th><th>Total</th><th>Points</th><th>Status</th><th>Date</th><th></th></tr></thead>
    <tbody>
    <?php foreach($orders as $o): $bmap=['pending'=>'b-warning','confirmed'=>'b-info','preparing'=>'b-warning','ready'=>'b-success','delivered'=>'b-success','cancelled'=>'b-danger']; ?>
    <tr>
      <td style="font-family:monospace;font-size:.72rem;color:var(--gold)"><?=e($o['order_ref'])?></td>
      <td><?=$o['tnum']?'Table '.$o['tnum']:'Takeaway'?></td>
      <td class="text-gold"><?=number_format($o['total'],2)?> DT</td>
      <td><span class="badge b-gold">+<?=$o['points_earned']?></span></td>
      <td><span class="badge <?=$bmap[$o['status']]??'b-dim'?>"><?=ucfirst($o['status'])?></span></td>
      <td class="text-dim text-sm"><?=date('d M, H:i',strtotime($o['created_at']))?></td>
      <td><?php if($o['status']==='pending'): ?><form method="POST" style="display:inline" onsubmit="return confirm('Cancel this order?')"><?php csrfField(); ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?=$o['id']?>"><button class="btn btn-danger btn-sm">Cancel</button></form><?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if(empty($orders)): ?><tr><td colspan="7" style="text-align:center;color:var(--text-dim);padding:28px">No orders yet. <a href="menu.php" style="color:var(--gold)">Browse the menu →</a></td></tr><?php endif; ?>
    </tbody>
  </table>
  <?php $pages=ceil($total/$per); if($pages>1): ?><div class="pagination"><?php for($i=1;$i<=$pages;$i++): ?><a href="?status=<?=$status?>&p=<?=$i?>" class="btn btn-sm <?=$i===$page?'btn-primary':'btn-ghost'?>"><?=$i?></a><?php endfor; ?></div><?php endif; ?>
</div>
<?php pageEnd(); ?>

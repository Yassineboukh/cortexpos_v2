<?php
// admin/clients.php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('admin'); $pdo=db();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    $id=(int)$_POST['id']; $active=(int)$_POST['active'];
    $pdo->prepare("UPDATE clients SET is_active=? WHERE id=?")->execute([$active,$id]);
    flash($active?'success':'warning',$active?'Client enabled.':'Client disabled.');
    redirect(APP_URL.'/admin/clients.php');
}

$page=max(1,(int)($_GET['p']??1)); $per=20; $offset=($page-1)*$per;
$q=trim($_GET['q']??''); $where=''; $params=[];
if ($q) { $where="WHERE (c.name LIKE ? OR c.email LIKE ?)"; $params=["%$q%","%$q%"]; }
$total=$pdo->prepare("SELECT COUNT(*) FROM clients c $where"); $total->execute($params); $total=(int)$total->fetchColumn();
$clients=$pdo->prepare("SELECT c.*,r.name as rname,r.icon as ricon,(SELECT COUNT(*) FROM orders WHERE client_id=c.id AND status!='cancelled') as ords,(SELECT COALESCE(SUM(total),0) FROM orders WHERE client_id=c.id AND status!='cancelled') as spent FROM clients c LEFT JOIN ranks r ON c.rank_id=r.id $where ORDER BY c.points DESC LIMIT $per OFFSET $offset");
$clients->execute($params); $clients=$clients->fetchAll();

pageHead('Clients'); sidebar($user); topbar('Clients','Client Management'); showFlash();
?>
<div class="flex ai-c jc-b mb-4">
  <form method="GET" class="flex gap">
    <div class="search-bar" style="width:260px"><span class="text-dim">⌕</span><input type="text" name="q" value="<?=e($q)?>" placeholder="Search name or email…"></div>
    <button class="btn btn-primary">Search</button>
  </form>
  <div class="text-dim text-sm"><?=$total?> clients</div>
</div>
<div class="card">
  <table class="tbl">
    <thead><tr><th>Client</th><th>Rank</th><th>Points</th><th>Orders</th><th>Spent</th><th>Streak</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach($clients as $c): ?>
    <tr>
      <td><div class="flex ai-c gap-sm"><div class="av" style="width:28px;height:28px;font-size:.65rem"><?=strtoupper(substr($c['name'],0,1))?></div><div><div class="text-cream"><?=e($c['name'])?></div><div class="text-xs text-dim"><?=e($c['email'])?></div></div></div></td>
      <td><?=$c['ricon']??'☕'?> <span class="text-sm"><?=e($c['rname']??'Espresso')?></span></td>
      <td><span class="badge b-gold">⭐ <?=number_format($c['points'])?></span></td>
      <td><?=$c['ords']?></td>
      <td class="text-gold"><?=number_format($c['spent'],2)?> DT</td>
      <td style="color:var(--caramel)">🔥 <?=$c['streak_days']?></td>
      <td><?=$c['is_active']?'<span class="badge b-success">Active</span>':'<span class="badge b-danger">Disabled</span>'?></td>
      <td>
        <form method="POST" style="display:inline">
          <?php csrfField(); ?>
          <input type="hidden" name="id" value="<?=$c['id']?>">
          <input type="hidden" name="active" value="<?=$c['is_active']?0:1?>">
          <button class="btn btn-<?=$c['is_active']?'danger':'success'?> btn-sm" onclick="return confirm('<?=$c['is_active']?'Disable':'Enable'?> this client?')"><?=$c['is_active']?'Disable':'Enable'?></button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if(empty($clients)): ?><tr><td colspan="8" style="text-align:center;color:var(--text-dim);padding:28px">No clients found</td></tr><?php endif; ?>
    </tbody>
  </table>
  <?php $pages=ceil($total/$per); if($pages>1): ?><div class="pagination"><?php for($i=1;$i<=$pages;$i++): ?><a href="?q=<?=urlencode($q)?>&p=<?=$i?>" class="btn btn-sm <?=$i===$page?'btn-primary':'btn-ghost'?>"><?=$i?></a><?php endfor; ?></div><?php endif; ?>
</div>
<?php pageEnd(); ?>

<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('client'); $pdo = db();

$client=$pdo->prepare("SELECT c.*,r.name as rname,r.icon as ricon,r.color as rcolor,r.min_points,r.can_preorder,(SELECT r2.min_points FROM ranks r2 WHERE r2.min_points>r.min_points ORDER BY r2.min_points ASC LIMIT 1) as next_pts,(SELECT r2.name FROM ranks r2 WHERE r2.min_points>r.min_points ORDER BY r2.min_points ASC LIMIT 1) as next_name FROM clients c LEFT JOIN ranks r ON c.rank_id=r.id WHERE c.id=?");
$client->execute([$user['id']]); $client=$client->fetch();

$recentOrders=$pdo->prepare("SELECT o.*,t.number as tnum FROM orders o LEFT JOIN cafe_tables t ON o.table_id=t.id WHERE o.client_id=? ORDER BY o.created_at DESC LIMIT 5");
$recentOrders->execute([$user['id']]); $recentOrders=$recentOrders->fetchAll();

$lb=$pdo->query("SELECT c.name,c.points,c.streak_days,r.icon,r.color FROM clients c LEFT JOIN ranks r ON c.rank_id=r.id WHERE c.is_active=1 AND c.profile_public=1 ORDER BY c.points DESC LIMIT 10")->fetchAll();
$myPos=$pdo->prepare("SELECT COUNT(*)+1 FROM clients WHERE points>? AND is_active=1");$myPos->execute([$client['points']]);$myPos=(int)$myPos->fetchColumn();

$featured=$pdo->query("SELECT * FROM products WHERE is_featured=1 AND is_available=1 LIMIT 4")->fetchAll();
$pct=$client['next_pts']?min(100,round(($client['points']/$client['next_pts'])*100)):100;

pageHead('Home'); sidebar($user); topbar('Home','Welcome back'); showFlash();
?>
<!-- Banner -->
<div style="background:linear-gradient(135deg,rgba(192,122,47,.14),rgba(212,146,42,.06));border:1px solid rgba(192,122,47,.28);border-radius:var(--radius);padding:24px 28px;margin-bottom:20px;position:relative;overflow:hidden">
  <div style="position:absolute;right:20px;top:50%;transform:translateY(-50%);font-size:5rem;opacity:.07">☕</div>
  <div class="flex ai-c jc-b">
    <div><div class="text-xs text-dim upper mb-2" style="letter-spacing:.3em">Welcome back</div>
      <div class="serif" style="font-size:1.8rem;color:var(--cream);margin-bottom:4px"><?=e($client['name'])?></div>
      <div class="flex ai-c gap-sm"><span style="color:<?=e($client['rcolor']??'var(--caramel)')?>;font-size:1.1rem"><?=$client['ricon']??'☕'?></span><span style="color:<?=e($client['rcolor']??'var(--caramel)')?>;font-weight:500"><?=e($client['rname']??'Espresso')?></span><span class="text-dim text-sm">· #<?=$myPos?> leaderboard</span></div>
    </div>
    <div class="tr"><div class="serif text-gold" style="font-size:2.2rem;font-weight:500"><?=number_format($client['points'])?></div><div class="text-dim text-sm">loyalty points</div><div style="color:var(--caramel);font-size:.8rem;margin-top:4px">🔥 <?=$client['streak_days']?> day streak</div></div>
  </div>
  <?php if($client['next_pts']): ?>
  <div style="margin-top:18px"><div class="flex ai-c jc-b text-xs text-dim mb-2"><span><?=number_format($client['points'])?> pts</span><span>→ <?=e($client['next_name'])?> at <?=number_format($client['next_pts'])?> pts</span></div>
  <div class="progress"><div class="progress-fill" style="width:<?=$pct?>%"></div></div>
  <div class="text-xs text-dim" style="margin-top:5px"><?=$pct?>% — <?=number_format($client['next_pts']-$client['points'])?> pts needed</div></div>
  <?php else: ?><div class="badge b-gold" style="margin-top:12px">👑 Maximum rank achieved!</div><?php endif; ?>
</div>

<div class="g2 mb-4">
  <!-- Quick actions -->
  <div class="card">
    <div class="card-title">Quick Actions</div>
    <div class="g2" style="gap:10px">
      <?php $acts=[['menu.php','☕','Order Now','Place a new order'],['loyalty.php','⭐','My Rewards','Points & gifts'],['moments.php','📸','Coffee Moment','Upload + earn pts'],['leaderboard.php','🏆','Leaderboard','See top clients']]; ?>
      <?php foreach($acts as [$href,$icon,$label,$sub]): ?>
      <a href="<?=$href?>" style="display:block;padding:16px;background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:var(--radius-sm);text-decoration:none;text-align:center;transition:var(--transition)" onmouseenter="this.style.borderColor='rgba(212,146,42,.5)'" onmouseleave="this.style.borderColor='var(--border)'">
        <div style="font-size:1.4rem;margin-bottom:6px"><?=$icon?></div>
        <div class="text-cream" style="font-size:.82rem;font-weight:500"><?=$label?></div>
        <div class="text-xs text-dim" style="margin-top:2px"><?=$sub?></div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Mini leaderboard -->
  <div class="card">
    <div class="card-head"><div class="card-title" style="margin:0">Leaderboard</div><a href="leaderboard.php" class="btn btn-ghost btn-sm">Full View</a></div>
    <?php foreach($lb as $i=>$lc): $isMe=$lc['name']===$client['name']; ?>
    <div class="flex ai-c jc-b" style="padding:8px 0;border-bottom:1px solid rgba(192,122,47,.07)">
      <div class="flex ai-c gap-sm"><span style="width:20px;font-size:.78rem;text-align:center;font-weight:600;color:<?=$i<3?'var(--gold)':'var(--text-dim)'?>"><?=$i<3?['🥇','🥈','🥉'][$i]:($i+1)?></span><span style="font-size:.8rem;color:<?=$isMe?'var(--gold)':'var(--latte)'?>;font-weight:<?=$isMe?600:400?>"><?=e($lc['name'])?><?=$isMe?' (you)':''?></span></div>
      <span class="text-xs text-dim">⭐ <?=number_format($lc['points'])?></span>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<?php if($featured): ?>
<div class="card mb-4">
  <div class="card-head"><div class="card-title" style="margin:0">Featured Today</div><a href="menu.php" class="btn btn-ghost btn-sm">Full Menu</a></div>
  <div class="g4">
  <?php foreach($featured as $p): ?>
  <a href="menu.php" style="display:block;text-decoration:none;padding:14px;background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:var(--radius-sm);text-align:center;transition:var(--transition)" onmouseenter="this.style.borderColor='rgba(212,146,42,.5)'" onmouseleave="this.style.borderColor='var(--border)'">
    <div style="font-size:1.8rem;margin-bottom:8px">☕</div>
    <div class="text-cream" style="font-size:.82rem;font-weight:500;margin-bottom:4px"><?=e($p['name'])?></div>
    <div class="text-gold" style="font-size:.86rem"><?=number_format($p['price'],2)?> DT</div>
    <?php if($p['points_earn']>0): ?><div class="badge b-gold" style="margin-top:6px;font-size:.57rem">+<?=$p['points_earn']?></div><?php endif; ?>
  </a>
  <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-head"><div class="card-title" style="margin:0">Recent Orders</div><a href="my_orders.php" class="btn btn-ghost btn-sm">All Orders</a></div>
  <table class="tbl">
    <thead><tr><th>Ref</th><th>Table</th><th>Total</th><th>Points</th><th>Status</th><th>Date</th></tr></thead>
    <tbody>
    <?php foreach($recentOrders as $o): $bmap=['pending'=>'b-warning','confirmed'=>'b-info','preparing'=>'b-warning','ready'=>'b-success','delivered'=>'b-success','cancelled'=>'b-danger']; ?>
    <tr><td style="font-family:monospace;font-size:.72rem;color:var(--gold)"><?=e($o['order_ref'])?></td><td><?=$o['tnum']?'Table '.$o['tnum']:'Takeaway'?></td><td class="text-gold"><?=number_format($o['total'],2)?> DT</td><td><span class="badge b-gold">+<?=$o['points_earned']?></span></td><td><span class="badge <?=$bmap[$o['status']]??'b-dim'?>"><?=ucfirst($o['status'])?></span></td><td class="text-dim text-sm"><?=date('d M, H:i',strtotime($o['created_at']))?></td></tr>
    <?php endforeach; ?>
    <?php if(empty($recentOrders)): ?><tr><td colspan="6" style="text-align:center;color:var(--text-dim);padding:24px">No orders yet. <a href="menu.php" style="color:var(--gold)">Browse the menu →</a></td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php pageEnd(); ?>

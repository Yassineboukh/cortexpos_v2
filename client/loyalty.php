<?php
// client/loyalty.php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
$user=requireLogin('client'); $pdo=db();
$client=$pdo->prepare("SELECT c.*,r.name as rname,r.icon as ricon,r.color as rcolor,r.min_points,r.streak_restores as max_res FROM clients c LEFT JOIN ranks r ON c.rank_id=r.id WHERE c.id=?");
$client->execute([$user['id']]); $client=$client->fetch();
$allRanks=$pdo->query("SELECT * FROM ranks ORDER BY min_points ASC")->fetchAll();
$nextRank=null; foreach($allRanks as $r){if($r['min_points']>$client['points']){$nextRank=$r;break;}}
$history=$pdo->prepare("SELECT 'order' as src,o.order_ref as ref,o.points_earned as pts,o.created_at FROM orders o WHERE o.client_id=? AND o.points_earned>0 AND o.status!='cancelled' UNION ALL SELECT 'moment',cm.image_path,cm.points_earned,cm.created_at FROM coffee_moments cm WHERE cm.client_id=? AND cm.points_earned>0 ORDER BY created_at DESC LIMIT 20");
$history->execute([$user['id'],$user['id']]); $history=$history->fetchAll();
pageHead('Loyalty'); sidebar($user); topbar('Loyalty','Points & Rewards');
?>
<div style="background:linear-gradient(135deg,rgba(192,122,47,.14),rgba(212,146,42,.06));border:1px solid rgba(192,122,47,.28);border-radius:var(--radius);padding:26px;margin-bottom:20px">
  <div class="flex ai-c jc-b mb-4">
    <div><div class="text-xs text-dim upper mb-2" style="letter-spacing:.3em">Your Balance</div><div class="serif text-gold" style="font-size:3rem;font-weight:500;line-height:1"><?=number_format($client['points'])?></div><div class="text-dim text-sm">loyalty points</div></div>
    <div class="tc"><div style="font-size:3rem"><?=$client['ricon']??'☕'?></div><div style="color:<?=e($client['rcolor']??'var(--caramel)')?>;font-weight:600;margin-top:6px"><?=e($client['rname']??'Espresso')?></div></div>
    <div class="tr"><div style="color:var(--caramel);font-size:1.5rem">🔥</div><div class="serif" style="font-size:2rem;color:var(--cream)"><?=$client['streak_days']?></div><div class="text-dim text-sm">day streak</div><div class="text-xs text-dim" style="margin-top:4px"><?=$client['streak_restores']?>/<?=$client['max_res']?> restores</div></div>
  </div>
  <?php if($nextRank): $pct=min(100,round(($client['points']/$nextRank['min_points'])*100)); ?>
  <div class="flex ai-c jc-b text-xs text-dim mb-2"><span>Current: <?=e($client['rname']??'Espresso')?></span><span>Next: <?=$nextRank['icon']?> <?=e($nextRank['name'])?> at <?=number_format($nextRank['min_points'])?> pts</span></div>
  <div class="progress" style="height:8px"><div class="progress-fill" style="width:<?=$pct?>%"></div></div>
  <div class="text-xs text-dim" style="margin-top:6px"><?=number_format($nextRank['min_points']-$client['points'])?> more pts needed</div>
  <?php else: ?><div class="badge b-gold" style="margin-top:12px">👑 Maximum rank!</div><?php endif; ?>
</div>
<div class="g2 mb-4">
  <div class="card"><div class="card-title">Rank Progression</div>
  <?php foreach($allRanks as $r): $cur=$r['name']===$client['rname']; $past=$client['points']>=$r['min_points']; ?>
  <div class="flex ai-c gap mb-3" style="padding-bottom:12px;border-bottom:1px solid rgba(192,122,47,.07)">
    <div style="font-size:1.5rem;opacity:<?=$past?1:.3?>"><?=e($r['icon'])?></div>
    <div class="f1"><div style="color:<?=$cur?e($r['color']):'var(--latte)'?>;font-weight:<?=$cur?600:400?>"><?=e($r['name'])?><?=$cur?' ← You':''?></div><div class="text-xs text-dim"><?=number_format($r['min_points'])?> pts · <?=$r['streak_restores']?> restores<?=$r['can_preorder']?' · Pre-order ✓':''?></div></div>
    <?=$past?'<span class="badge b-success" style="font-size:.58rem">✓ Unlocked</span>':'<span class="badge b-dim" style="font-size:.58rem">Locked</span>'?>
  </div>
  <?php endforeach; ?></div>
  <div class="card"><div class="card-title">Points History</div>
  <table class="tbl"><thead><tr><th>Source</th><th>Type</th><th>Points</th><th>Date</th></tr></thead><tbody>
  <?php foreach($history as $h): ?>
  <tr><td style="font-family:monospace;font-size:.71rem;color:var(--gold)"><?=e($h['src']==='order'?$h['ref']:'☕ Moment')?></td><td><span class="badge b-dim"><?=$h['src']==='order'?'☕ Order':'📸 Moment'?></span></td><td><span class="badge b-gold">+<?=$h['pts']?></span></td><td class="text-dim text-sm"><?=date('d M · H:i',strtotime($h['created_at']))?></td></tr>
  <?php endforeach; ?>
  <?php if(empty($history)): ?><tr><td colspan="4" style="text-align:center;color:var(--text-dim);padding:20px">No points yet. Start ordering!</td></tr><?php endif; ?>
  </tbody></table></div>
</div>
<?php pageEnd(); ?>

<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
$user=requireLogin('client'); $pdo=db();
$lb=$pdo->query("SELECT c.name,c.points,c.streak_days,r.name as rname,r.icon as ricon,r.color as rcolor FROM clients c LEFT JOIN ranks r ON c.rank_id=r.id WHERE c.is_active=1 AND c.profile_public=1 ORDER BY c.points DESC LIMIT 20")->fetchAll();
$me=$pdo->prepare("SELECT c.*,r.name as rname,r.icon as ricon,r.color as rcolor FROM clients c LEFT JOIN ranks r ON c.rank_id=r.id WHERE c.id=?");$me->execute([$user['id']]);$me=$me->fetch();
$myPos=$pdo->prepare("SELECT COUNT(*)+1 FROM clients WHERE points>? AND is_active=1");$myPos->execute([$me['points']]);$myPos=(int)$myPos->fetchColumn();
pageHead('Leaderboard'); sidebar($user); topbar('Leaderboard','Top Clients');
?>
<div style="background:linear-gradient(135deg,rgba(192,122,47,.12),rgba(212,146,42,.06));border:1px solid rgba(192,122,47,.25);border-radius:var(--radius);padding:20px 24px;margin-bottom:22px">
  <div class="flex ai-c jc-b">
    <div><div class="text-xs text-dim upper mb-1" style="letter-spacing:.3em">Your Position</div><div class="serif text-gold" style="font-size:2.5rem;font-weight:500;line-height:1">#<?=$myPos?></div></div>
    <div class="tc"><div style="font-size:2rem"><?=$me['ricon']??'☕'?></div><div style="color:<?=e($me['rcolor']??'var(--caramel)')?>;font-weight:500;font-size:.85rem"><?=e($me['rname']??'Espresso')?></div></div>
    <div class="tr"><div class="serif" style="font-size:1.8rem;color:var(--cream)">⭐ <?=number_format($me['points'])?></div><div class="text-dim text-sm">loyalty points</div><div style="color:var(--caramel);font-size:.8rem;margin-top:4px">🔥 <?=$me['streak_days']?> day streak</div></div>
  </div>
</div>
<?php if(count($lb)>=3): ?>
<div class="flex ai-c jc-c gap mb-6" style="align-items:flex-end">
<?php foreach([1,0,2] as $pos=>$idx): $lc=$lb[$idx]; $h=['140px','170px','110px'][$pos]; $medal=['🥈','🥇','🥉'][$pos]; ?>
<div class="flex fc ai-c gap" style="gap:7px">
  <div style="font-size:1.5rem"><?=$lc['ricon']??'☕'?></div>
  <div class="text-cream text-sm" style="font-weight:500;text-align:center;max-width:90px"><?=e($lc['name'])?></div>
  <div class="text-gold" style="font-size:.75rem">⭐ <?=number_format($lc['points'])?></div>
  <div style="width:80px;height:<?=$h?>;background:rgba(192,122,47,.1);border:1px solid rgba(192,122,47,.3);border-bottom:none;border-radius:4px 4px 0 0;display:flex;align-items:flex-start;justify-content:center;padding-top:10px"><span style="font-size:1.3rem"><?=$medal?></span></div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<div class="card" style="padding:0">
<?php foreach($lb as $i=>$lc): $isMe=$lc['name']===$me['name']; ?>
<div class="flex ai-c gap" style="padding:14px 18px;border-bottom:1px solid rgba(192,122,47,.07);<?=$isMe?'background:rgba(212,146,42,.05);':''?><?=$i===count($lb)-1?'border-bottom:none':''?>">
  <div style="width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:<?=$i<3?'.88rem':'.76rem'?>;font-weight:700;flex-shrink:0;background:<?=$i===0?'linear-gradient(135deg,#F5C842,#d4922a)':($i===1?'linear-gradient(135deg,#C0C0C0,#909090)':($i===2?'linear-gradient(135deg,#CD7F32,#a06020)':'rgba(192,122,47,.12)'))?>; color:<?=$i<3?'#0d0905':'var(--text-mid)'?>"><?=$i<3?['🥇','🥈','🥉'][$i]:($i+1)?></div>
  <div style="font-size:1.2rem"><?=$lc['ricon']??'☕'?></div>
  <div class="f1"><div style="color:<?=$isMe?'var(--gold)':'var(--cream)'?>;font-weight:<?=$isMe?600:400?>;font-size:.85rem"><?=e($lc['name'])?><?=$isMe?' <span class="badge b-gold" style="font-size:.57rem">You</span>':''?></div><div style="color:<?=e($lc['rcolor']??'var(--text-dim)')?>;font-size:.71rem"><?=e($lc['rname']??'Espresso')?></div></div>
  <div class="tc text-sm"><div style="color:var(--caramel)">🔥 <?=$lc['streak_days']?></div><div class="text-xs text-dim">streak</div></div>
  <div class="tr"><div class="text-gold serif" style="font-size:1.05rem;font-weight:600"><?=number_format($lc['points'])?></div><div class="text-xs text-dim">points</div></div>
</div>
<?php endforeach; ?>
<?php if(empty($lb)): ?><div style="text-align:center;color:var(--text-dim);padding:32px">Be the first on the leaderboard!</div><?php endif; ?>
</div>
<div class="tc text-xs text-dim" style="margin-top:16px">Updates in real time · Public profiles only · Daily visits build your streak 🔥</div>
<?php pageEnd(); ?>

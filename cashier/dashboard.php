<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('cashier'); $pdo = db();

// Handle order status updates
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    $action=(string)($_POST['action']??'');
    if ($action==='update_status') {
        $oid=(int)$_POST['order_id']; $status=$_POST['status'];
        $valid=['confirmed','preparing','ready','delivered','cancelled'];
        if (in_array($status,$valid)) {
            $pdo->prepare("UPDATE orders SET status=?,updated_at=NOW(),cashier_id=COALESCE(cashier_id,?) WHERE id=?")->execute([$status,$user['id'],$oid]);
            if ($status==='delivered') {
                assignPoints($oid);
                // Free table
                $t=$pdo->prepare("SELECT table_id FROM orders WHERE id=?");$t->execute([$oid]);$tid=$t->fetchColumn();
                if ($tid) $pdo->prepare("UPDATE cafe_tables SET status='free',client_id=NULL WHERE id=?")->execute([$tid]);
            }
            if ($status==='cancelled') {
                $t=$pdo->prepare("SELECT table_id FROM orders WHERE id=?");$t->execute([$oid]);$tid=$t->fetchColumn();
                if ($tid) $pdo->prepare("UPDATE cafe_tables SET status='free',client_id=NULL WHERE id=?")->execute([$tid]);
            }
            flash('success','Order updated to '.ucfirst($status).'.');
        }
    } elseif ($action==='table_status') {
        $pdo->prepare("UPDATE cafe_tables SET status=?,updated_at=NOW() WHERE id=?")->execute([$_POST['status'],(int)$_POST['table_id']]);
        if ($_POST['status']==='free') $pdo->prepare("UPDATE cafe_tables SET client_id=NULL WHERE id=?")->execute([(int)$_POST['table_id']]);
        flash('success','Table updated.');
    }
    redirect(APP_URL.'/cashier/dashboard.php');
}

$shift=$pdo->prepare("SELECT * FROM shifts WHERE cashier_id=? AND ended_at IS NULL ORDER BY started_at DESC LIMIT 1");
$shift->execute([$user['id']]); $shift=$shift->fetch();
$shiftRev=0; $shiftCnt=0;
if ($shift) {
    $sr=$pdo->prepare("SELECT COUNT(*) as c,COALESCE(SUM(total),0) as r FROM orders WHERE cashier_id=? AND created_at>=? AND status!='cancelled'");
    $sr->execute([$user['id'],$shift['started_at']]); $sr=$sr->fetch(); $shiftCnt=$sr['c']; $shiftRev=$sr['r'];
}
$pending = $pdo->query("SELECT o.*,c.name as cn,t.number as tnum FROM orders o LEFT JOIN clients c ON o.client_id=c.id LEFT JOIN cafe_tables t ON o.table_id=t.id WHERE o.status='pending' ORDER BY o.created_at ASC")->fetchAll();
$active  = $pdo->query("SELECT o.*,c.name as cn,t.number as tnum FROM orders o LEFT JOIN clients c ON o.client_id=c.id LEFT JOIN cafe_tables t ON o.table_id=t.id WHERE o.status IN ('confirmed','preparing','ready') ORDER BY o.created_at ASC LIMIT 15")->fetchAll();
$tables  = $pdo->query("SELECT * FROM cafe_tables ORDER BY zone,number")->fetchAll();

pageHead('Dashboard'); sidebar($user); topbar('Dashboard','Cashier Workspace'); showFlash();
?>
<div class="stats-row col3 mb-4">
  <div class="stat-card"><div class="stat-icon">⏱</div><div class="stat-val" id="shift-timer">00:00</div><div class="stat-lbl">Shift Duration</div><?php if($shift): ?><div class="stat-delta up">Started <?=date('H:i',strtotime($shift['started_at']))?></div><?php endif; ?></div>
  <div class="stat-card"><div class="stat-icon">◈</div><div class="stat-val"><?=$shiftCnt?></div><div class="stat-lbl">Orders This Shift</div></div>
  <div class="stat-card"><div class="stat-icon">💰</div><div class="stat-val"><?=number_format($shiftRev,2)?></div><div class="stat-lbl">Revenue This Shift (DT)</div></div>
</div>

<div class="g2 mb-4">
  <!-- Pending -->
  <div class="card">
    <div class="card-head"><div class="card-title" style="margin:0">Pending Orders</div><?php if($pending): ?><span style="background:var(--danger);color:#fff;border-radius:10px;padding:2px 8px;font-size:.65rem"><?=count($pending)?></span><?php endif; ?></div>
    <?php if($pending): ?>
    <div style="display:flex;flex-direction:column;gap:10px">
    <?php foreach($pending as $o): ?>
    <div style="border:1px solid rgba(212,146,42,.3);border-radius:var(--radius-sm);padding:14px;background:rgba(212,146,42,.04)">
      <div class="flex ai-c jc-b mb-2">
        <div><div class="text-cream" style="font-weight:500"><?=$o['tnum']?'Table '.$o['tnum']:'Takeaway'?></div><div class="text-xs text-dim"><?=e($o['cn']??'Walk-in')?> · <?=date('H:i',strtotime($o['created_at']))?></div></div>
        <div class="text-gold"><?=number_format($o['total'],2)?> DT</div>
      </div>
      <div class="flex gap">
        <form method="POST" class="f1"><?php csrfField(); ?><input type="hidden" name="action" value="update_status"><input type="hidden" name="order_id" value="<?=$o['id']?>"><input type="hidden" name="status" value="confirmed"><button class="btn btn-success btn-sm btn-w">✓ Confirm</button></form>
        <form method="POST"><?php csrfField(); ?><input type="hidden" name="action" value="update_status"><input type="hidden" name="order_id" value="<?=$o['id']?>"><input type="hidden" name="status" value="cancelled"><button class="btn btn-danger btn-sm" onclick="return confirm('Cancel this order?')">✕</button></form>
      </div>
    </div>
    <?php endforeach; ?>
    </div>
    <?php else: ?><div class="flex ai-c jc-c" style="height:80px;color:var(--text-dim);font-size:.82rem">No pending orders ✓</div><?php endif; ?>
  </div>

  <!-- Active -->
  <div class="card">
    <div class="card-title">Active Orders</div>
    <?php if($active): ?>
    <div style="display:flex;flex-direction:column;gap:8px">
    <?php foreach($active as $o): $bmap=['confirmed'=>'b-info','preparing'=>'b-warning','ready'=>'b-success']; $next=['confirmed'=>'preparing','preparing'=>'ready','ready'=>'delivered']; $nLabel=['confirmed'=>'→ Preparing','preparing'=>'→ Ready','ready'=>'✓ Delivered']; ?>
    <div class="flex ai-c jc-b" style="padding:10px 12px;background:rgba(255,255,255,.02);border:1px solid var(--border);border-radius:var(--radius-sm)">
      <div><div class="text-cream text-sm"><?=$o['tnum']?'Table '.$o['tnum']:'Takeaway'?></div><div class="text-xs text-dim"><?=e($o['cn']??'Walk-in')?></div></div>
      <span class="badge <?=$bmap[$o['status']]??'b-dim'?>"><?=ucfirst($o['status'])?></span>
      <?php if(isset($next[$o['status']])): ?>
      <form method="POST"><?php csrfField(); ?><input type="hidden" name="action" value="update_status"><input type="hidden" name="order_id" value="<?=$o['id']?>"><input type="hidden" name="status" value="<?=$next[$o['status']]?>"><button class="btn btn-ghost btn-sm"><?=$nLabel[$o['status']]?></button></form>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    </div>
    <?php else: ?><div class="flex ai-c jc-c" style="height:80px;color:var(--text-dim);font-size:.82rem">No active orders</div><?php endif; ?>
  </div>
</div>

<!-- Table map -->
<div class="card">
  <div class="card-head"><div class="card-title" style="margin:0">Table Map</div><a href="tables.php" class="btn btn-ghost btn-sm">Manage</a></div>
  <div class="table-grid">
  <?php foreach($tables as $t): ?>
  <div class="table-cell t-<?=$t['status']?>" onclick="openTableForm(<?=$t['id']?>,'<?=$t['status']?>','<?=e($t['number'],ENT_QUOTES)?>','<?=e($t['zone'],ENT_QUOTES)?>')">
    <div style="font-size:1.5rem">⊡</div>
    <div class="text-cream" style="font-weight:700">T<?=$t['number']?></div>
    <div style="font-size:.62rem;text-transform:uppercase;letter-spacing:.1em;margin:3px 0;color:<?=$t['status']==='free'?'#5fb870':($t['status']==='occupied'?'#e07060':($t['status']==='reserved'?'var(--gold)':'#7eb8e8'))?>"><?=$t['status']?></div>
    <div class="text-xs text-dim"><?=$t['capacity']?> seats</div>
  </div>
  <?php endforeach; ?>
  </div>
</div>

<!-- Table status form (hidden) -->
<form method="POST" id="table-form" style="display:none">
  <?php csrfField(); ?><input type="hidden" name="action" value="table_status">
  <input type="hidden" name="table_id" id="tf-id">
  <input type="hidden" name="status" id="tf-status">
</form>

<!-- Table modal -->
<div class="overlay" id="table-modal">
  <div class="modal" style="max-width:320px"><div class="modal-head"><div class="modal-title" id="tm-title">Table</div><button class="modal-close" onclick="closeModal('table-modal')">✕</button></div>
  <div id="tm-actions" style="display:flex;flex-direction:column;gap:8px"></div>
  </div>
</div>

<script>
<?php if($shift): ?>
const shiftStart=new Date('<?=$shift['started_at']?>').getTime();
setInterval(()=>{
  const d=Date.now()-shiftStart,h=Math.floor(d/3600000),m=Math.floor((d%3600000)/60000);
  document.getElementById('shift-timer').textContent=String(h).padStart(2,'0')+':'+String(m).padStart(2,'0');
},1000);
<?php endif; ?>

function openTableForm(id,status,num,zone){
  const acts=[];
  if(status!=='free')     acts.push({label:'✓ Mark Free',s:'free',cls:'btn-success'});
  if(status==='free')     acts.push({label:'Reserve',s:'reserved',cls:'btn-ghost'});
  if(status!=='cleaning') acts.push({label:'🧹 Cleaning',s:'cleaning',cls:'btn-ghost'});
  document.getElementById('tm-title').textContent=`Table ${num} — ${zone}`;
  document.getElementById('tm-actions').innerHTML=
    acts.map(a=>`<button class="btn ${a.cls} btn-w" onclick="setTable(${id},'${a.s}')">${a.label}</button>`).join('')+
    `<button class="btn btn-ghost btn-w" onclick="closeModal('table-modal')">Close</button>`;
  openModal('table-modal');
}
function setTable(id,status){
  document.getElementById('tf-id').value=id;
  document.getElementById('tf-status').value=status;
  document.getElementById('table-form').submit();
}
// Refresh every 30s
setTimeout(()=>location.reload(),30000);
</script>
<?php pageEnd(); ?>

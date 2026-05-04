<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('cashier'); $pdo = db();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    $pdo->prepare("UPDATE cafe_tables SET status=?,updated_at=NOW() WHERE id=?")->execute([$_POST['status'],(int)$_POST['table_id']]);
    if ($_POST['status']==='free') $pdo->prepare("UPDATE cafe_tables SET client_id=NULL WHERE id=?")->execute([(int)$_POST['table_id']]);
    flash('success','Table status updated.');
    redirect(APP_URL.'/cashier/tables.php');
}

$tables=$pdo->query("SELECT * FROM cafe_tables ORDER BY zone,number")->fetchAll();
$zones=array_unique(array_column($tables,'zone'));

pageHead('Tables'); sidebar($user); topbar('Tables','Table Map'); showFlash();
?>
<?php foreach($zones as $zone): ?>
<div class="card mb-4">
  <div class="card-title"><?=e($zone)?> Zone</div>
  <div class="table-grid">
  <?php foreach(array_filter($tables,fn($t)=>$t['zone']===$zone) as $t): ?>
  <div class="table-cell t-<?=$t['status']?>" onclick="showActions(<?=$t['id']?>,'<?=$t['status']?>',<?=$t['number']?>)">
    <div style="font-size:1.6rem">⊡</div>
    <div class="text-cream" style="font-weight:700">T<?=$t['number']?></div>
    <div style="font-size:.62rem;text-transform:uppercase;letter-spacing:.1em;margin:3px 0;color:<?=$t['status']==='free'?'#5fb870':($t['status']==='occupied'?'#e07060':($t['status']==='reserved'?'var(--gold)':'#7eb8e8'))?>"><?=$t['status']?></div>
    <div class="text-xs text-dim"><?=$t['capacity']?> seats</div>
  </div>
  <?php endforeach; ?>
  </div>
</div>
<?php endforeach; ?>

<form method="POST" id="ts-form" style="display:none">
  <?php csrfField(); ?><input type="hidden" name="table_id" id="ts-id"><input type="hidden" name="status" id="ts-status">
</form>
<div class="overlay" id="ts-modal">
  <div class="modal" style="max-width:300px"><div class="modal-head"><div class="modal-title" id="ts-title">Table</div><button class="modal-close" onclick="closeModal('ts-modal')">✕</button></div>
  <div id="ts-btns" style="display:flex;flex-direction:column;gap:8px"></div></div>
</div>
<script>
function showActions(id,status,num){
  const acts=[];
  if(status!=='free')acts.push(['✓ Mark Free','free','btn-success']);
  if(status==='free')acts.push(['Reserve','reserved','btn-ghost']);
  acts.push(['🧹 Cleaning','cleaning','btn-ghost']);
  document.getElementById('ts-title').textContent='Table '+num;
  document.getElementById('ts-btns').innerHTML=
    acts.map(([l,s,c])=>`<button class="btn ${c} btn-w" onclick="doStatus(${id},'${s}')">${l}</button>`).join('')+
    `<button class="btn btn-ghost btn-w" onclick="closeModal('ts-modal')">Cancel</button>`;
  openModal('ts-modal');
}
function doStatus(id,status){document.getElementById('ts-id').value=id;document.getElementById('ts-status').value=status;document.getElementById('ts-form').submit();}
</script>
<?php pageEnd(); ?>

 <?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
$user=requireLogin('admin'); $pdo=db();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    $action=$_POST['action']??'';
    if ($action==='add') {
        $num=(int)$_POST['number']; $cap=(int)$_POST['capacity']; $zone=trim($_POST['zone']??'Main');
        $ex=$pdo->prepare("SELECT id FROM cafe_tables WHERE number=?");$ex->execute([$num]);
        if ($ex->fetch()) flash('error',"Table #{$num} already exists.");
        else { $pdo->prepare("INSERT INTO cafe_tables (number,capacity,zone) VALUES (?,?,?)")->execute([$num,$cap,$zone]); flash('success',"Table {$num} added."); }
    } elseif ($action==='edit') {
        $pdo->prepare("UPDATE cafe_tables SET status=?,capacity=?,zone=?,notes=? WHERE id=?")->execute([$_POST['status'],(int)$_POST['capacity'],trim($_POST['zone']),trim($_POST['notes']??''),(int)$_POST['id']]);
        flash('success','Table updated.');
    } elseif ($action==='delete') {
        $pdo->prepare("UPDATE orders SET table_id=NULL WHERE table_id=?")->execute([(int)$_POST['id']]);
        $pdo->prepare("DELETE FROM cafe_tables WHERE id=?")->execute([(int)$_POST['id']]);
        flash('success','Table deleted.');
    }
    redirect(APP_URL.'/admin/tables.php');
}

$tables=$pdo->query("SELECT * FROM cafe_tables ORDER BY zone,number")->fetchAll();
$zones=array_unique(array_column($tables,'zone'));
$sc=array_count_values(array_column($tables,'status'));

pageHead('Tables'); sidebar($user); topbar('Tables','Table Management'); showFlash();
?>
<div class="stats-row mb-4">
  <div class="stat-card"><div class="stat-icon" style="color:#5fb870">✓</div><div class="stat-val"><?=$sc['free']??0?></div><div class="stat-lbl">Free</div></div>
  <div class="stat-card"><div class="stat-icon" style="color:#e07060">◉</div><div class="stat-val"><?=$sc['occupied']??0?></div><div class="stat-lbl">Occupied</div></div>
  <div class="stat-card"><div class="stat-icon" style="color:var(--gold)">⊡</div><div class="stat-val"><?=$sc['reserved']??0?></div><div class="stat-lbl">Reserved</div></div>
  <div class="stat-card"><div class="stat-icon" style="color:#7eb8e8">⊘</div><div class="stat-val"><?=$sc['cleaning']??0?></div><div class="stat-lbl">Cleaning</div></div>
</div>
<div class="flex ai-c jc-b mb-4">
  <div class="flex gap-sm" style="font-size:.75rem">
    <span class="flex ai-c gap-sm"><span style="width:10px;height:10px;background:rgba(74,158,92,.6);border-radius:2px;display:inline-block"></span>Free</span>
    <span class="flex ai-c gap-sm"><span style="width:10px;height:10px;background:rgba(184,64,48,.6);border-radius:2px;display:inline-block"></span>Occupied</span>
    <span class="flex ai-c gap-sm"><span style="width:10px;height:10px;background:rgba(192,122,47,.6);border-radius:2px;display:inline-block"></span>Reserved</span>
    <span class="flex ai-c gap-sm"><span style="width:10px;height:10px;background:rgba(58,122,184,.6);border-radius:2px;display:inline-block"></span>Cleaning</span>
  </div>
  <button class="btn btn-primary" onclick="openModal('add-table-modal')">+ Add Table</button>
</div>

<?php foreach($zones as $zone): ?>
<div class="card mb-4">
  <div class="card-title"><?=e($zone)?> Zone</div>
  <div class="table-grid">
  <?php foreach(array_filter($tables,fn($t)=>$t['zone']===$zone) as $t): ?>
  <div class="table-cell t-<?=$t['status']?>" onclick='fillTable(<?=json_encode($t)?>)'>
    <div style="font-size:1.6rem;margin-bottom:6px">⊡</div>
    <div class="text-cream" style="font-weight:700;font-size:1rem">T<?=$t['number']?></div>
    <div style="font-size:.63rem;text-transform:uppercase;letter-spacing:.1em;margin:3px 0;
      color:<?=$t['status']==='free'?'#5fb870':($t['status']==='occupied'?'#e07060':($t['status']==='reserved'?'var(--gold)':'#7eb8e8'))?>"><?=$t['status']?></div>
    <div class="text-xs text-dim"><?=$t['capacity']?> seats</div>
    <?php if($t['notes']): ?><div class="text-xs text-dim truncate" style="margin-top:4px"><?=e($t['notes'])?></div><?php endif; ?>
  </div>
  <?php endforeach; ?>
  </div>
</div>
<?php endforeach; ?>

<!-- Add Table Modal -->
<div class="overlay" id="add-table-modal">
  <div class="modal"><div class="modal-head"><div class="modal-title">Add Table</div><button class="modal-close" onclick="closeModal('add-table-modal')">✕</button></div>
  <form method="POST">
    <?php csrfField(); ?><input type="hidden" name="action" value="add">
    <div class="form-row">
      <div class="form-group"><label class="form-label">Table Number</label><input type="number" class="form-input" name="number" min="1" required placeholder="11"></div>
      <div class="form-group"><label class="form-label">Capacity</label><input type="number" class="form-input" name="capacity" min="1" value="4" required></div>
    </div>
    <div class="form-group"><label class="form-label">Zone</label><input type="text" class="form-input" name="zone" value="Main" required></div>
    <div class="modal-foot"><button type="button" class="btn btn-ghost" onclick="closeModal('add-table-modal')">Cancel</button><button type="submit" class="btn btn-primary">Add</button></div>
  </form></div>
</div>

<!-- Edit Table Modal -->
<div class="overlay" id="edit-table-modal">
  <div class="modal"><div class="modal-head"><div class="modal-title" id="edit-t-title">Edit Table</div><button class="modal-close" onclick="closeModal('edit-table-modal')">✕</button></div>
  <form method="POST" id="edit-t-form">
    <?php csrfField(); ?><input type="hidden" name="action" value="edit"><input type="hidden" name="id" id="et-id">
    <div class="form-row">
      <div class="form-group"><label class="form-label">Status</label>
        <select class="form-select" name="status" id="et-status">
          <option value="free">Free</option><option value="occupied">Occupied</option><option value="reserved">Reserved</option><option value="cleaning">Cleaning</option>
        </select>
      </div>
      <div class="form-group"><label class="form-label">Capacity</label><input type="number" class="form-input" name="capacity" id="et-cap" min="1"></div>
    </div>
    <div class="form-group"><label class="form-label">Zone</label><input type="text" class="form-input" name="zone" id="et-zone"></div>
    <div class="form-group"><label class="form-label">Notes</label><input type="text" class="form-input" name="notes" id="et-notes" placeholder="Optional"></div>
    <div class="modal-foot">
      <form method="POST" style="display:inline" onsubmit="return confirm('Delete this table?')">
        <?php csrfField(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" id="del-t-id">
        <button class="btn btn-danger btn-sm">Delete</button>
      </form>
      <button type="button" class="btn btn-ghost" onclick="closeModal('edit-table-modal')">Cancel</button>
      <button type="submit" form="edit-t-form" class="btn btn-primary">Save</button>
    </div>
  </form></div>
</div>

<script>
function fillTable(t){
  document.getElementById('edit-t-title').textContent=`Table ${t.number} — ${t.zone}`;
  document.getElementById('et-id').value=t.id;
  document.getElementById('del-t-id').value=t.id;
  document.getElementById('et-status').value=t.status;
  document.getElementById('et-cap').value=t.capacity;
  document.getElementById('et-zone').value=t.zone;
  document.getElementById('et-notes').value=t.notes||'';
  openModal('edit-table-modal');
}
</script>
<?php pageEnd(); ?>

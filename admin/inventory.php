<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('admin');
$pdo  = db();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action==='add' || $action==='edit') {
        $name  = trim($_POST['name']  ?? '');
        $unit  = trim($_POST['unit']  ?? 'g');
        $stock = (float)($_POST['stock'] ?? 0);
        $alert = (float)($_POST['alert'] ?? 0);
        $cost  = (float)($_POST['cost']  ?? 0);
        if (!$name) { flash('error','Name is required.'); }
        elseif ($action==='add') {
            $pdo->prepare("INSERT INTO ingredients (name,unit,stock_qty,alert_qty,cost_per_unit) VALUES (?,?,?,?,?)")->execute([$name,$unit,$stock,$alert,$cost]);
            flash('success','Ingredient added.');
        } else {
            $pdo->prepare("UPDATE ingredients SET name=?,unit=?,stock_qty=?,alert_qty=?,cost_per_unit=? WHERE id=?")->execute([$name,$unit,$stock,$alert,$cost,(int)$_POST['id']]);
            flash('success','Ingredient updated.');
        }
    } elseif ($action==='restock') {
        $id=(int)$_POST['id']; $qty=(float)$_POST['qty'];
        if ($qty>0) { $pdo->prepare("UPDATE ingredients SET stock_qty=stock_qty+? WHERE id=?")->execute([$qty,$id]); flash('success','Stock updated.'); }
        else flash('error','Enter a valid quantity.');
    } elseif ($action==='delete') {
        $id=(int)$_POST['id'];
        $used=$pdo->prepare("SELECT COUNT(*) FROM product_ingredients WHERE ingredient_id=?");$used->execute([$id]);
        if ($used->fetchColumn()>0) flash('error','Cannot delete — ingredient used in product recipes.');
        else { $pdo->prepare("DELETE FROM ingredients WHERE id=?")->execute([$id]); flash('success','Ingredient deleted.'); }
    }
    redirect(APP_URL.'/admin/inventory.php');
}

$ingredients=$pdo->query("SELECT i.*,CASE WHEN stock_qty<=alert_qty AND alert_qty>0 THEN 'low' WHEN stock_qty<=alert_qty*2 AND alert_qty>0 THEN 'medium' ELSE 'ok' END as lvl FROM ingredients i ORDER BY lvl ASC,name")->fetchAll();
$lowCount=count(array_filter($ingredients,fn($i)=>$i['lvl']==='low'));

pageHead('Inventory'); sidebar($user); topbar('Inventory','Stock Management'); showFlash();
?>
<?php if($lowCount>0): ?>
<div style="padding:10px 16px;background:rgba(184,64,48,.1);border:1px solid rgba(184,64,48,.3);border-radius:var(--radius-sm);font-size:.78rem;color:#e8907e;margin-bottom:16px">⚠ <?=$lowCount?> ingredient<?=$lowCount>1?'s':''?> below alert threshold — restock required</div>
<?php endif; ?>

<div class="flex ai-c jc-b mb-4">
  <div class="search-bar" style="width:260px"><span class="text-dim">⌕</span><input type="text" placeholder="Search ingredients…" oninput="filterTable(this,'ing-table')"></div>
  <button class="btn btn-primary" onclick="openModal('add-modal')">+ Add Ingredient</button>
</div>

<div class="card">
  <table class="tbl" id="ing-table">
    <thead><tr><th>Ingredient</th><th>Unit</th><th>In Stock</th><th>Alert At</th><th>Status</th><th>Cost/Unit</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach($ingredients as $ing): ?>
    <tr>
      <td class="text-cream" style="font-weight:500"><?=e($ing['name'])?></td>
      <td><span class="badge b-dim"><?=e($ing['unit'])?></span></td>
      <td style="color:<?=$ing['lvl']==='low'?'#e07060':($ing['lvl']==='medium'?'var(--gold)':'#5fb870')?>;font-weight:600"><?=number_format($ing['stock_qty'],2)?></td>
      <td class="text-dim"><?=number_format($ing['alert_qty'],2)?></td>
      <td><?php if($ing['lvl']==='low') echo'<span class="badge b-danger">⬇ Low</span>';
             elseif($ing['lvl']==='medium') echo'<span class="badge b-warning">≈ Medium</span>';
             else echo'<span class="badge b-success">✓ OK</span>';?></td>
      <td class="text-dim"><?=number_format($ing['cost_per_unit'],4)?> DT</td>
      <td>
        <!-- Restock form -->
        <form method="POST" class="flex ai-c gap-sm" style="display:inline-flex">
          <?php csrfField(); ?>
          <input type="hidden" name="action" value="restock">
          <input type="hidden" name="id" value="<?=$ing['id']?>">
          <input type="number" name="qty" step="0.01" min="0.01" placeholder="Qty" class="form-input" style="width:70px;padding:6px 8px;font-size:.75rem">
          <button class="btn btn-success btn-sm">+ Add</button>
        </form>
        <button class="btn btn-ghost btn-sm" onclick='fillEdit(<?=json_encode($ing)?>)'>Edit</button>
        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this ingredient?')">
          <?php csrfField(); ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?=$ing['id']?>">
          <button class="btn btn-danger btn-sm">Delete</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if(empty($ingredients)): ?><tr><td colspan="7" style="text-align:center;color:var(--text-dim);padding:28px">No ingredients. Add your first one.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Add Modal -->
<div class="overlay" id="add-modal">
  <div class="modal">
    <div class="modal-head"><div class="modal-title">Add Ingredient</div><button class="modal-close" onclick="closeModal('add-modal')">✕</button></div>
    <form method="POST">
      <?php csrfField(); ?>
      <input type="hidden" name="action" value="add">
      <div class="form-row">
        <div class="form-group"><label class="form-label">Name</label><input type="text" class="form-input" name="name" required placeholder="e.g. Ground Coffee"></div>
        <div class="form-group"><label class="form-label">Unit</label>
          <select class="form-select" name="unit"><option value="g">g</option><option value="ml">ml</option><option value="pcs">pcs</option><option value="kg">kg</option><option value="l">l</option></select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Current Stock</label><input type="number" class="form-input" name="stock" step="0.01" min="0" value="0" required></div>
        <div class="form-group"><label class="form-label">Alert Threshold</label><input type="number" class="form-input" name="alert" step="0.01" min="0" value="0" required></div>
      </div>
      <div class="form-group"><label class="form-label">Cost per Unit (DT)</label><input type="number" class="form-input" name="cost" step="0.0001" min="0" value="0"></div>
      <div class="modal-foot"><button type="button" class="btn btn-ghost" onclick="closeModal('add-modal')">Cancel</button><button type="submit" class="btn btn-primary">Add</button></div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="overlay" id="edit-modal">
  <div class="modal">
    <div class="modal-head"><div class="modal-title">Edit Ingredient</div><button class="modal-close" onclick="closeModal('edit-modal')">✕</button></div>
    <form method="POST">
      <?php csrfField(); ?>
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="edit-id">
      <div class="form-row">
        <div class="form-group"><label class="form-label">Name</label><input type="text" class="form-input" name="name" id="edit-name" required></div>
        <div class="form-group"><label class="form-label">Unit</label>
          <select class="form-select" name="unit" id="edit-unit"><option value="g">g</option><option value="ml">ml</option><option value="pcs">pcs</option><option value="kg">kg</option><option value="l">l</option></select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Stock</label><input type="number" class="form-input" name="stock" id="edit-stock" step="0.01" min="0" required></div>
        <div class="form-group"><label class="form-label">Alert Threshold</label><input type="number" class="form-input" name="alert" id="edit-alert" step="0.01" min="0" required></div>
      </div>
      <div class="form-group"><label class="form-label">Cost/Unit (DT)</label><input type="number" class="form-input" name="cost" id="edit-cost" step="0.0001" min="0"></div>
      <div class="modal-foot"><button type="button" class="btn btn-ghost" onclick="closeModal('edit-modal')">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
    </form>
  </div>
</div>

<script>
function fillEdit(d){
  document.getElementById('edit-id').value=d.id;
  document.getElementById('edit-name').value=d.name;
  document.getElementById('edit-unit').value=d.unit;
  document.getElementById('edit-stock').value=d.stock_qty;
  document.getElementById('edit-alert').value=d.alert_qty;
  document.getElementById('edit-cost').value=d.cost_per_unit;
  openModal('edit-modal');
}
</script>
<?php pageEnd(); ?>

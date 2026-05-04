<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('admin');
$pdo  = db();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    if ($action==='add'||$action==='edit') {
        $name = trim($_POST['name']??''); $catId=(int)$_POST['cat_id']; $price=(float)$_POST['price'];
        $desc=trim($_POST['desc']??''); $pts=(int)($_POST['points']??0);
        $avail=(int)isset($_POST['available']); $feat=(int)isset($_POST['featured']);
        if (!$name||!$catId||$price<=0) flash('error','Name, category, and price are required.');
        elseif ($action==='add') {
            $pdo->prepare("INSERT INTO products (category_id,name,description,price,points_earn,is_available,is_featured) VALUES (?,?,?,?,?,?,?)")->execute([$catId,$name,$desc,$price,$pts,$avail,$feat]);
            flash('success','Product added.');
        } else {
            $pdo->prepare("UPDATE products SET category_id=?,name=?,description=?,price=?,points_earn=?,is_available=?,is_featured=? WHERE id=?")->execute([$catId,$name,$desc,$price,$pts,$avail,$feat,(int)$_POST['id']]);
            flash('success','Product updated.');
        }
    } elseif ($action==='delete') {
        $id=(int)$_POST['id'];
        $used=$pdo->prepare("SELECT COUNT(*) FROM order_items WHERE product_id=?");$used->execute([$id]);
        if ($used->fetchColumn()>0) { $pdo->prepare("UPDATE products SET is_available=0 WHERE id=?")->execute([$id]); flash('warning','Product hidden (has order history).'); }
        else { $pdo->prepare("DELETE FROM products WHERE id=?")->execute([$id]); flash('success','Product deleted.'); }
    } elseif ($action==='add_ing') {
        $pdo->prepare("INSERT INTO product_ingredients (product_id,ingredient_id,quantity) VALUES (?,?,?) ON DUPLICATE KEY UPDATE quantity=?")->execute([(int)$_POST['prod_id'],(int)$_POST['ing_id'],(float)$_POST['qty'],(float)$_POST['qty']]);
        flash('success','Ingredient added to recipe.');
    } elseif ($action==='rem_ing') {
        $pdo->prepare("DELETE FROM product_ingredients WHERE product_id=? AND ingredient_id=?")->execute([(int)$_POST['prod_id'],(int)$_POST['ing_id']]);
        flash('success','Ingredient removed.');
    }
    $redirectCat = filter_input(INPUT_GET, 'cat', FILTER_VALIDATE_INT);
    redirect(APP_URL.'/admin/products.php'.($redirectCat ? '?cat='.$redirectCat : ''));
}

$filterCat = filter_input(INPUT_GET, 'cat', FILTER_VALIDATE_INT);
if ($filterCat !== null && $filterCat !== false) {
    $products = $pdo->prepare("SELECT p.*,c.name as cat_name FROM products p JOIN categories c ON p.category_id=c.id WHERE p.category_id=? ORDER BY c.sort_order,p.sort_order,p.name");
    $products->execute([$filterCat]);
} else {
    $products = $pdo->query("SELECT p.*,c.name as cat_name FROM products p JOIN categories c ON p.category_id=c.id ORDER BY c.sort_order,p.sort_order,p.name");
}
$products = $products->fetchAll();
$categories = $pdo->query("SELECT * FROM categories WHERE is_active=1 ORDER BY sort_order")->fetchAll();
$ingredients= $pdo->query("SELECT id,name,unit FROM ingredients ORDER BY name")->fetchAll();

pageHead('Products'); sidebar($user); topbar('Products','Menu Management'); showFlash();
?>
<div class="flex ai-c jc-b mb-4">
  <div class="flex gap">
    <a href="products.php" class="btn btn-sm <?=!$filterCat?'btn-primary':'btn-ghost'?>">All</a>
    <?php foreach($categories as $cat): ?>
    <a href="?cat=<?=$cat['id']?>" class="btn btn-sm <?=$filterCat==$cat['id']?'btn-primary':'btn-ghost'?>"><?=$cat['icon']?> <?=e($cat['name'])?></a>
    <?php endforeach; ?>
  </div>
  <button class="btn btn-primary" onclick="openModal('add-prod-modal')">+ Add Product</button>
</div>

<div class="card">
  <table class="tbl">
    <thead><tr><th>Product</th><th>Category</th><th>Price</th><th>Points</th><th>Available</th><th>Featured</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach($products as $p): ?>
    <tr>
      <td><div class="text-cream" style="font-weight:500"><?=e($p['name'])?></div><?php if($p['description']): ?><div class="text-xs text-dim"><?=e(substr($p['description'],0,55))?>…</div><?php endif; ?></td>
      <td><span class="badge b-dim"><?=e($p['cat_name'])?></span></td>
      <td class="text-gold"><?=number_format($p['price'],2)?> DT</td>
      <td><span class="badge b-gold">+<?=$p['points_earn']?></span></td>
      <td><?=$p['is_available']?'<span class="badge b-success">Yes</span>':'<span class="badge b-dim">No</span>'?></td>
      <td><?=$p['is_featured']?'<span class="badge b-gold">★</span>':'<span class="badge b-dim">—</span>'?></td>
      <td>
        <button class="btn btn-ghost btn-sm" onclick='fillProd(<?=json_encode($p)?>)'>Edit</button>
        <button class="btn btn-ghost btn-sm" onclick='openRecipe(<?=json_encode($p['id'])?>, <?=json_encode($p['name'])?>)'>Recipe</button>
        <form method="POST" style="display:inline" onsubmit="return confirm('Delete or hide this product?')">
          <?php csrfField(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$p['id']?>">
          <button class="btn btn-danger btn-sm">Delete</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if(empty($products)): ?><tr><td colspan="7" style="text-align:center;color:var(--text-dim);padding:28px">No products found.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Add Product Modal -->
<div class="overlay" id="add-prod-modal">
  <div class="modal modal-lg">
    <div class="modal-head"><div class="modal-title" id="prod-modal-title">Add Product</div><button class="modal-close" onclick="closeModal('add-prod-modal')">✕</button></div>
    <form method="POST">
      <?php csrfField(); ?>
      <input type="hidden" name="action" id="prod-action" value="add">
      <input type="hidden" name="id" id="prod-id">
      <div class="form-row">
        <div class="form-group"><label class="form-label">Name</label><input type="text" class="form-input" name="name" id="prod-name" required placeholder="e.g. Spanish Latte"></div>
        <div class="form-group"><label class="form-label">Category</label>
          <select class="form-select" name="cat_id" id="prod-cat" required>
            <?php foreach($categories as $cat): ?><option value="<?=$cat['id']?>"><?=$cat['icon']?> <?=e($cat['name'])?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-group"><label class="form-label">Description</label><textarea class="form-textarea" name="desc" id="prod-desc" style="min-height:70px" placeholder="Short description…"></textarea></div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Price (DT)</label><input type="number" class="form-input" name="price" id="prod-price" step="0.01" min="0.01" required placeholder="0.00"></div>
        <div class="form-group"><label class="form-label">Points Earned</label><input type="number" class="form-input" name="points" id="prod-pts" min="0" value="0"></div>
      </div>
      <div class="flex gap-lg mb-3">
        <label class="form-check"><input type="checkbox" name="available" id="prod-avail" checked> Available on menu</label>
        <label class="form-check"><input type="checkbox" name="featured" id="prod-feat"> Featured item</label>
      </div>
      <div class="modal-foot"><button type="button" class="btn btn-ghost" onclick="closeModal('add-prod-modal')">Cancel</button><button type="submit" class="btn btn-primary">Save Product</button></div>
    </form>
  </div>
</div>

<!-- Recipe Modal -->
<div class="overlay" id="recipe-modal">
  <div class="modal modal-lg">
    <div class="modal-head"><div class="modal-title">Recipe — <span id="recipe-name"></span></div><button class="modal-close" onclick="closeModal('recipe-modal')">✕</button></div>
    <div id="recipe-list" class="mb-4"></div>
    <div style="background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:var(--radius-sm);padding:16px">
      <div class="text-xs text-dim upper mb-3">Add Ingredient to Recipe</div>
      <form method="POST" class="flex ai-c gap">
        <?php csrfField(); ?>
        <input type="hidden" name="action" value="add_ing">
        <input type="hidden" name="prod_id" id="recipe-pid">
        <select name="ing_id" class="form-select flex-1">
          <?php foreach($ingredients as $ing): ?><option value="<?=$ing['id']?>"><?=e($ing['name'])?> (<?=$ing['unit']?>)</option><?php endforeach; ?>
        </select>
        <input type="number" name="qty" step="0.001" min="0.001" placeholder="Qty" class="form-input" style="width:90px" required>
        <button type="submit" class="btn btn-primary">+ Add</button>
      </form>
    </div>
  </div>
</div>

<!-- Remove ingredient from recipe (hidden form) -->
<form method="POST" id="rem-form">
  <?php csrfField(); ?>
  <input type="hidden" name="action" value="rem_ing">
  <input type="hidden" name="prod_id" id="rem-pid">
  <input type="hidden" name="ing_id" id="rem-iid">
</form>

<script>
function fillProd(p){
  document.getElementById('prod-modal-title').textContent='Edit Product';
  document.getElementById('prod-action').value='edit';
  document.getElementById('prod-id').value=p.id;
  document.getElementById('prod-name').value=p.name;
  document.getElementById('prod-cat').value=p.category_id;
  document.getElementById('prod-desc').value=p.description||'';
  document.getElementById('prod-price').value=p.price;
  document.getElementById('prod-pts').value=p.points_earn||0;
  document.getElementById('prod-avail').checked=!!p.is_available;
  document.getElementById('prod-feat').checked=!!p.is_featured;
  openModal('add-prod-modal');
}

async function openRecipe(pid, name){
  document.getElementById('recipe-name').textContent=name;
  document.getElementById('recipe-pid').value=pid;
  openModal('recipe-modal');
  // Load recipe via simple GET page reload is not needed — we fetch inline
  const res=await fetch('?get_recipe='+pid);
  const data=await res.json();
  const list=document.getElementById('recipe-list');
  if(!data.length){list.innerHTML='<p class="text-dim text-sm">No ingredients defined yet. Add below.</p>';return;}
  list.innerHTML='<table class="tbl"><thead><tr><th>Ingredient</th><th>Quantity</th><th>Unit</th><th></th></tr></thead><tbody>'+
    data.map(r=>`<tr><td>${r.name}</td><td>${parseFloat(r.quantity).toFixed(3)}</td><td><span class="badge b-dim">${r.unit}</span></td>
      <td><button class="btn btn-danger btn-sm" onclick="remIng(${pid},${r.ingredient_id})">Remove</button></td></tr>`).join('')+'</tbody></table>';
}

function remIng(pid,iid){
  if(!confirm('Remove this ingredient from recipe?'))return;
  document.getElementById('rem-pid').value=pid;
  document.getElementById('rem-iid').value=iid;
  document.getElementById('rem-form').submit();
}
</script>
<?php
// AJAX: return recipe JSON
if (isset($_GET['get_recipe'])) {
    header('Content-Type: application/json');
    $id=(int)$_GET['get_recipe'];
    $r=$pdo->prepare("SELECT pi.*,i.name,i.unit FROM product_ingredients pi JOIN ingredients i ON pi.ingredient_id=i.id WHERE pi.product_id=?");
    $r->execute([$id]);
    echo json_encode($r->fetchAll());
    exit;
}
pageEnd();
?>

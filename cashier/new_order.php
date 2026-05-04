<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('cashier'); $pdo = db();
error_log('User logged in: ' . json_encode($user));

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    error_log('POST request received in new_order.php');
    $tableId  = $_POST['table_id']  ? (int)$_POST['table_id']  : null;
    $clientId = $_POST['client_id'] ? (int)$_POST['client_id'] : null;
    $notes    = trim($_POST['notes'] ?? '');
    $products = $_POST['products']  ?? []; // array of [product_id, quantity]
    $qtys     = $_POST['qtys']      ?? [];
    error_log('Products: ' . json_encode($products));
    error_log('Qtys: ' . json_encode($qtys));

    $items = [];
    $subtotal = 0; $pointsEarned = 0;
    foreach ($products as $i => $pid) {
        $qty = max(1,(int)($qtys[$i]??1));
        if (!$pid) continue;
        $p=$pdo->prepare("SELECT price,points_earn,is_available,name FROM products WHERE id=? AND is_available=1");
        $p->execute([$pid]); $p=$p->fetch();
        if (!$p) continue;
        $items[] = ['product_id'=>(int)$pid,'name'=>$p['name'],'quantity'=>$qty,'unit_price'=>$p['price'],'subtotal'=>$p['price']*$qty,'points'=>$p['points_earn']*$qty];
        $subtotal += $p['price']*$qty;
        $pointsEarned += $p['points_earn']*$qty;
    }

    if (empty($items)) { flash('error','Add at least one item to the order.'); redirect(APP_URL.'/cashier/new_order.php'); }

    $pdo->beginTransaction();
    try {
        $ref = generateOrderRef();
        $pdo->prepare("INSERT INTO orders (order_ref,client_id,table_id,cashier_id,status,type,subtotal,total,points_earned,notes) VALUES (?,?,?,?,?,?,?,?,?,?)")
            ->execute([$ref,$clientId,$tableId,$user['id'],'pending',$tableId?'dine_in':'takeaway',$subtotal,$subtotal,$pointsEarned,$notes]);
        $orderId=(int)$pdo->lastInsertId();
        foreach ($items as $item) {
            $pdo->prepare("INSERT INTO order_items (order_id,product_id,quantity,unit_price,subtotal) VALUES (?,?,?,?,?)")
                ->execute([$orderId,$item['product_id'],$item['quantity'],$item['unit_price'],$item['subtotal']]);
        }
        deductStock($orderId);
        if ($tableId) $pdo->prepare("UPDATE cafe_tables SET status='occupied',client_id=? WHERE id=?")->execute([$clientId,$tableId]);
        $pdo->commit();
        flash('success',"Order {$ref} placed successfully!");
        redirect(APP_URL.'/cashier/dashboard.php');
    } catch (Exception $e) {
        $pdo->rollback();
        flash('error','Failed to place order. Please try again.');
        redirect(APP_URL.'/cashier/new_order.php');
    }
}

$categories = $pdo->query("SELECT * FROM categories WHERE is_active=1 ORDER BY sort_order")->fetchAll();
$products   = $pdo->query("SELECT p.*,c.name as cname FROM products p JOIN categories c ON p.category_id=c.id WHERE p.is_available=1 ORDER BY c.sort_order,p.name")->fetchAll();
$tables     = $pdo->query("SELECT * FROM cafe_tables WHERE status='free' ORDER BY number")->fetchAll();

pageHead('New Order'); sidebar($user); topbar('New Order','Create Order'); showFlash();
?>
<form method="POST" id="order-form">
<?php csrfField(); ?>
<div style="display:grid;grid-template-columns:1fr 300px;gap:18px;min-height:calc(100vh - 110px)">

  <!-- Left: Menu -->
  <div style="overflow-y:auto">
    <div class="flex gap mb-3 flex-wrap">
      <button type="button" class="btn btn-primary btn-sm cat-btn" data-cat="all" onclick="filterCat(this,'all')">All</button>
      <?php foreach($categories as $cat): ?>
      <button type="button" class="btn btn-ghost btn-sm cat-btn" data-cat="<?=$cat['id']?>" onclick="filterCat(this,'<?=$cat['id']?>')"><?=e($cat['icon'])?> <?=e($cat['name'])?></button>
      <?php endforeach; ?>
    </div>
    <div class="search-bar mb-4"><span class="text-dim">⌕</span><input type="text" placeholder="Search menu…" oninput="searchMenu(this.value)"></div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px" id="menu-grid">
    <?php foreach($products as $p): ?>
    <div class="product-card" data-cat="<?=$p['category_id']?>" data-name="<?=strtolower(e($p['name']))?>"
      style="background:var(--dark-roast);border:1px solid var(--border);border-radius:var(--radius);padding:16px;cursor:pointer;transition:var(--transition);text-align:center"
      onclick='addItem(<?=json_encode(["id"=>$p["id"],"name"=>$p["name"],"price"=>(float)$p["price"],"pts"=>(int)$p["points_earn"]])?>)'
      onmouseenter="this.style.borderColor='rgba(212,146,42,.6)';this.style.transform='translateY(-2px)'"
      onmouseleave="this.style.borderColor='var(--border)';this.style.transform='none'">
      <div style="font-size:1.6rem;margin-bottom:8px">☕</div>
      <div class="text-cream" style="font-weight:500;font-size:.84rem;margin-bottom:4px"><?=e($p['name'])?></div>
      <div class="text-gold" style="font-weight:600;font-size:.88rem"><?=number_format($p['price'],2)?> DT</div>
      <?php if($p['points_earn']>0): ?><div class="badge b-gold" style="margin-top:6px;font-size:.58rem">+<?=$p['points_earn']?></div><?php endif; ?>
    </div>
    <?php endforeach; ?>
    </div>
  </div>

  <!-- Right: Cart -->
  <div style="background:var(--dark-roast);border:1px solid var(--border);border-radius:var(--radius);display:flex;flex-direction:column;position:sticky;top:0;max-height:calc(100vh-100px);overflow:hidden">
    <div style="padding:14px 16px;border-bottom:1px solid var(--border)">
      <div class="card-title mb-3">Order Details</div>
      <div class="form-group" style="margin-bottom:10px">
        <label class="form-label">Table</label>
        <select class="form-select" name="table_id" style="font-size:.79rem">
          <option value="">Takeaway</option>
          <?php foreach($tables as $t): ?><option value="<?=$t['id']?>">Table <?=$t['number']?> (<?=e($t['zone'])?>)</option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin-bottom:0;position:relative">
        <label class="form-label">Client Email (optional)</label>
        <input type="text" id="client-search" class="form-input" style="font-size:.79rem" placeholder="Search client…" autocomplete="off" oninput="searchClient(this.value)">
        <div id="client-results" style="display:none;position:absolute;background:var(--dark-roast);border:1px solid var(--border);border-radius:var(--radius-sm);z-index:100;width:100%;max-height:150px;overflow-y:auto;top:100%;left:0;margin-top:4px"></div>
        <input type="hidden" name="client_id" id="client-id">
      </div>
    </div>

    <div id="cart-items" style="flex:1;overflow-y:auto;padding:12px 16px;min-height:80px">
      <div id="cart-empty" class="flex ai-c jc-c" style="height:80px;color:var(--text-dim);font-size:.8rem;flex-direction:column;gap:6px"><span style="font-size:1.6rem">🛒</span>Add items</div>
    </div>

    <!-- Hidden inputs for cart items -->
    <div id="cart-inputs"></div>

    <div style="padding:14px 16px;border-top:1px solid var(--border)">
      <div class="form-group mb-3"><textarea class="form-textarea" name="notes" placeholder="Notes…" style="min-height:50px;font-size:.79rem"></textarea></div>
      <div class="flex ai-c jc-b mb-4"><span class="text-dim text-sm">Total</span><span id="cart-total" class="text-gold serif" style="font-size:1.2rem">0.00 DT</span></div>
      <button type="submit" class="btn btn-primary btn-w" id="place-btn" disabled style="padding:12px;letter-spacing:.08em">Place Order</button>
      <button type="button" class="btn btn-ghost btn-w" style="margin-top:7px;font-size:.75rem" onclick="clearCart()">Clear</button>
    </div>
  </div>
</div>
</form>

<script>
let cart=[];

function filterCat(btn,catId){
  document.querySelectorAll('.cat-btn').forEach(b=>b.className='btn btn-ghost btn-sm cat-btn');
  btn.className='btn btn-primary btn-sm cat-btn';
  document.querySelectorAll('.product-card').forEach(c=>c.style.display=(catId==='all'||c.dataset.cat==catId)?'':'none');
}
function searchMenu(q){q=q.toLowerCase();document.querySelectorAll('.product-card').forEach(c=>c.style.display=c.dataset.name.includes(q)?'':'none');}

function addItem(p){
  console.log('addItem called with:', p);
  const ex=cart.find(i=>i.id===p.id);
  if(ex) ex.qty++;
  else cart.push({...p,qty:1});
  console.log('cart after add:', cart);
  renderCart();
}


function renderCart(){
  console.log('renderCart called, cart length:', cart.length);
  const el=document.getElementById('cart-items');
  const emp=document.getElementById('cart-empty');
  const inp=document.getElementById('cart-inputs');
  if(!cart.length){emp.style.display='flex';inp.innerHTML='';updateTotal();return;}
  emp.style.display='none';
  el.innerHTML=cart.map(it=>`
    <div class="cart-row flex ai-c gap" style="padding:9px 0;border-bottom:1px solid rgba(192,122,47,.07)" data-price="${it.price}">
      <div class="f1"><div class="text-cream" style="font-size:.81rem;font-weight:500">${it.name}</div><div class="text-gold" style="font-size:.74rem">${it.price.toFixed(2)} DT</div></div>
      <button type="button" data-action="decrease" data-id="${it.id}" style="width:20px;height:20px;border-radius:50%;background:rgba(255,255,255,.07);border:1px solid var(--border);color:var(--cream);cursor:pointer;font-size:.72rem">−</button>
      <input type="number" data-action="qty" data-id="${it.id}" value="${it.qty}" min="1" style="width:32px;text-align:center;background:none;border:none;color:var(--cream);font-size:.82rem" readonly>
      <button type="button" data-action="increase" data-id="${it.id}" style="width:20px;height:20px;border-radius:50%;background:rgba(255,255,255,.07);border:1px solid var(--border);color:var(--cream);cursor:pointer;font-size:.72rem">+</button>
      <span class="row-subtotal" style="min-width:46px;text-align:right;color:var(--gold);font-size:.8rem">${(it.price*it.qty).toFixed(2)}</span>
      <button type="button" data-action="remove" data-id="${it.id}" style="background:none;border:none;color:var(--text-dim);cursor:pointer;font-size:.8rem">✕</button>
    </div>`).join('');
  inp.innerHTML=cart.map(it=>`<input type="hidden" name="products[]" value="${it.id}"><input type="hidden" name="qtys[]" value="${it.qty}">`).join('');
  updateTotal();
}

const cartItemsContainer = document.getElementById('cart-items');
cartItemsContainer.addEventListener('click', function(event){
  const button = event.target.closest('button[data-action]');
  if(!button) return;
  const id = Number(button.dataset.id);
  const action = button.dataset.action;
  if(action === 'decrease') chg(id, -1);
  if(action === 'increase') chg(id, 1);
  if(action === 'remove') rem(id);
});

cartItemsContainer.addEventListener('input', function(event){
  const input = event.target.closest('input[data-action="qty"]');
  if(!input) return;
  const id = Number(input.dataset.id);
  setQty(id, input.value);
});

cartItemsContainer.addEventListener('blur', function(event){
  const input = event.target.closest('input[data-action="qty"]');
  if(!input) return;
  const id = Number(input.dataset.id);
  validateQty(id, input);
}, true);

function chg(id, delta){
  const item = cart.find(i => i.id === id);
  if(!item) return;
  item.qty += delta;
  if(item.qty < 1){
    cart = cart.filter(i => i.id !== id);
  }
  renderCart();
}

function setQty(id, value){
  const item = cart.find(i => i.id === id);
  if(!item) return;
  const qty = Math.max(1, parseInt(value, 10) || 1);
  item.qty = qty;
  renderCart();
}

function validateQty(id, input){
  const qty = Math.max(1, parseInt(input.value, 10) || 1);
  input.value = qty;
  setQty(id, qty);
}

function rem(id){
  cart = cart.filter(i => i.id !== id);
  renderCart();
}
function clearCart(){cart=[];renderCart();}
function updateTotal(){
  let total = 0;
  let itemCount = 0;
  cart.forEach(item => {
    total += item.price * item.qty;
    itemCount += item.qty;
  });
  document.getElementById('cart-total').textContent = total.toFixed(2) + ' DT';
  document.getElementById('place-btn').disabled = cart.length === 0;
  const summary = document.getElementById('cart-summary');
  if(itemCount > 0){
    summary.textContent = `${itemCount} item${itemCount !== 1 ? 's' : ''}`;
    summary.style.display = 'block';
  } else {
    summary.style.display = 'none';
  }
}

// Client search (basic - searches by typing then selects)
let clientTimer;
function searchClient(q){
  clearTimeout(clientTimer);
  if(q.length<2){document.getElementById('client-results').style.display='none';document.getElementById('client-id').value='';return;}
  clientTimer=setTimeout(async()=>{
    try{
      const res=await fetch('?search_client='+encodeURIComponent(q));
      if(!res.ok) throw new Error('Search failed');
      const data=await res.json();
      const box=document.getElementById('client-results');
      if(!data.length){box.style.display='none';return;}
      box.style.display='block';
      box.innerHTML=data.map(c=>`<div onclick="pickClient(${c.id},'${c.name.replace(/'/g,"\\'")} — ${c.email}')" style="padding:9px 12px;cursor:pointer;font-size:.79rem;border-bottom:1px solid var(--border);color:var(--latte)" onmouseenter="this.style.background='rgba(192,122,47,.1)'" onmouseleave="this.style.background=''"><div class="text-cream">${c.name}</div><div style="font-size:.7rem;color:var(--text-dim)">${c.email} · ⭐${c.points}</div></div>`).join('');
    }catch(e){console.log('Search error:',e);}
  },300);
}
function pickClient(id,label){
  document.getElementById('client-id').value=id;
  document.getElementById('client-search').value=label;
  document.getElementById('client-results').style.display='none';
}
</script>
<?php
// AJAX client search
if (isset($_GET['search_client'])) {
    header('Content-Type: application/json');
    $q='%'.trim($_GET['search_client']).'%';
    $r=$pdo->prepare("SELECT id,name,email,points FROM clients WHERE (name LIKE ? OR email LIKE ?) AND is_active=1 LIMIT 6");
    $r->execute([$q,$q]);
    echo json_encode($r->fetchAll());
    exit;
}
pageEnd();
?>

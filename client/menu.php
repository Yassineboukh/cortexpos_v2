<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('client'); $pdo = db();

// Handle order placement
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    $tableId = $_POST['table_id'] ? (int)$_POST['table_id'] : null;
    $notes   = trim($_POST['notes'] ?? '');
    $pids    = $_POST['products'] ?? [];
    $qtys    = $_POST['qtys']     ?? [];

    $items=[]; $subtotal=0; $pts=0;
    foreach ($pids as $i=>$pid) {
        $qty=max(1,(int)($qtys[$i]??1)); if(!$pid) continue;
        $p=$pdo->prepare("SELECT price,points_earn,name FROM products WHERE id=? AND is_available=1");$p->execute([$pid]);$p=$p->fetch();
        if (!$p) continue;
        $items[]=['product_id'=>(int)$pid,'qty'=>$qty,'price'=>$p['price'],'name'=>$p['name'],'pts'=>$p['points_earn']*$qty];
        $subtotal+=$p['price']*$qty; $pts+=$p['points_earn']*$qty;
    }
    if (empty($items)) { flash('error','Add at least one item.'); redirect(APP_URL.'/client/menu.php'); }

    $pdo->beginTransaction();
    try {
        $ref=generateOrderRef();
        $pdo->prepare("INSERT INTO orders (order_ref,client_id,table_id,status,type,subtotal,total,points_earned,notes) VALUES (?,?,?,'pending',?,?,?,?,?)")
            ->execute([$ref,$user['id'],$tableId,$tableId?'dine_in':'takeaway',$subtotal,$subtotal,$pts,$notes]);
        $oid=(int)$pdo->lastInsertId();
        foreach ($items as $it) {
            $pdo->prepare("INSERT INTO order_items (order_id,product_id,quantity,unit_price,subtotal) VALUES (?,?,?,?,?)")->execute([$oid,$it['product_id'],$it['qty'],$it['price'],$it['price']*$it['qty']]);
        }
        deductStock($oid);
        if ($tableId) $pdo->prepare("UPDATE cafe_tables SET status='occupied',client_id=? WHERE id=?")->execute([$user['id'],$tableId]);
        $pdo->commit();
        flash('success',"Order {$ref} placed! A cashier will confirm shortly. +{$pts} points pending.");
        redirect(APP_URL.'/client/my_orders.php');
    } catch (Exception $e) {
        $pdo->rollback();
        flash('error','Failed to place order. Please try again.');
        redirect(APP_URL.'/client/menu.php');
    }
}

$categories=$pdo->query("SELECT * FROM categories WHERE is_active=1 ORDER BY sort_order")->fetchAll();
$products=$pdo->query("SELECT p.*,c.name as cname FROM products p JOIN categories c ON p.category_id=c.id WHERE p.is_available=1 ORDER BY c.sort_order,p.name")->fetchAll();
$tables=$pdo->query("SELECT * FROM cafe_tables WHERE status='free' ORDER BY number")->fetchAll();
$usual=$pdo->prepare("SELECT p.id,p.name,p.price,COUNT(*) as cnt FROM order_items oi JOIN orders o ON oi.order_id=o.id JOIN products p ON oi.product_id=p.id WHERE o.client_id=? AND o.status!='cancelled' GROUP BY p.id ORDER BY cnt DESC LIMIT 3");
$usual->execute([$user['id']]); $usual=$usual->fetchAll();

pageHead('Menu'); sidebar($user); topbar('Menu','Order Now'); showFlash();
?>
<form method="POST" id="order-form">
<?php csrfField(); ?>
<div style="display:grid;grid-template-columns:1fr 300px;gap:18px">
  <!-- Menu -->
  <div>
    <?php if($usual): ?>
    <div class="card mb-4">
      <div class="card-title">⭐ Your Usual</div>
      <div class="flex gap flex-wrap">
      <?php foreach($usual as $p): ?>
      <button type="button" onclick='quickAdd(<?=$p["id"]?>,<?=json_encode($p["name"])?>,<?=$p["price"]?>)' style="padding:10px 16px;background:rgba(212,146,42,.08);border:1px solid rgba(212,146,42,.3);border-radius:var(--radius-sm);cursor:pointer;color:var(--cream);font-size:.81rem;transition:var(--transition)" onmouseenter="this.style.borderColor='var(--gold)'" onmouseleave="this.style.borderColor='rgba(212,146,42,.3)'">☕ <?=e($p['name'])?> <span class="text-gold"><?=number_format($p['price'],2)?> DT</span></button>
      <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
    <div class="flex gap mb-3 flex-wrap">
      <button type="button" class="btn btn-primary btn-sm cat-btn" data-cat="all" onclick="filterCat(this,'all')">All</button>
      <?php foreach($categories as $cat): ?><button type="button" class="btn btn-ghost btn-sm cat-btn" data-cat="<?=$cat['id']?>" onclick="filterCat(this,'<?=$cat['id']?>')"><?=e($cat['icon'])?> <?=e($cat['name'])?></button><?php endforeach; ?>
    </div>
    <div class="search-bar mb-4"><span class="text-dim">⌕</span><input type="text" placeholder="Search…" oninput="searchMenu(this.value)"></div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px" id="menu-grid">
    <?php foreach($products as $p): ?>
    <div class="product-card" data-cat="<?=$p['category_id']?>" data-name="<?=strtolower(e($p['name']))?>"
      style="background:var(--dark-roast);border:1px solid var(--border);border-radius:var(--radius);padding:16px;cursor:pointer;transition:var(--transition);text-align:center"
      onclick='addItem(<?=json_encode(["id"=>$p["id"],"name"=>$p["name"],"price"=>(float)$p["price"],"pts"=>(int)$p["points_earn"]])?>)'
      onmouseenter="this.style.borderColor='rgba(212,146,42,.6)';this.style.transform='translateY(-2px)'"
      onmouseleave="this.style.borderColor='var(--border)';this.style.transform='none'">
      <div style="font-size:1.6rem;margin-bottom:8px">☕</div>
      <div class="text-cream" style="font-weight:500;font-size:.83rem;margin-bottom:4px"><?=e($p['name'])?></div>
      <div class="text-gold" style="font-weight:600;font-size:.87rem"><?=number_format($p['price'],2)?> DT</div>
      <?php if($p['points_earn']>0): ?><div class="badge b-gold" style="margin-top:6px;font-size:.57rem">+<?=$p['points_earn']?></div><?php endif; ?>
    </div>
    <?php endforeach; ?>
    </div>
  </div>

  <!-- Cart -->
  <div style="background:var(--dark-roast);border:1px solid var(--border);border-radius:var(--radius);display:flex;flex-direction:column;position:sticky;top:0;max-height:calc(100vh-100px);overflow:hidden">
    <div style="padding:14px 16px;border-bottom:1px solid var(--border)">
      <div class="card-title mb-3">Your Order</div>
      <div class="form-group mb-0">
        <label class="form-label">Table</label>
        <select class="form-select" name="table_id" style="font-size:.79rem">
          <option value="">No Table (Takeaway)</option>
          <?php foreach($tables as $t): ?><option value="<?=$t['id']?>">Table <?=$t['number']?> (<?=e($t['zone'])?>)</option><?php endforeach; ?>
        </select>
      </div>
    </div>
    <div id="cart-list" style="flex:1;overflow-y:auto;padding:12px 16px;min-height:80px">
      <div id="cart-empty" class="flex ai-c jc-c" style="height:80px;color:var(--text-dim);font-size:.8rem;flex-direction:column;gap:6px"><span style="font-size:1.6rem">🛒</span>Add items</div>
    </div>
    <div id="cart-inputs"></div>
    <div style="padding:14px 16px;border-top:1px solid var(--border)">
      <div class="form-group mb-3"><textarea class="form-textarea" name="notes" placeholder="Special requests…" style="min-height:50px;font-size:.79rem"></textarea></div>
      <div class="flex ai-c jc-b mb-2 text-sm"><span class="text-dim">Points to earn</span><span id="cart-pts" class="badge b-gold" style="font-size:.61rem">+0</span></div>
      <div class="flex ai-c jc-b mb-4"><span class="text-dim text-sm">Total</span><span id="cart-total" class="text-gold serif" style="font-size:1.2rem">0.00 DT</span></div>
      <button type="submit" id="place-btn" class="btn btn-primary btn-w" disabled style="padding:12px">Place Order</button>
      <button type="button" class="btn btn-ghost btn-w" style="margin-top:7px;font-size:.75rem" onclick="clearCart()">Clear</button>
    </div>
  </div>
</div>
</form>

<script>
let cart=[];
function filterCat(btn,c){document.querySelectorAll('.cat-btn').forEach(b=>b.className='btn btn-ghost btn-sm cat-btn');btn.className='btn btn-primary btn-sm cat-btn';document.querySelectorAll('.product-card').forEach(el=>el.style.display=(c==='all'||el.dataset.cat==c)?'':'none');}
function searchMenu(q){q=q.toLowerCase();document.querySelectorAll('.product-card').forEach(el=>el.style.display=el.dataset.name.includes(q)?'':'none');}
function quickAdd(id,name,price){addItem({id:id,name:name,price:price,pts:0});}
function addItem(p){
  const itemId = Number(p.id);
  const existing = cart.find(item=>item.id===itemId);
  if(existing){
    existing.qty += 1;
  } else {
    cart.push({id:itemId, name:String(p.name), price:parseFloat(p.price), pts:parseInt(p.pts,10)||0, qty:1});
  }
  renderCart();
}
function renderCart(){
  const el=document.getElementById('cart-list');
  const emp=document.getElementById('cart-empty');
  const inp=document.getElementById('cart-inputs');
  if(!el||!emp||!inp)return;
  if(cart.length===0){
    emp.style.display='flex';
    el.innerHTML='';
    inp.innerHTML='';
    updateTotal();
    return;
  }
  emp.style.display='none';
  el.innerHTML=cart.map(item=>`
    <div class="flex ai-c gap" style="padding:9px 0;border-bottom:1px solid rgba(192,122,47,.07)" data-id="${item.id}">
      <div class="f1">
        <div class="text-cream" style="font-size:.8rem;font-weight:500">${item.name}</div>
        <div class="text-gold" style="font-size:.73rem">${item.price.toFixed(2)} DT</div>
      </div>
      <button type="button" data-action="decrease" data-id="${item.id}" style="width:20px;height:20px;border-radius:50%;background:rgba(255,255,255,.07);border:1px solid var(--border);color:var(--cream);cursor:pointer;font-size:.72rem">−</button>
      <span style="min-width:18px;text-align:center;font-size:.8rem;color:var(--cream)">${item.qty}</span>
      <button type="button" data-action="increase" data-id="${item.id}" style="width:20px;height:20px;border-radius:50%;background:rgba(255,255,255,.07);border:1px solid var(--border);color:var(--cream);cursor:pointer;font-size:.72rem">+</button>
      <span style="min-width:42px;color:var(--gold);font-size:.79rem">${(item.price*item.qty).toFixed(2)}</span>
      <button type="button" data-action="remove" data-id="${item.id}" style="background:none;border:none;color:var(--text-dim);cursor:pointer;font-size:.79rem">✕</button>
    </div>
  `).join('');
  inp.innerHTML=cart.map(item=>`<input type="hidden" name="products[]" value="${item.id}"><input type="hidden" name="qtys[]" value="${item.qty}">`).join('');
  updateTotal();
}
const cartList=document.getElementById('cart-list');
if(cartList){
  cartList.addEventListener('click',function(event){
    const button=event.target.closest('button[data-action]');
    if(!button)return;
    const id=Number(button.dataset.id);
    const action=button.dataset.action;
    if(action==='decrease')changeQty(id,-1);
    if(action==='increase')changeQty(id,1);
    if(action==='remove')removeItem(id);
  });
}
function changeQty(id,delta){
  const item=cart.find(item=>item.id===id);
  if(!item)return;
  item.qty += delta;
  if(item.qty < 1){cart = cart.filter(item=>item.id!==id);}
  renderCart();
}
function removeItem(id){cart = cart.filter(item=>item.id!==id);renderCart();}
function clearCart(){cart=[];renderCart();}
function updateTotal(){
  const total=cart.reduce((sum,item)=>sum + item.price*item.qty,0);
  const points=cart.reduce((sum,item)=>sum + item.pts*item.qty,0);
  const totalEl=document.getElementById('cart-total');
  const pointsEl=document.getElementById('cart-pts');
  const placeBtn=document.getElementById('place-btn');
  if(totalEl)totalEl.textContent=total.toFixed(2) + ' DT';
  if(pointsEl)pointsEl.textContent='+' + points;
  if(placeBtn)placeBtn.disabled = cart.length === 0;
}
</script>
<?php pageEnd(); ?>

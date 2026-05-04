<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('cashier'); $pdo = db();

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='mark_sent') {
    verifyCsrf();
    $pdo->prepare("UPDATE orders SET receipt_sent=1 WHERE id=?")->execute([(int)$_POST['id']]);
    flash('success','Receipt marked as sent.');
    redirect(APP_URL.'/cashier/receipts.php');
}

$q=trim($_GET['q']??'');
$where="WHERE o.cashier_id=?"; $params=[$user['id']];
if ($q) { $where.=" AND (o.order_ref LIKE ? OR c.name LIKE ?)"; $params[]="%$q%"; $params[]="%$q%"; }

$orders=$pdo->prepare("SELECT o.*,c.name as cn,c.email as cemail,t.number as tnum FROM orders o LEFT JOIN clients c ON o.client_id=c.id LEFT JOIN cafe_tables t ON o.table_id=t.id $where ORDER BY o.created_at DESC LIMIT 30");
$orders->execute($params); $orders=$orders->fetchAll();

// View single receipt
$viewId=(int)($_GET['print']??0);
$receipt=null; $receiptItems=[];
if ($viewId) {
    $r=$pdo->prepare("SELECT o.*,c.name as cn,t.number as tnum FROM orders o LEFT JOIN clients c ON o.client_id=c.id LEFT JOIN cafe_tables t ON o.table_id=t.id WHERE o.id=? AND o.cashier_id=?");
    $r->execute([$viewId,$user['id']]); $receipt=$r->fetch();
    if ($receipt) { $ri=$pdo->prepare("SELECT oi.*,p.name FROM order_items oi JOIN products p ON oi.product_id=p.id WHERE oi.order_id=?"); $ri->execute([$viewId]); $receiptItems=$ri->fetchAll(); }
}

pageHead('Receipts'); sidebar($user); topbar('Receipts','Print & Send'); showFlash();
?>
<div class="g2" style="gap:18px;align-items:start">
  <div>
    <form method="GET" class="flex gap mb-4">
      <div class="search-bar f1"><span class="text-dim">⌕</span><input type="text" name="q" value="<?=e($q)?>" placeholder="Search ref or client…"></div>
      <button class="btn btn-primary">Search</button>
    </form>
    <div class="card">
      <table class="tbl">
        <thead><tr><th>Ref</th><th>Client</th><th>Total</th><th>Receipt</th><th>Date</th><th></th></tr></thead>
        <tbody>
        <?php foreach($orders as $o): ?>
        <tr>
          <td style="font-family:monospace;font-size:.72rem;color:var(--gold)"><?=e($o['order_ref'])?></td>
          <td><?=e($o['cn']??'Walk-in')?></td>
          <td class="text-gold"><?=number_format($o['total'],2)?> DT</td>
          <td><?=$o['receipt_sent']?'<span class="badge b-success">✓ Sent</span>':'<span class="badge b-dim">Pending</span>'?></td>
          <td class="text-dim text-sm"><?=date('d M, H:i',strtotime($o['created_at']))?></td>
          <td class="flex gap-sm">
            <a href="?print=<?=$o['id']?>" class="btn btn-ghost btn-sm">🖨 Print</a>
            <?php if(!$o['receipt_sent']): ?>
            <form method="POST" style="display:inline"><?php csrfField(); ?><input type="hidden" name="action" value="mark_sent"><input type="hidden" name="id" value="<?=$o['id']?>"><button class="btn btn-success btn-sm">✉ Mark Sent</button></form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($orders)): ?><tr><td colspan="6" style="text-align:center;color:var(--text-dim);padding:24px">No orders</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Receipt preview -->
  <?php if ($receipt): ?>
  <div class="card" id="receipt-preview" style="font-family:monospace;font-size:.82rem">
    <div style="text-align:center;margin-bottom:16px">
      <div class="serif" style="font-size:1.2rem;color:var(--cream);letter-spacing:.1em">CORTEXPOS</div>
      <div class="text-xs text-dim">Smart Café Ecosystem</div>
      <div class="divider"></div>
    </div>
    <div class="flex ai-c jc-b mb-2 text-xs"><span class="text-dim">Ref:</span><span><?=e($receipt['order_ref'])?></span></div>
    <div class="flex ai-c jc-b mb-2 text-xs"><span class="text-dim">Date:</span><span><?=date('d M Y H:i',strtotime($receipt['created_at']))?></span></div>
    <div class="flex ai-c jc-b mb-2 text-xs"><span class="text-dim">Table:</span><span><?=$receipt['tnum']?'Table '.$receipt['tnum']:'Takeaway'?></span></div>
    <div class="flex ai-c jc-b mb-3 text-xs"><span class="text-dim">Client:</span><span><?=e($receipt['cn']??'Walk-in')?></span></div>
    <div class="divider"></div>
    <?php foreach($receiptItems as $item): ?>
    <div class="flex ai-c jc-b mb-2 text-xs"><span><?=$item['quantity']?>× <?=e($item['name'])?></span><span><?=number_format($item['subtotal'],2)?> DT</span></div>
    <?php endforeach; ?>
    <div class="divider"></div>
    <div class="flex ai-c jc-b" style="font-weight:700;color:var(--gold)"><span>TOTAL</span><span><?=number_format($receipt['total'],2)?> DT</span></div>
    <?php if($receipt['points_earned']): ?><div class="text-xs text-dim tc" style="margin-top:10px">+<?=$receipt['points_earned']?> loyalty points earned ⭐</div><?php endif; ?>
    <div class="text-xs text-dim tc" style="margin-top:12px">Thank you for visiting!</div>
    <div class="divider"></div>
    <button onclick="window.print()" class="btn btn-primary btn-w">🖨 Print Receipt</button>
  </div>
  <?php else: ?><div class="card flex ai-c jc-c" style="height:300px;color:var(--text-dim)">← Select an order to print</div><?php endif; ?>
</div>

<style>@media print{.sidebar,.topbar,.main > .topbar,.card:not(#receipt-preview),.flex.gap{display:none!important}#receipt-preview{border:none;box-shadow:none}}</style>
<?php pageEnd(); ?>

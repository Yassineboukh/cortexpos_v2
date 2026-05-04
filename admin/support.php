<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('admin'); $pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'reply') {
        $tid = (int)$_POST['ticket_id'];
        $msg = trim($_POST['message'] ?? '');
        $st  = $_POST['status'] ?? '';
        if ($msg) {
            $pdo->prepare("INSERT INTO ticket_messages (ticket_id,sender_role,sender_id,message) VALUES (?,'admin',?,?)")->execute([$tid,$user['id'],$msg]);
            if ($st) $pdo->prepare("UPDATE support_tickets SET status=?,updated_at=NOW() WHERE id=?")->execute([$st,$tid]);
            else      $pdo->prepare("UPDATE support_tickets SET updated_at=NOW() WHERE id=?")->execute([$tid]);
            flash('success','Reply sent.');
        } else flash('error','Message cannot be empty.');
    }
    redirect(APP_URL.'/admin/support.php'.($tid?'?view='.$tid:''));
}

$statusFilter = $_GET['status'] ?? '';
$viewId       = (int)($_GET['view'] ?? 0);
$where        = $statusFilter ? "WHERE st.status='".htmlspecialchars($statusFilter)."'" : '';
$tickets = $pdo->query("SELECT st.*,c.name as cname,c.email as cemail,(SELECT COUNT(*) FROM ticket_messages WHERE ticket_id=st.id) as msgs FROM support_tickets st JOIN clients c ON st.client_id=c.id $where ORDER BY st.status='open' DESC,st.updated_at DESC")->fetchAll();
$counts  = $pdo->query("SELECT status,COUNT(*) as n FROM support_tickets GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);

// If viewing a ticket
$activeTicket = null; $activeMessages = [];
if ($viewId) {
    $t=$pdo->prepare("SELECT st.*,c.name as cname,c.email as cemail FROM support_tickets st JOIN clients c ON st.client_id=c.id WHERE st.id=?"); $t->execute([$viewId]); $activeTicket=$t->fetch();
    if ($activeTicket) {
        $m=$pdo->prepare("SELECT tm.*,CASE tm.sender_role WHEN 'client' THEN (SELECT name FROM clients WHERE id=tm.sender_id) ELSE (SELECT name FROM users WHERE id=tm.sender_id) END as sname FROM ticket_messages tm WHERE tm.ticket_id=? ORDER BY tm.created_at ASC");
        $m->execute([$viewId]); $activeMessages=$m->fetchAll();
    }
}

pageHead('Support'); sidebar($user); topbar('Support','Ticket Management'); showFlash();
?>
<div class="flex gap mb-4">
  <?php foreach ([''=>'All','open'=>'Open','in_progress'=>'In Progress','resolved'=>'Resolved','closed'=>'Closed'] as $v=>$l): ?>
  <a href="?status=<?=$v?>" class="btn btn-sm <?=$statusFilter===$v?'btn-primary':'btn-ghost'?>"><?=$l?><?php if($v&&($counts[$v]??0)): ?> <span style="background:var(--caramel);color:var(--espresso);border-radius:10px;padding:1px 6px;font-size:.6rem;margin-left:4px"><?=$counts[$v]?></span><?php endif;?></a>
  <?php endforeach; ?>
</div>

<div class="g2" style="gap:18px;align-items:start">
  <!-- Ticket list -->
  <div class="card" style="padding:0">
    <?php foreach ($tickets as $t): $bmap=['open'=>'b-warning','in_progress'=>'b-info','resolved'=>'b-success','closed'=>'b-dim']; ?>
    <a href="?<?=$statusFilter?'status='.$statusFilter.'&':''?>view=<?=$t['id']?>" style="display:block;padding:14px 18px;border-bottom:1px solid rgba(192,122,47,.07);text-decoration:none;<?=$viewId==$t['id']?'background:rgba(192,122,47,.06);':''?>transition:var(--transition)" onmouseenter="this.style.background='rgba(192,122,47,.04)'" onmouseleave="this.style.background='<?=$viewId==$t['id']?'rgba(192,122,47,.06)':'transparent'?>'">
      <div class="flex ai-c jc-b">
        <div class="flex ai-c gap-sm"><span class="badge <?=$bmap[$t['status']]??'b-dim'?>"><?=ucfirst(str_replace('_',' ',$t['status']))?></span><div class="text-cream text-sm" style="font-weight:500"><?=e($t['subject'])?></div></div>
        <div class="text-xs text-dim"><?=$t['msgs']?> msg<?=$t['msgs']!=1?'s':''?></div>
      </div>
      <div class="text-xs text-dim" style="margin-top:4px"><?=e($t['cname'])?> · <?=ucfirst(str_replace('_',' ',$t['category']))?> · <?=date('d M H:i',strtotime($t['updated_at']))?></div>
    </a>
    <?php endforeach; ?>
    <?php if (empty($tickets)): ?><div style="text-align:center;color:var(--text-dim);padding:32px">No tickets found</div><?php endif; ?>
  </div>

  <!-- Ticket thread -->
  <?php if ($activeTicket): ?>
  <div class="card">
    <div class="flex ai-c jc-b mb-3">
      <div><div class="text-cream" style="font-weight:500"><?=e($activeTicket['subject'])?></div>
        <div class="text-xs text-dim"><?=e($activeTicket['cname'])?> · <?=e($activeTicket['cemail'])?> · <?=ucfirst(str_replace('_',' ',$activeTicket['category']))?></div>
      </div>
      <span class="badge <?=$bmap[$activeTicket['status']]??'b-dim'?>"><?=ucfirst(str_replace('_',' ',$activeTicket['status']))?></span>
    </div>
    <div style="max-height:360px;overflow-y:auto;display:flex;flex-direction:column;gap:10px;margin-bottom:16px">
      <?php foreach ($activeMessages as $m): ?>
      <div style="display:flex;gap:10px;flex-direction:<?=$m['sender_role']==='client'?'row':'row-reverse'?>">
        <div class="av" style="width:28px;height:28px;font-size:.62rem;flex-shrink:0"><?=strtoupper(substr($m['sname']??'?',0,1))?></div>
        <div style="max-width:75%">
          <div style="background:<?=$m['sender_role']==='client'?'rgba(255,255,255,.04)':'rgba(192,122,47,.1)'?>;border:1px solid var(--border);border-radius:var(--radius-sm);padding:10px 14px;font-size:.8rem;color:var(--cream);line-height:1.5"><?=nl2br(e($m['message']))?></div>
          <div class="text-xs text-dim" style="margin-top:3px;text-align:<?=$m['sender_role']==='client'?'left':'right'?>"><?=e($m['sname']??'')?> · <?=date('d M H:i',strtotime($m['created_at']))?></div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($activeMessages)): ?><p class="text-dim text-sm tc">No messages yet.</p><?php endif; ?>
    </div>
    <div class="divider"></div>
    <form method="POST" style="margin-top:14px">
      <?php csrfField(); ?><input type="hidden" name="action" value="reply"><input type="hidden" name="ticket_id" value="<?=$activeTicket['id']?>">
      <div class="form-group"><label class="form-label">Reply</label><textarea class="form-textarea" name="message" required placeholder="Type your reply…" style="min-height:80px"></textarea></div>
      <div class="flex ai-c jc-b">
        <select class="form-select" name="status" style="width:170px">
          <option value="">Keep Current Status</option>
          <option value="in_progress">Mark In Progress</option>
          <option value="resolved">Mark Resolved</option>
          <option value="closed">Close Ticket</option>
        </select>
        <button type="submit" class="btn btn-primary">Send Reply</button>
      </div>
    </form>
  </div>
  <?php else: ?>
  <div class="card flex ai-c jc-c" style="height:300px;color:var(--text-dim)">← Select a ticket to view</div>
  <?php endif; ?>
</div>
<?php pageEnd(); ?>

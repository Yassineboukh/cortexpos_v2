<?php
// client/support.php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
$user=requireLogin('client'); $pdo=db();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    $action=$_POST['action']??'';
    if ($action==='create') {
        $sub=trim($_POST['subject']??''); $msg=trim($_POST['message']??''); $cat=$_POST['category']??'other';
        if (!$sub||!$msg) flash('error','Subject and message are required.');
        else {
            $pdo->beginTransaction();
            try {
                $pdo->prepare("INSERT INTO support_tickets (client_id,category,subject) VALUES (?,?,?)")->execute([$user['id'],$cat,$sub]);
                $tid=(int)$pdo->lastInsertId();
                $pdo->prepare("INSERT INTO ticket_messages (ticket_id,sender_role,sender_id,message) VALUES (?,'client',?,?)")->execute([$tid,$user['id'],$msg]);
                $pdo->commit();
                flash('success','Ticket submitted. We\'ll respond shortly.');
            } catch (Exception $e) {
                $pdo->rollback();
                flash('error','Failed to submit ticket. Please try again.');
            }
        }
    } elseif ($action==='reply') {
        $tid=(int)$_POST['ticket_id']; $msg=trim($_POST['message']??'');
        $t=$pdo->prepare("SELECT client_id FROM support_tickets WHERE id=?");$t->execute([$tid]);$t=$t->fetch();
        if ($t&&$t['client_id']==$user['id']&&$msg) {
            $pdo->prepare("INSERT INTO ticket_messages (ticket_id,sender_role,sender_id,message) VALUES (?,'client',?,?)")->execute([$tid,$user['id'],$msg]);
            $pdo->prepare("UPDATE support_tickets SET updated_at=NOW() WHERE id=?")->execute([$tid]);
            flash('success','Reply sent.');
        }
    }
    redirect(APP_URL.'/client/support.php'.($_POST['ticket_id']??''?'?view='.$_POST['ticket_id']:''));
}

$tickets=$pdo->prepare("SELECT st.*,(SELECT COUNT(*) FROM ticket_messages WHERE ticket_id=st.id) as msgs FROM support_tickets st WHERE st.client_id=? ORDER BY st.updated_at DESC");$tickets->execute([$user['id']]);$tickets=$tickets->fetchAll();
$viewId=(int)($_GET['view']??0);
$active=null; $messages=[];
if ($viewId) {
    $a=$pdo->prepare("SELECT * FROM support_tickets WHERE id=? AND client_id=?");$a->execute([$viewId,$user['id']]);$active=$a->fetch();
    if ($active) { $m=$pdo->prepare("SELECT tm.*,CASE tm.sender_role WHEN 'client' THEN (SELECT name FROM clients WHERE id=tm.sender_id) ELSE (SELECT name FROM users WHERE id=tm.sender_id) END as sname FROM ticket_messages tm WHERE tm.ticket_id=? ORDER BY tm.created_at ASC");$m->execute([$viewId]);$messages=$m->fetchAll(); }
}
pageHead('Support'); sidebar($user); topbar('Support','Help & Support'); showFlash();
?>
<div class="flex ai-c jc-b mb-4"><div class="text-dim text-sm"><?=count($tickets)?> ticket<?=count($tickets)!=1?'s':''?></div><button class="btn btn-primary" onclick="openModal('new-ticket-modal')">+ New Ticket</button></div>
<div class="g2" style="gap:18px;align-items:start">
  <div>
  <?php if($tickets): ?>
  <div style="display:flex;flex-direction:column;gap:8px">
  <?php foreach($tickets as $t): $bmap=['open'=>'b-warning','in_progress'=>'b-info','resolved'=>'b-success','closed'=>'b-dim']; ?>
  <a href="?view=<?=$t['id']?>" style="display:block;padding:14px 16px;background:var(--dark-roast);border:1px solid <?=$viewId==$t['id']?'rgba(192,122,47,.5)':'var(--border)'?>;border-radius:var(--radius);text-decoration:none;transition:var(--transition)" onmouseenter="this.style.borderColor='rgba(192,122,47,.4)'" onmouseleave="this.style.borderColor='<?=$viewId==$t['id']?'rgba(192,122,47,.5)':'var(--border)'?>'">
    <div class="flex ai-c jc-b"><div class="flex ai-c gap-sm"><span class="badge <?=$bmap[$t['status']]??'b-dim'?>"><?=ucfirst(str_replace('_',' ',$t['status']))?></span><div class="text-cream text-sm" style="font-weight:500"><?=e($t['subject'])?></div></div><div class="text-xs text-dim"><?=$t['msgs']?> msg<?=$t['msgs']!=1?'s':''?></div></div>
    <div class="text-xs text-dim" style="margin-top:4px"><?=ucfirst(str_replace('_',' ',$t['category']))?> · <?=date('d M H:i',strtotime($t['updated_at']))?></div>
  </a>
  <?php endforeach; ?>
  </div>
  <?php else: ?><div class="card flex ai-c jc-c" style="height:180px;flex-direction:column;gap:12px;color:var(--text-dim)"><div style="font-size:2.5rem">◇</div><div>No tickets yet</div><button class="btn btn-primary btn-sm" onclick="openModal('new-ticket-modal')">Open a Ticket</button></div><?php endif; ?>
  </div>

  <?php if($active): ?>
  <div class="card">
    <div class="flex ai-c jc-b mb-3"><div class="text-cream" style="font-weight:500"><?=e($active['subject'])?></div><span class="badge <?=$bmap[$active['status']]??'b-dim'?>"><?=ucfirst(str_replace('_',' ',$active['status']))?></span></div>
    <div style="max-height:320px;overflow-y:auto;display:flex;flex-direction:column;gap:10px;margin-bottom:16px">
      <?php foreach($messages as $m): ?>
      <div style="display:flex;gap:10px;flex-direction:<?=$m['sender_role']==='client'?'row':'row-reverse'?>">
        <div class="av" style="width:26px;height:26px;font-size:.6rem;flex-shrink:0"><?=strtoupper(substr($m['sname']??'?',0,1))?></div>
        <div style="max-width:80%"><div style="background:<?=$m['sender_role']==='client'?'rgba(255,255,255,.04)':'rgba(192,122,47,.1)'?>;border:1px solid var(--border);border-radius:var(--radius-sm);padding:10px 13px;font-size:.8rem;color:var(--cream);line-height:1.5"><?=nl2br(e($m['message']))?></div>
        <div class="text-xs text-dim" style="margin-top:3px;text-align:<?=$m['sender_role']==='client'?'left':'right'?>"><?=e($m['sname']??'')?> · <?=date('d M H:i',strtotime($m['created_at']))?></div></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php if($active['status']!=='closed'&&$active['status']!=='resolved'): ?>
    <div class="divider"></div>
    <form method="POST" style="margin-top:12px">
      <?php csrfField(); ?><input type="hidden" name="action" value="reply"><input type="hidden" name="ticket_id" value="<?=$active['id']?>">
      <div class="form-group"><label class="form-label">Reply</label><textarea class="form-textarea" name="message" required placeholder="Type your message…" style="min-height:74px"></textarea></div>
      <button type="submit" class="btn btn-primary">Send</button>
    </form>
    <?php else: ?><p class="text-dim text-sm tc" style="margin-top:12px">This ticket is <?=$active['status']?>.</p><?php endif; ?>
  </div>
  <?php else: ?><div class="card flex ai-c jc-c" style="height:280px;color:var(--text-dim)">← Select a ticket</div><?php endif; ?>
</div>

<div class="overlay" id="new-ticket-modal">
  <div class="modal"><div class="modal-head"><div class="modal-title">New Support Ticket</div><button class="modal-close" onclick="closeModal('new-ticket-modal')">✕</button></div>
  <form method="POST">
    <?php csrfField(); ?><input type="hidden" name="action" value="create">
    <div class="form-group"><label class="form-label">Category</label><select class="form-select" name="category"><option value="order_issue">Order Issue</option><option value="reward_problem">Reward Problem</option><option value="lost_item">Lost Item</option><option value="other">Other</option></select></div>
    <div class="form-group"><label class="form-label">Subject</label><input type="text" class="form-input" name="subject" required placeholder="Brief description"></div>
    <div class="form-group"><label class="form-label">Message</label><textarea class="form-textarea" name="message" required placeholder="Describe your issue…" style="min-height:100px"></textarea></div>
    <div class="modal-foot"><button type="button" class="btn btn-ghost" onclick="closeModal('new-ticket-modal')">Cancel</button><button type="submit" class="btn btn-primary">Submit Ticket</button></div>
  </form></div>
</div>
<?php pageEnd(); ?>

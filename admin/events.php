<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('admin'); $pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if ($_POST['action'] === 'send') {
        $title = trim($_POST['title'] ?? '');
        if (!$title) flash('error', 'Title is required.');
        else {
            $pdo->prepare("INSERT INTO notifications (target_role,title,body,type) VALUES (?,?,?,?)")
                ->execute([$_POST['target'] ?? 'all', $title, trim($_POST['body'] ?? ''), $_POST['type'] ?? 'info']);
            flash('success', 'Notification sent.');
        }
    } elseif ($_POST['action'] === 'delete') {
        $pdo->prepare("DELETE FROM notifications WHERE id=?")->execute([(int)$_POST['id']]);
        flash('success', 'Deleted.');
    }
    redirect(APP_URL . '/admin/events.php');
}

$notifs = $pdo->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 50")->fetchAll();
pageHead('Events'); sidebar($user); topbar('Events', 'Promotions & Notifications'); showFlash();
?>
<div class="flex ai-c jc-b mb-4">
  <div class="text-dim text-sm"><?= count($notifs) ?> notifications</div>
  <button class="btn btn-primary" onclick="openModal('notif-modal')">+ New Notification</button>
</div>
<div class="card">
  <table class="tbl">
    <thead><tr><th>Title</th><th>Body</th><th>Target</th><th>Type</th><th>Date</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($notifs as $n): ?>
    <tr>
      <td class="text-cream" style="font-weight:500"><?= e($n['title']) ?></td>
      <td class="text-dim text-sm"><?= $n['body'] ? e(substr($n['body'],0,60)).'…' : '—' ?></td>
      <td><span class="badge b-dim"><?= ucfirst($n['target_role']) ?></span></td>
      <td><span class="badge <?= ['info'=>'b-info','promo'=>'b-gold','alert'=>'b-danger','leaderboard'=>'b-warning'][$n['type']] ?? 'b-dim' ?>"><?= ucfirst($n['type']) ?></span></td>
      <td class="text-dim text-sm"><?= date('d M, H:i', strtotime($n['created_at'])) ?></td>
      <td><form method="POST" style="display:inline" onsubmit="return confirm('Delete?')"><?php csrfField(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $n['id'] ?>"><button class="btn btn-danger btn-sm">Delete</button></form></td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($notifs)): ?><tr><td colspan="6" style="text-align:center;color:var(--text-dim);padding:28px">No notifications yet</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<div class="overlay" id="notif-modal">
  <div class="modal"><div class="modal-head"><div class="modal-title">New Notification</div><button class="modal-close" onclick="closeModal('notif-modal')">✕</button></div>
  <form method="POST">
    <?php csrfField(); ?><input type="hidden" name="action" value="send">
    <div class="form-group"><label class="form-label">Title</label><input type="text" class="form-input" name="title" required placeholder="Notification title"></div>
    <div class="form-group"><label class="form-label">Body (optional)</label><textarea class="form-textarea" name="body" placeholder="Message…"></textarea></div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Target</label>
        <select class="form-select" name="target"><option value="all">All Users</option><option value="client">All Clients</option><option value="cashier">All Cashiers</option><option value="admin">Admins</option></select>
      </div>
      <div class="form-group"><label class="form-label">Type</label>
        <select class="form-select" name="type"><option value="info">Info</option><option value="promo">Promotion</option><option value="alert">Alert</option><option value="leaderboard">Leaderboard</option></select>
      </div>
    </div>
    <div class="modal-foot"><button type="button" class="btn btn-ghost" onclick="closeModal('notif-modal')">Cancel</button><button type="submit" class="btn btn-primary">Send</button></div>
  </form></div>
</div>
<?php pageEnd(); ?>

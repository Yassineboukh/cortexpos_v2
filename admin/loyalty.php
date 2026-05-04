<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('admin');
$pdo  = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $name     = trim($_POST['name'] ?? '');
        $min_pts  = (int)($_POST['min_points'] ?? 0);
        $color    = trim($_POST['color'] ?? '#c07a2f');
        $icon     = trim($_POST['icon'] ?? '☕');
        $restores = (int)($_POST['restores'] ?? 5);
        $preorder = isset($_POST['preorder']) ? 1 : 0;
        $desc     = trim($_POST['desc'] ?? '');
        if (!$name) { flash('error', 'Name is required.'); }
        elseif ($action === 'add') {
            $pdo->prepare("INSERT INTO ranks (name,min_points,color,icon,streak_restores,can_preorder,description) VALUES (?,?,?,?,?,?,?)")
                ->execute([$name,$min_pts,$color,$icon,$restores,$preorder,$desc]);
            flash('success', 'Rank added.');
        } else {
            $pdo->prepare("UPDATE ranks SET name=?,min_points=?,color=?,icon=?,streak_restores=?,can_preorder=?,description=? WHERE id=?")
                ->execute([$name,$min_pts,$color,$icon,$restores,$preorder,$desc,(int)$_POST['id']]);
            // Re-assign clients to correct rank
            $ranks = $pdo->query("SELECT id,min_points FROM ranks ORDER BY min_points DESC")->fetchAll();
            foreach ($pdo->query("SELECT id,points FROM clients")->fetchAll() as $c) {
                foreach ($ranks as $r) {
                    if ($c['points'] >= $r['min_points']) {
                        $pdo->prepare("UPDATE clients SET rank_id=? WHERE id=?")->execute([$r['id'],$c['id']]);
                        break;
                    }
                }
            }
            flash('success', 'Rank updated and clients reassigned.');
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $used = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE rank_id=?"); $used->execute([$id]);
        if ($used->fetchColumn() > 0) flash('error', 'Cannot delete — clients are on this rank.');
        else { $pdo->prepare("DELETE FROM ranks WHERE id=?")->execute([$id]); flash('success', 'Rank deleted.'); }
    }
    redirect(APP_URL . '/admin/loyalty.php');
}

$ranks = $pdo->query("SELECT r.*,(SELECT COUNT(*) FROM clients WHERE rank_id=r.id) as cnt FROM ranks r ORDER BY r.min_points ASC")->fetchAll();
$leaderboard = $pdo->query("SELECT c.name,c.points,c.streak_days,r.name as rname,r.icon as ricon,r.color as rcolor FROM clients c LEFT JOIN ranks r ON c.rank_id=r.id WHERE c.is_active=1 AND c.profile_public=1 ORDER BY c.points DESC LIMIT 10")->fetchAll();

pageHead('Loyalty'); sidebar($user); topbar('Loyalty', 'Ranks & Gamification'); showFlash();
?>
<div class="g2" style="gap:20px;align-items:start">

  <!-- Ranks -->
  <div>
    <div class="flex ai-c jc-b mb-4">
      <div class="text-xs upper text-dim" style="letter-spacing:.3em">Rank Tiers</div>
      <button class="btn btn-primary btn-sm" onclick="openModal('rank-modal');resetRank()">+ Add Rank</button>
    </div>
    <div style="display:flex;flex-direction:column;gap:10px">
    <?php foreach ($ranks as $rank): ?>
    <div class="card" style="padding:16px">
      <div class="flex ai-c jc-b mb-3">
        <div class="flex ai-c gap"><span style="font-size:1.5rem"><?= e($rank['icon']) ?></span>
          <div><div style="color:<?=e($rank['color'])?>;font-weight:600"><?=e($rank['name'])?></div>
          <div class="text-xs text-dim">From <?=number_format($rank['min_points'])?> pts</div></div>
        </div>
        <div class="tr"><div class="text-cream serif" style="font-size:1.1rem"><?=$rank['cnt']?></div><div class="text-xs text-dim">clients</div></div>
      </div>
      <div class="flex gap-lg mb-3">
        <div><div class="text-xs text-dim">Restores</div><div class="text-mid"><?=$rank['streak_restores']?>×</div></div>
        <div><div class="text-xs text-dim">Pre-order</div><?=$rank['can_preorder']?'<span class="badge b-success">Yes</span>':'<span class="badge b-dim">No</span>'?></div>
      </div>
      <?php if ($rank['description']): ?><div class="text-xs text-dim mb-3" style="font-style:italic">"<?=e($rank['description'])?>"</div><?php endif; ?>
      <div class="flex gap">
        <button class="btn btn-ghost btn-sm f1" onclick='fillRank(<?=json_encode($rank)?>)'>Edit</button>
        <?php if ($rank['cnt'] == 0): ?>
        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this rank?')">
          <?php csrfField(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$rank['id']?>">
          <button class="btn btn-danger btn-sm">Delete</button>
        </form>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
    </div>
  </div>

  <!-- Leaderboard -->
  <div class="card">
    <div class="card-title">Live Leaderboard — Top 10</div>
    <?php foreach ($leaderboard as $i => $lc): ?>
    <div class="flex ai-c gap mb-3" style="padding-bottom:12px;border-bottom:1px solid rgba(192,122,47,.07)">
      <div style="width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:700;flex-shrink:0;
        background:<?=$i===0?'linear-gradient(135deg,#F5C842,#d4922a)':($i===1?'linear-gradient(135deg,#C0C0C0,#a0a0a0)':($i===2?'linear-gradient(135deg,#CD7F32,#a06020)':'rgba(192,122,47,.15)'))?>; color:<?=$i<3?'#0d0905':'var(--text-mid)'?>">
        <?= $i < 3 ? ['🥇','🥈','🥉'][$i] : ($i+1) ?>
      </div>
      <div style="font-size:1.3rem"><?= e($lc['ricon'] ?? '☕') ?></div>
      <div class="f1"><div class="text-cream text-sm"><?=e($lc['name'])?></div><div class="text-xs" style="color:<?=e($lc['rcolor']??'var(--text-dim)')?>"><?=e($lc['rname']??'Espresso')?></div></div>
      <div class="tr"><div class="text-gold" style="font-size:.85rem">⭐ <?=number_format($lc['points'])?></div><div class="text-xs text-dim">🔥 <?=$lc['streak_days']?>d</div></div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($leaderboard)): ?><p class="text-dim text-sm tc">No clients yet.</p><?php endif; ?>
  </div>
</div>

<!-- Rank Modal -->
<div class="overlay" id="rank-modal">
  <div class="modal">
    <div class="modal-head"><div class="modal-title" id="rank-modal-title">Add Rank</div><button class="modal-close" onclick="closeModal('rank-modal')">✕</button></div>
    <form method="POST">
      <?php csrfField(); ?>
      <input type="hidden" name="action" id="rank-action" value="add">
      <input type="hidden" name="id" id="rank-id">
      <div class="form-row">
        <div class="form-group"><label class="form-label">Name</label><input type="text" class="form-input" name="name" id="rank-name" required placeholder="e.g. Gold"></div>
        <div class="form-group"><label class="form-label">Icon (emoji)</label><input type="text" class="form-input" name="icon" id="rank-icon" placeholder="☕" maxlength="4"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Min Points</label><input type="number" class="form-input" name="min_points" id="rank-pts" min="0" value="0" required></div>
        <div class="form-group"><label class="form-label">Color</label><input type="color" class="form-input" name="color" id="rank-color" value="#c07a2f" style="height:42px;padding:4px"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Streak Restores</label><input type="number" class="form-input" name="restores" id="rank-restores" min="0" max="20" value="5"></div>
        <div class="form-group" style="display:flex;align-items:flex-end;padding-bottom:4px"><label class="form-check"><input type="checkbox" name="preorder" id="rank-preorder"> Pre-order privilege</label></div>
      </div>
      <div class="form-group"><label class="form-label">Description</label><textarea class="form-textarea" name="desc" id="rank-desc" style="min-height:66px" placeholder="Short flavour text…"></textarea></div>
      <div class="modal-foot"><button type="button" class="btn btn-ghost" onclick="closeModal('rank-modal')">Cancel</button><button type="submit" class="btn btn-primary">Save Rank</button></div>
    </form>
  </div>
</div>

<script>
function resetRank(){
  document.getElementById('rank-modal-title').textContent='Add Rank';
  document.getElementById('rank-action').value='add';
  ['rank-id','rank-name','rank-desc'].forEach(id=>document.getElementById(id).value='');
  document.getElementById('rank-icon').value='☕';
  document.getElementById('rank-pts').value='0';
  document.getElementById('rank-color').value='#c07a2f';
  document.getElementById('rank-restores').value='5';
  document.getElementById('rank-preorder').checked=false;
}
function fillRank(r){
  document.getElementById('rank-modal-title').textContent='Edit Rank';
  document.getElementById('rank-action').value='edit';
  document.getElementById('rank-id').value=r.id;
  document.getElementById('rank-name').value=r.name;
  document.getElementById('rank-icon').value=r.icon;
  document.getElementById('rank-pts').value=r.min_points;
  document.getElementById('rank-color').value=r.color;
  document.getElementById('rank-restores').value=r.streak_restores;
  document.getElementById('rank-preorder').checked=parseInt(r.can_preorder)===1;
  document.getElementById('rank-desc').value=r.description||'';
  openModal('rank-modal');
}
</script>
<?php pageEnd(); ?>

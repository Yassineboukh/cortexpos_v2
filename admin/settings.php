<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('admin'); $pdo = db();

$generatedCode = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'generate_otc') {
        $targetRole = $_POST['target_role'] ?? 'cashier';
        $note       = trim($_POST['note'] ?? '');
        if (!in_array($targetRole, ['admin','cashier'])) flash('error','Invalid role.');
        else {
            // Max 10 active codes
            $active = $pdo->prepare("SELECT COUNT(*) FROM otc_codes WHERE generated_by=? AND is_used=0 AND expires_at>NOW()");
            $active->execute([$user['id']]);
            if ($active->fetchColumn() >= 10) flash('error','Max 10 active codes. Revoke some first.');
            else {
                $raw    = generateOTC(); // XXXX-XXXX-XXXX-XXXX
                $flat   = str_replace('-','',$raw);
                $prefix = substr($flat,0,4);
                $hash   = password_hash($flat,PASSWORD_BCRYPT,['cost'=>12]);
                $exp    = date('Y-m-d H:i:s',strtotime('+'.OTC_EXPIRY.' minutes'));
                $pdo->prepare("INSERT INTO otc_codes (code_hash,code_prefix,role,generated_by,note,expires_at) VALUES (?,?,?,?,?,?)")
                    ->execute([$hash,$prefix,$targetRole,$user['id'],$note?:null,$exp]);
                $generatedCode = $raw;
                auditLog('otc_generated',$targetRole,null,$user['id'],'For: '.$targetRole);
            }
        }
    } elseif ($action === 'revoke_otc') {
        $id = (int)$_POST['id'];
        $pdo->prepare("UPDATE otc_codes SET is_used=1,used_at=NOW(),used_by_email='[revoked]' WHERE id=? AND generated_by=? AND is_used=0")->execute([$id,$user['id']]);
        flash('success','Code revoked.');
        redirect(APP_URL.'/admin/settings.php');
    }
}

$activeCodes = $pdo->prepare("SELECT * FROM otc_codes WHERE generated_by=? AND is_used=0 AND expires_at>NOW() ORDER BY created_at DESC");
$activeCodes->execute([$user['id']]); $activeCodes=$activeCodes->fetchAll();

$auditLog = $pdo->query("SELECT * FROM auth_log ORDER BY created_at DESC LIMIT 40")->fetchAll();

pageHead('Settings'); sidebar($user); topbar('Settings','System Configuration'); showFlash();
?>
<div class="g2" style="gap:20px;align-items:start">

  <!-- OTC Generator -->
  <div>
    <div class="card mb-4">
      <div class="card-title">Generate Authorization Code</div>
      <p class="text-dim text-sm mb-4" style="line-height:1.7">Create a one-time code to authorize a new admin or cashier. Expires in <strong style="color:var(--gold)"><?=OTC_EXPIRY?> minutes</strong>. Single use only.</p>
      <form method="POST">
        <?php csrfField(); ?><input type="hidden" name="action" value="generate_otc">
        <div class="form-group"><label class="form-label">Target Role</label>
          <select class="form-select" name="target_role"><option value="cashier">Cashier</option><option value="admin">Admin</option></select>
        </div>
        <div class="form-group"><label class="form-label">Note (optional)</label><input type="text" class="form-input" name="note" placeholder="e.g. For Ahmed - morning shift"></div>
        <button type="submit" class="btn btn-primary btn-w">⬡ Generate Code</button>
      </form>

      <?php if ($generatedCode): ?>
      <div style="margin-top:18px;background:rgba(212,146,42,.08);border:1px solid rgba(212,146,42,.3);border-radius:var(--radius-sm);padding:20px;text-align:center">
        <div class="text-xs text-dim upper mb-2" style="letter-spacing:.3em">One-Time Code — Copy Now</div>
        <div style="font-family:monospace;font-size:1.4rem;color:var(--gold);letter-spacing:.15em;font-weight:600;user-select:all" id="otc-display"><?=e($generatedCode)?></div>
        <div class="text-xs text-dim" style="margin-top:8px">Expires in <?=OTC_EXPIRY?> minutes · Single use</div>
        <button type="button" class="btn btn-ghost btn-sm" style="margin-top:10px" onclick="navigator.clipboard.writeText('<?=e($generatedCode)?>').then(()=>alert('Copied!'))">⎘ Copy to Clipboard</button>
      </div>
      <div style="margin-top:10px;padding:10px 14px;background:rgba(184,64,48,.08);border:1px solid rgba(184,64,48,.25);border-radius:var(--radius-sm);font-size:.75rem;color:#e8907e">
        ⚠ This code is shown <strong>once only</strong>. Copy and send it to the user immediately.
      </div>
      <?php endif; ?>
    </div>

    <!-- Active codes -->
    <div class="card">
      <div class="card-title">Active Codes (<?=count($activeCodes)?>/10)</div>
      <?php if ($activeCodes): ?>
      <table class="tbl">
        <thead><tr><th>Role</th><th>Note</th><th>Expires</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($activeCodes as $code): ?>
        <tr>
          <td><span class="badge <?=$code['role']==='admin'?'b-danger':'b-info'?>"><?=ucfirst($code['role'])?></span></td>
          <td class="text-dim"><?=e($code['note']??'—')?></td>
          <td class="text-dim text-sm"><?=date('H:i',strtotime($code['expires_at']))?></td>
          <td><form method="POST" style="display:inline" onsubmit="return confirm('Revoke this code?')"><?php csrfField(); ?><input type="hidden" name="action" value="revoke_otc"><input type="hidden" name="id" value="<?=$code['id']?>"><button class="btn btn-danger btn-sm">Revoke</button></form></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?><p class="text-dim text-sm">No active codes.</p><?php endif; ?>
    </div>
  </div>

  <!-- Audit log -->
  <div class="card">
    <div class="card-title">Security Audit Log</div>
    <div style="max-height:560px;overflow-y:auto">
    <?php
    $aColors=['login_success'=>'b-success','login_fail'=>'b-danger','logout'=>'b-dim','otc_generated'=>'b-gold','otc_used'=>'b-success','otc_fail'=>'b-danger','register_success'=>'b-success','register_fail'=>'b-danger'];
    foreach ($auditLog as $log): ?>
    <div class="flex gap" style="padding:10px 0;border-bottom:1px solid rgba(192,122,47,.07)">
      <span class="badge <?=$aColors[$log['action']]??'b-dim'?>" style="align-self:flex-start;flex-shrink:0;font-size:.58rem"><?=ucfirst(str_replace('_',' ',$log['action']))?></span>
      <div class="f1" style="min-width:0">
        <div class="text-sm truncate"><?=e($log['identifier']??$log['detail']??'—')?></div>
        <div class="text-xs text-dim"><?=e($log['ip_address']??'')?> · <?=date('d M H:i',strtotime($log['created_at']))?></div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($auditLog)): ?><p class="text-dim text-sm">No log entries yet.</p><?php endif; ?>
    </div>
  </div>
</div>
<?php pageEnd(); ?>

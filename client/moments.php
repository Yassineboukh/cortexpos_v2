<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
$user=requireLogin('client'); $pdo=db();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    
    // Handle moment deletion
    if (!empty($_POST['delete_moment'])) {
        $momentId = (int)$_POST['delete_moment'];
        $m=$pdo->prepare("SELECT id,image_path FROM coffee_moments WHERE id=? AND client_id=?");
        $m->execute([$momentId,$user['id']]);
        $m=$m->fetch();
        if ($m) {
            $fpath = __DIR__ . '/../' . $m['image_path'];
            if (file_exists($fpath)) @unlink($fpath);
            $pdo->prepare("DELETE FROM coffee_moments WHERE id=?")->execute([$momentId]);
            flash('success','Moment deleted.');
        } else {
            flash('error','Moment not found.');
        }
        redirect(APP_URL.'/client/moments.php');
    }
    
    $chk=$pdo->prepare("SELECT COUNT(*) FROM coffee_moments WHERE client_id=? AND DATE(created_at)=CURDATE()");$chk->execute([$user['id']]);$today=(int)$chk->fetchColumn();
    if ($today>=2) { flash('error','Daily limit reached. Max 2 photos per day.'); redirect(APP_URL.'/client/moments.php'); }

    $file=$_FILES['image']??null;
    if (!$file||$file['error']!==0) { flash('error','No file uploaded.'); redirect(APP_URL.'/client/moments.php'); }
    if (!is_uploaded_file($file['tmp_name'])) { flash('error','Invalid upload.'); redirect(APP_URL.'/client/moments.php'); }
    if (!in_array($file['type'],['image/jpeg','image/png','image/webp'])) { flash('error','Invalid type. JPG/PNG/WEBP only.'); redirect(APP_URL.'/client/moments.php'); }
    if ($file['size']>5*1024*1024) { flash('error','File too large. Max 5MB.'); redirect(APP_URL.'/client/moments.php'); }

    $dir=__DIR__.'/../uploads/moments/'; if(!is_dir($dir)) mkdir($dir,0755,true);
    $ext=pathinfo($file['name'],PATHINFO_EXTENSION)?:'jpg';
    $fname='moment_'.$user['id'].'_'.time().'_'.bin2hex(random_bytes(4)).'.'.$ext;
    if (!move_uploaded_file($file['tmp_name'],$dir.$fname)) { flash('error','Upload failed.'); redirect(APP_URL.'/client/moments.php'); }

    // Streak logic
    $cl=$pdo->prepare("SELECT streak_days,last_streak,streak_restores FROM clients WHERE id=?");$cl->execute([$user['id']]);$cl=$cl->fetch();
    $tod=date('Y-m-d'); $yes=date('Y-m-d',strtotime('-1 day'));
    $newStreak=$cl['streak_days'];
    $newRestores=$cl['streak_restores'];
    if ($cl['last_streak']===$tod) { /* no change */ }
    elseif ($cl['last_streak']===$yes||!$cl['last_streak']) $newStreak++;
    else {
        // Streak broken
        if ($newRestores > 0) {
            $newRestores--; // Use a restore to maintain streak
        } else {
            $newStreak=1; // Reset streak
        }
    }

    $pts=10+min($newStreak*2,30);
    $caption=trim($_POST['caption']??'');

    $pdo->beginTransaction();
    try {
        $pdo->prepare("INSERT INTO coffee_moments (client_id,image_path,caption,points_earned) VALUES (?,?,?,?)")->execute([$user['id'],'uploads/moments/'.$fname,$caption,$pts]);
        $pdo->prepare("UPDATE clients SET points=points+?,streak_days=?,last_streak=?,streak_restores=? WHERE id=?")->execute([$pts,$newStreak,$tod,$newRestores,$user['id']]);
        updateClientRank($user['id']);
        $pdo->commit();
        flash('success',"Moment uploaded! +{$pts} points earned 🔥 Streak: {$newStreak} days");
        redirect(APP_URL.'/client/moments.php');
    } catch (Exception $e) {
        $pdo->rollback();
        if (file_exists($dir.$fname)) @unlink($dir.$fname);
        flash('error','Failed to upload moment. Please try again.');
        redirect(APP_URL.'/client/moments.php');
    }
}

$client=$pdo->prepare("SELECT c.*,r.streak_restores as max_res FROM clients c LEFT JOIN ranks r ON c.rank_id=r.id WHERE c.id=?");$client->execute([$user['id']]);$client=$client->fetch();
$chk=$pdo->prepare("SELECT COUNT(*) FROM coffee_moments WHERE client_id=? AND DATE(created_at)=CURDATE()");$chk->execute([$user['id']]);$todayCount=(int)$chk->fetchColumn();
$myMoments=$pdo->prepare("SELECT * FROM coffee_moments WHERE client_id=? ORDER BY created_at DESC LIMIT 6");$myMoments->execute([$user['id']]);$myMoments=$myMoments->fetchAll();
$feed=$pdo->query("SELECT cm.*,c.name as cname FROM coffee_moments cm JOIN clients c ON cm.client_id=c.id WHERE cm.is_approved=1 ORDER BY cm.created_at DESC LIMIT 20")->fetchAll();

pageHead('Coffee Moments'); sidebar($user); topbar('Coffee Moments','Share Your Experience'); showFlash();
?>
<div class="g2" style="gap:18px;align-items:start">
  <div>
    <div class="card mb-4">
      <div class="card-title">Daily Upload</div>
      <div style="text-align:center;padding:18px 0;border-bottom:1px solid var(--border);margin-bottom:18px">
        <div style="font-size:3rem;margin-bottom:8px">🔥</div>
        <div class="serif text-gold" style="font-size:2.5rem;font-weight:500"><?=$client['streak_days']?></div>
        <div class="text-dim text-sm">day streak</div>
        <div class="flex ai-c jc-c gap-sm" style="margin-top:10px">
          <?php for($i=0;$i<min(7,$client['streak_days']+1);$i++): ?><div style="width:9px;height:9px;border-radius:50%;background:<?=$i<$client['streak_days']?'var(--gold)':'rgba(192,122,47,.2)'?>"></div><?php endfor; ?>
        </div>
        <div class="text-xs text-dim" style="margin-top:10px"><?=$client['streak_restores']?>/<?=$client['max_res']?> restores remaining</div>
      </div>

      <?php if($todayCount>=2): ?>
      <div class="badge b-warning" style="padding:12px;width:100%;justify-content:center;border-radius:var(--radius-sm);margin-bottom:12px">✓ Daily limit reached (<?=$todayCount?>/2 photos)</div>
      <p class="text-dim text-sm tc">Come back tomorrow to continue your streak!</p>
      <?php else: ?>
      <p class="text-dim text-sm mb-4 tc" style="line-height:1.6">Upload a photo from inside the café. <strong style="color:var(--gold)"><?=2-$todayCount?> upload<?=(2-$todayCount)!==1?'s':''?> left today.</strong></p>
      <form method="POST" enctype="multipart/form-data">
        <?php csrfField(); ?>
        <div id="drop-zone" onclick="document.getElementById('img-file').click()" style="border:2px dashed rgba(192,122,47,.3);border-radius:var(--radius);padding:28px;text-align:center;cursor:pointer;transition:var(--transition)" onmouseenter="this.style.borderColor='var(--gold)'" onmouseleave="this.style.borderColor='rgba(192,122,47,.3)'">
          <div style="font-size:2.2rem;margin-bottom:8px">📸</div>
          <div class="text-cream text-sm mb-2">Click to upload</div>
          <div class="text-xs text-dim">JPG · PNG · WEBP · Max 5MB</div>
        </div>
        <input type="file" id="img-file" name="image" accept="image/*" style="display:none" onchange="previewImage(this,'preview-img');document.getElementById('preview-sec').style.display='block'">
        <div id="preview-sec" style="display:none;margin-top:12px">
          <img id="preview-img" style="width:100%;border-radius:var(--radius-sm);max-height:200px;object-fit:cover">
          <div class="form-group" style="margin-top:10px"><label class="form-label">Caption (optional)</label><input type="text" class="form-input" name="caption" placeholder="Add a caption…" maxlength="255"></div>
          <button type="submit" class="btn btn-primary btn-w" style="margin-top:10px">Upload Moment ✓</button>
          <button type="button" class="btn btn-ghost btn-w" style="margin-top:6px" onclick="document.getElementById('preview-sec').style.display='none'">Cancel</button>
        </div>
      </form>
      <?php endif; ?>
    </div>

    <?php if($myMoments): ?>
    <div class="card"><div class="card-title">My Moments</div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px">
    <?php foreach($myMoments as $m): ?>
    <div id="moment-<?=$m['id']?>" style="position:relative;border-radius:var(--radius-sm);overflow:hidden;aspect-ratio:1;background:var(--mahogany);group/card" onmouseenter="this.querySelector('.moment-actions')?.style.display='flex'" onmouseleave="this.querySelector('.moment-actions')?.style.display='none'">
      <img src="<?=APP_URL.'/'.$m['image_path']?>" style="width:100%;height:100%;object-fit:cover" onerror="this.style.display='none'">
      <div style="position:absolute;bottom:0;left:0;right:0;padding:5px;background:linear-gradient(transparent,rgba(0,0,0,.7));font-size:.6rem;color:var(--cream)">+<?=$m['points_earned']?> pts · <?=date('d M',strtotime($m['created_at']))?></div>
      <div class="moment-actions" style="display:none;position:absolute;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.6);flex;flex-direction:column;align-items:center;justify-content:center;gap:8px">
        <button type="button" style="background:rgba(212,146,42,.8);color:white;border:none;padding:8px 12px;border-radius:4px;cursor:pointer;font-size:.75rem" onclick="shareMoment(<?=$m['id']?>)">📤 Share</button>
        <button type="button" style="background:rgba(184,64,48,.8);color:white;border:none;padding:8px 12px;border-radius:4px;cursor:pointer;font-size:.75rem" onclick="deleteMoment(<?=$m['id']?>)">🗑 Delete</button>
      </div>
    </div>
    <?php endforeach; ?>
    </div></div>
    <?php endif; ?>
  </div>

  <!-- Feed -->
  <div class="card">
    <div class="card-title">Community Feed</div>
    <?php if($feed): ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
    <?php foreach($feed as $m): ?>
    <div style="border-radius:var(--radius-sm);overflow:hidden;border:1px solid var(--border);background:var(--dark-roast)">
      <div style="height:120px;background:var(--mahogany);display:flex;align-items:center;justify-content:center;overflow:hidden">
        <img src="<?=APP_URL.'/'.$m['image_path']?>" style="width:100%;height:100%;object-fit:cover" onerror="this.parentElement.innerHTML='<span style=\"font-size:2rem\">📸</span>'">
      </div>
      <div style="padding:9px">
        <div class="text-cream text-sm" style="font-weight:500"><?=e($m['cname'])?></div>
        <?php if($m['caption']): ?><div class="text-xs text-dim" style="margin-top:2px;font-style:italic">"<?=e($m['caption'])?>"</div><?php endif; ?>
        <div class="text-xs text-dim" style="margin-top:4px"><?=date('d M · H:i',strtotime($m['created_at']))?></div>
      </div>
    </div>
    <?php endforeach; ?>
    </div>
    <?php else: ?><div style="text-align:center;padding:32px;color:var(--text-dim)"><div style="font-size:2rem;margin-bottom:8px">📸</div>Be the first to share a moment!</div><?php endif; ?>
  </div>
</div>

<script>
function shareMoment(momentId){
  const momentUrl='<?=APP_URL?>/client/moments.php#moment-'+momentId;
  if(navigator.share){
    navigator.share({title:'Coffee Moment',url:momentUrl}).catch(e=>console.log('Share cancelled'));
  } else {
    const text='Check out my coffee moment! '+momentUrl;
    if(navigator.clipboard){
      navigator.clipboard.writeText(text).then(()=>{alert('Shared link copied to clipboard!')});
    }else{
      prompt('Share this link:',momentUrl);
    }
  }
}
function deleteMoment(momentId){
  if(!confirm('Are you sure you want to delete this moment?')) return;
  const form=document.createElement('form');form.method='POST';form.style.display='none';
  form.innerHTML='<?php csrfField(); ?><input type="hidden" name="delete_moment" value="'+momentId+'">';
  document.body.appendChild(form);form.submit();
}
</script>
<?php pageEnd(); ?>

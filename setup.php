<?php
define('DB_HOST','localhost'); define('DB_USER','root'); define('DB_PASS',''); define('DB_NAME','cortexpos');
try {
    $pdo=new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
} catch(Exception $e) {
    die('<div style="font-family:sans-serif;padding:40px;background:#0d0905;color:#f5e6c8;min-height:100vh"><h2 style="color:#d4922a">Database Connection Failed</h2><p>Make sure XAMPP MySQL is running.</p><code style="color:#e8907e">'.$e->getMessage().'</code></div>');
}
$done=[];
// Admin
if (!$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn()) {
    $pdo->prepare("INSERT INTO users (name,email,password_hash,role) VALUES (?,?,?,'admin')")->execute(['Super Admin','admin@cortexpos.com',password_hash('Admin@1234',PASSWORD_BCRYPT,['cost'=>12])]);
    $done[]='✓ Default admin created — admin@cortexpos.com / Admin@1234';
} else $done[]='✓ Admin already exists';
// Ranks
if (!$pdo->query("SELECT COUNT(*) FROM ranks")->fetchColumn()) {
    $rs=[['Espresso',0,'#8B6347','☕',3,0,'Welcome rank.'],['Cappuccino',500,'#C07A2F','🍵',4,0,'You\'re warming up.'],['Latte',1500,'#D4922A','🌟',5,0,'A regular face.'],['Cortado',3500,'#E8B96A','💎',5,1,'Pre-orders unlocked.'],['Black Gold',8000,'#F5C842','👑',5,1,'The pinnacle.']];
    $s=$pdo->prepare("INSERT INTO ranks (name,min_points,color,icon,streak_restores,can_preorder,description) VALUES (?,?,?,?,?,?,?)");
    foreach($rs as $r) $s->execute($r); $done[]='✓ 5 default ranks created';
} else $done[]='✓ Ranks exist';
// Tables
if (!$pdo->query("SELECT COUNT(*) FROM cafe_tables")->fetchColumn()) {
    $ts=[[1,2,'Window'],[2,2,'Window'],[3,4,'Main'],[4,4,'Main'],[5,4,'Main'],[6,6,'Main'],[7,2,'Bar'],[8,2,'Bar'],[9,4,'Terrace'],[10,4,'Terrace']];
    $s=$pdo->prepare("INSERT INTO cafe_tables (number,capacity,zone) VALUES (?,?,?)");
    foreach($ts as $t) $s->execute($t); $done[]='✓ 10 default tables created';
} else $done[]='✓ Tables exist';
// Categories
if (!$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn()) {
    $cs=[['Hot Drinks','☕',1],['Cold Drinks','🧊',2],['Food','🥐',3],['Desserts','🍰',4],['Extras','✨',5]];
    $s=$pdo->prepare("INSERT INTO categories (name,icon,sort_order) VALUES (?,?,?)");
    foreach($cs as $c) $s->execute($c); $done[]='✓ 5 default categories created';
} else $done[]='✓ Categories exist';
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>CortexPOS Setup</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500&family=Cormorant+Garamond:wght@400;500&display=swap" rel="stylesheet">
<style>*{margin:0;padding:0;box-sizing:border-box}body{background:#0d0905;color:#f5e6c8;font-family:'DM Sans',sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}
.card{background:#1a100a;border:1px solid rgba(192,122,47,.25);border-radius:12px;padding:36px 40px;max-width:520px;width:100%}
h1{font-family:'Cormorant Garamond',serif;font-size:1.8rem;color:#d4922a;margin-bottom:6px}
.sub{color:#8a6a4a;font-size:.83rem;margin-bottom:24px}
.item{display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid rgba(192,122,47,.1);font-size:.83rem;color:#d4a96a}
.item:last-child{border-bottom:none}
.warn{margin-top:18px;padding:14px;background:rgba(184,64,48,.1);border:1px solid rgba(184,64,48,.28);border-radius:8px;font-size:.77rem;color:#e8907e;line-height:1.6}
.btn{display:inline-block;margin-top:20px;padding:12px 28px;background:linear-gradient(135deg,#c07a2f,#d4922a);color:#0d0905;border-radius:8px;text-decoration:none;font-weight:500;font-size:.85rem}
</style></head>
<body><div class="card">
  <h1>⚙ CortexPOS Setup</h1>
  <div class="sub">First-time installation complete</div>
  <?php foreach($done as $d): ?><div class="item"><?=$d?></div><?php endforeach; ?>
  <div class="warn">⚠ <strong>Delete this file immediately after setup</strong> — it is a security risk.<br>Then change your admin password on first login.</div>
  <a href="auth/login.php" class="btn">→ Go to Login</a>
</div></body></html>

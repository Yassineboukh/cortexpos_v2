<?php
// ============================================================
//  CortexPOS — Core Functions
//  Include this on every page
// ============================================================
require_once __DIR__ . '/../config/db.php';

// ── Session ──────────────────────────────────────────────────
function startSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_set_cookie_params(['lifetime'=>28800,'path'=>'/','httponly'=>true,'samesite'=>'Strict']);
    session_start();
}

// ── Require login, redirect if not ──────────────────────────
function requireLogin(string ...$roles): array {
    startSession();
    if (empty($_SESSION['user'])) {
        header('Location: '.APP_URL.'/auth/login.php?msg=session');
        exit;
    }
    if (!empty($roles) && !in_array($_SESSION['user']['role'], $roles, true)) {
        header('Location: '.APP_URL.'/auth/login.php?msg=unauthorized');
        exit;
    }
    return $_SESSION['user'];
}

// ── Flash messages ───────────────────────────────────────────
function flash(string $type, string $msg): void {
    startSession();
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function getFlash(): ?array {
    startSession();
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

function showFlash(): void {
    $f = getFlash();
    if (!$f) return;
    $icons  = ['success'=>'✓','error'=>'✕','warning'=>'⚠','info'=>'ℹ'];
    $ic = $icons[$f['type']] ?? '·';
    echo '<div class="flash-msg flash-'.$f['type'].'">
        <span class="flash-icon">'.$ic.'</span>
        <div class="flash-text">'.htmlspecialchars($f['msg']).'</div>
    </div>';
}

// ── CSRF ─────────────────────────────────────────────────────
function csrfToken(): string {
    startSession();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrfField(): void {
    echo '<input type="hidden" name="csrf" value="'.csrfToken().'">';
}
function verifyCsrf(): void {
    startSession();
    $token = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
        flash('error','Invalid request. Please try again.');
        header('Location: '.$_SERVER['HTTP_REFERER']);
        exit;
    }
}

// ── Rate limiting ─────────────────────────────────────────────
function rateLimit(string $ip, int $max=5, int $mins=15): bool {
    try {
        $r = db()->prepare("SELECT COUNT(*) FROM auth_log WHERE action='login_fail' AND ip_address=? AND created_at>DATE_SUB(NOW(),INTERVAL ? MINUTE)");
        $r->execute([$ip,$mins]);
        return (int)$r->fetchColumn() < $max;
    } catch (Exception $e) { return true; }
}

// ── Audit log ─────────────────────────────────────────────────
function auditLog(string $action, ?string $role=null, ?string $identifier=null, ?int $userId=null, ?string $detail=null): void {
    try {
        db()->prepare("INSERT INTO auth_log (action,role,identifier,user_id,ip_address,detail) VALUES (?,?,?,?,?,?)")
           ->execute([$action,$role,$identifier,$userId,$_SERVER['REMOTE_ADDR']??'0.0.0.0',$detail]);
    } catch (Exception $e) {}
}

// ── OTC helpers ───────────────────────────────────────────────
function generateOTC(): string {
    $raw = strtoupper(bin2hex(random_bytes(8)));
    return implode('-', str_split($raw, 4));
}

// ── Redirect helper ───────────────────────────────────────────
function redirect(string $url): void {
    header('Location: '.$url); exit;
}

// ── Sanitize output ───────────────────────────────────────────
function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// ── Update client rank ────────────────────────────────────────
function updateClientRank(int $clientId): void {
    $pts = db()->prepare("SELECT points FROM clients WHERE id=?");
    $pts->execute([$clientId]);
    $points = (int)$pts->fetchColumn();
    $rank = db()->prepare("SELECT id,streak_restores FROM ranks WHERE min_points<=? ORDER BY min_points DESC LIMIT 1");
    $rank->execute([$points]);
    $rank = $rank->fetch();
    if ($rank) {
        db()->prepare("UPDATE clients SET rank_id=?, streak_restores=? WHERE id=?")->execute([$rank['id'], $rank['streak_restores'], $clientId]);
    }
}

// ── Generate order reference ──────────────────────────────────
function generateOrderRef(): string {
    $count = (int)db()->query("SELECT COUNT(*)+1 FROM orders WHERE DATE(created_at)=CURDATE()")->fetchColumn();
    return 'ORD-'.date('Ymd').'-'.str_pad($count,4,'0',STR_PAD_LEFT);
}

// ── Deduct stock for an order ─────────────────────────────────
function deductStock(int $orderId): void {
    $items = db()->prepare("SELECT oi.product_id,oi.quantity FROM order_items oi WHERE oi.order_id=?");
    $items->execute([$orderId]);
    foreach ($items->fetchAll() as $item) {
        $ings = db()->prepare("SELECT ingredient_id,quantity FROM product_ingredients WHERE product_id=?");
        $ings->execute([$item['product_id']]);
        foreach ($ings->fetchAll() as $ing) {
            db()->prepare("UPDATE ingredients SET stock_qty=stock_qty-? WHERE id=?")
               ->execute([$ing['quantity']*$item['quantity'],$ing['ingredient_id']]);
        }
    }
}

// ── Assign points after delivery ──────────────────────────────
function assignPoints(int $orderId): void {
    $o = db()->prepare("SELECT client_id,points_earned FROM orders WHERE id=?");
    $o->execute([$orderId]); $o=$o->fetch();
    if ($o && $o['client_id'] && $o['points_earned']>0) {
        db()->prepare("UPDATE clients SET points=points+? WHERE id=?")->execute([$o['points_earned'],$o['client_id']]);
        updateClientRank($o['client_id']);
    }
}

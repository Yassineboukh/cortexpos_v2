<?php
// auth/logout.php
require_once __DIR__ . '/../includes/functions.php';
startSession();
$user = $_SESSION['user'] ?? null;
if ($user) {
    // Close cashier shift
    if ($user['role']==='cashier') {
        db()->prepare("UPDATE shifts SET ended_at=NOW() WHERE cashier_id=? AND ended_at IS NULL ORDER BY started_at DESC LIMIT 1")->execute([$user['id']]);
    }
    auditLog('logout',$user['role'],$user['email'],$user['id']);
}
session_destroy();
redirect(APP_URL.'/auth/login.php?msg=logout');

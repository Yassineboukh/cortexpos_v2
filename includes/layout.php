<?php
// ============================================================
//  CortexPOS — Shared Layout
// ============================================================

function pageHead(string $title): void {
    $base = APP_URL;
    echo '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#0d0905">
<title>'.e($title).' — CortexPOS</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400;500;600&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="'.$base.'/assets/css/app.css">
</head>
<body>
<div class="app-shell">';
}

function sidebar(array $user): void {
    $base = APP_URL;
    $role = $user['role'];
    $cur  = basename($_SERVER['PHP_SELF']);

    $navAdmin = [
        ['dashboard.php','⊞','Dashboard'],
        ['sales.php','◈','Sales'],
        ['cashiers.php','◉','Cashiers'],
        ['inventory.php','⊟','Inventory'],
        ['products.php','☕','Products'],
        ['tables.php','⊡','Tables'],
        ['clients.php','◎','Clients'],
        ['loyalty.php','★','Loyalty & Ranks'],
        ['events.php','◆','Events'],
        ['support.php','◇','Support'],
        ['analytics.php','≋','Analytics'],
        ['settings.php','⚙','Settings'],
    ];
    $navCashier = [
        ['dashboard.php','⊞','Dashboard'],
        ['new_order.php','◈','New Order'],
        ['tables.php','⊡','Tables'],
        ['history.php','◉','History'],
        ['receipts.php','◇','Receipts'],
    ];
    $navClient = [
        ['home.php','⊞','Home'],
        ['menu.php','☕','Menu & Order'],
        ['my_orders.php','◈','My Orders'],
        ['loyalty.php','★','Loyalty'],
        ['leaderboard.php','◆','Leaderboard'],
        ['moments.php','◎','Coffee Moments'],
        ['support.php','◇','Support'],
        ['profile.php','◉','Profile'],
    ];

    $nav = match($role) {
        'admin'   => $navAdmin,
        'cashier' => $navCashier,
        default   => $navClient,
    };
    $dir = match($role) {
        'admin'   => 'admin',
        'cashier' => 'cashier',
        default   => 'client',
    };

    $initial = strtoupper(substr($user['name'],0,1));

    echo '<aside class="sidebar">
    <div class="sidebar-logo">
        <img src="'.$base.'/assets/img/logo_mini.png" alt="CortexPOS">
        <span class="brand"><em>Cortex</em>POS</span>
    </div>
    <div class="nav-section">Navigation</div>';

    foreach ($nav as [$file,$icon,$label]) {
        $active = ($cur===$file) ? ' active' : '';
        echo '<a class="nav-item'.$active.'" href="'.$base.'/'.$dir.'/'.$file.'">
            <span class="ni">'.$icon.'</span><span>'.$label.'</span>
        </a>';
    }

    echo '<div class="sidebar-bottom">
        <div class="sidebar-user">
            <div class="user-av">'.$initial.'</div>
            <div class="user-info">
                <div class="user-name">'.e($user['name']).'</div>
                <div class="user-role">'.ucfirst($role).'</div>
            </div>
            <a href="'.$base.'/auth/logout.php" class="logout-btn" title="Logout">⏻</a>
        </div>
    </div>
    </aside>';
}

function topbar(string $title, string $sub=''): void {
    $subHtml = $sub ? '<span class="topbar-sub">/ '.e($sub).'</span>' : '';
    echo '<div class="main">
    <div class="topbar">
        <button type="button" class="menu-toggle" onclick="toggleSidebar()" aria-label="Toggle navigation">☰</button>
        <div class="topbar-title">'.e($title).$subHtml.'</div>
    </div>
    <div class="content">';
}

function pageEnd(): void {
    $base = APP_URL;
    echo '</div></div></div>
<script src="'.$base.'/assets/js/app.js"></script>
</body></html>';
}

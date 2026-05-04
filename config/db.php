<?php
// ============================================================
//  CortexPOS — Database Configuration
//  Edit DB_PASS if your MySQL has a password
// ============================================================
define('DB_HOST',    'localhost');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_NAME',    'cortexpos');
define('APP_NAME',   'CortexPOS');
define('APP_URL',    'http://localhost/cortexpos_v2');
define('OTC_EXPIRY', 30); // minutes

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    try {
        $pdo = new PDO(
            "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
             PDO::ATTR_EMULATE_PREPARES=>false]
        );
    } catch (PDOException $e) {
        die('<div style="font-family:sans-serif;padding:40px;background:#1a100a;color:#f5e6c8;min-height:100vh">
            <h2 style="color:#d4922a">Database Connection Failed</h2>
            <p>Make sure XAMPP MySQL is running and the database exists.</p>
            <p style="color:#8a6a4a">Run <a href="../setup.php" style="color:#d4922a">setup.php</a> first.</p>
            <code style="color:#e8907e">'.$e->getMessage().'</code></div>');
    }
    return $pdo;
}

<?php
// includes/config.php — core config, DB, session, helpers
// Uses __DIR__ everywhere so paths always resolve correctly.

// DB credentials: use Railway env vars when deployed, fall back to local XAMPP
define('DB_HOST', getenv('MYSQLHOST') ?: 'localhost');
define('DB_PORT', getenv('MYSQLPORT') ?: '3306');
define('DB_USER', getenv('MYSQLUSER') ?: 'root');
define('DB_PASS', getenv('MYSQLPASSWORD') ?: '');
define('DB_NAME', getenv('MYSQLDATABASE') ?: 'expedia_ph');
define('SITE_NAME', 'Expedia PH');
define('CURRENCY',  '₱');

// Auto-detect site URL — works on local XAMPP and on Railway
$_host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_isLocal = ($_host === 'localhost' || str_contains($_host, '127.0.0.1'));
// Railway terminates SSL at the proxy and sets X-Forwarded-Proto
$_proto = 'http';
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') $_proto = 'https';
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) $_proto = $_SERVER['HTTP_X_FORWARDED_PROTO'];
if ($_isLocal) {
    $_script = $_SERVER['SCRIPT_NAME'] ?? '/expedia/index.php';
    $_base   = implode('/', array_slice(explode('/', $_script), 0, 2));
} else {
    $_base = '';
}
define('SITE_URL', $_proto . '://' . $_host . $_base);

/* ── PDO singleton ─────────────────────────────────────────── */
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME.';charset=utf8mb4',
                DB_USER, DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            die('
<!DOCTYPE html><html><head><meta charset="UTF-8">
<style>body{font-family:sans-serif;padding:2rem;background:#f5f5f5}
.box{background:#fff;border:2px solid #c00;border-radius:8px;padding:2rem;max-width:600px;margin:2rem auto}
h2{color:#c00;margin:0 0 1rem}pre{background:#ffeaea;padding:1rem;border-radius:4px;font-size:13px;overflow:auto}</style></head><body>
<div class="box">
<h2>⚠ Database connection failed</h2>
<p>Make sure XAMPP MySQL is running and you have imported <strong>expedia_ph_FINAL.sql</strong>.</p>
<pre>'.htmlspecialchars($e->getMessage()).'</pre>
<p>If you set a MySQL root password, open <code>includes/config.php</code> and update <code>DB_PASS</code>.</p>
</div></body></html>');
        }
    }
    return $pdo;
}

/* ── Session ───────────────────────────────────────────────── */
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['path' => '/', 'samesite' => 'Lax']);
    session_start();
}

/* ── Auth helpers ──────────────────────────────────────────── */
function isLoggedIn(): bool { return !empty($_SESSION['user_id']); }
function isAdmin(): bool    { return isLoggedIn() && ($_SESSION['role'] ?? '') === 'admin'; }
function isStaff(): bool    { return isLoggedIn() && ($_SESSION['role'] ?? '') === 'staff'; }
function isAdminOrStaff(): bool { return isAdmin() || isStaff(); }

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . SITE_URL . '/pages/login.php?next=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}
function requireAdmin(): void {
    if (!isAdmin()) { header('Location: ' . SITE_URL . '/index.php'); exit; }
}
function requireAdminOrStaff(): void {
    if (!isAdminOrStaff()) { header('Location: ' . SITE_URL . '/index.php'); exit; }
}

function validEmail(string $email): bool {
    return (bool)preg_match('/^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.com$/', $email);
}

/* ── Flash ─────────────────────────────────────────────────── */
function flashSet(string $type, string $msg): void {
    $_SESSION['_flash'] = compact('type','msg');
}
function flashGet(): ?array {
    if (empty($_SESSION['_flash'])) return null;
    $f = $_SESSION['_flash'];
    unset($_SESSION['_flash']);
    return $f;
}

/* ── Currency / Region ─────────────────────────────────────── */
$_CURRENCIES = [
    'PHP' => ['symbol'=>'₱',  'rate'=>1,       'label'=>'Philippine Peso'],
    'USD' => ['symbol'=>'$',  'rate'=>0.0175,   'label'=>'US Dollar'],
    'EUR' => ['symbol'=>'€',  'rate'=>0.016,    'label'=>'Euro'],
    'GBP' => ['symbol'=>'£',  'rate'=>0.0138,   'label'=>'British Pound'],
    'JPY' => ['symbol'=>'¥',  'rate'=>2.65,     'label'=>'Japanese Yen'],
    'SGD' => ['symbol'=>'S$', 'rate'=>0.0234,   'label'=>'Singapore Dollar'],
    'AUD' => ['symbol'=>'A$', 'rate'=>0.027,    'label'=>'Australian Dollar'],
    'KRW' => ['symbol'=>'₩',  'rate'=>23.8,     'label'=>'Korean Won'],
];
$_REGIONS = ['PH'=>'🇵🇭 Philippines','US'=>'🇺🇸 United States','GB'=>'🇬🇧 United Kingdom',
             'AU'=>'🇦🇺 Australia','JP'=>'🇯🇵 Japan','SG'=>'🇸🇬 Singapore','KR'=>'🇰🇷 South Korea','EU'=>'🇪🇺 Europe'];

$_selCurrency = $_SESSION['currency'] ?? 'PHP';
$_selRegion   = $_SESSION['region']   ?? 'PH';
if (!array_key_exists($_selCurrency, $_CURRENCIES)) $_selCurrency = 'PHP';

define('CURR_SYMBOL', $_CURRENCIES[$_selCurrency]['symbol']);
define('CURR_RATE',   $_CURRENCIES[$_selCurrency]['rate']);
define('CURR_CODE',   $_selCurrency);
define('SEL_REGION',  $_selRegion);

/* ── Utilities ─────────────────────────────────────────────── */
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function money(float $n): string {
    $converted = $n * CURR_RATE;
    if (CURR_CODE === 'JPY' || CURR_CODE === 'KRW') return CURR_SYMBOL . number_format($converted, 0);
    return CURR_SYMBOL . number_format($converted, CURR_CODE === 'PHP' ? 0 : 2);
}

function csrf(): string {
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['_csrf'];
}
function verifyCsrf(): void {
    if (!hash_equals($_SESSION['_csrf'] ?? '', $_POST['_csrf'] ?? ''))
        die('Security token mismatch. Please go back and try again.');
}

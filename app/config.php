<?php
// ============================================================
// Loaf'n Ladle - Application Configuration
// ============================================================

// Load environment variables from app/.env (if present)
$envFile = __DIR__ . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
        list($k, $v) = explode('=', $line, 2);
        $k = trim($k);
        $v = trim(trim($v), "\"'");
        if ($k !== '') {
            putenv("$k=$v");
            $_ENV[$k] = $v;
        }
    }
}

define('APP_NAME', "Loaf'n Ladle");
// Dynamic APP_URL that preserves port number
$scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
define('APP_URL', "$scheme://$host/app");
define('APP_ROOT', __DIR__);

// Database
define('DB_PATH', APP_ROOT . '/data/bakery.db');

// Session
define('SESSION_NAME', 'loafnladle_session');
define('SESSION_LIFETIME', 86400); // 24 hours

// Admin account seeded on first run (override via .env)
define('ADMIN_EMAIL', getenv('LNL_ADMIN_EMAIL') ?: 'admin@loafnladle.com');
define('ADMIN_PASSWORD', getenv('LNL_ADMIN_PASSWORD') ?: 'admin123');

// Stripe (configured via Admin panel, stored in DB; can be pre-seeded via .env)
define('STRIPE_WEBHOOK_SECRET', getenv('STRIPE_WEBHOOK_SECRET') ?: '');

// Security
define('BCRYPT_COST', 12);

// Timezone
date_default_timezone_set('America/New_York');

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', APP_ROOT . '/logs/error.log');

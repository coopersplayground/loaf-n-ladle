<?php
// Initial setup: checks the environment, creates .env, initializes the database.
// Run from the command line:  php setup.php

if (php_sapi_name() !== 'cli') {
    exit("This script must be run from the command line.\n");
}

$root = __DIR__;
$errors = [];

echo "Loaf'n Ladle — Setup\n====================\n\n";

// PHP version
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    $errors[] = 'PHP 7.4+ is required (found ' . PHP_VERSION . ')';
} else {
    echo "[ok] PHP " . PHP_VERSION . "\n";
}

// Required / recommended extensions
if (extension_loaded('pdo_sqlite')) {
    echo "[ok] pdo_sqlite\n";
} else {
    $errors[] = 'pdo_sqlite extension is not enabled';
}
if (extension_loaded('curl')) {
    echo "[ok] curl\n";
} else {
    echo "[warn] curl extension missing — required for Stripe payments\n";
}

// Writable directories (test with a real write — is_writable is unreliable on network shares)
foreach (['data', 'logs'] as $d) {
    $dir = "$root/$d";
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    if (@file_put_contents("$dir/.write_test", 'x') !== false) {
        unlink("$dir/.write_test");
        echo "[ok] $d/ writable\n";
    } else {
        $errors[] = "$d/ is not writable by the web server";
    }
}

// .env
if (is_file("$root/.env")) {
    echo "[ok] .env exists\n";
} elseif (is_file("$root/.env.example") && copy("$root/.env.example", "$root/.env")) {
    echo "[ok] Created .env from .env.example — edit it to set your admin password and Stripe keys\n";
}

if ($errors) {
    echo "\nSetup failed:\n - " . implode("\n - ", $errors) . "\n";
    exit(1);
}

// Initialize the database (creates schema and seeds admin + defaults)
require_once "$root/db.php";
Database::getInstance();

echo "\n[ok] Database initialized at data/bakery.db\n";
echo "\nDone. Admin login: " . ADMIN_EMAIL . "\n";
echo "Open /app/index.php?page=admin in your browser to finish configuration.\n";

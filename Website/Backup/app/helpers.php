<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function setting($key, $default = '') {
    $row = Database::getInstance()->fetchOne("SELECT value FROM settings WHERE key = ?", [$key]);
    return $row ? $row['value'] : $default;
}

function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function formatMoney($amount) {
    return '$' . number_format((float)$amount, 2);
}

function generateOrderNumber() {
    return 'LNL-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
}

function assignDailyOrderNumber($orderId) {
    $db = Database::getInstance();
    $today = date('Y-m-d');
    // Get the highest daily_order_number used today (wraps at 999 back to 1)
    $row = $db->fetchOne(
        "SELECT MAX(daily_order_number) as max_num FROM orders WHERE DATE(created_at) = ? AND daily_order_number IS NOT NULL",
        [$today]
    );
    $next = (($row['max_num'] ?? 0) % 999) + 1;
    $db->execute("UPDATE orders SET daily_order_number = ? WHERE id = ?", [$next, $orderId]);
    return $next;
}

function slugify($text) {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-');
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

function flash($type, $message) {
    Auth::startSession();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    Auth::startSession();
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function renderFlash() {
    $flash = getFlash();
    if (!$flash) return '';
    $type = $flash['type'];
    $msg = e($flash['message']);
    $classes = [
        'success' => 'bg-green-50 border-green-400 text-green-800',
        'error'   => 'bg-red-50 border-red-400 text-red-800',
        'info'    => 'bg-blue-50 border-blue-400 text-blue-800',
        'warning' => 'bg-yellow-50 border-yellow-400 text-yellow-800',
    ];
    $cls = $classes[$type] ?? $classes['info'];
    return "<div class=\"border-l-4 p-4 mb-4 rounded {$cls}\" role=\"alert\"><p>{$msg}</p></div>";
}

// Cart helpers (session-based)
function cartAdd($productId, $qty = 1) {
    Auth::startSession();
    if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
    $productId = (int)$productId;
    if (isset($_SESSION['cart'][$productId])) {
        $_SESSION['cart'][$productId] += (int)$qty;
    } else {
        $_SESSION['cart'][$productId] = (int)$qty;
    }
}

function cartRemove($productId) {
    Auth::startSession();
    unset($_SESSION['cart'][(int)$productId]);
}

function cartUpdate($productId, $qty) {
    Auth::startSession();
    $qty = (int)$qty;
    if ($qty <= 0) {
        cartRemove($productId);
    } else {
        $_SESSION['cart'][(int)$productId] = $qty;
    }
}

function cartClear() {
    Auth::startSession();
    $_SESSION['cart'] = [];
}

function cartItems() {
    Auth::startSession();
    if (empty($_SESSION['cart'])) return [];
    $ids = array_keys($_SESSION['cart']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $products = Database::getInstance()->fetchAll(
        "SELECT * FROM products WHERE id IN ($placeholders)",
        $ids
    );
    $items = [];
    foreach ($products as $p) {
        $qty = $_SESSION['cart'][$p['id']] ?? 0;
        if ($qty > 0) {
            $p['quantity'] = $qty;
            $p['line_total'] = $p['price'] * $qty;
            $items[] = $p;
        }
    }
    return $items;
}

function cartTotal() {
    $items = cartItems();
    return array_sum(array_column($items, 'line_total'));
}

function cartCount() {
    Auth::startSession();
    if (empty($_SESSION['cart'])) return 0;
    return array_sum($_SESSION['cart']);
}

function csrfToken() {
    Auth::startSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf($token) {
    Auth::startSession();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function isPost() {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function post($key, $default = '') {
    return $_POST[$key] ?? $default;
}

function get($key, $default = '') {
    return $_GET[$key] ?? $default;
}

function sanitize($str) {
    return htmlspecialchars(strip_tags(trim($str)), ENT_QUOTES, 'UTF-8');
}

function isPageEnabled($pageKey) {
    static $pageStatus = [];
    
    if (empty($pageStatus)) {
        $db = Database::getInstance();
        $pages = $db->fetchAll("SELECT page_key, is_enabled FROM page_management");
        foreach ($pages as $page) {
            $pageStatus[$page['page_key']] = (bool)$page['is_enabled'];
        }
    }
    
    return $pageStatus[$pageKey] ?? true; // Default to enabled if not found
}

function appUrl($path = '') {
    // Detect if running from app directory directly (development server)
    $scriptPath = $_SERVER['PHP_SELF'];
    $isAppRoot = strpos($scriptPath, '/app/') === false;
    
    if ($isAppRoot) {
        // Running from app directory directly - no /app prefix needed
        $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base = $scheme . '://' . $host;
    } else {
        // Running from web root with /app subdirectory
        $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base = $scheme . '://' . $host . '/app';
    }
    return $base . '/' . ltrim($path, '/');
}

function homeUrl() {
    // Always generate URL to the main homepage (parent directory)
    $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . '/index.php';
}

function pageUrl($page, $params = []) {
    // Detect if running from app directory directly (development server)
    $scriptPath = $_SERVER['PHP_SELF'];
    $isAppRoot = strpos($scriptPath, '/app/') === false;
    
    if ($isAppRoot) {
        // Running from app directory directly - no /app prefix needed
        $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base = $scheme . '://' . $host;
    } else {
        // Running from web root with /app subdirectory
        $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base = $scheme . '://' . $host . '/app';
    }
    $url = $base . '/index.php?page=' . $page;
    foreach ($params as $k => $v) {
        $url .= '&' . urlencode($k) . '=' . urlencode($v);
    }
    return $url;
}

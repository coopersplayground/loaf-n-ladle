<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

class Auth {
    private static $db;

    private static function db() {
        if (!self::$db) self::$db = Database::getInstance();
        return self::$db;
    }

    public static function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_set_cookie_params(SESSION_LIFETIME, '/', '', false, true);
            session_start();
        }
    }

    public static function login($email, $password) {
        $user = self::db()->fetchOne("SELECT * FROM users WHERE email = ?", [strtolower(trim($email))]);
        if ($user && password_verify($password, $user['password'])) {
            self::startSession();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            session_regenerate_id(true);
            return $user;
        }
        return false;
    }

    public static function logout() {
        self::startSession();
        $_SESSION = [];
        session_destroy();
    }

    public static function register($name, $email, $password, $phone = '', $address = '') {
        $email = strtolower(trim($email));
        $existing = self::db()->fetchOne("SELECT id FROM users WHERE email = ?", [$email]);
        if ($existing) return ['error' => 'Email already registered.'];
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
        $id = self::db()->insert(
            "INSERT INTO users (name, email, password, phone, address) VALUES (?,?,?,?,?)",
            [$name, $email, $hash, $phone, $address]
        );
        return ['success' => true, 'id' => $id];
    }

    public static function isLoggedIn() {
        self::startSession();
        return isset($_SESSION['user_id']);
    }

    public static function isAdmin() {
        self::startSession();
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    }

    public static function requireLogin($redirect = null) {
        if (!self::isLoggedIn()) {
            $scriptPath = $_SERVER['PHP_SELF'];
            $isAppRoot = strpos($scriptPath, '/app/') === false;
            $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
            $base = $isAppRoot ? $scheme . '://' . $host : $scheme . '://' . $host . '/app';
            if ($redirect === null) {
                $currentUrl = $scheme . '://' . $host . $_SERVER['REQUEST_URI'];
                $redirect = $base . '/index.php?page=login&redirect=' . urlencode($currentUrl);
            }
            header('Location: ' . $redirect);
            exit;
        }
    }

    public static function requireAdmin($redirect = null) {
        if (!self::isAdmin()) {
            if ($redirect === null) {
                // Use dynamic URL detection
                $scriptPath = $_SERVER['PHP_SELF'];
                $isAppRoot = strpos($scriptPath, '/app/') === false;
                $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
                $base = $isAppRoot ? $scheme . '://' . $host : $scheme . '://' . $host . '/app';
                $redirect = $base . '/index.php?page=login';
            }
            header('Location: ' . $redirect);
            exit;
        }
    }

    public static function currentUser() {
        if (!self::isLoggedIn()) return null;
        return self::db()->fetchOne("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    }

    public static function currentUserId() {
        self::startSession();
        return $_SESSION['user_id'] ?? null;
    }
}

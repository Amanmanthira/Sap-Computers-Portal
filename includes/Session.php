<?php
class Session {
    public static function start(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_set_cookie_params(['lifetime' => SESSION_LIFETIME, 'httponly' => true, 'samesite' => 'Lax']);
            session_start();
        }
    }

    public static function set(string $key, mixed $value): void {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed {
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool {
        return isset($_SESSION[$key]);
    }

    public static function delete(string $key): void {
        unset($_SESSION[$key]);
    }

    public static function destroy(): void {
        session_unset();
        session_destroy();
    }

    public static function setFlash(string $type, string $message): void {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }

    public static function getFlash(): ?array {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $flash;
    }

    public static function isLoggedIn(): bool {
        return isset($_SESSION['user_id']);
    }

    public static function requireLogin(): void {
        if (!self::isLoggedIn()) {
            header('Location: /sap-computers/login.php');
            exit;
        }
    }

    public static function requireRole(string $role): void {
        self::requireLogin();
        if (self::get('user_role') !== $role) {
            http_response_code(403);
            die('<h1>403 Forbidden</h1><p>You do not have permission to access this page.</p>');
        }
    }

    public static function requireSeller(): void {
        self::requireLogin();
        $role = self::get('user_role');
        if ($role === 'seller') return;
        if ($role === 'staff') {
            header('Location: /sap-computers/branch_orders.php');
            exit;
        }
        // otherwise supplier or unknown
        header('Location: /sap-computers/supplier/dashboard.php');
        exit;
    }
}

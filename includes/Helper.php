<?php
class Helper {
    public static function escape(mixed $val): string {
        return htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8');
    }

    public static function e(mixed $val): string {
        return self::escape($val);
    }

    public static function formatCurrency(float $amount): string {
        return 'Rs. ' . number_format($amount, 2);
    }

    public static function formatDate(string $date): string {
        return $date ? date('d M Y', strtotime($date)) : '-';
    }

    public static function formatDateTime(string $dt): string {
        return $dt ? date('d M Y, h:i A', strtotime($dt)) : '-';
    }

    public static function sanitize(string $input): string {
        return trim(strip_tags($input));
    }

    public static function redirect(string $url): void {
        header("Location: $url");
        exit;
    }

    public static function jsonResponse(mixed $data, int $status = 200): never {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    public static function isAjax(): bool {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    public static function csrf(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrf(string $token): bool {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function stockBadge(int $qty, int $reorder): string {
        if ($qty <= 0) return '<span class="badge bg-danger">Out of Stock</span>';
        if ($qty <= $reorder) return '<span class="badge bg-warning text-dark">Low Stock</span>';
        return '<span class="badge bg-success">In Stock</span>';
    }

    public static function paginate(int $total, int $page, int $perPage = ITEMS_PER_PAGE): array {
        $totalPages = (int)ceil($total / $perPage);
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;
        return ['total' => $total, 'page' => $page, 'perPage' => $perPage, 'totalPages' => $totalPages, 'offset' => $offset];
    }
}

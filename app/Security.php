<?php
declare(strict_types=1);
final class Security {
    public static function csrfToken(): string {
        if (empty($_SESSION['_csrf'])) $_SESSION['_csrf']=bin2hex(random_bytes(32));
        return $_SESSION['_csrf'];
    }
    public static function verifyCsrf(?string $token): bool {
        return is_string($token) && isset($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'],$token);
    }
    public static function json(array $data,int $status=200): never {
        http_response_code($status); header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
        echo json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); exit;
    }
    public static function e(string $v): string { return htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
}

<?php

namespace QuietRent\Core;

class Auth
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => false,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            session_start();
        }
    }

    public static function login(array $user): void
    {
        self::start();
        session_regenerate_id(true);
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['account_id'] = $user['account_id'];
        $_SESSION['user_name']  = $user['name'];
        $_SESSION['user_role']  = $user['role'];
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        session_destroy();
        setcookie(session_name(), '', time() - 3600, '/');
    }

    public static function check(): bool
    {
        self::start();
        return isset($_SESSION['user_id']);
    }

    public static function user(): ?array
    {
        self::start();
        if (!self::check()) {
            return null;
        }
        return [
            'id'         => $_SESSION['user_id'],
            'account_id' => $_SESSION['account_id'],
            'name'       => $_SESSION['user_name'],
            'role'       => $_SESSION['user_role'],
        ];
    }

    public static function userId(): int
    {
        return (int) ($_SESSION['user_id'] ?? 0);
    }

    public static function accountId(): int
    {
        return (int) ($_SESSION['account_id'] ?? 0);
    }

    public static function switchAccount(int $accountId): void
    {
        self::start();
        $_SESSION['account_id'] = $accountId;
    }

    public static function require(): void
    {
        if (!self::check()) {
            header('Location: /login');
            exit;
        }
    }

    public static function csrf(): string
    {
        self::start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        setcookie('csrf', $_SESSION['csrf_token'], 0, '/', '', false, false);
        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrf(): void
    {
        self::start();
        $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

        if (!$token && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $body = json_decode(file_get_contents('php://input'), true);
            $token = $body['_csrf'] ?? '';
        }

        if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            http_response_code(403);
            die('Invalid CSRF token');
        }
    }
}

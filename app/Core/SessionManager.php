<?php

namespace App\Core;

class SessionManager
{
    private static bool $started = false;
    private static string $secretKey = 'bathyal-app-secret-key-change-in-prod';

    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        $secret = Config::get('app_secret') ?: self::$secretKey;
        self::$secretKey = $secret;

        $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';

        session_set_cookie_params([
            'lifetime' => 86400 * 7, // 7 days
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
        self::$started = true;
    }

    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        self::start();
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    public static function login(array $user): string
    {
        self::start();
        session_regenerate_id(true);

        // Sanitize user object for session
        $safeUser = [
            'id' => (int)$user['id'],
            'email' => $user['email'],
            'full_name' => $user['full_name'],
            'default_mode' => $user['default_mode'] ?? 'simple',
        ];

        $_SESSION['user'] = $safeUser;

        return self::generateToken($safeUser);
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        session_destroy();
        self::$started = false;
    }

    public static function user(): ?array
    {
        self::start();
        return $_SESSION['user'] ?? null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    }

    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public static function generateToken(array $user): string
    {
        $payload = [
            'uid' => (int)$user['id'],
            'exp' => time() + (86400 * 30), // 30 days
        ];
        $encodedPayload = base64_encode(json_encode($payload));
        $signature = hash_hmac('sha256', $encodedPayload, self::$secretKey);
        return "{$encodedPayload}.{$signature}";
    }

    public static function validateToken(string $token): ?int
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }

        [$encodedPayload, $signature] = $parts;
        $expectedSignature = hash_hmac('sha256', $encodedPayload, self::$secretKey);

        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        $payload = json_decode(base64_decode($encodedPayload), true);
        if (!is_array($payload) || !isset($payload['uid']) || !isset($payload['exp'])) {
            return null;
        }

        if ($payload['exp'] < time()) {
            return null; // Expired
        }

        return (int)$payload['uid'];
    }
}

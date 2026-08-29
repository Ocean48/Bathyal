<?php

namespace App\Core;

class Config
{
    private static array $settings = [];
    private static bool $loaded = false;

    public static function load(?string $envPath = null): void
    {
        if (self::$loaded) {
            return;
        }

        $envFile = $envPath ?? dirname(__DIR__, 2) . '/.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || str_starts_with($line, '#')) {
                    continue;
                }
                if (str_contains($line, '=')) {
                    [$key, $value] = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value, " \t\n\r\0\x0B\"'");
                    if (getenv($key) === false) {
                        putenv("{$key}={$value}");
                    }
                    if (!isset($_ENV[$key])) {
                        $_ENV[$key] = $value;
                    }
                    if (!isset($_SERVER[$key])) {
                        $_SERVER[$key] = $value;
                    }
                }
            }
        }

        self::$settings = [
            'app_env' => getenv('APP_ENV') ?: 'development',
            'app_debug' => filter_var(getenv('APP_DEBUG') ?: true, FILTER_VALIDATE_BOOLEAN),
            'app_url' => getenv('APP_URL') ?: 'http://localhost',
            'db' => [
                'host' => getenv('DB_HOST') ?: 'db',
                'port' => (int)(getenv('DB_PORT') ?: 3306),
                'database' => getenv('DB_DATABASE') ?: 'bathyal_db',
                'username' => getenv('DB_USERNAME') ?: 'bathyal_user',
                'password' => getenv('DB_PASSWORD') ?: 'bathyal_secret',
            ],
            'ai' => [
                'url' => getenv('AI_SERVICE_URL') ?: 'http://ai:8000',
            ],
        ];

        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (!self::$loaded) {
            self::load();
        }

        $keys = explode('.', $key);
        $value = self::$settings;

        foreach ($keys as $nestedKey) {
            if (is_array($value) && array_key_exists($nestedKey, $value)) {
                $value = $value[$nestedKey];
            } else {
                return $default;
            }
        }

        return $value;
    }

    public static function isDebug(): bool
    {
        return (bool)self::get('app_debug', false);
    }
}

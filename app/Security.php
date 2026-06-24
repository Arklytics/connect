<?php

declare(strict_types=1);

final class Security
{
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }

    public static function csrfToken(): string
    {
        self::startSession();

        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf_token'];
    }

    public static function csrfField(): string
    {
        return '<input type="hidden" name="_csrf_token" value="' . self::escape(self::csrfToken()) . '">';
    }

    public static function verifyCsrf(): void
    {
        self::startSession();

        $token = $_POST['_csrf_token'] ?? '';
        if (!is_string($token) || !hash_equals($_SESSION['_csrf_token'] ?? '', $token)) {
            http_response_code(419);
            exit('Invalid security token. Please refresh and try again.');
        }
    }

    public static function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function intFrom(mixed $value, int $default = 0): int
    {
        $filtered = filter_var($value, FILTER_VALIDATE_INT);
        return $filtered === false ? $default : (int) $filtered;
    }

    public static function dateFrom(mixed $value, string $default): string
    {
        if (!is_string($value)) {
            return $default;
        }

        $date = DateTime::createFromFormat('Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : $default;
    }

    private static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? null) === '443');
    }
}

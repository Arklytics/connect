<?php

declare(strict_types=1);

final class Database
{
    public static function connect(): mysqli
    {
        $db = self::open();
        if ($db instanceof mysqli) {
            return $db;
        }

        http_response_code(500);
        $message = 'Database connection failed for '
            . Config::get('DB_USER', 'root')
            . '@'
            . Config::get('DB_HOST', 'localhost')
            . ':'
            . Config::get('DB_PORT', '3306')
            . ' using database '
            . Config::get('DB_NAME', 'growthlink')
            . '. Check DB_HOST, DB_PORT, DB_USER, DB_PASSWORD, and DB_NAME in .env.';

        if (self::isApiRequest()) {
            header('Content-Type: application/json; charset=utf-8');
            exit(json_encode([
                'ok' => false,
                'error' => $message,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }

        exit($message);
    }

    public static function connectOrNull(): ?mysqli
    {
        return self::open();
    }

    private static function open(): ?mysqli
    {
        ini_set('mysql.connect_timeout', '3');
        ini_set('default_socket_timeout', '3');
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        try {
            $db = mysqli_init();
            $db->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
            if (defined('MYSQLI_OPT_READ_TIMEOUT')) {
                $db->options(MYSQLI_OPT_READ_TIMEOUT, 3);
            }
            @$db->real_connect(
                Config::get('DB_HOST', 'localhost'),
                Config::get('DB_USER', 'root'),
                Config::get('DB_PASSWORD', ''),
                Config::get('DB_NAME', 'growthlink'),
                (int) Config::get('DB_PORT', '3306')
            );
        } catch (mysqli_sql_exception $exception) {
            return null;
        }

        $db->set_charset('utf8mb4');

        return $db;
    }

    private static function isApiRequest(): bool
    {
        $requestPath = str_replace('\\', '/', (string) ($_SERVER['REQUEST_URI'] ?? $_SERVER['SCRIPT_NAME'] ?? ''));
        $basePath = '/' . trim((string) Config::get('APP_BASE', ''), '/');
        $apiPrefix = ($basePath !== '/' ? $basePath : '') . '/api';
        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));

        return str_starts_with($requestPath, '/api')
            || str_starts_with($requestPath, $apiPrefix)
            || str_contains($scriptName, '/api/');
    }
}

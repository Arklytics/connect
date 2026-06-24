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
        exit(
            'Database connection failed for '
            . Config::get('DB_USER', 'root')
            . '@'
            . Config::get('DB_HOST', 'localhost')
            . ':'
            . Config::get('DB_PORT', '3306')
            . ' using database '
            . Config::get('DB_NAME', 'growthlink')
            . '. Check DB_HOST, DB_PORT, DB_USER, DB_PASSWORD, and DB_NAME in .env.'
        );
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
}

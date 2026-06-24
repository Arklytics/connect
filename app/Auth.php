<?php

declare(strict_types=1);

final class Auth
{
    public static function id(): ?int
    {
        Security::startSession();
        return isset($_SESSION['biz_id']) ? (int) $_SESSION['biz_id'] : null;
    }

    public static function requireLogin(): int
    {
        $id = self::id();
        if ($id === null || $id <= 0) {
            header('Location: login.php');
            exit();
        }

        return $id;
    }

    public static function login(int $businessId): void
    {
        Security::startSession();
        session_regenerate_id(true);
        $_SESSION['biz_id'] = $businessId;
    }

    public static function logout(): void
    {
        Security::startSession();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        session_destroy();
    }

    public static function masterId(): ?int
    {
        Security::startSession();
        return isset($_SESSION['master_id']) ? (int) $_SESSION['master_id'] : null;
    }

    public static function requireMaster(): int
    {
        $id = self::masterId();
        if ($id === null || $id <= 0) {
            header('Location: manuel/login.php');
            exit();
        }

        return $id;
    }

    public static function loginMaster(int $masterId): void
    {
        Security::startSession();
        session_regenerate_id(true);
        $_SESSION['master_id'] = $masterId;
    }

    public static function logoutMaster(): void
    {
        self::logout();
    }
}

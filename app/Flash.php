<?php

declare(strict_types=1);

final class Flash
{
    public static function set(string $message, string $type = 'success'): void
    {
        Security::startSession();
        $_SESSION['_flash'] = ['message' => $message, 'type' => $type];
    }

    public static function pull(): ?array
    {
        Security::startSession();
        $flash = $_SESSION['_flash'] ?? null;
        unset($_SESSION['_flash']);

        return is_array($flash) ? $flash : null;
    }
}

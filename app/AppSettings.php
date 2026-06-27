<?php

declare(strict_types=1);

final class AppSettings
{
    public static function getGlobal(mysqli $db, string $key, ?string $default = null): ?string
    {
        return self::get($db, 0, $key, $default);
    }

    public static function get(mysqli $db, int $adminId, string $key, ?string $default = null): ?string
    {
        try {
            $stmt = $db->prepare('SELECT setting_value FROM gd_app_settings WHERE admin_id = ? AND setting_key = ? LIMIT 1');
            $stmt->bind_param('is', $adminId, $key);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();

            if ($row && array_key_exists('setting_value', $row)) {
                $value = trim((string) $row['setting_value']);
                if ($value !== '') {
                    return $value;
                }
            }
        } catch (Throwable $exception) {
            // Fall back to env below.
        }

        $envValue = Config::get($key, $default);
        return $envValue !== null && $envValue !== '' ? $envValue : $default;
    }

    public static function setGlobal(mysqli $db, array $values): void
    {
        self::set($db, 0, $values);
    }

    public static function set(mysqli $db, int $adminId, array $values): void
    {
        $stmt = $db->prepare('
            INSERT INTO gd_app_settings (admin_id, setting_key, setting_value, created_at, updated_at)
            VALUES (?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
        ');

        foreach ($values as $key => $value) {
            $settingKey = trim((string) $key);
            if ($settingKey === '') {
                continue;
            }

            $settingValue = trim((string) $value);
            $stmt->bind_param('iss', $adminId, $settingKey, $settingValue);
            $stmt->execute();
        }
    }

    public static function masked(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $length = strlen($value);
        if ($length <= 6) {
            return str_repeat('*', $length);
        }

        return substr($value, 0, 3) . str_repeat('*', max(4, $length - 6)) . substr($value, -3);
    }
}

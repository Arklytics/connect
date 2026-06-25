<?php

declare(strict_types=1);

require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Security.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Flash.php';
require_once __DIR__ . '/Crm.php';

Config::load(dirname(__DIR__) . '/.env');
Security::startSession();

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

function h(mixed $value): string
{
    return Security::escape($value);
}

function app_base_path(): string
{
    $configuredBase = Config::get('APP_BASE');
    if ($configuredBase !== null && $configuredBase !== '') {
        return '/' . trim(str_replace('\\', '/', $configuredBase), '/');
    }

    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (preg_match('~^[A-Za-z]:/~', $scriptName)) {
        return '';
    }

    $basePath = rtrim(str_replace('/index.php', '', dirname($scriptName)), '/');

    return $basePath === '' || $basePath === '.' || $basePath === '/' ? '' : $basePath;
}

function app_url(string $path = ''): string
{
    $basePath = app_base_path();
    $path = '/' . ltrim($path, '/');

    return ($basePath !== '' ? $basePath : '') . $path;
}

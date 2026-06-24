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

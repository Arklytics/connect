<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

function app_dispatch(string $relativeFile): void
{
    $target = __DIR__ . '/' . ltrim($relativeFile, '/');
    if (!is_file($target)) {
        http_response_code(404);
        echo '404 Page Not Found';
        return;
    }

    $previousDir = getcwd();
    chdir(dirname($target));
    require $target;

    if ($previousDir !== false) {
        chdir($previousDir);
    }
}

$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$requestPath = preg_replace('~/{2,}~', '/', $requestPath) ?? '/';

if ($requestPath === '/' || $requestPath === '/index.php') {
    app_dispatch('website/index.php');
    return;
}

if (str_starts_with($requestPath, '/business')) {
    $subPath = trim(substr($requestPath, strlen('/business')), '/');
    app_dispatch($subPath === '' ? 'website/index.php' : 'website/' . $subPath . '.php');
    return;
}

if (str_starts_with($requestPath, '/master')) {
    $subPath = trim(substr($requestPath, strlen('/master')), '/');
    app_dispatch($subPath === '' ? 'master/index.php' : 'master/' . $subPath . '.php');
    return;
}

http_response_code(404);
echo '404 Page Not Found';

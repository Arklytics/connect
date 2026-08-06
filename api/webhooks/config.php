<?php

declare(strict_types=1);

require_once __DIR__ . '/../../db_conn.php';

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$payload = ApiSupport::requestJson();
$requestedBizId = Security::intFrom($payload['biz_id'] ?? ($_GET['biz_id'] ?? null));
$bizId = ApiSupport::requireBusinessApiKey($db, $requestedBizId);

try {
    ApiSupport::ensureApiWebhookColumns($db);
} catch (Throwable $exception) {
    ApiSupport::jsonResponse([
        'ok' => false,
        'error' => 'API webhooks are not installed. Run migrations first.',
    ], 500);
}

if ($method === 'GET') {
    $config = ApiSupport::apiWebhookConfig($db, $bizId);
    ApiSupport::jsonResponse([
        'ok' => true,
        'biz_id' => $bizId,
        'webhook' => [
            'enabled' => $config['enabled'],
            'url' => $config['url'],
            'secret_present' => $config['secret'] !== '',
        ],
    ], 200);
}

if ($method === 'DELETE') {
    $stmt = $db->prepare('UPDATE gd_orders SET api_webhook_enabled = 0 WHERE id = ?');
    $stmt->bind_param('i', $bizId);
    $stmt->execute();

    ApiSupport::jsonResponse([
        'ok' => true,
        'biz_id' => $bizId,
        'webhook' => [
            'enabled' => false,
        ],
    ], 200);
}

if (!in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
    ApiSupport::jsonResponse(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

$url = trim((string) ($payload['url'] ?? $payload['webhook_url'] ?? ''));
$enabled = filter_var($payload['enabled'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
$enabled = $enabled ?? true;
$secret = trim((string) ($payload['secret'] ?? ''));

if ($enabled && $url === '') {
    ApiSupport::jsonResponse(['ok' => false, 'error' => 'url is required when enabling API webhooks.'], 422);
}

if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
    ApiSupport::jsonResponse(['ok' => false, 'error' => 'url must be a valid absolute URL.'], 422);
}

if ($secret === '' && $enabled) {
    $current = ApiSupport::apiWebhookConfig($db, $bizId);
    $secret = $current['secret'] !== '' ? $current['secret'] : 'whsec_' . bin2hex(random_bytes(24));
}

$stmt = $db->prepare('UPDATE gd_orders SET api_webhook_url = ?, api_webhook_secret = ?, api_webhook_enabled = ? WHERE id = ?');
$enabledInt = $enabled ? 1 : 0;
$stmt->bind_param('ssii', $url, $secret, $enabledInt, $bizId);
$stmt->execute();

ApiSupport::jsonResponse([
    'ok' => true,
    'biz_id' => $bizId,
    'webhook' => [
        'enabled' => $enabled,
        'url' => $url,
        'secret' => $secret,
    ],
], 200);

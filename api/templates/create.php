<?php

declare(strict_types=1);

require_once __DIR__ . '/../../db_conn.php';

function apiTemplateDecodeJsonField(array &$payload, string $key): void
{
    if (!isset($payload[$key]) || is_array($payload[$key])) {
        return;
    }

    $decoded = json_decode((string) $payload[$key], true);
    if (is_array($decoded)) {
        $payload[$key] = $decoded;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiSupport::jsonResponse(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

$contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
$payload = strpos($contentType, 'multipart/form-data') !== false ? $_POST : ApiSupport::requestJson();
apiTemplateDecodeJsonField($payload, 'body_samples');
apiTemplateDecodeJsonField($payload, 'buttons');

$requestedBizId = Security::intFrom($payload['biz_id'] ?? null);
$bizId = ApiSupport::requireBusinessApiKey($db, $requestedBizId);
$mediaFile = is_array($_FILES['header_media_file'] ?? null) ? $_FILES['header_media_file'] : null;

$result = ApiSupport::createWhatsappTemplate($db, $bizId, $payload, $mediaFile);
$status = (int) ($result['status'] ?? (($result['ok'] ?? false) ? 201 : 422));
unset($result['status']);

if (!($result['ok'] ?? false)) {
    ApiSupport::jsonResponse($result, $status);
}

ApiSupport::jsonResponse($result, $status);

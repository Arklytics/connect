<?php

declare(strict_types=1);

require_once __DIR__ . '/../db_conn.php';

Auth::requireMaster();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'http_code' => 405,
        'response' => null,
        'error' => 'Method not allowed.',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function metaRegisterPhoneNumber(string $phoneNumberId, string $accessToken, string $pin = '123456'): array
{
    if ($phoneNumberId === '') {
        return [
            'ok' => false,
            'http_code' => 0,
            'response' => null,
            'error' => 'phone_number_id is required.',
        ];
    }

    if ($accessToken === '') {
        return [
            'ok' => false,
            'http_code' => 0,
            'response' => null,
            'error' => 'Access token is required.',
        ];
    }

    $url = 'https://graph.facebook.com/v23.0/' . rawurlencode($phoneNumberId) . '/register';
    $payload = [
        'messaging_product' => 'whatsapp',
        'pin' => $pin,
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $decoded = json_decode((string) $response, true);
    $error = '';
    if ($curlError !== '') {
        $error = $curlError;
    } elseif ($httpCode < 200 || $httpCode >= 300) {
        $error = (string) ($decoded['error']['message'] ?? $response ?? ('HTTP ' . $httpCode));
    }

    return [
        'ok' => $curlError === '' && $httpCode >= 200 && $httpCode < 300,
        'http_code' => $httpCode,
        'response' => $decoded,
        'error' => $error,
    ];
}

$phoneNumberId = trim((string) ($_POST['phone_number_id'] ?? ''));
$accessToken = trim((string) ($_POST['access_token'] ?? ''));
$pin = trim((string) ($_POST['pin'] ?? '123456'));

if ($accessToken === '') {
    $accessToken = trim((string) AppSettings::getGlobal($db, 'META_ACCESS_TOKEN', Config::get('META_ACCESS_TOKEN', '')));
}

$result = metaRegisterPhoneNumber($phoneNumberId, $accessToken, $pin !== '' ? $pin : '123456');

header('Content-Type: application/json; charset=utf-8');
http_response_code($result['ok'] ? 200 : 400);
echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

<?php

declare(strict_types=1);

final class ApiSupport
{
    public static function jsonResponse(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function requestJson(): array
    {
        $raw = trim((string) file_get_contents('php://input'));
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function requireBearerToken(): void
    {
        $db = Database::connectOrNull();
        $expected = '';
        if ($db) {
            $expected = trim((string) AppSettings::getGlobal($db, 'API_TOKEN', ''));
        }
        if ($expected === '') {
            $expected = trim((string) Config::get('API_TOKEN', ''));
        }
        if ($expected === '') {
            self::jsonResponse([
                'ok' => false,
                'error' => 'API_TOKEN is not configured.',
            ], 500);
        }

        $provided = self::extractToken();
        if ($provided === '' || !hash_equals($expected, $provided)) {
            self::jsonResponse([
                'ok' => false,
                'error' => 'Unauthorized.',
            ], 401);
        }
    }

    public static function extractToken(): string
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) === 'authorization') {
                $value = trim((string) $value);
                if (stripos($value, 'Bearer ') === 0) {
                    return trim(substr($value, 7));
                }
                return $value;
            }

            if (strtolower((string) $key) === 'x-api-key') {
                return trim((string) $value);
            }
        }

        $authorization = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
        if (stripos($authorization, 'Bearer ') === 0) {
            return trim(substr($authorization, 7));
        }

        return trim((string) ($_SERVER['HTTP_X_API_KEY'] ?? $authorization));
    }

    public static function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^\d+]/', '', trim($phone));
        if ($phone === '') {
            return '';
        }

        if (!preg_match('/^\+\d+$/', $phone)) {
            $phone = '+91' . ltrim($phone, '+');
        }

        return $phone;
    }

    public static function whatsappSendRequest(string $phoneNumberId, string $accessToken, array $payload): array
    {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://graph.facebook.com/v18.0/' . rawurlencode($phoneNumberId) . '/messages',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken,
            ],
        ]);

        $response = curl_exec($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        $decoded = json_decode((string) $response, true);
        $messageId = $decoded['messages'][0]['id'] ?? null;

        return [
            'ok' => $httpCode >= 200 && $httpCode < 300 && $messageId !== null,
            'message_id' => $messageId,
            'error' => $decoded['error']['message'] ?? ($curlError !== '' ? $curlError : null),
            'http_code' => $httpCode,
            'raw' => $decoded,
        ];
    }

    public static function whatsappTextPayload(string $to, string $messageBody): array
    {
        return [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $messageBody,
            ],
        ];
    }

    public static function whatsappTemplatePayload(string $to, string $templateName, string $language = 'en', array $components = []): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $language],
            ],
        ];

        if (!empty($components)) {
            $payload['template']['components'] = $components;
        }

        return $payload;
    }

    public static function storeSentMessage(
        mysqli $db,
        int $bizId,
        string $phoneNumber,
        ?int $templateId,
        string $messageTitle,
        string $messageBody,
        string $status,
        ?string $deliveryStatus,
        ?string $errorMessage,
        ?string $messageId,
        ?string $sentAt = null
    ): void {
        $stmt = $db->prepare('
            INSERT INTO gd_sent_messages
                (biz_id, phone_number, template_id, message_title, message_body, status, delivery_status, error_message, message_id, sent_at, created_at, updated_at)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');

        $now = date('Y-m-d H:i:s');
        $templateIdValue = $templateId;
        $sentAtValue = $sentAt;
        $stmt->bind_param(
            'isisssssssss',
            $bizId,
            $phoneNumber,
            $templateIdValue,
            $messageTitle,
            $messageBody,
            $status,
            $deliveryStatus,
            $errorMessage,
            $messageId,
            $sentAtValue,
            $now,
            $now
        );
        $stmt->execute();
    }

    public static function consumeMessageCredit(mysqli $db, int $bizId, int $count = 1): void
    {
        if ($count <= 0) {
            return;
        }

        $stmt = $db->prepare('UPDATE gd_orders SET messages_used = COALESCE(messages_used, 0) + ? WHERE id = ?');
        $stmt->bind_param('ii', $count, $bizId);
        $stmt->execute();
    }

    public static function businessCredentials(mysqli $db, int $bizId): array
    {
        $stmt = $db->prepare('SELECT phone_number_id, auth_token FROM gd_orders WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $bizId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: [];
    }

    public static function businessPackageStatus(mysqli $db, int $bizId): array
    {
        try {
            $columns = [];
            $colsStmt = $db->prepare('SHOW COLUMNS FROM gd_orders');
            $colsStmt->execute();
            $colsResult = $colsStmt->get_result();
            while ($row = $colsResult->fetch_assoc()) {
                $columns[] = $row['Field'] ?? '';
            }

            if (!in_array('message_limit', $columns, true) || !in_array('messages_used', $columns, true)) {
                return ['enabled' => false, 'limit' => null, 'used' => null, 'remaining' => null];
            }

            $stmt = $db->prepare('SELECT COALESCE(message_limit, 0) AS message_limit, COALESCE(messages_used, 0) AS messages_used FROM gd_orders WHERE id = ? LIMIT 1');
            $stmt->bind_param('i', $bizId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc() ?: [];
            $limit = (int) ($row['message_limit'] ?? 0);
            $used = (int) ($row['messages_used'] ?? 0);

            return [
                'enabled' => true,
                'limit' => $limit,
                'used' => $used,
                'remaining' => max(0, $limit - $used),
            ];
        } catch (Throwable $exception) {
            return ['enabled' => false, 'limit' => null, 'used' => null, 'remaining' => null];
        }
    }
}

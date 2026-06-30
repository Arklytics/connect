<?php

declare(strict_types=1);

final class ApiSupport
{
    public static function encodeJson(mixed $value): ?string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return $encoded === false ? null : $encoded;
    }

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
        $requestJson = self::encodeJson($payload);
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

        $responseBody = is_string($response) ? $response : '';
        $decoded = json_decode($responseBody, true);
        $messageId = $decoded['messages'][0]['id'] ?? null;
        $responseJson = null;
        if ($responseBody !== '') {
            $responseJson = json_last_error() === JSON_ERROR_NONE && is_array($decoded)
                ? self::encodeJson($decoded)
                : $responseBody;
        }

        $failureReason = null;
        if ($curlError !== '') {
            $failureReason = 'cURL error: ' . $curlError;
        } elseif ($httpCode >= 400) {
            $failureReason = $decoded['error']['message'] ?? ('WhatsApp API returned HTTP ' . $httpCode);
        } elseif ($httpCode < 200 || $httpCode >= 300 || $messageId === null) {
            $failureReason = $decoded['error']['message'] ?? ($responseBody !== '' ? 'Unexpected WhatsApp response.' : 'Empty WhatsApp response.');
        }

        return [
            'ok' => $httpCode >= 200 && $httpCode < 300 && $messageId !== null,
            'message_id' => $messageId,
            'error' => $failureReason,
            'failure_reason' => $failureReason,
            'http_code' => $httpCode,
            'request_json' => $requestJson,
            'response_json' => $responseJson,
            'raw' => $decoded,
        ];
    }

    public static function templatePlaceholderNumbers(string $text): array
    {
        preg_match_all('/{{\s*(\d+)\s*}}|\[\s*(\d+)\s*\]/', $text, $matches, PREG_SET_ORDER);
        $numbers = [];
        foreach ($matches as $match) {
            $numbers[] = isset($match[1]) && $match[1] !== '' ? (int) $match[1] : (int) ($match[2] ?? 0);
        }
        $numbers = array_values(array_unique($numbers));
        sort($numbers);

        return $numbers;
    }

    private static function sampleValue(array $values, int $index): string
    {
        if (array_key_exists($index, $values)) {
            return trim((string) $values[$index]);
        }

        $stringKey = (string) $index;
        if (array_key_exists($stringKey, $values)) {
            return trim((string) $values[$stringKey]);
        }

        return '';
    }

public static function buildTemplateSendComponents(array $templateRow): array
{
    $meta = [];

    if (!empty($templateRow['placeholders'])) {
        $decoded = json_decode((string)$templateRow['placeholders'], true);
        if (is_array($decoded)) {
            $meta = $decoded;
        }
    }

    $components = [];

    /*
    |--------------------------------------------------------------------------
    | HEADER
    |--------------------------------------------------------------------------
    */

    $headerType = strtoupper(trim((string)($meta['header_type'] ?? '')));

    switch ($headerType) {

        case 'TEXT':

            $headerText = (string)($meta['header_text'] ?? '');

            preg_match_all('/{{\s*(\d+)\s*}}/', $headerText, $matches);

            if (!empty($matches[1])) {

                $components[] = [
                    'type' => 'header',
                    'parameters' => [[
                        'type' => 'text',
                        'text' => trim((string)($meta['header_sample'] ?? ''))
                    ]]
                ];
            }

            break;

        case 'IMAGE':
        case 'VIDEO':
        case 'DOCUMENT':

            $type = strtolower($headerType);

            $mediaUrl = trim(
                (string)(
                    $meta['header_media_url']
                    ?? $templateRow['media_url']
                    ?? ''
                )
            );

            if ($mediaUrl !== '') {

                $parameter = [
                    'type' => $type,
                    $type => [
                        'link' => $mediaUrl
                    ]
                ];

                if ($type === 'document') {
                    $parameter['document']['filename'] =
                        basename(parse_url($mediaUrl, PHP_URL_PATH));
                }

                $components[] = [
                    'type' => 'header',
                    'parameters' => [$parameter]
                ];
            }

            break;
    }

    /*
    |--------------------------------------------------------------------------
    | BODY
    |--------------------------------------------------------------------------
    */

    $body = (string)($templateRow['message_body'] ?? '');

    preg_match_all('/{{\s*(\d+)\s*}}/', $body, $matches);

    if (!empty($matches[1])) {

        $parameters = [];

        foreach ($matches[1] as $number) {

            $value = self::templateExampleValue(
                $meta['body_samples'] ?? [],
                (int)$number
            );

            if ($value === '') {
                $value = 'Sample';
            }

            $parameters[] = [
                'type' => 'text',
                'text' => $value
            ];
        }

        $components[] = [
            'type' => 'body',
            'parameters' => $parameters
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | BUTTONS
    |--------------------------------------------------------------------------
    */

    if (!empty($meta['buttons']) && is_array($meta['buttons'])) {

        foreach ($meta['buttons'] as $index => $button) {

            $buttonType = strtoupper($button['type'] ?? '');

            if ($buttonType === 'URL') {

                $components[] = [
                    'type' => 'button',
                    'sub_type' => 'url',
                    'index' => (string)$index,
                    'parameters' => [[
                        'type' => 'text',
                        'text' => $button['sample'] ?? ''
                    ]]
                ];
            }
        }
    }

    return [
        'components' => $components,
        'error' => null
    ];
}
    private static function buildComponentsFromPayload(array $templateRow, array $meta, array $payloadComponents): array
    {
        $components = [];
        foreach ($payloadComponents as $component) {
            if (!is_array($component)) {
                continue;
            }

            $type = strtoupper(trim((string) ($component['type'] ?? '')));
            if ($type === 'BODY') {
                $built = self::buildBodyComponent($templateRow, $meta, $component);
                if ($built !== null) {
                    $components[] = $built;
                }
                continue;
            }

            if ($type === 'HEADER') {
                $built = self::buildHeaderComponent($templateRow, $meta, $component);
                if ($built !== null) {
                    $components[] = $built;
                }
                continue;
            }

            if ($type === 'BUTTONS' || $type === 'BUTTON') {
                $builtButtons = self::buildButtonComponents($templateRow, $meta, $component);
                if (isset($builtButtons['error'])) {
                    return $builtButtons;
                }

                foreach (($builtButtons['components'] ?? []) as $buttonComponent) {
                    $components[] = $buttonComponent;
                }
            }
        }

        return [
            'components' => $components,
            'error' => null,
        ];
    }

    private static function buildComponentsFromLegacyTemplate(array $templateRow, array $meta): array
    {
        $components = [];

        $bodyNumbers = self::templatePlaceholderNumbers((string) ($templateRow['message_body'] ?? ''));
        if (!empty($bodyNumbers)) {
            $bodySamples = [];
            if (isset($meta['body_samples']) && is_array($meta['body_samples'])) {
                $bodySamples = $meta['body_samples'];
            }

            $parameters = [];
            foreach ($bodyNumbers as $number) {
                $sample = self::templateExampleValue($bodySamples, $number);
                if ($sample === '') {
                    return [
                        'components' => [],
                        'error' => 'This template needs sample values for every body placeholder before it can be sent.',
                    ];
                }

                $parameters[] = [
                    'type' => 'text',
                    'text' => $sample,
                ];
            }

            $components[] = [
                'type' => 'body',
                'parameters' => $parameters,
            ];
        }

        $headerType = strtoupper(trim((string) ($meta['header_type'] ?? '')));
        $headerText = trim((string) ($meta['header_text'] ?? ($templateRow['message_title'] ?? '')));
        $headerNumbers = self::templatePlaceholderNumbers($headerText);
        if ($headerType === 'TEXT' && !empty($headerNumbers)) {
            $headerSample = trim((string) ($meta['header_sample'] ?? ''));
            if ($headerSample === '') {
                return [
                    'components' => [],
                    'error' => 'This template needs a header sample before it can be sent.',
                ];
            }

            $components[] = [
                'type' => 'header',
                'parameters' => [
                    [
                        'type' => 'text',
                        'text' => $headerSample,
                    ],
                ],
            ];
        }

        if (in_array($headerType, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)) {
            $mediaUrl = trim((string) ($meta['header_media_url'] ?? ($templateRow['media_url'] ?? '')));
            if ($mediaUrl === '') {
                return [
                    'components' => [],
                    'error' => 'This template needs a media URL for the header before it can be sent.',
                ];
            }

            $components[] = self::buildMediaHeaderComponent($headerType, $mediaUrl);
        }

        return [
            'components' => $components,
            'error' => null,
        ];
    }

    private static function buildBodyComponent(array $templateRow, array $meta, array $component): ?array
    {
        $text = (string) ($component['text'] ?? $templateRow['message_body'] ?? '');
        $numbers = self::templatePlaceholderNumbers($text);
        if (empty($numbers)) {
            return null;
        }

        $examples = self::extractComponentExamples($component, 'body_text');
        if (empty($examples) && isset($meta['body_samples']) && is_array($meta['body_samples'])) {
            $examples = $meta['body_samples'];
        }

        $parameters = [];
        foreach ($numbers as $number) {
            $sample = self::templateExampleValue($examples, $number);
            if ($sample === '') {
                return null;
            }

            $parameters[] = [
                'type' => 'text',
                'text' => $sample,
            ];
        }

        return [
            'type' => 'body',
            'parameters' => $parameters,
        ];
    }

    private static function buildHeaderComponent(array $templateRow, array $meta, array $component): ?array
    {
        $format = strtoupper(trim((string) ($component['format'] ?? 'TEXT')));

        if ($format === 'TEXT') {
            $text = trim((string) ($component['text'] ?? $templateRow['message_title'] ?? ''));
            $numbers = self::templatePlaceholderNumbers($text);
            if (empty($numbers)) {
                return null;
            }

            $examples = self::extractComponentExamples($component, 'header_text');
            if (empty($examples) && isset($meta['header_sample'])) {
                $examples = [1 => $meta['header_sample']];
            }

            $parameters = [];
            foreach ($numbers as $number) {
                $sample = self::templateExampleValue($examples, $number);
                if ($sample === '') {
                    return null;
                }

                $parameters[] = [
                    'type' => 'text',
                    'text' => $sample,
                ];
            }

            return [
                'type' => 'header',
                'parameters' => $parameters,
            ];
        }

        if (in_array($format, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)) {
            $mediaUrl = trim((string) ($meta['header_media_url'] ?? ($templateRow['media_url'] ?? '')));
            if ($mediaUrl === '') {
                return null;
            }

            return self::buildMediaHeaderComponent($format, $mediaUrl);
        }

        return null;
    }

    private static function buildButtonComponents(array $templateRow, array $meta, array $component): array
    {
        $buttons = [];
        if (isset($meta['buttons']) && is_array($meta['buttons'])) {
            $buttons = $meta['buttons'];
        } elseif (isset($templateRow['buttons'])) {
            $decodedButtons = json_decode((string) $templateRow['buttons'], true);
            if (is_array($decodedButtons)) {
                $buttons = $decodedButtons;
            }
        }

        if (empty($buttons) && isset($component['buttons']) && is_array($component['buttons'])) {
            $buttons = $component['buttons'];
        }

        if (empty($buttons)) {
            return [
                'components' => [],
                'error' => null,
            ];
        }

        $sendComponents = [];
        foreach (array_values($buttons) as $index => $button) {
            if (!is_array($button)) {
                continue;
            }

            $buttonType = strtoupper(trim((string) ($button['type'] ?? '')));
            if ($buttonType !== 'URL') {
                continue;
            }

            $url = trim((string) ($button['url'] ?? $button['link'] ?? ''));
            if ($url === '') {
                continue;
            }

            $numbers = self::templatePlaceholderNumbers($url);
            if (empty($numbers)) {
                continue;
            }

            $sampleUrl = $url;
            foreach ($numbers as $number) {
                $replacement = self::templateExampleValue(
                    isset($meta['body_samples']) && is_array($meta['body_samples']) ? $meta['body_samples'] : [],
                    $number
                );
                if ($replacement === '') {
                    $replacement = self::templateExampleValue(
                        isset($meta['button_samples']) && is_array($meta['button_samples']) ? $meta['button_samples'] : [],
                        $number
                    );
                }

                if ($replacement === '') {
                    return [
                        'components' => [],
                        'error' => 'This template needs sample values for dynamic button URLs before it can be sent.',
                    ];
                }

                $sampleUrl = preg_replace('/\{\{\s*' . $number . '\s*\}\}/', $replacement, $sampleUrl) ?? $sampleUrl;
            }

            $sendComponents[] = [
                'type' => 'button',
                'sub_type' => 'url',
                'index' => (string) $index,
                'parameters' => [
                    [
                        'type' => 'text',
                        'text' => $sampleUrl,
                    ],
                ],
            ];
        }

        return [
            'components' => $sendComponents,
            'error' => null,
        ];
    }

    private static function buildMediaHeaderComponent(string $format, string $mediaUrl): array
    {
        $type = strtolower($format);
        $mimeType = $type;
        $parameter = [
            'type' => $mimeType,
            $mimeType => [
                'link' => $mediaUrl,
            ],
        ];

        if ($type === 'document') {
            $filename = basename(parse_url($mediaUrl, PHP_URL_PATH) ?: $mediaUrl);
            if ($filename !== '') {
                $parameter['document']['filename'] = $filename;
            }
        }

        return [
            'type' => 'header',
            'parameters' => [
                $parameter,
            ],
        ];
    }

public static function metaUploadMediaHandle(
    string $appId,
    string $accessToken,
    string $filePath,
    string $fileName,
    string $fileType,
    int $fileLength
): array {

    if (!is_file($filePath)) {
        return [
            'ok' => false,
            'handle' => null,
            'error' => 'File not found.'
        ];
    }

    $graphVersion = 'v23.0';

    /*
     * STEP 1
     * Create Upload Session
     */

    $url = "https://graph.facebook.com/{$graphVersion}/{$appId}/uploads";

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer {$accessToken}",
        ],
        CURLOPT_POSTFIELDS => http_build_query([
            'file_name'   => $fileName,
            'file_length' => $fileLength,
            'file_type'   => $fileType,
        ]),
    ]);

    $response = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = json_decode($response, true);

    if ($http >= 300 || empty($json['id'])) {

        return [
            'ok' => false,
            'handle' => null,
            'error' => $json['error']['message'] ?? $response,
        ];
    }

    $uploadId = $json['id'];

    /*
     * STEP 2
     * Upload Binary
     */

    $binary = file_get_contents($filePath);

    $uploadUrl = "https://graph.facebook.com/{$graphVersion}/{$uploadId}";

    $ch = curl_init($uploadUrl);

    curl_setopt_array($ch, [

        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_HTTPHEADER => [

            "Authorization: OAuth {$accessToken}",

            "file_offset: 0",

            "Content-Type: application/octet-stream",

            "Content-Length: ".strlen($binary),

        ],

        CURLOPT_POSTFIELDS => $binary,

    ]);

    $response = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    $json = json_decode($response, true);

    if ($http >= 300 || empty($json['h'])) {

        return [

            'ok' => false,

            'handle' => null,

            'error' => $json['error']['message'] ?? $response,

        ];
    }

    return [

        'ok' => true,

        'handle' => $json['h'],

        'error' => null,

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
        ?string $sentAt = null,
        ?string $requestJson = null,
        ?string $responseJson = null,
        ?int $httpStatusCode = null,
        ?string $failureReason = null
    ): void {
        $stmt = $db->prepare('SHOW COLUMNS FROM gd_sent_messages');
        $stmt->execute();
        $result = $stmt->get_result();
        $columns = [];
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'] ?? '';
        }

        $payload = [
            'biz_id' => [$bizId, 'i'],
            'phone_number' => [$phoneNumber, 's'],
            'template_id' => [$templateId, 'i'],
            'message_title' => [$messageTitle, 's'],
            'message_body' => [$messageBody, 's'],
            'status' => [$status, 's'],
            'error_message' => [$errorMessage, 's'],
            'message_id' => [$messageId, 's'],
            'sent_at' => [$sentAt ?? date('Y-m-d H:i:s'), 's'],
            'created_at' => [date('Y-m-d H:i:s'), 's'],
            'updated_at' => [date('Y-m-d H:i:s'), 's'],
            'request_json' => [$requestJson, 's'],
            'response_json' => [$responseJson, 's'],
            'http_status_code' => [$httpStatusCode, 'i'],
            'failure_reason' => [$failureReason, 's'],
        ];

        if (in_array('delivery_status', $columns, true)) {
            $payload['delivery_status'] = [$deliveryStatus, 's'];
        }

        if (in_array('delivered_at', $columns, true)) {
            $payload['delivered_at'] = [$deliveryStatus === 'sent' ? date('Y-m-d H:i:s') : null, 's'];
        }

        if (in_array('read_at', $columns, true)) {
            $payload['read_at'] = [null, 's'];
        }

        $insertColumns = [];
        $placeholders = [];
        $types = '';
        $values = [];

        foreach ($payload as $column => [$value, $type]) {
            if ($value === null && !in_array($column, ['template_id', 'error_message', 'message_id', 'sent_at', 'delivery_status', 'delivered_at', 'read_at', 'request_json', 'response_json', 'http_status_code', 'failure_reason'], true)) {
                continue;
            }

            if ($value === null && !in_array($column, $columns, true)) {
                continue;
            }

            $insertColumns[] = $column;
            $placeholders[] = '?';
            $types .= $type;
            $values[] = $value;
        }

        $sql = 'INSERT INTO gd_sent_messages (`' . implode('`, `', $insertColumns) . '`) VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $db->prepare($sql);
        $bind = [$types];
        foreach ($values as $i => $value) {
            $bind[] = &$values[$i];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);
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

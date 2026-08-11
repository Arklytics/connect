<?php

declare(strict_types=1);

final class ApiSupport
{
    public const GRAPH_VERSION = 'v23.0';
    private static ?mysqli $apiCallLogDb = null;
    private static ?int $apiCallLogId = null;

    public static function tableColumns(mysqli $db, string $table): array
    {
        $stmt = $db->prepare('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`');
        $stmt->execute();
        $result = $stmt->get_result();
        $columns = [];
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'] ?? '';
        }

        return $columns;
    }

    public static function hasColumn(mysqli $db, string $table, string $column): bool
    {
        return in_array($column, self::tableColumns($db, $table), true);
    }

    public static function ensureSentMessageDeliveryColumns(mysqli $db): void
    {
        $columns = self::tableColumns($db, 'gd_sent_messages');

        if (!in_array('delivery_status', $columns, true)) {
            $db->query('ALTER TABLE gd_sent_messages ADD COLUMN delivery_status VARCHAR(30) NOT NULL DEFAULT "pending" AFTER status');
            $columns[] = 'delivery_status';
        }

        if (!in_array('delivered_at', $columns, true)) {
            $after = in_array('sent_at', $columns, true) ? 'sent_at' : 'message_id';
            $db->query('ALTER TABLE gd_sent_messages ADD COLUMN delivered_at TIMESTAMP NULL AFTER `' . $after . '`');
            $columns[] = 'delivered_at';
        }

        if (!in_array('read_at', $columns, true)) {
            $db->query('ALTER TABLE gd_sent_messages ADD COLUMN read_at TIMESTAMP NULL AFTER delivered_at');
        }
    }

    public static function ensureWebhookLogTable(mysqli $db): void
    {
        $db->query(
            "CREATE TABLE IF NOT EXISTS gd_webhook_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                biz_id BIGINT UNSIGNED NULL,
                contact_id BIGINT UNSIGNED NULL,
                phone_number_id VARCHAR(120) NULL,
                whatsapp_business_account_id VARCHAR(120) NULL,
                event_type VARCHAR(40) NOT NULL DEFAULT 'message',
                direction VARCHAR(40) NOT NULL DEFAULT 'inbound',
                from_phone VARCHAR(30) NULL,
                message_id VARCHAR(191) NULL,
                delivery_status VARCHAR(30) NULL,
                message_text TEXT NULL,
                payload_json TEXT NULL,
                notes TEXT NULL,
                webhook_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX gd_webhook_logs_biz_id_index (biz_id),
                INDEX gd_webhook_logs_contact_id_index (contact_id),
                INDEX gd_webhook_logs_phone_number_id_index (phone_number_id),
                INDEX gd_webhook_logs_waba_index (whatsapp_business_account_id),
                INDEX gd_webhook_logs_event_type_index (event_type),
                INDEX gd_webhook_logs_direction_index (direction),
                INDEX gd_webhook_logs_from_phone_index (from_phone),
                INDEX gd_webhook_logs_message_id_index (message_id),
                INDEX gd_webhook_logs_delivery_status_index (delivery_status),
                INDEX gd_webhook_logs_webhook_at_index (webhook_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public static function ensureApiCallLogTable(mysqli $db): void
    {
        $db->query(
            "CREATE TABLE IF NOT EXISTS gd_api_call_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                biz_id BIGINT UNSIGNED NULL,
                endpoint VARCHAR(255) NOT NULL,
                method VARCHAR(12) NOT NULL,
                status_code SMALLINT UNSIGNED NULL,
                ok TINYINT(1) NOT NULL DEFAULT 0,
                api_key_prefix VARCHAR(20) NULL,
                ip_address VARCHAR(60) NULL,
                user_agent VARCHAR(500) NULL,
                request_json MEDIUMTEXT NULL,
                response_json MEDIUMTEXT NULL,
                error_message TEXT NULL,
                started_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                finished_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX gd_api_call_logs_biz_id_index (biz_id),
                INDEX gd_api_call_logs_endpoint_index (endpoint),
                INDEX gd_api_call_logs_status_code_index (status_code),
                INDEX gd_api_call_logs_started_at_index (started_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public static function ensureTemplateMediaTable(mysqli $db): void
    {
        $db->query(
            "CREATE TABLE IF NOT EXISTS gd_template_media (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                biz_id BIGINT UNSIGNED NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                mime_type VARCHAR(120) NOT NULL,
                file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
                s3_key VARCHAR(500) NULL,
                s3_url TEXT NOT NULL,
                media_handle TEXT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX gd_template_media_biz_id_index (biz_id),
                INDEX gd_template_media_created_at_index (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $columnResult = $db->query("SHOW COLUMNS FROM gd_template_media LIKE 'file_hash'");
        if ($columnResult instanceof mysqli_result && $columnResult->num_rows === 0) {
            $db->query("ALTER TABLE gd_template_media ADD COLUMN file_hash CHAR(64) NULL AFTER file_size, ADD INDEX gd_template_media_file_hash_index (file_hash)");
        }
    }

    public static function storeTemplateMedia(
        mysqli $db,
        int $bizId,
        string $originalName,
        string $mimeType,
        int $fileSize,
        string $s3Url,
        string $mediaHandle = '',
        string $s3Key = '',
        string $fileHash = ''
    ): void {
        self::ensureTemplateMediaTable($db);

        $stmt = $db->prepare(
            'INSERT INTO gd_template_media (biz_id, original_name, mime_type, file_size, file_hash, s3_key, s3_url, media_handle, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $fileHash = strtolower(trim($fileHash));
        $stmt->bind_param('ississss', $bizId, $originalName, $mimeType, $fileSize, $fileHash, $s3Key, $s3Url, $mediaHandle);
        $stmt->execute();
    }

    public static function findTemplateMediaByFile(
        mysqli $db,
        int $bizId,
        string $originalName,
        string $mimeType,
        int $fileSize,
        string $fileHash = ''
    ): ?array {
        self::ensureTemplateMediaTable($db);

        $fileHash = strtolower(trim($fileHash));
        if ($fileHash !== '') {
            $stmt = $db->prepare(
                'SELECT * FROM gd_template_media
                 WHERE biz_id = ? AND file_hash = ? AND media_handle IS NOT NULL AND media_handle <> ""
                 ORDER BY id DESC LIMIT 1'
            );
            $stmt->bind_param('is', $bizId, $fileHash);
        } else {
            $stmt = $db->prepare(
                'SELECT * FROM gd_template_media
                 WHERE biz_id = ? AND original_name = ? AND mime_type = ? AND file_size = ? AND media_handle IS NOT NULL AND media_handle <> ""
                 ORDER BY id DESC LIMIT 1'
            );
            $stmt->bind_param('issi', $bizId, $originalName, $mimeType, $fileSize);
        }

        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        return is_array($row) ? $row : null;
    }

    public static function businessTemplateMedia(mysqli $db, int $bizId, int $limit = 100): array
    {
        self::ensureTemplateMediaTable($db);

        $limit = max(1, min(500, $limit));
        $stmt = $db->prepare('SELECT * FROM gd_template_media WHERE biz_id = ? ORDER BY id DESC LIMIT ?');
        $stmt->bind_param('ii', $bizId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        return $rows;
    }

    public static function mediaKind(string $mimeType, string $url = ''): string
    {
        $mimeType = strtolower($mimeType);
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }
        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }
        if ($mimeType === 'application/pdf' || preg_match('/\.pdf($|\?)/i', $url)) {
            return 'document';
        }

        return 'file';
    }

    public static function generateBusinessApiKey(): array
    {
        $key = 'wpi_live_' . bin2hex(random_bytes(24));

        return [
            'key' => $key,
            'hash' => hash('sha256', $key),
            'prefix' => substr($key, 0, 12),
            'last4' => substr($key, -4),
        ];
    }

    public static function encodeJson(mixed $value): ?string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return $encoded === false ? null : $encoded;
    }

    public static function s3UploadFile(string $filePath, string $fileName, string $contentType, string $prefix = 'template-media'): array
    {
        if (!is_file($filePath)) {
            return [
                'ok' => false,
                'url' => '',
                'key' => '',
                'error' => 'File not found.',
            ];
        }

        $config = self::s3Config();
        foreach (['access_key', 'secret_key', 'region', 'bucket'] as $required) {
            if (($config[$required] ?? '') === '') {
                return [
                    'ok' => false,
                    'url' => '',
                    'key' => '',
                    'error' => 'AWS S3 is not configured. Add AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_REGION, and AWS_BUCKET.',
                ];
            }
        }

        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $safePrefix = trim((string) $prefix, "/ \t\n\r\0\x0B");
        $key = ($safePrefix !== '' ? $safePrefix . '/' : '')
            . date('Y/m/')
            . bin2hex(random_bytes(16))
            . ($extension !== '' ? '.' . preg_replace('/[^a-z0-9]+/', '', $extension) : '');

        $body = file_get_contents($filePath);
        if ($body === false) {
            return [
                'ok' => false,
                'url' => '',
                'key' => '',
                'error' => 'Could not read uploaded file.',
            ];
        }

        $region = (string) $config['region'];
        $bucket = (string) $config['bucket'];
        $endpoint = rtrim((string) $config['endpoint'], '/');
        $pathStyle = (bool) $config['path_style'];
        $host = $endpoint !== ''
            ? (string) parse_url($endpoint, PHP_URL_HOST)
            : $bucket . '.s3.' . $region . '.amazonaws.com';

        if ($endpoint !== '' && $pathStyle) {
            $canonicalUri = '/' . self::s3EncodePath($bucket . '/' . $key);
            $url = $endpoint . $canonicalUri;
        } elseif ($endpoint !== '') {
            $scheme = (string) (parse_url($endpoint, PHP_URL_SCHEME) ?: 'https');
            $baseHost = (string) parse_url($endpoint, PHP_URL_HOST);
            $port = parse_url($endpoint, PHP_URL_PORT);
            $host = $bucket . '.' . $baseHost . ($port ? ':' . $port : '');
            $canonicalUri = '/' . self::s3EncodePath($key);
            $url = $scheme . '://' . $host . $canonicalUri;
        } else {
            $canonicalUri = '/' . self::s3EncodePath($key);
            $url = 'https://' . $host . $canonicalUri;
        }

        $payloadHash = hash('sha256', $body);
        $amzDate = gmdate('Ymd\THis\Z');
        $shortDate = gmdate('Ymd');
        $headers = [
            'content-type' => $contentType !== '' ? $contentType : 'application/octet-stream',
            'host' => $host,
            'x-amz-content-sha256' => $payloadHash,
            'x-amz-date' => $amzDate,
        ];

        $acl = trim((string) $config['acl']);
        if ($acl !== '') {
            $headers['x-amz-acl'] = $acl;
        }

        ksort($headers);
        $canonicalHeaders = '';
        $signedHeaderNames = [];
        foreach ($headers as $name => $value) {
            $canonicalHeaders .= strtolower($name) . ':' . trim((string) $value) . "\n";
            $signedHeaderNames[] = strtolower($name);
        }

        $signedHeaders = implode(';', $signedHeaderNames);
        $credentialScope = $shortDate . '/' . $region . '/s3/aws4_request';
        $canonicalRequest = "PUT\n"
            . $canonicalUri . "\n\n"
            . $canonicalHeaders . "\n"
            . $signedHeaders . "\n"
            . $payloadHash;
        $stringToSign = "AWS4-HMAC-SHA256\n"
            . $amzDate . "\n"
            . $credentialScope . "\n"
            . hash('sha256', $canonicalRequest);
        $signature = hash_hmac('sha256', $stringToSign, self::awsSigningKey((string) $config['secret_key'], $shortDate, $region, 's3'));
        $headers['authorization'] = 'AWS4-HMAC-SHA256 Credential='
            . $config['access_key'] . '/'
            . $credentialScope
            . ', SignedHeaders=' . $signedHeaders
            . ', Signature=' . $signature;

        $curlHeaders = [];
        foreach ($headers as $name => $value) {
            $curlHeaders[] = $name . ': ' . $value;
        }

        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
        ]);

        $response = curl_exec($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($curlError !== '' || $httpCode < 200 || $httpCode >= 300) {
            return [
                'ok' => false,
                'url' => '',
                'key' => $key,
                'error' => $curlError !== '' ? 'S3 upload failed: ' . $curlError : 'S3 upload failed with HTTP ' . $httpCode . '. ' . trim((string) $response),
            ];
        }

        return [
            'ok' => true,
            'url' => self::s3PublicUrl($key, $config),
            'key' => $key,
            'error' => null,
        ];
    }

    private static function s3Config(): array
    {
        $settings = [];
        $db = Database::connectOrNull();
        if ($db) {
            foreach (['AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY', 'AWS_REGION', 'AWS_DEFAULT_REGION', 'AWS_BUCKET', 'AWS_URL', 'AWS_ENDPOINT', 'AWS_S3_PATH_STYLE', 'AWS_S3_ACL'] as $key) {
                $settings[$key] = trim((string) AppSettings::getGlobal($db, $key, ''));
            }
        }

        $get = static function (string $key, string $default = '') use ($settings): string {
            $settingValue = trim($settings[$key] ?? '');
            if ($settingValue !== '') {
                return $settingValue;
            }

            return trim((string) Config::get($key, $default));
        };

        return [
            'access_key' => $get('AWS_ACCESS_KEY_ID'),
            'secret_key' => $get('AWS_SECRET_ACCESS_KEY'),
            'region' => $get('AWS_REGION', $get('AWS_DEFAULT_REGION', 'ap-south-1')),
            'bucket' => $get('AWS_BUCKET'),
            'url' => $get('AWS_URL'),
            'endpoint' => $get('AWS_ENDPOINT'),
            'path_style' => in_array(strtolower($get('AWS_S3_PATH_STYLE')), ['1', 'true', 'yes'], true),
            'acl' => $get('AWS_S3_ACL'),
        ];
    }

    private static function s3PublicUrl(string $key, array $config): string
    {
        if (trim((string) ($config['url'] ?? '')) !== '') {
            return rtrim((string) $config['url'], '/') . '/' . self::s3EncodePath($key);
        }

        $region = (string) ($config['region'] ?? 'ap-south-1');
        $bucket = (string) ($config['bucket'] ?? '');
        $endpoint = rtrim((string) ($config['endpoint'] ?? ''), '/');
        if ($endpoint !== '' && !empty($config['path_style'])) {
            return $endpoint . '/' . rawurlencode($bucket) . '/' . self::s3EncodePath($key);
        }

        if ($endpoint !== '') {
            $scheme = (string) (parse_url($endpoint, PHP_URL_SCHEME) ?: 'https');
            $host = (string) parse_url($endpoint, PHP_URL_HOST);
            $port = parse_url($endpoint, PHP_URL_PORT);
            return $scheme . '://' . $bucket . '.' . $host . ($port ? ':' . $port : '') . '/' . self::s3EncodePath($key);
        }

        return 'https://' . $bucket . '.s3.' . $region . '.amazonaws.com/' . self::s3EncodePath($key);
    }

    private static function s3EncodePath(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', ltrim($path, '/'))));
    }

    private static function awsSigningKey(string $secretKey, string $date, string $region, string $service): string
    {
        $dateKey = hash_hmac('sha256', $date, 'AWS4' . $secretKey, true);
        $regionKey = hash_hmac('sha256', $region, $dateKey, true);
        $serviceKey = hash_hmac('sha256', $service, $regionKey, true);
        return hash_hmac('sha256', 'aws4_request', $serviceKey, true);
    }

    public static function jsonResponse(array $payload, int $status = 200): void
    {
        self::finishApiCallLog($payload, $status);
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

    public static function requireBusinessApiKey(mysqli $db, int $requestedBizId = 0): int
    {
        if (!self::hasColumn($db, 'gd_orders', 'api_key_hash')) {
            self::jsonResponse([
                'ok' => false,
                'error' => 'Business API keys are not installed. Run migrations first.',
            ], 500);
        }

        $provided = self::extractToken();
        if ($provided === '') {
            self::jsonResponse([
                'ok' => false,
                'error' => 'Unauthorized. Send Authorization: Bearer YOUR_BUSINESS_API_KEY or X-API-KEY.',
            ], 401);
        }

        $providedHash = hash('sha256', $provided);
        $enabledSql = self::hasColumn($db, 'gd_orders', 'api_enabled') ? ' AND COALESCE(api_enabled, 0) = 1' : '';
        $stmt = $db->prepare('SELECT id FROM gd_orders WHERE api_key_hash = ?' . $enabledSql . ' LIMIT 1');
        $stmt->bind_param('s', $providedHash);
        $stmt->execute();
        $business = $stmt->get_result()->fetch_assoc();

        if (!$business) {
            self::jsonResponse([
                'ok' => false,
                'error' => 'Unauthorized.',
            ], 401);
        }

        $bizId = (int) $business['id'];
        if ($requestedBizId > 0 && $requestedBizId !== $bizId) {
            self::jsonResponse([
                'ok' => false,
                'error' => 'The supplied API key does not belong to this biz_id.',
            ], 403);
        }

        self::startApiCallLog($db, $bizId, $provided);

        return $bizId;
    }

    private static function startApiCallLog(mysqli $db, int $bizId, string $apiKey): void
    {
        if (self::$apiCallLogId !== null) {
            return;
        }

        try {
            self::ensureApiCallLogTable($db);
            $endpoint = parse_url((string) ($_SERVER['REQUEST_URI'] ?? $_SERVER['SCRIPT_NAME'] ?? ''), PHP_URL_PATH) ?: '';
            $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
            $ipAddress = trim((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? ''));
            if (str_contains($ipAddress, ',')) {
                $ipAddress = trim(explode(',', $ipAddress)[0]);
            }
            $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
            $requestJson = trim((string) file_get_contents('php://input'));
            if ($requestJson === '' && !empty($_POST)) {
                $requestJson = self::encodeJson($_POST) ?? '';
            }
            $prefix = substr($apiKey, 0, 12);

            $stmt = $db->prepare(
                'INSERT INTO gd_api_call_logs (biz_id, endpoint, method, api_key_prefix, ip_address, user_agent, request_json, started_at, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())'
            );
            $stmt->bind_param('issssss', $bizId, $endpoint, $method, $prefix, $ipAddress, $userAgent, $requestJson);
            $stmt->execute();

            self::$apiCallLogDb = $db;
            self::$apiCallLogId = (int) $stmt->insert_id;
        } catch (Throwable $exception) {
            error_log('API call log start failed: ' . $exception->getMessage());
        }
    }

    private static function finishApiCallLog(array $payload, int $status): void
    {
        if (self::$apiCallLogDb === null || self::$apiCallLogId === null) {
            return;
        }

        try {
            $responseJson = self::encodeJson($payload) ?? '';
            $ok = !empty($payload['ok']) && $status >= 200 && $status < 300 ? 1 : 0;
            $error = trim((string) ($payload['error'] ?? $payload['message'] ?? ''));
            $id = self::$apiCallLogId;
            $stmt = self::$apiCallLogDb->prepare(
                'UPDATE gd_api_call_logs
                 SET status_code = ?, ok = ?, response_json = ?, error_message = ?, finished_at = NOW(), updated_at = NOW()
                 WHERE id = ?'
            );
            $stmt->bind_param('iissi', $status, $ok, $responseJson, $error, $id);
            $stmt->execute();
        } catch (Throwable $exception) {
            error_log('API call log finish failed: ' . $exception->getMessage());
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

        $digits = ltrim($phone, '+');
        while (str_starts_with($digits, '9191') && strlen($digits) > 12) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 10) {
            return '+91' . $digits;
        }

        return '+' . $digits;
    }

    public static function whatsappSendRequest(string $phoneNumberId, string $accessToken, array $payload): array
    {
        $requestJson = self::encodeJson($payload);
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://graph.facebook.com/' . self::GRAPH_VERSION . '/' . rawurlencode($phoneNumberId) . '/messages',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $requestJson !== null ? $requestJson : json_encode($payload),
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
            if (is_array($decoded) && isset($decoded['error']['code'])) {
                $failureReason .= ' (code ' . $decoded['error']['code'] . ')';
            }
            if (is_array($decoded) && isset($decoded['error']['error_subcode'])) {
                $failureReason .= ' (subcode ' . $decoded['error']['error_subcode'] . ')';
            }
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

    public static function normalizeTemplateName(string $name): string
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/[^a-z0-9_]+/', '_', $name) ?? '';
        $name = preg_replace('/_+/', '_', $name) ?? '';

        return trim($name, '_');
    }

    public static function normalizeTemplateText(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/{{\s*(\d+)\s*}}/', '{{$1}}', $text) ?? $text;
        $text = preg_replace('/\[\s*(\d+)\s*\]/', '{{$1}}', $text) ?? $text;
        $text = preg_replace('/(?<!\{)\{\s*(\d+)\s*\}(?!\})/', '{{$1}}', $text) ?? $text;

        return $text;
    }

    public static function createWhatsappTemplate(mysqli $db, int $bizId, array $input, ?array $mediaFile = null): array
    {
        $templateName = self::normalizeTemplateName((string) ($input['template_name'] ?? $input['name'] ?? ''));
        $category = strtoupper(trim((string) ($input['category'] ?? 'MARKETING')));
        $language = trim((string) ($input['language'] ?? 'en_US')) ?: 'en_US';
        $headerType = strtoupper(trim((string) ($input['header_type'] ?? 'NONE')));
        $headerText = self::normalizeTemplateText((string) ($input['header_text'] ?? ''));
        $headerSample = trim((string) ($input['header_sample'] ?? ''));
        $headerMediaHandle = trim((string) ($input['header_media_handle'] ?? ''));
        $mediaUrl = trim((string) ($input['header_media_url'] ?? $input['media_url'] ?? ''));
        $bodyText = self::normalizeTemplateText((string) ($input['body_text'] ?? $input['message_body'] ?? $input['body'] ?? ''));
        $footerText = trim((string) ($input['footer_text'] ?? $input['subtitle'] ?? ''));
        $bodySamples = is_array($input['body_samples'] ?? null) ? $input['body_samples'] : [];
        $buttonsInput = is_array($input['buttons'] ?? null) ? $input['buttons'] : [];

        if ($templateName === '') {
            return ['ok' => false, 'status' => 422, 'error' => 'template_name is required.'];
        }
        if (!in_array($category, ['MARKETING', 'UTILITY', 'AUTHENTICATION'], true)) {
            return ['ok' => false, 'status' => 422, 'error' => 'category must be MARKETING, UTILITY, or AUTHENTICATION.'];
        }
        if (!in_array($headerType, ['NONE', 'TEXT', 'IMAGE', 'VIDEO', 'DOCUMENT'], true)) {
            return ['ok' => false, 'status' => 422, 'error' => 'header_type must be NONE, TEXT, IMAGE, VIDEO, or DOCUMENT.'];
        }
        if ($bodyText === '') {
            return ['ok' => false, 'status' => 422, 'error' => 'body_text is required.'];
        }
        if ($category === 'AUTHENTICATION') {
            return ['ok' => false, 'status' => 422, 'error' => 'Authentication templates need Meta OTP formatting. Use Marketing or Utility for text, image, video, or document templates.'];
        }

        $credentialStmt = $db->prepare('SELECT whatsapp_id, auth_token FROM gd_orders WHERE id = ? LIMIT 1');
        $credentialStmt->bind_param('i', $bizId);
        $credentialStmt->execute();
        $business = $credentialStmt->get_result()->fetch_assoc() ?: [];

        $accessToken = trim((string) ($business['auth_token'] ?? ''));
        if ($accessToken === '') {
            $accessToken = trim((string) AppSettings::getGlobal($db, 'META_ACCESS_TOKEN', Config::get('META_ACCESS_TOKEN', '')));
        }
        $whatsappBusinessId = trim((string) ($business['whatsapp_id'] ?? ''));
        $appId = trim((string) AppSettings::getGlobal($db, 'META_APP_ID', Config::get('META_APP_ID', '')));

        if ($headerMediaHandle === '' && is_array($mediaFile) && (int) ($mediaFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if ((int) ($mediaFile['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                return ['ok' => false, 'status' => 422, 'error' => 'header_media_file upload failed.'];
            }

            $mimeType = (string) ($mediaFile['type'] ?? '');
            if (function_exists('mime_content_type')) {
                $detected = mime_content_type((string) ($mediaFile['tmp_name'] ?? ''));
                if (is_string($detected) && $detected !== '') {
                    $mimeType = $detected;
                }
            }

            $allowedTypes = ['image/jpeg', 'image/png', 'video/mp4', 'video/3gpp', 'application/pdf'];
            if (!in_array($mimeType, $allowedTypes, true)) {
                return ['ok' => false, 'status' => 422, 'error' => 'Unsupported file type. Use JPG, PNG, MP4, 3GP, or PDF.'];
            }
            if ($appId === '' || $accessToken === '') {
                return ['ok' => false, 'status' => 422, 'error' => 'Meta App ID or access token is missing. Add API credentials first.'];
            }

            $filePath = (string) ($mediaFile['tmp_name'] ?? '');
            $fileName = (string) ($mediaFile['name'] ?? 'template-media');
            $fileSize = (int) ($mediaFile['size'] ?? 0);
            $fileHash = is_file($filePath) ? (string) hash_file('sha256', $filePath) : '';
            $existingMedia = self::findTemplateMediaByFile($db, $bizId, $fileName, $mimeType, $fileSize, $fileHash);
            if (is_array($existingMedia)) {
                $headerMediaHandle = (string) ($existingMedia['media_handle'] ?? '');
                $mediaUrl = (string) ($existingMedia['s3_url'] ?? $mediaUrl);
            }
        }

        if ($headerMediaHandle === '' && is_array($mediaFile) && (int) ($mediaFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $filePath = (string) ($mediaFile['tmp_name'] ?? '');
            $fileName = (string) ($mediaFile['name'] ?? 'template-media');
            $fileSize = (int) ($mediaFile['size'] ?? 0);
            $mimeType = (string) ($mediaFile['type'] ?? '');
            if (function_exists('mime_content_type')) {
                $detected = mime_content_type($filePath);
                if (is_string($detected) && $detected !== '') {
                    $mimeType = $detected;
                }
            }
            $fileHash = is_file($filePath) ? (string) hash_file('sha256', $filePath) : '';

            $s3Upload = self::s3UploadFile($filePath, $fileName, $mimeType);
            if (!($s3Upload['ok'] ?? false)) {
                return ['ok' => false, 'status' => 422, 'error' => 'S3 upload failed: ' . (string) ($s3Upload['error'] ?? 'Unknown S3 upload error.')];
            }

            $uploadResult = self::metaUploadMediaHandle($appId, $accessToken, $filePath, $fileName, $mimeType, $fileSize);
            if (!($uploadResult['ok'] ?? false)) {
                return ['ok' => false, 'status' => 422, 'error' => 'Media handle generation failed: ' . (string) ($uploadResult['error'] ?? 'Unknown error.')];
            }

            $mediaUrl = (string) ($s3Upload['url'] ?? '');
            $headerMediaHandle = (string) ($uploadResult['handle'] ?? '');
            self::storeTemplateMedia($db, $bizId, $fileName, $mimeType, $fileSize, $mediaUrl, $headerMediaHandle, (string) ($s3Upload['key'] ?? ''), $fileHash);
        }

        $validationErrors = [];
        $headerVariableNumbers = self::templatePlaceholderNumbers($headerText);
        $bodyVariableNumbers = self::templatePlaceholderNumbers($bodyText);
        $bodySequenceError = self::sequentialTemplateVariableError($bodyVariableNumbers, 'Body');
        if ($bodySequenceError !== '') {
            $validationErrors[] = $bodySequenceError;
        }

        $components = [];
        if ($headerType === 'TEXT' && $headerText !== '') {
            $header = ['type' => 'HEADER', 'format' => 'TEXT', 'text' => $headerText];
            if (count($headerVariableNumbers) > 1) {
                $validationErrors[] = 'Text header can contain only one variable.';
            } elseif ($headerVariableNumbers !== []) {
                if ($headerSample === '') {
                    return ['ok' => false, 'status' => 422, 'error' => 'Header variable example is required.'];
                }
                $header['example'] = ['header_text' => [$headerSample]];
            }
            $components[] = $header;
        } elseif (in_array($headerType, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)) {
            if ($headerMediaHandle === '') {
                return ['ok' => false, 'status' => 422, 'error' => ucfirst(strtolower($headerType)) . ' header requires a WhatsApp media handle for template review.'];
            }
            $components[] = [
                'type' => 'HEADER',
                'format' => $headerType,
                'example' => ['header_handle' => [$headerMediaHandle]],
            ];
        }

        $body = ['type' => 'BODY', 'text' => $bodyText];
        if (!empty($bodyVariableNumbers)) {
            $sampleRow = [];
            foreach ($bodyVariableNumbers as $number) {
                $sampleRow[] = self::templateExampleValue($bodySamples, $number);
            }
            if (in_array('', $sampleRow, true)) {
                return ['ok' => false, 'status' => 422, 'error' => 'Every body variable needs an example value.'];
            }
            $body['example'] = ['body_text' => [$sampleRow]];
        }
        $components[] = $body;

        if ($footerText !== '') {
            $components[] = ['type' => 'FOOTER', 'text' => $footerText];
        }

        $buttons = [];
        foreach ($buttonsInput as $button) {
            if (!is_array($button)) {
                continue;
            }

            $buttonType = strtoupper(trim((string) ($button['type'] ?? '')));
            $buttonText = trim((string) ($button['text'] ?? ''));
            $buttonValue = self::normalizeTemplateText((string) ($button['value'] ?? $button['url'] ?? $button['phone_number'] ?? ''));
            if ($buttonType === '' || $buttonText === '') {
                continue;
            }

            if ($buttonType === 'URL' && $buttonValue !== '') {
                $urlButton = ['type' => 'URL', 'text' => $buttonText, 'url' => $buttonValue];
                $buttonNumbers = self::templatePlaceholderNumbers($buttonValue);
                if (!empty($buttonNumbers)) {
                    if (count($buttonNumbers) > 1 || $buttonNumbers !== [1]) {
                        $validationErrors[] = 'Dynamic URL buttons can use only {{1}}.';
                    } else {
                        $urlExample = preg_replace('/{{\s*1\s*}}/', self::templateExampleValue($bodySamples, 1) ?: 'sample', $buttonValue) ?? $buttonValue;
                        $urlButton['example'] = [$urlExample];
                        if (!self::validTemplateUrlExample($urlExample)) {
                            $validationErrors[] = 'URL button example must be a valid http or https URL.';
                        }
                    }
                } elseif (!self::validTemplateUrlExample($buttonValue)) {
                    $validationErrors[] = 'URL button must be a valid http or https URL.';
                }
                $buttons[] = $urlButton;
            } elseif ($buttonType === 'PHONE_NUMBER' && $buttonValue !== '') {
                $buttons[] = ['type' => 'PHONE_NUMBER', 'text' => $buttonText, 'phone_number' => $buttonValue];
            } elseif ($buttonType === 'QUICK_REPLY') {
                $buttons[] = ['type' => 'QUICK_REPLY', 'text' => $buttonText];
            }
        }

        if (!empty($buttons)) {
            $components[] = ['type' => 'BUTTONS', 'buttons' => $buttons];
        }
        if (!empty($validationErrors)) {
            return ['ok' => false, 'status' => 422, 'error' => implode(' ', $validationErrors)];
        }
        if ($whatsappBusinessId === '' || $accessToken === '') {
            return ['ok' => false, 'status' => 422, 'error' => 'WhatsApp Business ID or access token is missing. Add API credentials first.'];
        }

        $payload = [
            'name' => $templateName,
            'category' => $category,
            'language' => $language,
            'components' => $components,
        ];

        $ch = curl_init('https://graph.facebook.com/' . self::GRAPH_VERSION . '/' . rawurlencode($whatsappBusinessId) . '/message_templates');
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken, 'Content-Type: application/json'],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => self::encodeJson($payload) ?? json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $apiResponse = json_decode((string) $response, true);
        if ($curlError !== '') {
            return ['ok' => false, 'status' => 502, 'error' => 'WhatsApp API request failed: ' . $curlError];
        }
        if ($httpStatus < 200 || $httpStatus >= 300 || isset($apiResponse['error'])) {
            $apiError = is_array($apiResponse) ? (string) ($apiResponse['error']['message'] ?? 'Unexpected WhatsApp API error.') : 'Unexpected WhatsApp API error.';
            $apiDetails = is_array($apiResponse) ? ($apiResponse['error']['error_data']['details'] ?? $apiResponse['error']['error_user_msg'] ?? '') : '';
            if (is_string($apiDetails) && trim($apiDetails) !== '') {
                $apiError .= ' Details: ' . trim($apiDetails);
            }
            return ['ok' => false, 'status' => 422, 'error' => 'WhatsApp API rejected the template: ' . $apiError, 'meta_response' => $apiResponse];
        }

        $templateId = (string) ($apiResponse['id'] ?? '');
        $status = (string) ($apiResponse['status'] ?? 'PENDING');
        $apiCategory = (string) ($apiResponse['category'] ?? $category);
        $placeholdersJson = self::encodeJson([
            'header_type' => $headerType,
            'header_text' => $headerText,
            'header_sample' => $headerSample,
            'header_media_handle' => $headerMediaHandle,
            'header_media_url' => $mediaUrl,
            'body_samples' => $bodySamples,
            'body_placeholder_numbers' => $bodyVariableNumbers,
            'buttons' => $buttons,
            'payload' => $payload,
        ]);
        $buttonsJson = self::encodeJson($buttons);

        $insertStmt = $db->prepare(
            'INSERT INTO gd_whatsapp_templates (biz_id, template_id, template_name, message_title, message_body, placeholders, subtitle, media_url, status, category, buttons, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $messageTitle = $headerType === 'TEXT' && $headerText !== '' ? $headerText : ($headerType !== 'NONE' ? ucfirst(strtolower($headerType)) . ' Header' : 'Template');
        $insertStmt->bind_param('issssssssss', $bizId, $templateId, $templateName, $messageTitle, $bodyText, $placeholdersJson, $footerText, $mediaUrl, $status, $apiCategory, $buttonsJson);
        $insertStmt->execute();
        $localId = (int) $insertStmt->insert_id;

        return [
            'ok' => true,
            'status' => 201,
            'template' => [
                'id' => $localId,
                'biz_id' => $bizId,
                'template_id' => $templateId,
                'template_name' => $templateName,
                'message_title' => $messageTitle,
                'message_body' => $bodyText,
                'subtitle' => $footerText,
                'media_url' => $mediaUrl,
                'status' => $status,
                'category' => $apiCategory,
                'buttons' => $buttons,
            ],
            'meta_response' => $apiResponse,
            'payload' => $payload,
        ];
    }

    private static function sequentialTemplateVariableError(array $numbers, string $label): string
    {
        if (empty($numbers)) {
            return '';
        }

        return $numbers === range(1, count($numbers)) ? '' : $label . ' variables must start at {{1}} and continue without gaps.';
    }

    private static function validTemplateUrlExample(string $url): bool
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return filter_var($url, FILTER_VALIDATE_URL) !== false && in_array($scheme, ['http', 'https'], true);
    }

    private static function sampleValue(array $values, int $index): string
    {
        $isList = array_keys($values) === range(0, count($values) - 1);
        if ($isList) {
            $zeroBasedKey = $index - 1;
            if ($zeroBasedKey >= 0 && array_key_exists($zeroBasedKey, $values)) {
                return trim((string) $values[$zeroBasedKey]);
            }
        }

        if (array_key_exists($index, $values)) {
            return trim((string) $values[$index]);
        }

        $stringKey = (string) $index;
        if (array_key_exists($stringKey, $values)) {
            return trim((string) $values[$stringKey]);
        }

        if (!$isList) {
            $zeroBasedKey = $index - 1;
            if ($zeroBasedKey >= 0 && array_key_exists($zeroBasedKey, $values)) {
                return trim((string) $values[$zeroBasedKey]);
            }
        }

        return '';
    }

    public static function templateSendValuesFromInput(array $input): array
    {
        $values = [];
        foreach ([
            'parameters',
            'params',
            'template_values',
            'variables',
            'body',
            'body_values',
            'body_parameters',
            'header',
            'header_values',
            'header_parameters',
            'button',
            'button_values',
            'button_parameters',
        ] as $key) {
            $values[$key] = is_array($input[$key] ?? null) ? $input[$key] : [];
        }

        $values['header_media_url'] = trim((string) ($input['header_media_url'] ?? $input['media_url'] ?? ''));

        return $values;
    }

    public static function templateVariableRequirements(array $templateRow): array
    {
        $meta = [];
        if (!empty($templateRow['placeholders'])) {
            $decoded = json_decode((string) $templateRow['placeholders'], true);
            if (is_array($decoded)) {
                $meta = $decoded;
            }
        }

        $headerType = strtoupper(trim((string) ($meta['header_type'] ?? 'NONE')));
        $headerText = (string) ($meta['header_text'] ?? ($templateRow['message_title'] ?? ''));
        $requirements = [
            'header' => $headerType === 'TEXT' ? self::templatePlaceholderNumbers($headerText) : [],
            'body' => self::templatePlaceholderNumbers((string) ($templateRow['message_body'] ?? '')),
            'buttons' => [],
        ];

        $buttons = [];
        if (isset($meta['buttons']) && is_array($meta['buttons'])) {
            $buttons = $meta['buttons'];
        } elseif (isset($templateRow['buttons'])) {
            $decodedButtons = json_decode((string) $templateRow['buttons'], true);
            if (is_array($decodedButtons)) {
                $buttons = $decodedButtons;
            }
        }

        foreach (array_values($buttons) as $index => $button) {
            if (is_array($button) && strtoupper(trim((string) ($button['type'] ?? ''))) === 'URL') {
                $numbers = self::templatePlaceholderNumbers((string) ($button['url'] ?? $button['link'] ?? ''));
                if (!empty($numbers)) {
                    $requirements['buttons'][] = [
                        'index' => $index,
                        'text' => (string) ($button['text'] ?? 'Button ' . ($index + 1)),
                        'numbers' => $numbers,
                    ];
                }
            }
        }

        return $requirements;
    }

    private static function templateExampleValue(array $values, int $index): string
{
    return self::sampleValue($values, $index);
}

public static function buildTemplateSendComponents(array $templateRow, array $sendValues = []): array
{
    $meta = [];
    if (!empty($templateRow['placeholders'])) {
        $decoded = json_decode((string) $templateRow['placeholders'], true);
        if (is_array($decoded)) {
            $meta = $decoded;
        }
    }

    $components = [];
    $headerType = strtoupper(trim((string) ($meta['header_type'] ?? 'NONE')));
    $headerText = (string) ($meta['header_text'] ?? ($templateRow['message_title'] ?? ''));
    $headerNumbers = self::templatePlaceholderNumbers($headerText);

    if ($headerType === 'TEXT' && !empty($headerNumbers)) {
        $parameters = [];
        foreach ($headerNumbers as $number) {
            $value = self::templateSendValue($sendValues, 'header', $number);
            if ($value === '') {
                return [
                    'components' => [],
                    'error' => 'This template needs a header value for {{' . $number . '}}.',
                ];
            }

            $parameters[] = ['type' => 'text', 'text' => $value];
        }

        $components[] = ['type' => 'header', 'parameters' => $parameters];
    } elseif (in_array($headerType, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)) {
        $mediaUrl = trim((string) ($sendValues['header_media_url'] ?? $meta['header_media_url'] ?? $templateRow['media_url'] ?? ''));
        if ($mediaUrl === '') {
            return [
                'components' => [],
                'error' => 'This template needs a media URL for the header before it can be sent.',
            ];
        }

        $components[] = self::buildMediaHeaderComponent($headerType, $mediaUrl);
    }

    $bodyNumbers = self::templatePlaceholderNumbers((string) ($templateRow['message_body'] ?? ''));
    if (!empty($bodyNumbers)) {
        $parameters = [];
        foreach ($bodyNumbers as $number) {
            $value = self::templateSendValue($sendValues, 'body', $number);
            if ($value === '') {
                return [
                    'components' => [],
                    'error' => 'This template needs a body value for {{' . $number . '}}.',
                ];
            }

            $parameters[] = ['type' => 'text', 'text' => $value];
        }

        $components[] = ['type' => 'body', 'parameters' => $parameters];
    }

    $buttons = [];
    if (isset($meta['buttons']) && is_array($meta['buttons'])) {
        $buttons = $meta['buttons'];
    } elseif (isset($templateRow['buttons'])) {
        $decodedButtons = json_decode((string) $templateRow['buttons'], true);
        if (is_array($decodedButtons)) {
            $buttons = $decodedButtons;
        }
    }

    foreach (array_values($buttons) as $index => $button) {
        if (!is_array($button) || strtoupper(trim((string) ($button['type'] ?? ''))) !== 'URL') {
            continue;
        }

        $url = trim((string) ($button['url'] ?? $button['link'] ?? ''));
        $numbers = self::templatePlaceholderNumbers($url);
        if (empty($numbers)) {
            continue;
        }

        $buttonParameters = [];
        foreach ($numbers as $number) {
            $value = self::templateSendValue($sendValues, 'button', $number, (string) $index);
            if ($value === '') {
                $value = self::templateSendValue($sendValues, 'body', $number);
            }
            if ($value === '') {
                return [
                    'components' => [],
                    'error' => 'This template needs a dynamic URL button value for button ' . ($index + 1) . '.',
                ];
            }

            $buttonParameters[] = ['type' => 'text', 'text' => $value];
        }

        $components[] = [
            'type' => 'button',
            'sub_type' => 'url',
            'index' => (string) $index,
            'parameters' => $buttonParameters,
        ];
    }

    return [
        'components' => $components,
        'error' => null,
    ];
}

    private static function templateSendValue(array $values, string $section, int $number, ?string $buttonIndex = null): string
    {
        $sectionKeys = [
            $section,
            $section . '_values',
            $section . '_parameters',
        ];

        foreach ($sectionKeys as $key) {
            if (!isset($values[$key]) || !is_array($values[$key])) {
                continue;
            }

            if ($buttonIndex !== null && isset($values[$key][$buttonIndex]) && is_array($values[$key][$buttonIndex])) {
                $value = self::sampleValue($values[$key][$buttonIndex], $number);
                if ($value !== '') {
                    return $value;
                }
            }

            $value = self::sampleValue($values[$key], $number);
            if ($value !== '') {
                return $value;
            }
        }

        if ($section === 'body') {
            foreach (['parameters', 'params', 'template_values', 'variables'] as $key) {
                if (isset($values[$key]) && is_array($values[$key])) {
                    $value = self::sampleValue($values[$key], $number);
                    if ($value !== '') {
                        return $value;
                    }
                }
            }
        }

        return '';
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

    $graphVersion = self::GRAPH_VERSION;

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

            "Content-Type: " . ($fileType !== '' ? $fileType : 'application/octet-stream'),

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
        $columns = self::tableColumns($db, 'gd_sent_messages');

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
            $payload['delivered_at'] = [$deliveryStatus === 'delivered' ? date('Y-m-d H:i:s') : null, 's'];
        }

        if (in_array('read_at', $columns, true)) {
            $payload['read_at'] = [null, 's'];
        }

        $insertColumns = [];
        $placeholders = [];
        $types = '';
        $values = [];

        foreach ($payload as $column => [$value, $type]) {
            if (!in_array($column, $columns, true)) {
                continue;
            }

            if ($value === null && !in_array($column, ['template_id', 'error_message', 'message_id', 'sent_at', 'delivery_status', 'delivered_at', 'read_at', 'request_json', 'response_json', 'http_status_code', 'failure_reason'], true)) {
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
        if ($count <= 0 || !self::hasColumn($db, 'gd_orders', 'messages_used')) {
            return;
        }

        $stmt = $db->prepare('UPDATE gd_orders SET messages_used = COALESCE(messages_used, 0) + ? WHERE id = ?');
        $stmt->bind_param('ii', $count, $bizId);
        $stmt->execute();
    }

    public static function ensureApiWebhookColumns(mysqli $db): void
    {
        $columns = self::tableColumns($db, 'gd_orders');

        if (!in_array('api_webhook_url', $columns, true)) {
            $db->query('ALTER TABLE gd_orders ADD COLUMN api_webhook_url VARCHAR(500) NULL');
            $columns[] = 'api_webhook_url';
        }

        if (!in_array('api_webhook_secret', $columns, true)) {
            $db->query('ALTER TABLE gd_orders ADD COLUMN api_webhook_secret VARCHAR(120) NULL');
            $columns[] = 'api_webhook_secret';
        }

        if (!in_array('api_webhook_enabled', $columns, true)) {
            $db->query('ALTER TABLE gd_orders ADD COLUMN api_webhook_enabled TINYINT(1) NOT NULL DEFAULT 0');
        }
    }

    public static function ensureGroupHierarchyColumns(mysqli $db): void
    {
        $columns = self::tableColumns($db, 'gd_groups');

        if (!in_array('parent_id', $columns, true)) {
            $db->query('ALTER TABLE gd_groups ADD COLUMN parent_id BIGINT UNSIGNED NULL AFTER biz_id');
        }
    }

    public static function groupTargetIds(mysqli $db, int $bizId, int $groupId, bool $includeChildren = true): array
    {
        if ($groupId <= 0) {
            return [];
        }

        try {
            self::ensureGroupHierarchyColumns($db);
        } catch (Throwable $exception) {
            error_log('Group hierarchy unavailable: ' . $exception->getMessage());
            return [$groupId];
        }

        $stmt = $db->prepare('SELECT id FROM gd_groups WHERE id = ? AND biz_id = ? LIMIT 1');
        $stmt->bind_param('ii', $groupId, $bizId);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            return [];
        }

        $ids = [$groupId];
        if (!$includeChildren) {
            return $ids;
        }

        $pending = [$groupId];
        while (!empty($pending)) {
            $parentId = array_shift($pending);
            $childStmt = $db->prepare('SELECT id FROM gd_groups WHERE biz_id = ? AND parent_id = ?');
            $childStmt->bind_param('ii', $bizId, $parentId);
            $childStmt->execute();
            $result = $childStmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $childId = (int) ($row['id'] ?? 0);
                if ($childId > 0 && !in_array($childId, $ids, true)) {
                    $ids[] = $childId;
                    $pending[] = $childId;
                }
            }
        }

        return $ids;
    }

    public static function isSubgroup(mysqli $db, int $bizId, int $groupId): bool
    {
        if ($groupId <= 0) {
            return false;
        }

        try {
            self::ensureGroupHierarchyColumns($db);
        } catch (Throwable $exception) {
            error_log('Group hierarchy unavailable: ' . $exception->getMessage());
            return false;
        }

        $stmt = $db->prepare('SELECT id FROM gd_groups WHERE id = ? AND biz_id = ? AND parent_id IS NOT NULL LIMIT 1');
        $stmt->bind_param('ii', $groupId, $bizId);
        $stmt->execute();

        return (bool) $stmt->get_result()->fetch_assoc();
    }

    public static function apiWebhookConfig(mysqli $db, int $bizId): array
    {
        self::ensureApiWebhookColumns($db);

        $stmt = $db->prepare('SELECT api_webhook_url, api_webhook_secret, api_webhook_enabled FROM gd_orders WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $bizId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: [];

        return [
            'url' => trim((string) ($row['api_webhook_url'] ?? '')),
            'secret' => trim((string) ($row['api_webhook_secret'] ?? '')),
            'enabled' => (bool) ((int) ($row['api_webhook_enabled'] ?? 0)),
        ];
    }

    public static function dispatchApiWebhook(mysqli $db, int $bizId, string $event, array $data, array $rawPayload = []): array
    {
        try {
            $config = self::apiWebhookConfig($db, $bizId);
        } catch (Throwable $exception) {
            error_log('API webhook config unavailable for business ' . $bizId . ': ' . $exception->getMessage());
            return ['ok' => false, 'skipped' => true, 'error' => 'API webhook config is unavailable.'];
        }

        if (!$config['enabled'] || $config['url'] === '') {
            return ['ok' => false, 'skipped' => true, 'error' => 'API webhook is not configured.'];
        }

        $deliveryId = 'whd_' . bin2hex(random_bytes(16));
        $createdAt = gmdate('c');
        $body = self::encodeJson([
            'event' => $event,
            'delivery_id' => $deliveryId,
            'api_version' => '2026-07-01',
            'created_at' => $createdAt,
            'biz_id' => $bizId,
            'data' => $data,
            'raw' => $rawPayload,
        ]);

        if ($body === null) {
            return ['ok' => false, 'skipped' => false, 'error' => 'Could not encode webhook payload.'];
        }

        $timestamp = (string) time();
        $headers = [
            'Content-Type: application/json',
            'User-Agent: Arklytics-Connect-Webhooks/1.0',
            'X-Arklytics-Event: ' . $event,
            'X-Arklytics-Delivery: ' . $deliveryId,
            'X-Arklytics-Timestamp: ' . $timestamp,
        ];

        if ($config['secret'] !== '') {
            $signature = hash_hmac('sha256', $timestamp . '.' . $body, $config['secret']);
            $headers[] = 'X-Arklytics-Signature: sha256=' . $signature;
        }

        $curl = curl_init($config['url']);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        $ok = $curlError === '' && $httpCode >= 200 && $httpCode < 300;
        if (!$ok) {
            error_log('API webhook delivery failed for business ' . $bizId . ': ' . ($curlError !== '' ? $curlError : 'HTTP ' . $httpCode . ' ' . trim((string) $response)));
        }

        return [
            'ok' => $ok,
            'skipped' => false,
            'delivery_id' => $deliveryId,
            'http_code' => $httpCode,
            'error' => $ok ? null : ($curlError !== '' ? $curlError : 'HTTP ' . $httpCode),
        ];
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

<?php

declare(strict_types=1);

final class PaymentSupport
{
    public const DEFAULT_PACKAGES = [
        'starter' => [
            'key' => 'starter',
            'label' => 'Starter',
            'marketing_limit' => 500,
            'utility_limit' => 500,
            'days' => 30,
            'price' => 999,
            'marketing_price' => 599,
            'utility_price' => 400,
        ],
        'growth' => [
            'key' => 'growth',
            'label' => 'Growth',
            'marketing_limit' => 2500,
            'utility_limit' => 2500,
            'days' => 30,
            'price' => 2499,
            'marketing_price' => 1499,
            'utility_price' => 1000,
        ],
        'pro' => [
            'key' => 'pro',
            'label' => 'Pro',
            'marketing_limit' => 7500,
            'utility_limit' => 7500,
            'days' => 30,
            'price' => 6999,
            'marketing_price' => 3999,
            'utility_price' => 3000,
        ],
    ];

    public static function packages(?mysqli $db = null, bool $activeOnly = true): array
    {
        if ($db instanceof mysqli) {
            try {
                self::ensureTables($db);
                $sql = 'SELECT * FROM gd_packages';
                if ($activeOnly) {
                    $sql .= ' WHERE is_active = 1';
                }
                $sql .= ' ORDER BY sort_order ASC, id ASC';
                $result = $db->query($sql);
                $packages = [];
                while ($row = $result?->fetch_assoc()) {
                    $key = strtolower(trim((string) ($row['package_key'] ?? '')));
                    if ($key === '') {
                        continue;
                    }
                    $packages[$key] = [
                        'key' => $key,
                        'label' => (string) ($row['package_name'] ?? $key),
                        'marketing_limit' => (int) ($row['marketing_message_limit'] ?? 0),
                        'utility_limit' => (int) ($row['utility_message_limit'] ?? 0),
                        'days' => (int) ($row['duration_days'] ?? 30),
                        'price' => (float) ($row['total_price'] ?? 0),
                        'marketing_price' => (float) ($row['marketing_price'] ?? 0),
                        'utility_price' => (float) ($row['utility_price'] ?? 0),
                        'active' => (bool) ($row['is_active'] ?? true),
                    ];
                }

                return $packages;
            } catch (Throwable $exception) {
                error_log('Dynamic packages unavailable: ' . $exception->getMessage());
            }
        }

        return self::DEFAULT_PACKAGES;
    }

    public static function package(string $key, ?mysqli $db = null): array
    {
        $key = strtolower(trim($key));
        $packages = self::packages($db);

        return $packages[$key] ?? reset($packages);
    }

    public static function packageTotalMessages(array $package): int
    {
        return (int) ($package['marketing_limit'] ?? 0) + (int) ($package['utility_limit'] ?? 0);
    }

    public static function ensureTables(mysqli $db): void
    {
        $db->query(
            'CREATE TABLE IF NOT EXISTS gd_packages (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                package_key VARCHAR(50) NOT NULL UNIQUE,
                package_name VARCHAR(120) NOT NULL,
                marketing_message_limit INT UNSIGNED NOT NULL DEFAULT 0,
                utility_message_limit INT UNSIGNED NOT NULL DEFAULT 0,
                duration_days INT UNSIGNED NOT NULL DEFAULT 30,
                marketing_price DECIMAL(10,2) NOT NULL DEFAULT 0,
                utility_price DECIMAL(10,2) NOT NULL DEFAULT 0,
                total_price DECIMAL(10,2) NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT UNSIGNED NOT NULL DEFAULT 0,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )'
        );

        foreach (self::DEFAULT_PACKAGES as $key => $package) {
            $stmt = $db->prepare(
                'INSERT IGNORE INTO gd_packages
                    (package_key, package_name, marketing_message_limit, utility_message_limit, duration_days, marketing_price, utility_price, total_price, is_active, sort_order, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW(), NOW())'
            );
            $label = (string) $package['label'];
            $marketingLimit = (int) $package['marketing_limit'];
            $utilityLimit = (int) $package['utility_limit'];
            $days = (int) $package['days'];
            $marketingPrice = (float) $package['marketing_price'];
            $utilityPrice = (float) $package['utility_price'];
            $totalPrice = (float) $package['price'];
            $sortOrder = array_search($key, array_keys(self::DEFAULT_PACKAGES), true) + 1;
            $stmt->bind_param('ssiiidddi', $key, $label, $marketingLimit, $utilityLimit, $days, $marketingPrice, $utilityPrice, $totalPrice, $sortOrder);
            $stmt->execute();
        }

        $db->query(
            'CREATE TABLE IF NOT EXISTS gd_package_payments (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                biz_id BIGINT UNSIGNED NOT NULL,
                package_key VARCHAR(50) NOT NULL,
                package_name VARCHAR(120) NOT NULL,
                amount DECIMAL(10,2) NOT NULL DEFAULT 0,
                currency VARCHAR(10) NOT NULL DEFAULT "INR",
                razorpay_order_id VARCHAR(120) NULL,
                razorpay_payment_id VARCHAR(120) NULL,
                razorpay_signature VARCHAR(255) NULL,
                status VARCHAR(30) NOT NULL DEFAULT "created",
                failure_reason TEXT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX gd_package_payments_biz_id_index (biz_id),
                INDEX gd_package_payments_order_id_index (razorpay_order_id)
            )'
        );
    }

    public static function businessPackageStatus(mysqli $db, int $bizId): array
    {
        $columns = Crm::tableColumns($db, 'gd_orders');
        if (!in_array('message_limit', $columns, true) || !in_array('messages_used', $columns, true)) {
            return ['active' => false, 'reason' => 'Package is not configured.'];
        }

        $stmt = $db->prepare('SELECT package_name, message_limit, messages_used, package_ends_at FROM gd_orders WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $bizId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: [];

        $packageName = trim((string) ($row['package_name'] ?? ''));
        $limit = (int) ($row['message_limit'] ?? 0);
        $used = (int) ($row['messages_used'] ?? 0);
        $endsAt = trim((string) ($row['package_ends_at'] ?? ''));

        if ($packageName === '' || $limit <= 0) {
            return ['active' => false, 'reason' => 'Choose a package to activate messages.'];
        }

        if ($used >= $limit) {
            return ['active' => false, 'reason' => 'Message limit completed. Renew or upgrade your package.'];
        }

        if ($endsAt !== '' && strtotime($endsAt) !== false && strtotime($endsAt) < time()) {
            return ['active' => false, 'reason' => 'Package expired. Renew your package to continue.'];
        }

        return [
            'active' => true,
            'reason' => '',
            'package_name' => $packageName,
            'limit' => $limit,
            'used' => $used,
            'remaining' => max(0, $limit - $used),
            'ends_at' => $endsAt,
        ];
    }

    public static function requiresPayment(mysqli $db, int $bizId): bool
    {
        return !((bool) (self::businessPackageStatus($db, $bizId)['active'] ?? false));
    }

    public static function redirectIfPaymentRequired(mysqli $db, int $bizId): void
    {
        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
        $path = strtolower($path);
        if (str_contains($path, '/business/payments') || str_contains($path, '/business/logout')) {
            return;
        }

        if (self::requiresPayment($db, $bizId)) {
            header('Location: ' . app_url('business/payments'));
            exit();
        }
    }

    public static function razorpayKeyId(): string
    {
        return trim((string) Config::get('RAZORPAY_KEY_ID', ''));
    }

    public static function razorpayKeySecret(): string
    {
        return trim((string) Config::get('RAZORPAY_KEY_SECRET', ''));
    }

    public static function createRazorpayOrder(mysqli $db, int $bizId, string $packageKey): array
    {
        self::ensureTables($db);

        $keyId = self::razorpayKeyId();
        $keySecret = self::razorpayKeySecret();
        if ($keyId === '' || $keySecret === '') {
            return ['ok' => false, 'error' => 'Razorpay keys are not configured.'];
        }

        $package = self::package($packageKey, $db);
        $amount = (float) ($package['price'] ?? 0);
        if ($amount <= 0) {
            return ['ok' => false, 'error' => 'Selected package price is invalid.'];
        }

        $receipt = 'pkg_' . $bizId . '_' . time();
        $payload = [
            'amount' => (int) round($amount * 100),
            'currency' => 'INR',
            'receipt' => $receipt,
            'notes' => [
                'biz_id' => (string) $bizId,
                'package_key' => $packageKey,
            ],
        ];

        $curl = curl_init('https://api.razorpay.com/v1/orders');
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_USERPWD => $keyId . ':' . $keySecret,
            CURLOPT_TIMEOUT => 20,
        ]);
        $response = curl_exec($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        $decoded = json_decode((string) $response, true);
        if ($curlError !== '' || $httpCode < 200 || $httpCode >= 300 || !is_array($decoded) || empty($decoded['id'])) {
            return ['ok' => false, 'error' => $curlError !== '' ? $curlError : (string) ($decoded['error']['description'] ?? 'Unable to create Razorpay order.')];
        }

        $orderId = (string) $decoded['id'];
        $stmt = $db->prepare('INSERT INTO gd_package_payments (biz_id, package_key, package_name, amount, currency, razorpay_order_id, status, created_at, updated_at) VALUES (?, ?, ?, ?, "INR", ?, "created", NOW(), NOW())');
        $packageName = (string) $package['label'];
        $stmt->bind_param('issds', $bizId, $packageKey, $packageName, $amount, $orderId);
        $stmt->execute();

        return [
            'ok' => true,
            'order_id' => $orderId,
            'key_id' => $keyId,
            'amount' => (int) round($amount * 100),
            'currency' => 'INR',
            'package' => $package,
        ];
    }

    public static function verifyAndActivate(mysqli $db, int $bizId, array $payload): array
    {
        self::ensureTables($db);

        $orderId = trim((string) ($payload['razorpay_order_id'] ?? ''));
        $paymentId = trim((string) ($payload['razorpay_payment_id'] ?? ''));
        $signature = trim((string) ($payload['razorpay_signature'] ?? ''));
        if ($orderId === '' || $paymentId === '' || $signature === '') {
            return ['ok' => false, 'error' => 'Payment verification details are missing.'];
        }

        $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, self::razorpayKeySecret());
        if (!hash_equals($expected, $signature)) {
            self::markPaymentFailed($db, $bizId, $orderId, 'Razorpay signature verification failed.');
            return ['ok' => false, 'error' => 'Payment verification failed.'];
        }

        $stmt = $db->prepare('SELECT package_key FROM gd_package_payments WHERE biz_id = ? AND razorpay_order_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->bind_param('is', $bizId, $orderId);
        $stmt->execute();
        $payment = $stmt->get_result()->fetch_assoc() ?: [];
        $packageKey = (string) ($payment['package_key'] ?? 'starter');
        $package = self::package($packageKey, $db);
        $marketingLimit = (int) $package['marketing_limit'];
        $utilityLimit = (int) $package['utility_limit'];
        $totalLimit = $marketingLimit + $utilityLimit;
        $days = (int) $package['days'];
        $price = (float) $package['price'];

        $columns = Crm::tableColumns($db, 'gd_orders');
        $updates = [
            'package_name' => (string) $package['label'],
            'message_limit' => $totalLimit,
            'messages_used' => 0,
            'package_price' => (string) $price,
            'package_started_at' => date('Y-m-d H:i:s'),
            'package_ends_at' => date('Y-m-d H:i:s', strtotime('+' . $days . ' days')),
            'limit_request_status' => 'approved',
            'limit_request_note' => '',
            'limit_request_at' => date('Y-m-d H:i:s'),
        ];

        foreach ([
            'marketing_message_limit' => $marketingLimit,
            'utility_message_limit' => $utilityLimit,
            'marketing_messages_used' => 0,
            'utility_messages_used' => 0,
            'marketing_package_price' => (float) ($package['marketing_price'] ?? 0),
            'utility_package_price' => (float) ($package['utility_price'] ?? 0),
        ] as $column => $value) {
            if (in_array($column, $columns, true)) {
                $updates[$column] = $value;
            }
        }

        $setParts = [];
        $types = '';
        $values = [];
        foreach ($updates as $column => $value) {
            if (!in_array($column, $columns, true)) {
                continue;
            }
            $setParts[] = "`{$column}` = ?";
            $types .= is_int($value) ? 'i' : 's';
            $values[] = $value;
        }

        if ($setParts === []) {
            return ['ok' => false, 'error' => 'Package columns are missing. Run migrations first.'];
        }

        $sql = 'UPDATE gd_orders SET ' . implode(', ', $setParts) . ' WHERE id = ?';
        $stmt = $db->prepare($sql);
        $types .= 'i';
        $values[] = $bizId;
        $bind = [$types];
        foreach ($values as $i => $value) {
            $bind[] = &$values[$i];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);
        $stmt->execute();

        $stmt = $db->prepare('UPDATE gd_package_payments SET razorpay_payment_id = ?, razorpay_signature = ?, status = "paid", updated_at = NOW() WHERE biz_id = ? AND razorpay_order_id = ?');
        $stmt->bind_param('ssis', $paymentId, $signature, $bizId, $orderId);
        $stmt->execute();

        return ['ok' => true, 'package' => $package];
    }

    private static function markPaymentFailed(mysqli $db, int $bizId, string $orderId, string $reason): void
    {
        $stmt = $db->prepare('UPDATE gd_package_payments SET status = "failed", failure_reason = ?, updated_at = NOW() WHERE biz_id = ? AND razorpay_order_id = ?');
        $stmt->bind_param('sis', $reason, $bizId, $orderId);
        $stmt->execute();
    }
}

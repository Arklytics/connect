<?php

declare(strict_types=1);

final class AiAutoReply
{
    public static function ensureSchema(mysqli $db): void
    {
        $db->query('
            CREATE TABLE IF NOT EXISTS gd_ai_knowledge_sections (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                biz_id BIGINT UNSIGNED NOT NULL,
                title VARCHAR(255) NOT NULL,
                content MEDIUMTEXT NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT "active",
                sort_order INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_ai_sections_business_status (biz_id, status),
                INDEX idx_ai_sections_business_sort (biz_id, sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        self::ensureOrderColumn($db, 'ai_auto_reply_enabled', 'TINYINT(1) NOT NULL DEFAULT 0');
        self::ensureOrderColumn($db, 'ai_fallback_reply', 'TEXT NULL');
    }

    public static function businessSetting(mysqli $db, int $bizId): array
    {
        self::ensureSchema($db);

        $stmt = $db->prepare('SELECT ai_auto_reply_enabled, ai_fallback_reply FROM gd_orders WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $bizId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: [];

        return [
            'enabled' => (int) ($row['ai_auto_reply_enabled'] ?? 0) === 1,
            'fallback' => trim((string) ($row['ai_fallback_reply'] ?? '')),
        ];
    }

    public static function activeSections(mysqli $db, int $bizId): array
    {
        self::ensureSchema($db);

        $stmt = $db->prepare('
            SELECT id, title, content, sort_order
            FROM gd_ai_knowledge_sections
            WHERE biz_id = ? AND status = "active"
            ORDER BY sort_order ASC, id ASC
        ');
        $stmt->bind_param('i', $bizId);
        $stmt->execute();

        $sections = [];
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $sections[] = $row;
        }

        return $sections;
    }

    public static function generateReply(mysqli $db, int $bizId, string $question, array $contact = []): ?string
    {
        $question = trim($question);
        if ($question === '') {
            return null;
        }

        $setting = self::businessSetting($db, $bizId);
        if (!$setting['enabled']) {
            return null;
        }

        $sections = self::activeSections($db, $bizId);
        if (!$sections) {
            return null;
        }

        $apiKey = trim((string) Config::get('OPENAI_API_KEY', ''));
        if ($apiKey === '') {
            error_log('AI auto reply skipped: OPENAI_API_KEY is missing.');
            return null;
        }

        $model = trim((string) Config::get('OPENAI_MODEL', 'gpt-4.1-mini'));
        $fallback = $setting['fallback'] !== ''
            ? $setting['fallback']
            : 'Thanks for your message. Our team will share the right details shortly.';

        $context = self::sectionContext($sections);
        $contactName = trim((string) ($contact['full_name'] ?? ''));

        $payload = [
            'model' => $model,
            'input' => [
                [
                    'role' => 'system',
                    'content' => [[
                        'type' => 'input_text',
                        'text' => implode("\n", [
                            'You are a WhatsApp CRM assistant for this business.',
                            'Answer only from the approved knowledge sections below.',
                            'If the answer is not in the sections, use this fallback reply exactly: ' . $fallback,
                            'Keep replies concise, friendly, and suitable for WhatsApp.',
                            'Do not invent prices, dates, product claims, links, or policies.',
                            'If a launch date or event date appears in the sections, preserve it exactly.',
                        ]),
                    ]],
                ],
                [
                    'role' => 'user',
                    'content' => [[
                        'type' => 'input_text',
                        'text' => "Contact name: " . ($contactName !== '' ? $contactName : 'Customer')
                            . "\n\nKnowledge sections:\n" . $context
                            . "\n\nCustomer question:\n" . $question,
                    ]],
                ],
            ],
            'temperature' => 0.2,
            'max_output_tokens' => 220,
        ];

        $response = self::openAiRequest($apiKey, $payload);
        if (!$response['ok']) {
            error_log('AI auto reply failed: ' . ($response['error'] ?? 'Unknown OpenAI error'));
            return null;
        }

        $reply = trim((string) ($response['text'] ?? ''));
        return $reply !== '' ? self::limitWhatsappText($reply) : null;
    }

    private static function sectionContext(array $sections): string
    {
        $blocks = [];
        foreach ($sections as $index => $section) {
            $title = trim((string) ($section['title'] ?? 'Section ' . ($index + 1)));
            $content = trim((string) ($section['content'] ?? ''));
            if ($content === '') {
                continue;
            }

            $blocks[] = 'Section ' . ($index + 1) . ': ' . $title . "\n" . $content;
        }

        return implode("\n\n", $blocks);
    }

    private static function openAiRequest(string $apiKey, array $payload): array
    {
        $ch = curl_init('https://api.openai.com/v1/responses');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT => 25,
        ]);

        $body = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $error !== '') {
            return ['ok' => false, 'text' => null, 'error' => $error ?: 'OpenAI request failed.'];
        }

        $json = json_decode((string) $body, true);
        if (!is_array($json)) {
            return ['ok' => false, 'text' => null, 'error' => 'OpenAI returned invalid JSON.'];
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            return ['ok' => false, 'text' => null, 'error' => $json['error']['message'] ?? (string) $body];
        }

        return ['ok' => true, 'text' => self::extractResponseText($json), 'error' => null];
    }

    private static function extractResponseText(array $json): string
    {
        if (isset($json['output_text']) && is_string($json['output_text'])) {
            return $json['output_text'];
        }

        $parts = [];
        foreach (($json['output'] ?? []) as $item) {
            foreach (($item['content'] ?? []) as $content) {
                $text = $content['text'] ?? null;
                if (is_string($text) && trim($text) !== '') {
                    $parts[] = $text;
                }
            }
        }

        return trim(implode("\n", $parts));
    }

    private static function limitWhatsappText(string $text): string
    {
        $text = preg_replace("/[ \t]+/", ' ', trim($text)) ?? trim($text);
        if (strlen($text) <= 1200) {
            return $text;
        }

        return rtrim(substr($text, 0, 1197)) . '...';
    }

    private static function ensureOrderColumn(mysqli $db, string $column, string $definition): void
    {
        $stmt = $db->prepare('
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = "gd_orders"
              AND column_name = ?
            LIMIT 1
        ');
        $stmt->bind_param('s', $column);
        $stmt->execute();

        if ($stmt->get_result()->fetch_assoc()) {
            return;
        }

        $db->query('ALTER TABLE gd_orders ADD COLUMN `' . $db->real_escape_string($column) . '` ' . $definition);
    }
}

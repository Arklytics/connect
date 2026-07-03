<?php

declare(strict_types=1);

require_once __DIR__ . '/../../db_conn.php';

function apiResolveRecipients(mysqli $db, int $bizId, array $payload): array
{
    $recipients = [];

    $phoneNumbers = $payload['phone_numbers'] ?? $payload['to'] ?? $payload['phone_number'] ?? [];
    if (!is_array($phoneNumbers)) {
        $phoneNumbers = [$phoneNumbers];
    }

    foreach ($phoneNumbers as $phoneNumber) {
        if (is_string($phoneNumber) || is_numeric($phoneNumber)) {
            $phone = ApiSupport::normalizePhone((string) $phoneNumber);
            if ($phone !== '') {
                $recipients[] = [
                    'phone_number' => $phone,
                    'contact_id' => null,
                    'full_name' => null,
                ];
            }
        }
    }

    $contactIds = $payload['contact_ids'] ?? [];
    if (!is_array($contactIds)) {
        $contactIds = [$contactIds];
    }

    foreach ($contactIds as $contactIdValue) {
        $contactId = Security::intFrom($contactIdValue);
        if ($contactId <= 0) {
            continue;
        }

        $stmt = $db->prepare('SELECT id, full_name, phone_number FROM gd_user_contacts WHERE id = ? AND biz_id = ? LIMIT 1');
        $stmt->bind_param('ii', $contactId, $bizId);
        $stmt->execute();
        $contact = $stmt->get_result()->fetch_assoc();
        if (!$contact) {
            continue;
        }

        $phone = ApiSupport::normalizePhone((string) $contact['phone_number']);
        if ($phone === '') {
            continue;
        }

        $recipients[] = [
            'phone_number' => $phone,
            'contact_id' => $contactId,
            'full_name' => (string) ($contact['full_name'] ?? ''),
        ];
    }

    $rawRecipients = $payload['recipients'] ?? [];
    if (!is_array($rawRecipients)) {
        $rawRecipients = [$rawRecipients];
    }

    foreach ($rawRecipients as $recipient) {
        if (is_string($recipient) || is_numeric($recipient)) {
            $phone = ApiSupport::normalizePhone((string) $recipient);
            if ($phone !== '') {
                $recipients[] = [
                    'phone_number' => $phone,
                    'contact_id' => null,
                    'full_name' => null,
                ];
            }
            continue;
        }

        if (!is_array($recipient)) {
            continue;
        }

        if (!empty($recipient['contact_id'])) {
            $contactId = Security::intFrom($recipient['contact_id']);
            if ($contactId > 0) {
                $stmt = $db->prepare('SELECT id, full_name, phone_number FROM gd_user_contacts WHERE id = ? AND biz_id = ? LIMIT 1');
                $stmt->bind_param('ii', $contactId, $bizId);
                $stmt->execute();
                $contact = $stmt->get_result()->fetch_assoc();
                if ($contact) {
                    $phone = ApiSupport::normalizePhone((string) $contact['phone_number']);
                    if ($phone !== '') {
                        $recipients[] = [
                            'phone_number' => $phone,
                            'contact_id' => $contactId,
                            'full_name' => (string) ($contact['full_name'] ?? ''),
                        ];
                    }
                }
            }
            continue;
        }

        $phone = ApiSupport::normalizePhone((string) ($recipient['phone_number'] ?? $recipient['phone'] ?? $recipient['to'] ?? ''));
        if ($phone === '') {
            continue;
        }

        $recipients[] = [
            'phone_number' => $phone,
            'contact_id' => null,
            'full_name' => trim((string) ($recipient['full_name'] ?? $recipient['name'] ?? '')) ?: null,
        ];
    }

    $unique = [];
    foreach ($recipients as $recipient) {
        $unique[$recipient['phone_number']] = $recipient;
    }

    return array_values($unique);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiSupport::jsonResponse(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

$payload = ApiSupport::requestJson();
$requestedBizId = Security::intFrom($payload['biz_id'] ?? null);
$bizId = ApiSupport::requireBusinessApiKey($db, $requestedBizId);
$kind = strtolower(trim((string) ($payload['kind'] ?? 'text')));
$language = trim((string) ($payload['language'] ?? 'en')) ?: 'en';
$messageBody = trim((string) ($payload['message'] ?? $payload['message_body'] ?? ''));
$templateName = trim((string) ($payload['template_name'] ?? ''));
$components = $payload['components'] ?? [];
$parameters = $payload['parameters'] ?? $payload['params'] ?? [];
$otp = trim((string) ($payload['otp'] ?? $payload['code'] ?? ''));
$templateRow = [];

$credentials = ApiSupport::businessCredentials($db, $bizId);
$phoneNumberId = trim((string) ($credentials['phone_number_id'] ?? ''));
$accessToken = trim((string) ($credentials['auth_token'] ?? '')) ?: AppSettings::getGlobal($db, 'META_ACCESS_TOKEN', Config::get('META_ACCESS_TOKEN', ''));

if ($phoneNumberId === '' || $accessToken === '') {
    ApiSupport::jsonResponse(['ok' => false, 'error' => 'WhatsApp credentials are not configured for this business.'], 422);
}

$recipients = apiResolveRecipients($db, $bizId, $payload);
if (empty($recipients)) {
    ApiSupport::jsonResponse(['ok' => false, 'error' => 'No recipients were provided.'], 422);
}

$packageStatus = ApiSupport::businessPackageStatus($db, $bizId);
if (($packageStatus['enabled'] ?? false) && (int) ($packageStatus['remaining'] ?? 0) <= 0) {
    ApiSupport::jsonResponse(['ok' => false, 'error' => 'Message limit exhausted. Please request a package upgrade.'], 422);
}

$isTemplateSend = in_array($kind, ['authentication', 'utility', 'marketing', 'template'], true);
if ($isTemplateSend && $templateName === '') {
    ApiSupport::jsonResponse(['ok' => false, 'error' => 'template_name is required for authentication, utility, marketing, and template sends.'], 422);
}

if (!$isTemplateSend && $messageBody === '') {
    ApiSupport::jsonResponse(['ok' => false, 'error' => 'message is required for text sends.'], 422);
}

$templateId = null;
$templateTitle = '';
$templateBody = '';

if ($templateName !== '') {
    $stmt = $db->prepare('SELECT id, message_title, message_body, placeholders FROM gd_whatsapp_templates WHERE biz_id = ? AND template_name = ? LIMIT 1');
    $stmt->bind_param('is', $bizId, $templateName);
    $stmt->execute();
    $templateRow = $stmt->get_result()->fetch_assoc() ?: [];
    $templateId = !empty($templateRow['id']) ? (int) $templateRow['id'] : null;
    $templateTitle = (string) ($templateRow['message_title'] ?? '');
    $templateBody = (string) ($templateRow['message_body'] ?? '');
}

$templateSendComponents = [];
if ($isTemplateSend && empty($components) && !empty($templateRow ?? [])) {
    $builtComponents = ApiSupport::buildTemplateSendComponents($templateRow);
    if (!empty($builtComponents['error'])) {
        ApiSupport::jsonResponse(['ok' => false, 'error' => (string) $builtComponents['error']], 422);
    }

    $templateSendComponents = is_array($builtComponents['components'] ?? null) ? $builtComponents['components'] : [];
}

if ($isTemplateSend && empty($components) && !empty($templateSendComponents)) {
    $components = $templateSendComponents;
}

if ($isTemplateSend && empty($components) && (is_array($parameters) || $otp !== '')) {
    $bodyValues = [];
    if ($otp !== '') {
        $bodyValues[] = $otp;
    }

    if (is_array($parameters)) {
        foreach ($parameters as $value) {
            if (is_string($value) || is_numeric($value)) {
                $bodyValues[] = (string) $value;
            }
        }
    }

    if (!empty($bodyValues)) {
        $components = [
            [
                'type' => 'body',
                'parameters' => array_map(
                    static fn (string $value): array => ['type' => 'text', 'text' => $value],
                    $bodyValues
                ),
            ],
        ];

        if ($kind === 'authentication' && $otp !== '') {
            $components[] = [
                'type' => 'button',
                'sub_type' => 'url',
                'index' => '0',
                'parameters' => [
                    [
                        'type' => 'text',
                        'text' => $otp,
                    ],
                ],
            ];
        }
    }
}

$sent = 0;
$failed = 0;
$details = [];

foreach ($recipients as $recipient) {
    $packageStatus = ApiSupport::businessPackageStatus($db, $bizId);
    if (($packageStatus['enabled'] ?? false) && (int) ($packageStatus['remaining'] ?? 0) <= 0) {
        $failed += count($recipients) - ($sent + $failed);
        $details[] = [
            'to' => null,
            'status' => 'blocked',
            'message_id' => null,
            'error' => 'Message limit exhausted.',
        ];
        break;
    }

    $to = (string) $recipient['phone_number'];
    $sendPayload = $isTemplateSend
        ? ApiSupport::whatsappTemplatePayload($to, $templateName, $language, is_array($components) ? $components : [])
        : ApiSupport::whatsappTextPayload($to, $messageBody);

    $result = ApiSupport::whatsappSendRequest($phoneNumberId, $accessToken, $sendPayload);
    $messageId = $result['message_id'] !== null ? (string) $result['message_id'] : null;
    $status = $result['ok'] ? 'success' : 'failed';
    $deliveryStatus = $result['ok'] ? 'sent' : 'failed';
    $errorMessage = $result['ok'] ? null : (string) ($result['failure_reason'] ?? $result['error'] ?? 'Unknown WhatsApp error');
    $sentAt = $result['ok'] ? date('Y-m-d H:i:s') : null;

    $messageTitle = $isTemplateSend
        ? strtoupper($kind) . ' template: ' . $templateName
        : 'WhatsApp text message';

    if ($isTemplateSend) {
        $messageBodyForLog = trim($templateBody) !== ''
            ? $templateBody
            : (string) json_encode([
                'template_name' => $templateName,
                'language' => $language,
                'components' => $components,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    } else {
        $messageBodyForLog = $messageBody;
    }

    ApiSupport::storeSentMessage(
        $db,
        $bizId,
        $to,
        $templateId,
        $messageTitle,
        $messageBodyForLog,
        $status,
        $deliveryStatus,
        $errorMessage,
        $messageId,
        $sentAt,
        $result['request_json'] ?? null,
        $result['response_json'] ?? null,
        $result['http_code'] ?? null,
        $result['failure_reason'] ?? null
    );

    $details[] = [
        'to' => $to,
        'status' => $status,
        'message_id' => $messageId,
        'error' => $errorMessage,
    ];

    if ($result['ok']) {
        $sent++;
        ApiSupport::consumeMessageCredit($db, $bizId);
    } else {
        $failed++;
    }
}

ApiSupport::jsonResponse([
    'ok' => $failed === 0,
    'biz_id' => $bizId,
    'kind' => $kind,
    'sent' => $sent,
    'failed' => $failed,
    'total' => count($recipients),
    'results' => $details,
], $failed === 0 ? 200 : 207);

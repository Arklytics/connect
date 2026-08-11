<?php

declare(strict_types=1);

require_once __DIR__ . '/../../db_conn.php';

function apiResolveRecipients(mysqli $db, int $bizId, array $payload): array
{
    $recipients = [];

    try {
        ApiSupport::ensureGroupHierarchyColumns($db);
    } catch (Throwable $exception) {
        error_log('Group hierarchy ensure failed: ' . $exception->getMessage());
    }

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
                    'send_values' => [],
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
            'send_values' => [],
        ];
    }

    $groupIds = [];
    foreach (['group_id', 'subgroup_id', 'group_ids', 'subgroup_ids'] as $key) {
        $values = $payload[$key] ?? [];
        if (!is_array($values)) {
            $values = [$values];
        }

        foreach ($values as $value) {
            $id = Security::intFrom($value);
            if ($id > 0) {
                $groupIds[] = $id;
            }
        }
    }

    $targetGroupIds = [];
    foreach (array_values(array_unique($groupIds)) as $groupId) {
        if (!ApiSupport::isSubgroup($db, $bizId, $groupId)) {
            ApiSupport::jsonResponse([
                'ok' => false,
                'error' => 'Messages can only target subgroup IDs. Select a parent group first, then send subgroup_id/subgroup_ids.',
            ], 422);
        }

        $targetGroupIds[] = $groupId;
    }
    $targetGroupIds = array_values(array_unique(array_filter($targetGroupIds)));

    if (!empty($targetGroupIds)) {
        $placeholders = implode(',', array_fill(0, count($targetGroupIds), '?'));
        $types = 'i' . str_repeat('i', count($targetGroupIds)) . str_repeat('i', count($targetGroupIds));
        $values = array_merge([$bizId], $targetGroupIds, $targetGroupIds);
        $sql = 'SELECT DISTINCT c.id, c.full_name, c.phone_number
                FROM gd_user_contacts c
                LEFT JOIN gd_group_contacts gc ON gc.contact_id = c.id AND gc.biz_id = c.biz_id
                WHERE c.biz_id = ?
                  AND (c.group_id IN (' . $placeholders . ') OR gc.group_id IN (' . $placeholders . '))';
        $stmt = $db->prepare($sql);
        $bind = [$types];
        foreach ($values as $index => $value) {
            $bind[] = &$values[$index];
        }
        $stmt->bind_param(...$bind);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($contact = $result->fetch_assoc()) {
            $phone = ApiSupport::normalizePhone((string) ($contact['phone_number'] ?? ''));
            if ($phone !== '') {
                $recipients[] = [
                    'phone_number' => $phone,
                    'contact_id' => (int) ($contact['id'] ?? 0),
                    'full_name' => (string) ($contact['full_name'] ?? ''),
                    'send_values' => [],
                ];
            }
        }
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
                    'send_values' => [],
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
                            'send_values' => ApiSupport::templateSendValuesFromInput($recipient),
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
            'send_values' => ApiSupport::templateSendValuesFromInput($recipient),
        ];
    }

    $unique = [];
    foreach ($recipients as $recipient) {
        $unique[$recipient['phone_number']] = $recipient;
    }

    return array_values($unique);
}

function apiMergeTemplateSendValues(array $globalValues, array $recipientValues): array
{
    foreach ($recipientValues as $key => $value) {
        if (is_array($value)) {
            $globalValues[$key] = array_replace_recursive(
                is_array($globalValues[$key] ?? null) ? $globalValues[$key] : [],
                $value
            );
        } elseif (trim((string) $value) !== '') {
            $globalValues[$key] = $value;
        }
    }

    return $globalValues;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiSupport::jsonResponse(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

$payload = ApiSupport::requestJson();
$requestedBizId = Security::intFrom($payload['biz_id'] ?? null);
$bizId = ApiSupport::requireBusinessApiKey($db, $requestedBizId);
$kind = strtolower(trim((string) ($payload['kind'] ?? 'text')));
$languageFromPayload = array_key_exists('language', $payload);
$language = trim((string) ($payload['language'] ?? ''));
$messageBody = trim((string) ($payload['message'] ?? $payload['message_body'] ?? ''));
$templateName = trim((string) ($payload['template_name'] ?? ''));
$components = $payload['components'] ?? [];
$parameters = $payload['parameters'] ?? $payload['params'] ?? [];
$templateSendValues = ApiSupport::templateSendValuesFromInput($payload);
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

$templateBodyValues = [];
if ($otp !== '') {
    $templateBodyValues[] = $otp;
}

if (is_array($parameters)) {
    foreach ($parameters as $value) {
        if (is_string($value) || is_numeric($value)) {
            $templateBodyValues[] = (string) $value;
        }
    }
}

$templateBodyValues = array_values(array_filter(
    array_map(static fn (string $value): string => trim($value), $templateBodyValues),
    static fn (string $value): bool => $value !== ''
));

$templateId = null;
$templateTitle = '';
$templateBody = '';

if ($templateName !== '') {
    $stmt = $db->prepare('SELECT id, message_title, message_body, placeholders, category FROM gd_whatsapp_templates WHERE biz_id = ? AND template_name = ? LIMIT 1');
    $stmt->bind_param('is', $bizId, $templateName);
    $stmt->execute();
    $templateRow = $stmt->get_result()->fetch_assoc() ?: [];
    $templateId = !empty($templateRow['id']) ? (int) $templateRow['id'] : null;
    $templateTitle = (string) ($templateRow['message_title'] ?? '');
    $templateBody = (string) ($templateRow['message_body'] ?? '');
}

$isAuthenticationSend = $kind === 'authentication'
    || strtoupper(trim((string) ($templateRow['category'] ?? ''))) === 'AUTHENTICATION';

if ($isTemplateSend && (!$languageFromPayload || $language === '')) {
    $templateMeta = [];
    if (!empty($templateRow['placeholders'])) {
        $decodedMeta = json_decode((string) $templateRow['placeholders'], true);
        if (is_array($decodedMeta)) {
            $templateMeta = $decodedMeta;
        }
    }

    $language = trim((string) ($templateMeta['payload']['language'] ?? $templateMeta['language'] ?? 'en_US'));
}

if ($language === '') {
    $language = $isTemplateSend ? 'en_US' : 'en';
}

if ($isAuthenticationSend && empty($components)) {
    $authCode = $templateBodyValues[0] ?? '';
    if ($authCode === '') {
        ApiSupport::jsonResponse([
            'ok' => false,
            'error' => 'otp or code is required for authentication template sends.',
        ], 422);
    }

    $components = [
        [
            'type' => 'body',
            'parameters' => [
                [
                    'type' => 'text',
                    'text' => $authCode,
                ],
            ],
        ],
        [
            'type' => 'button',
            'sub_type' => 'url',
            'index' => '0',
            'parameters' => [
                [
                    'type' => 'text',
                    'text' => $authCode,
                ],
            ],
        ],
    ];
}

if ($isTemplateSend && empty($components) && empty($templateRow ?? []) && !empty($templateBodyValues)) {
    $components = [
        [
            'type' => 'body',
            'parameters' => array_map(
                static fn (string $value): array => ['type' => 'text', 'text' => $value],
                $templateBodyValues
            ),
        ],
    ];
}

if ($isAuthenticationSend && is_array($components)) {
    $hasButtonComponent = false;
    $authCode = $templateBodyValues[0] ?? '';

    foreach ($components as $component) {
        if (!is_array($component)) {
            continue;
        }

        if (strtolower((string) ($component['type'] ?? '')) === 'button') {
            $hasButtonComponent = true;
        }

        if ($authCode === '' && strtolower((string) ($component['type'] ?? '')) === 'body') {
            $firstParameter = $component['parameters'][0]['text'] ?? '';
            if (is_string($firstParameter) || is_numeric($firstParameter)) {
                $authCode = trim((string) $firstParameter);
            }
        }
    }

    if (!$hasButtonComponent) {
        if ($authCode === '') {
            ApiSupport::jsonResponse([
                'ok' => false,
                'error' => 'Authentication template sends need a button parameter. Send otp/code, or include a button component at index 0.',
            ], 422);
        }

        $components[] = [
            'type' => 'button',
            'sub_type' => 'url',
            'index' => '0',
            'parameters' => [
                [
                    'type' => 'text',
                    'text' => $authCode,
                ],
            ],
        ];
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
    $recipientComponents = is_array($components) ? $components : [];
    if ($isTemplateSend && empty($recipientComponents) && !empty($templateRow ?? [])) {
        $recipientContext = [
            'contact_id' => $recipient['contact_id'] ?? null,
            'full_name' => $recipient['full_name'] ?? '',
            'name' => $recipient['full_name'] ?? '',
            'phone_number' => $to,
            'phone' => $to,
        ];
        $recipientSendValues = apiMergeTemplateSendValues(
            $templateSendValues + ['_recipient' => $recipientContext],
            is_array($recipient['send_values'] ?? null) ? $recipient['send_values'] : []
        );
        $builtComponents = ApiSupport::buildTemplateSendComponents($templateRow, $recipientSendValues);
        if (!empty($builtComponents['error'])) {
            $failed++;
            $details[] = [
                'to' => $to,
                'status' => 'failed',
                'message_id' => null,
                'error' => (string) $builtComponents['error'],
            ];
            continue;
        }

        $recipientComponents = is_array($builtComponents['components'] ?? null) ? $builtComponents['components'] : [];
    }

    $sendPayload = $isTemplateSend
        ? ApiSupport::whatsappTemplatePayload($to, $templateName, $language, $recipientComponents)
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
                'components' => $recipientComponents,
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

<?php
include __DIR__ . '/../session.php';
include __DIR__ . '/../db_conn.php';

$biz_id = Auth::requireLogin();

function batchRange(array $payload, int $total): array
{
    $mode = strtolower(trim((string) ($payload['send_scope'] ?? 'all')));
    if ($mode !== 'partial') {
        return [1, $total, $total];
    }

    $start = max(1, Security::intFrom($payload['range_start'] ?? 1, 1));
    $end = max($start, Security::intFrom($payload['range_end'] ?? $total, $total));
    $start = min($start, max(1, $total));
    $end = min($end, $total);

    return [$start, $end, max(0, $end - $start + 1)];
}

function batchSelectedGroupIds(mysqli $db, int $bizId, array $payload): array
{
    $mode = strtolower(trim((string) ($payload['recipient_mode'] ?? 'all')));
    if ($mode !== 'subgroups') {
        ApiSupport::jsonResponse(['ok' => false, 'error' => 'Select at least one subgroup.'], 422);
    }

    $ids = [];
    foreach ((array) ($payload['subgroup_ids'] ?? []) as $id) {
        $cleanId = Security::intFrom($id);
        if ($cleanId > 0) {
            $ids[] = $cleanId;
        }
    }

    $ids = array_values(array_unique($ids));
    if ($ids === []) {
        ApiSupport::jsonResponse(['ok' => false, 'error' => 'Select at least one subgroup.'], 422);
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids)) . 'i';
    $values = array_merge($ids, [$bizId]);
    $stmt = $db->prepare('SELECT id FROM gd_groups WHERE id IN (' . $placeholders . ') AND biz_id = ? AND parent_id IS NOT NULL');
    $bind = [$types];
    foreach ($values as $index => $value) {
        $bind[] = &$values[$index];
    }
    $stmt->bind_param(...$bind);
    $stmt->execute();

    $validIds = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $validIds[] = (int) ($row['id'] ?? 0);
    }

    if ($validIds === []) {
        ApiSupport::jsonResponse(['ok' => false, 'error' => 'Selected subgroups were not found.'], 422);
    }

    return $validIds;
}

function batchRecipients(mysqli $db, int $bizId, array $groupIds, int $offset, int $limit, int $rangeStart): array
{
    $sqlOffset = max(0, ($rangeStart - 1) + $offset);
    if ($groupIds === []) {
        $stmt = $db->prepare(
            'SELECT DISTINCT c.id, c.full_name, c.phone_number
             FROM gd_user_contacts c
             WHERE c.biz_id = ?
             ORDER BY c.id ASC
             LIMIT ? OFFSET ?'
        );
        $stmt->bind_param('iii', $bizId, $limit, $sqlOffset);
        $stmt->execute();

        $rows = [];
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        return $rows;
    }

    $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
    $types = 'i' . str_repeat('i', count($groupIds)) . str_repeat('i', count($groupIds)) . 'ii';
    $values = array_merge([$bizId], $groupIds, $groupIds, [$limit, $sqlOffset]);
    $stmt = $db->prepare(
        'SELECT DISTINCT c.id, c.full_name, c.phone_number
         FROM gd_user_contacts c
         LEFT JOIN gd_group_contacts gc ON gc.contact_id = c.id AND gc.biz_id = c.biz_id
         WHERE c.biz_id = ? AND (c.group_id IN (' . $placeholders . ') OR gc.group_id IN (' . $placeholders . '))
         ORDER BY c.id ASC
         LIMIT ? OFFSET ?'
    );
    $bind = [$types];
    foreach ($values as $index => $value) {
        $bind[] = &$values[$index];
    }
    $stmt->bind_param(...$bind);
    $stmt->execute();

    $rows = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    return $rows;
}

function batchRecipientCount(mysqli $db, int $bizId, array $groupIds): int
{
    if ($groupIds === []) {
        $stmt = $db->prepare('SELECT COUNT(DISTINCT id) AS total FROM gd_user_contacts WHERE biz_id = ?');
        $stmt->bind_param('i', $bizId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: [];

        return (int) ($row['total'] ?? 0);
    }

    $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
    $types = 'i' . str_repeat('i', count($groupIds)) . str_repeat('i', count($groupIds));
    $values = array_merge([$bizId], $groupIds, $groupIds);
    $stmt = $db->prepare(
        'SELECT COUNT(*) AS total
         FROM (
            SELECT DISTINCT c.id
            FROM gd_user_contacts c
            LEFT JOIN gd_group_contacts gc ON gc.contact_id = c.id AND gc.biz_id = c.biz_id
            WHERE c.biz_id = ? AND (c.group_id IN (' . $placeholders . ') OR gc.group_id IN (' . $placeholders . '))
         ) recipients'
    );
    $bind = [$types];
    foreach ($values as $index => $value) {
        $bind[] = &$values[$index];
    }
    $stmt->bind_param(...$bind);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];

    return (int) ($row['total'] ?? 0);
}

function batchTemplate(mysqli $db, int $bizId, int $templateId): array
{
    $stmt = $db->prepare('SELECT * FROM gd_whatsapp_templates WHERE id = ? AND biz_id = ? LIMIT 1');
    $stmt->bind_param('ii', $templateId, $bizId);
    $stmt->execute();
    $template = $stmt->get_result()->fetch_assoc();

    if (!$template) {
        ApiSupport::jsonResponse(['ok' => false, 'error' => 'Template not found.'], 404);
    }

    return batchHydrateTemplateMediaUrl($db, $bizId, $template);
}

function batchTemplateMediaHandle(array $meta): string
{
    $mediaHandle = trim((string) ($meta['header_media_handle'] ?? ''));
    if ($mediaHandle !== '') {
        return $mediaHandle;
    }

    foreach ((array) ($meta['payload']['components'] ?? []) as $component) {
        if (!is_array($component) || strtoupper(trim((string) ($component['type'] ?? ''))) !== 'HEADER') {
            continue;
        }

        $handles = $component['example']['header_handle'] ?? [];
        if (is_array($handles) && trim((string) ($handles[0] ?? '')) !== '') {
            return trim((string) $handles[0]);
        }
    }

    return '';
}

function batchHydrateTemplateMediaUrl(mysqli $db, int $bizId, array $template): array
{
    ApiSupport::ensureTemplateMediaTable($db);
    $meta = json_decode((string) ($template['placeholders'] ?? ''), true);
    if (!is_array($meta)) {
        return $template;
    }

    $headerType = strtoupper(trim((string) ($meta['header_type'] ?? 'NONE')));
    if (!in_array($headerType, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)) {
        return $template;
    }

    $mediaUrl = trim((string) ($meta['header_media_url'] ?? $template['media_url'] ?? ''));
    if ($mediaUrl !== '') {
        return $template;
    }

    $mediaHandle = batchTemplateMediaHandle($meta);
    if ($mediaHandle === '') {
        return $template;
    }

    $stmt = $db->prepare('SELECT s3_url FROM gd_template_media WHERE biz_id = ? AND media_handle = ? AND s3_url <> "" ORDER BY id DESC LIMIT 1');
    if (!$stmt) {
        return $template;
    }

    $stmt->bind_param('is', $bizId, $mediaHandle);
    $stmt->execute();
    $media = $stmt->get_result()->fetch_assoc();
    $s3Url = trim((string) ($media['s3_url'] ?? ''));
    if ($s3Url === '') {
        return $template;
    }

    $meta['header_media_url'] = $s3Url;
    $template['media_url'] = $s3Url;
    $template['placeholders'] = ApiSupport::encodeJson($meta) ?? (string) ($template['placeholders'] ?? '');
    $updateStmt = $db->prepare('UPDATE gd_whatsapp_templates SET media_url = ?, placeholders = ?, updated_at = NOW() WHERE id = ? AND biz_id = ?');
    if ($updateStmt) {
        $templateJson = (string) $template['placeholders'];
        $templateId = (int) ($template['id'] ?? 0);
        $updateStmt->bind_param('ssii', $s3Url, $templateJson, $templateId, $bizId);
        $updateStmt->execute();
    }

    return $template;
}

function batchCredentials(mysqli $db, int $bizId): array
{
    $stmt = $db->prepare('SELECT phone_number_id, auth_token FROM gd_orders WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $bizId);
    $stmt->execute();
    $business = $stmt->get_result()->fetch_assoc() ?: [];

    $phoneNumberId = trim((string) ($business['phone_number_id'] ?? ''));
    $token = trim((string) ($business['auth_token'] ?? ''));
    if ($token === '') {
        $token = AppSettings::getGlobal($db, 'META_ACCESS_TOKEN', Config::get('META_ACCESS_TOKEN', ''));
    }

    if ($phoneNumberId === '' || $token === '') {
        ApiSupport::jsonResponse(['ok' => false, 'error' => 'WhatsApp credentials are missing.'], 422);
    }

    return [$phoneNumberId, $token];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiSupport::jsonResponse(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

try {
    Security::verifyCsrf();

    $action = strtolower(trim((string) ($_POST['action'] ?? 'send')));
    $templateId = Security::intFrom($_POST['template_id'] ?? null);
    $offset = max(0, Security::intFrom($_POST['offset'] ?? 0));
    $limit = max(1, min(10, Security::intFrom($_POST['limit'] ?? 5)));

    if ($templateId <= 0) {
        ApiSupport::jsonResponse(['ok' => false, 'error' => 'Select a template.'], 422);
    }

    $template = batchTemplate($db, (int) $biz_id, $templateId);
    $packageCategory = ApiSupport::packageMessageCategory((string) ($template['category'] ?? ''));
    $templateMeta = json_decode((string) ($template['placeholders'] ?? ''), true);
    $languageCode = is_array($templateMeta) ? (string) ($templateMeta['payload']['language'] ?? 'en_US') : 'en_US';
    $languageCode = $languageCode !== '' ? $languageCode : 'en_US';

    $templateSend = ApiSupport::buildTemplateSendComponents($template, ApiSupport::templateSendValuesFromInput($_POST));
    if (!empty($templateSend['error'])) {
        ApiSupport::jsonResponse(['ok' => false, 'error' => (string) $templateSend['error']], 422);
    }

    $selectedGroupIds = batchSelectedGroupIds($db, (int) $biz_id, $_POST);
    $groupTotal = batchRecipientCount($db, (int) $biz_id, $selectedGroupIds);
    if ($groupTotal <= 0) {
        ApiSupport::jsonResponse(['ok' => false, 'error' => 'No members found in the selected group.'], 422);
    }

    [$rangeStart, $rangeEnd, $total] = batchRange($_POST, $groupTotal);
    if ($total <= 0) {
        ApiSupport::jsonResponse(['ok' => false, 'error' => 'Selected contact range is empty.'], 422);
    }

    if ($action === 'prepare') {
        ApiSupport::jsonResponse([
            'ok' => true,
            'total' => $total,
            'group_total' => $groupTotal,
            'range_start' => $rangeStart,
            'range_end' => $rangeEnd,
            'offset' => 0,
            'batch_size' => $limit,
        ]);
    }

    [$phoneNumberId, $whatsappToken] = batchCredentials($db, (int) $biz_id);
    $limit = min($limit, max(0, $total - $offset));
    $recipients = batchRecipients($db, (int) $biz_id, $selectedGroupIds, $offset, $limit, $rangeStart);
    $sent = 0;
    $failed = 0;
    $errors = [];

    foreach ($recipients as $recipient) {
        $packageStatus = ApiSupport::businessPackageStatus($db, (int) $biz_id, $packageCategory);
        if (($packageStatus['enabled'] ?? false) && (int) ($packageStatus['remaining'] ?? 0) <= 0) {
            $errors[] = 'Message limit exhausted. Please request a package upgrade.';
            break;
        }

        $phone = ApiSupport::normalizePhone((string) ($recipient['phone_number'] ?? ''));
        if ($phone === '') {
            $failed++;
            $errors[] = 'Skipped empty phone number.';
            continue;
        }

        $recipientSendValues = ApiSupport::templateSendValuesFromInput($_POST);
        $recipientSendValues['_recipient'] = [
            'contact_id' => $recipient['id'] ?? null,
            'full_name' => $recipient['full_name'] ?? '',
            'name' => $recipient['full_name'] ?? '',
            'phone_number' => $phone,
            'phone' => $phone,
        ];
        $recipientTemplateSend = ApiSupport::buildTemplateSendComponents($template, $recipientSendValues);
        if (!empty($recipientTemplateSend['error'])) {
            $failed++;
            $errors[] = 'Failed to send to ' . $phone . ': ' . (string) $recipientTemplateSend['error'];
            continue;
        }
        $recipientTemplateComponents = is_array($recipientTemplateSend['components'] ?? null) ? $recipientTemplateSend['components'] : [];

        $payload = ApiSupport::whatsappTemplatePayload(
            $phone,
            (string) $template['template_name'],
            $languageCode,
            $recipientTemplateComponents
        );
        $response = ApiSupport::whatsappSendRequest($phoneNumberId, $whatsappToken, $payload);
        $status = $response['ok'] ? 'success' : 'failed';
        $deliveryStatus = $response['ok'] ? 'sent' : 'failed';
        $errorMessage = $response['ok'] ? null : (string) ($response['failure_reason'] ?? $response['error'] ?? 'Unknown WhatsApp error');

        ApiSupport::storeSentMessage(
            $db,
            (int) $biz_id,
            $phone,
            $templateId,
            (string) $template['message_title'],
            (string) $template['message_body'],
            $status,
            $deliveryStatus,
            $errorMessage,
            $response['message_id'] !== null ? (string) $response['message_id'] : null,
            $response['ok'] ? date('Y-m-d H:i:s') : null,
            $response['request_json'] ?? ApiSupport::encodeJson($payload),
            $response['response_json'] ?? null,
            $response['http_code'] ?? null,
            $response['failure_reason'] ?? null
        );

        if ($response['ok']) {
            $sent++;
            ApiSupport::consumeMessageCredit($db, (int) $biz_id, 1, $packageCategory);
        } else {
            $failed++;
            $errors[] = 'Failed to send to ' . $phone . ': ' . $errorMessage;
        }
    }

    $nextOffset = $offset + count($recipients);
    ApiSupport::jsonResponse([
        'ok' => true,
        'total' => $total,
        'offset' => $nextOffset,
        'sent' => $sent,
        'failed' => $failed,
        'done' => $nextOffset >= $total || ($errors !== [] && str_contains($errors[0], 'limit exhausted')),
        'errors' => array_slice($errors, 0, 5),
    ]);
} catch (Throwable $exception) {
    error_log('Batch message send failed: ' . $exception->getMessage());
    ApiSupport::jsonResponse(['ok' => false, 'error' => 'Unable to send messages right now. Please try again.'], 500);
}

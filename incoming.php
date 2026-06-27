<?php

require_once __DIR__ . '/app/bootstrap.php';

function incomingBusinessId(mysqli $db, string $phoneNumberId, string $whatsappId): ?int
{
    $phoneNumberId = trim($phoneNumberId);
    $whatsappId = trim($whatsappId);

    if ($phoneNumberId === '' && $whatsappId === '') {
        return null;
    }

    $stmt = $db->prepare('SELECT id FROM gd_orders WHERE phone_number_id = ? OR whatsapp_id = ? LIMIT 1');
    $stmt->bind_param('ss', $phoneNumberId, $whatsappId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    return $row ? (int) $row['id'] : null;
}

function incomingFindContact(mysqli $db, int $bizId, string $phone): ?array
{
    $normalized = Crm::normalizePhone($phone);
    if ($normalized === '') {
        return null;
    }

    $phoneWithPlus = str_starts_with($phone, '+') ? $phone : '+' . $normalized;
    $phoneWithoutPlus = ltrim($phoneWithPlus, '+');

    $stmt = $db->prepare('
        SELECT *
        FROM gd_user_contacts
        WHERE biz_id = ?
          AND (
            phone_number = ?
            OR phone_number = ?
            OR REPLACE(phone_number, "+", "") = ?
          )
        ORDER BY id DESC
        LIMIT 1
    ');
    $stmt->bind_param('isss', $bizId, $phone, $phoneWithPlus, $phoneWithoutPlus);
    $stmt->execute();

    $contact = $stmt->get_result()->fetch_assoc();
    return $contact ?: null;
}

function incomingLastOutboundTouch(mysqli $db, int $bizId, string $phone): ?DateTimeImmutable
{
    $columns = Crm::tableColumns($db, 'gd_sent_messages');
    if (!$columns) {
        return null;
    }

    $timeColumn = null;
    foreach (['sent_at', 'created_at', 'updated_at'] as $candidate) {
        if (in_array($candidate, $columns, true)) {
            $timeColumn = $candidate;
            break;
        }
    }

    if ($timeColumn === null) {
        return null;
    }

    $phoneWithPlus = str_starts_with($phone, '+') ? $phone : '+' . Crm::normalizePhone($phone);
    $phoneWithoutPlus = ltrim($phoneWithPlus, '+');

    $sql = 'SELECT `' . $timeColumn . '` AS touch_at
            FROM gd_sent_messages
            WHERE biz_id = ?
              AND (
                phone_number = ?
                OR phone_number = ?
                OR REPLACE(phone_number, "+", "") = ?
              )
            ORDER BY id DESC
            LIMIT 1';
    $stmt = $db->prepare($sql);
    $stmt->bind_param('isss', $bizId, $phone, $phoneWithPlus, $phoneWithoutPlus);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row || empty($row['touch_at'])) {
        return null;
    }

    try {
        return new DateTimeImmutable((string) $row['touch_at']);
    } catch (Throwable $exception) {
        return null;
    }
}

function incomingPauseFollowUps(mysqli $db, int $bizId, int $contactId): void
{
    $columns = Crm::tableColumns($db, 'gd_contact_followups');
    if (!$columns) {
        return;
    }

    if (!in_array('status', $columns, true)) {
        return;
    }

    $stmt = $db->prepare('
        UPDATE gd_contact_followups
        SET status = "paused", updated_at = NOW()
        WHERE biz_id = ? AND contact_id = ? AND status IN ("pending", "scheduled")
    ');
    $stmt->bind_param('ii', $bizId, $contactId);
    $stmt->execute();
}

function incomingActiveSequencePlan(mysqli $db, int $bizId): ?array
{
    $columns = Crm::tableColumns($db, 'gd_whatsapp_sequence_plans');
    if (!$columns || !in_array('structure_json', $columns, true)) {
        return null;
    }

    $stmt = $db->prepare('SELECT * FROM gd_whatsapp_sequence_plans WHERE biz_id = ? AND status = "active" ORDER BY id DESC LIMIT 1');
    $stmt->bind_param('i', $bizId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    return $row ?: null;
}

function incomingPlanSteps(array $plan): array
{
    $structure = json_decode((string) ($plan['structure_json'] ?? ''), true);
    if (!is_array($structure)) {
        return [];
    }

    $steps = $structure['steps'] ?? [];
    if (!is_array($steps)) {
        return [];
    }

    usort($steps, function (array $left, array $right): int {
        return ((int) ($left['step_no'] ?? 0)) <=> ((int) ($right['step_no'] ?? 0));
    });

    return $steps;
}

function incomingQueueReplyDrivenSequence(
    mysqli $db,
    int $bizId,
    array $contact,
    DateTimeImmutable $replyAt,
    ?int $responseMinutes,
    string $replyText
): void {
    $plan = incomingActiveSequencePlan($db, $bizId);
    if (!$plan) {
        return;
    }

    $steps = incomingPlanSteps($plan);
    if (empty($steps)) {
        return;
    }

    $targetSteps = ($responseMinutes !== null && $responseMinutes <= 120) ? [1, 3] : [2, 3];

    incomingPauseFollowUps($db, $bizId, (int) $contact['id']);

    $messagePrefix = trim((string) ($plan['plan_name'] ?? 'WhatsApp sequence'));
    $created = 0;

    foreach ($steps as $step) {
        $stepNo = (int) ($step['step_no'] ?? 0);
        if (!in_array($stepNo, $targetSteps, true)) {
            continue;
        }

        $delayDays = max(0, (int) ($step['delay_days'] ?? 0));
        $scheduledAt = $replyAt->modify('+' . $delayDays . ' day')->format('Y-m-d H:i:s');
        $message = trim((string) ($step['message'] ?? ''));
        if ($message === '') {
            continue;
        }

        $message = str_replace(['{{name}}', '{{contact_name}}'], [$contact['full_name'], $contact['full_name']], $message);
        $notes = 'Auto-queued from reply-driven plan.';
        if ($responseMinutes !== null) {
            $notes .= ' Response time: ' . $responseMinutes . ' minute(s).';
        }

        $stmt = $db->prepare('INSERT INTO gd_contact_followups (biz_id, contact_id, channel, sequence_name, step_no, scheduled_at, status, message, notes, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $channel = 'whatsapp';
        $status = 'pending';
        $now = date('Y-m-d H:i:s');
        $contactId = (int) $contact['id'];
        $stmt->bind_param(
            'iississssss',
            $bizId,
            $contactId,
            $channel,
            $messagePrefix,
            $stepNo,
            $scheduledAt,
            $status,
            $message,
            $notes,
            $now,
            $now
        );
        $stmt->execute();
        $created++;
    }

    if ($created > 0 && in_array('next_follow_up_at', Crm::tableColumns($db, 'gd_user_contacts'), true)) {
        $touchAt = $replyAt->format('Y-m-d H:i:s');
        $stmt = $db->prepare('UPDATE gd_user_contacts SET next_follow_up_at = ? WHERE id = ? AND biz_id = ?');
        $contactId = (int) $contact['id'];
        $stmt->bind_param('sii', $touchAt, $contactId, $bizId);
        $stmt->execute();
    }
}

function incomingUpdateContact(mysqli $db, array $contact, ?int $responseMinutes, string $temperature, string $replyText): void
{
    $updates = [];
    $types = '';
    $values = [];

    $columns = Crm::tableColumns($db, 'gd_user_contacts');
    $now = date('Y-m-d H:i:s');

    $append = function (string $column, mixed $value) use (&$updates, &$types, &$values) {
        if ($value === null) {
            $updates[] = "`$column` = NULL";
            return;
        }

        $updates[] = "`$column` = ?";
        $values[] = $value;
        $types .= is_int($value) ? 'i' : 's';
    };

    if (in_array('last_inbound_at', $columns, true)) {
        $append('last_inbound_at', $now);
    }

    if (in_array('reply_verified_at', $columns, true)) {
        $append('reply_verified_at', $now);
    }

    if (in_array('reply_path', $columns, true)) {
        $replyPath = 'verified';
        if ($responseMinutes !== null && $responseMinutes <= 120) {
            $replyPath = 'quick_reply';
        } elseif ($responseMinutes !== null) {
            $replyPath = 'delayed_reply';
        }
        $append('reply_path', $replyPath);
    }

    if (in_array('reply_verified_via', $columns, true)) {
        $append('reply_verified_via', 'whatsapp_webhook');
    }

    if (in_array('last_reply_text', $columns, true)) {
        $append('last_reply_text', trim($replyText) !== '' ? $replyText : null);
    }

    if (in_array('first_response_at', $columns, true) && empty($contact['first_response_at'])) {
        $append('first_response_at', $now);
    }

    if (in_array('response_time_minutes', $columns, true) && $responseMinutes !== null) {
        $append('response_time_minutes', $responseMinutes);
    }

    if (in_array('lead_temperature', $columns, true)) {
        $append('lead_temperature', $temperature);
    }

    if (in_array('lead_status', $columns, true) && !in_array(($contact['lead_status'] ?? ''), ['won', 'lost'], true)) {
        $append('lead_status', 'contacted');
    }

    if (in_array('status', $columns, true) && !in_array(($contact['status'] ?? ''), ['won', 'lost'], true)) {
        $append('status', 'contacted');
    }

    if (in_array('crm_notes', $columns, true) && trim($replyText) !== '') {
        $existingNotes = trim((string) ($contact['crm_notes'] ?? ''));
        $combined = $existingNotes !== '' ? $existingNotes . "\nInbound reply: " . $replyText : "Inbound reply: " . $replyText;
        $append('crm_notes', $combined);
    }

    if (in_array('next_follow_up_at', $columns, true)) {
        $append('next_follow_up_at', null);
    }

    if (!$updates) {
        return;
    }

    $sql = 'UPDATE gd_user_contacts SET ' . implode(', ', $updates) . ' WHERE id = ? AND biz_id = ?';
    $stmt = $db->prepare($sql);

    $values[] = (int) $contact['id'];
    $values[] = (int) $contact['biz_id'];
    $types .= 'ii';

    $params = [$types];
    foreach ($values as $index => $value) {
        $params[] = &$values[$index];
    }

    $stmt->bind_param(...$params);
    $stmt->execute();
}

function incomingUpdateMessageDelivery(mysqli $db, int $bizId, array $statusRow): void
{
    $messageId = trim((string) ($statusRow['id'] ?? $statusRow['message_id'] ?? ''));
    $deliveryStatus = strtolower(trim((string) ($statusRow['status'] ?? '')));

    if ($messageId === '' || $deliveryStatus === '') {
        return;
    }

    $updateSql = 'UPDATE gd_sent_messages SET delivery_status = ?, updated_at = NOW()';
    $params = [$deliveryStatus];
    $types = 's';

    $statusTimestamp = null;
    if (!empty($statusRow['timestamp'])) {
        try {
            $statusTimestamp = (new DateTimeImmutable())->setTimestamp((int) $statusRow['timestamp'])->format('Y-m-d H:i:s');
        } catch (Throwable $exception) {
            $statusTimestamp = null;
        }
    }

    if ($deliveryStatus === 'delivered' && $statusTimestamp !== null) {
        $updateSql .= ', delivered_at = COALESCE(delivered_at, ?)';
        $params[] = $statusTimestamp;
        $types .= 's';
    }

    if ($deliveryStatus === 'read' && $statusTimestamp !== null) {
        $updateSql .= ', read_at = COALESCE(read_at, ?)';
        $params[] = $statusTimestamp;
        $types .= 's';
    }

    if ($deliveryStatus === 'failed') {
        $updateSql .= ', status = "failed"';
    }

    $updateSql .= ' WHERE biz_id = ? AND message_id = ?';
    $params[] = $bizId;
    $params[] = $messageId;
    $types .= 'is';

    $stmt = $db->prepare($updateSql);
    $bind = [$types];
    foreach ($params as $index => $value) {
        $bind[] = &$params[$index];
    }
    $stmt->bind_param(...$bind);
    $stmt->execute();
}

$db = Database::connectOrNull();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $verifyToken = Config::require('META_VERIFY_TOKEN');
    if ($db) {
        $verifyToken = AppSettings::getGlobal($db, 'META_VERIFY_TOKEN', $verifyToken);
    }

    if (hash_equals($verifyToken, (string) ($_GET['hub_verify_token'] ?? ''))) {
        echo h($_GET['hub_challenge'] ?? '');
    } else {
        echo 'Error: Verification failed.';
    }

    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

if (!$db) {
    http_response_code(200);
    exit('Database unavailable');
}

$payload = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(200);
    exit('OK');
}

$processed = 0;

foreach (($payload['entry'] ?? []) as $entry) {
    foreach (($entry['changes'] ?? []) as $change) {
        $value = $change['value'] ?? [];
        $metadata = $value['metadata'] ?? [];
        $phoneNumberId = (string) ($metadata['phone_number_id'] ?? $value['phone_number_id'] ?? '');
        $whatsappId = (string) ($metadata['whatsapp_business_account_id'] ?? $value['whatsapp_business_account_id'] ?? '');
        $bizId = incomingBusinessId($db, $phoneNumberId, $whatsappId);

        if (!$bizId) {
            continue;
        }

        foreach (($value['messages'] ?? []) as $message) {
            $from = (string) ($message['from'] ?? $message['wa_id'] ?? '');
            $replyText = trim((string) ($message['text']['body'] ?? $message['button']['text'] ?? ''));
            $timestamp = (int) ($message['timestamp'] ?? time());
            $replyAt = (new DateTimeImmutable())->setTimestamp($timestamp);

            $contact = incomingFindContact($db, $bizId, $from);
            if (!$contact) {
                continue;
            }

            $lastTouch = incomingLastOutboundTouch($db, $bizId, (string) $contact['phone_number']);
            $responseMinutes = null;
            if ($lastTouch instanceof DateTimeImmutable) {
                $responseMinutes = max(0, (int) round(($replyAt->getTimestamp() - $lastTouch->getTimestamp()) / 60));
            }

            $temperature = Crm::responseTemperature($responseMinutes);
            incomingUpdateContact($db, $contact, $responseMinutes, $temperature, $replyText);
            incomingQueueReplyDrivenSequence($db, $bizId, $contact, $replyAt, $responseMinutes, $replyText);
            $processed++;
        }

        foreach (($value['statuses'] ?? []) as $statusRow) {
            if (!is_array($statusRow)) {
                continue;
            }

            incomingUpdateMessageDelivery($db, $bizId, $statusRow);
            $processed++;
        }
    }
}

http_response_code(200);
echo $processed > 0 ? 'OK' : 'NOOP';

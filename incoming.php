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

$db = Database::connectOrNull();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $verifyToken = Config::require('META_VERIFY_TOKEN');

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
            incomingPauseFollowUps($db, $bizId, (int) $contact['id']);
            $processed++;
        }
    }
}

http_response_code(200);
echo $processed > 0 ? 'OK' : 'NOOP';

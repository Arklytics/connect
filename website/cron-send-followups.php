<?php

declare(strict_types=1);

require_once __DIR__ . '/../db_conn.php';

function cronNormalizeWhatsappPhone(string $phone): string
{
    $phone = trim($phone);
    $phone = preg_replace('/[^\d+]/', '', $phone);

    if ($phone === '') {
        return '';
    }

    if (!preg_match('/^\+\d+$/', $phone)) {
        $phone = '+91' . ltrim($phone, '+');
    }

    return $phone;
}

function cronSendWhatsappText(string $phoneNumberId, string $token, string $to, string $messageBody): array
{
    $payload = [
        'messaging_product' => 'whatsapp',
        'recipient_type' => 'individual',
        'to' => $to,
        'type' => 'text',
        'text' => [
            'preview_url' => false,
            'body' => $messageBody,
        ],
    ];

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://graph.facebook.com/v18.0/{$phoneNumberId}/messages",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ],
    ]);

    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);

    $decoded = json_decode((string) $response, true);
    $messageId = $decoded['messages'][0]['id'] ?? null;
    $error = $decoded['error']['message'] ?? ($curlError !== '' ? $curlError : null);

    return [
        'ok' => $httpCode === 200 && $messageId !== null,
        'message_id' => $messageId,
        'error' => $error,
        'http_code' => $httpCode,
    ];
}

$db = Database::connect();
$tokenFallback = AppSettings::getGlobal($db, 'META_ACCESS_TOKEN', Config::get('META_ACCESS_TOKEN', ''));
$sent = 0;
$failed = 0;

$sql = '
    SELECT
        f.id AS followup_id,
        f.biz_id,
        f.contact_id,
        f.sequence_name,
        f.step_no,
        f.message,
        f.notes,
        c.full_name,
        c.phone_number,
        o.phone_number_id,
        o.auth_token
    FROM gd_contact_followups f
    INNER JOIN gd_user_contacts c ON c.id = f.contact_id
    INNER JOIN gd_orders o ON o.id = f.biz_id
    WHERE f.status IN ("pending", "scheduled")
      AND f.scheduled_at <= NOW()
      AND o.phone_number_id IS NOT NULL
      AND o.phone_number_id <> ""
    ORDER BY f.scheduled_at ASC, f.id ASC
    LIMIT 50
';

$result = $db->query($sql);
while ($followup = $result->fetch_assoc()) {
    $packageStatus = ApiSupport::businessPackageStatus($db, (int) $followup['biz_id']);
    if (($packageStatus['enabled'] ?? false) && (int) ($packageStatus['remaining'] ?? 0) <= 0) {
        $failed++;
        $update = $db->prepare('UPDATE gd_contact_followups SET status = ?, notes = CONCAT(COALESCE(notes, ""), ?, updated_at = NOW()) WHERE id = ?');
        $status = 'failed';
        $note = ' | Message limit exhausted.';
        $followupId = (int) $followup['followup_id'];
        $update->bind_param('ssi', $status, $note, $followupId);
        $update->execute();
        continue;
    }

    $messageBody = trim((string) ($followup['message'] ?? ''));
    if ($messageBody === '') {
        $messageBody = trim((string) $followup['sequence_name'] . ' step ' . (string) $followup['step_no']);
    }

    $to = cronNormalizeWhatsappPhone((string) $followup['phone_number']);
    if ($to === '') {
        $failed++;
        $update = $db->prepare('UPDATE gd_contact_followups SET status = ?, notes = CONCAT(COALESCE(notes, ""), ?, updated_at = NOW()) WHERE id = ?');
        $status = 'failed';
        $note = ' | Skipped: invalid phone number.';
        $followupId = (int) $followup['followup_id'];
        $update->bind_param('ssi', $status, $note, $followupId);
        $update->execute();
        continue;
    }

    $token = trim((string) ($followup['auth_token'] ?? ''));
    if ($token === '') {
        $token = $tokenFallback;
    }

    if ($token === '') {
        $failed++;
        $update = $db->prepare('UPDATE gd_contact_followups SET status = ?, notes = CONCAT(COALESCE(notes, ""), ?, updated_at = NOW()) WHERE id = ?');
        $status = 'failed';
        $note = ' | Missing WhatsApp access token.';
        $followupId = (int) $followup['followup_id'];
        $update->bind_param('ssi', $status, $note, $followupId);
        $update->execute();
        continue;
    }

    $db->begin_transaction();
    try {
        $markSending = $db->prepare('UPDATE gd_contact_followups SET status = ?, updated_at = NOW() WHERE id = ? AND status IN ("pending", "scheduled")');
        $sending = 'sending';
        $followupId = (int) $followup['followup_id'];
        $markSending->bind_param('si', $sending, $followupId);
        $markSending->execute();

        $sendResult = cronSendWhatsappText((string) $followup['phone_number_id'], $token, $to, $messageBody);
        $messageTitle = (string) $followup['sequence_name'];

        if ($sendResult['ok']) {
            $messageId = (string) ($sendResult['message_id'] ?? '');
            $sentAt = date('Y-m-d H:i:s');

            $sentStmt = $db->prepare('INSERT INTO gd_sent_messages (biz_id, phone_number, template_id, message_title, message_body, status, delivery_status, error_message, message_id, sent_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $templateId = null;
            $status = 'success';
            $deliveryStatus = 'sent';
            $error = null;
            $now = date('Y-m-d H:i:s');
            $bizId = (int) $followup['biz_id'];
            $sentStmt->bind_param('isssssssssss', $bizId, $to, $templateId, $messageTitle, $messageBody, $status, $deliveryStatus, $error, $messageId, $sentAt, $now, $now);
            $sentStmt->execute();
            ApiSupport::consumeMessageCredit($db, $bizId);

            $markSent = $db->prepare('UPDATE gd_contact_followups SET status = ?, sent_at = NOW(), updated_at = NOW() WHERE id = ?');
            $done = 'sent';
            $markSent->bind_param('si', $done, $followupId);
            $markSent->execute();

            $db->commit();
            $sent++;
        } else {
            $errorMessage = (string) ($sendResult['error'] ?? 'Unknown WhatsApp error');

            $sentStmt = $db->prepare('INSERT INTO gd_sent_messages (biz_id, phone_number, template_id, message_title, message_body, status, delivery_status, error_message, message_id, sent_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $templateId = null;
            $status = 'failed';
            $deliveryStatus = 'failed';
            $messageId = null;
            $now = date('Y-m-d H:i:s');
            $bizId = (int) $followup['biz_id'];
            $sentAt = null;
            $sentStmt->bind_param('isssssssssss', $bizId, $to, $templateId, $messageTitle, $messageBody, $status, $deliveryStatus, $errorMessage, $messageId, $sentAt, $now, $now);
            $sentStmt->execute();

            $markFailed = $db->prepare('UPDATE gd_contact_followups SET status = ?, notes = CONCAT(COALESCE(notes, ""), ?, updated_at = NOW()) WHERE id = ?');
            $failedStatus = 'failed';
            $note = ' | ' . $errorMessage;
            $markFailed->bind_param('ssi', $failedStatus, $note, $followupId);
            $markFailed->execute();

            $db->commit();
            $failed++;
        }
    } catch (Throwable $exception) {
        $db->rollback();
        $failed++;
    }
}

echo "Processed {$sent} sent and {$failed} failed follow-ups.\n";

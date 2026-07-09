<?php

declare(strict_types=1);

require_once __DIR__ . '/../../db_conn.php';

function apiBindParams(mysqli_stmt $stmt, string $types, array &$values): void
{
    if ($types === '') {
        return;
    }

    $bind = [$types];
    foreach ($values as $index => $_) {
        $bind[] = &$values[$index];
    }

    call_user_func_array([$stmt, 'bind_param'], $bind);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiSupport::jsonResponse(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

$payload = ApiSupport::requestJson();
$requestedBizId = Security::intFrom($payload['biz_id'] ?? null);
$bizId = ApiSupport::requireBusinessApiKey($db, $requestedBizId);
$groupId = Security::intFrom($payload['group_id'] ?? null);
$rows = $payload['contacts'] ?? $payload['contact'] ?? $payload['rows'] ?? [];

if (!is_array($rows)) {
    $rows = [$rows];
}

if ($groupId > 0) {
    $groupStmt = $db->prepare('SELECT id FROM gd_groups WHERE id = ? AND biz_id = ? LIMIT 1');
    $groupStmt->bind_param('ii', $groupId, $bizId);
    $groupStmt->execute();
    $groupExists = $groupStmt->get_result()->fetch_assoc();
    if (!$groupExists) {
        ApiSupport::jsonResponse(['ok' => false, 'error' => 'group_id not found for this business.'], 404);
    }
}

$created = 0;
$updated = 0;
$skipped = 0;
$results = [];
$contactColumns = ApiSupport::tableColumns($db, 'gd_user_contacts');

foreach ($rows as $row) {
    if (!is_array($row)) {
        $skipped++;
        continue;
    }

    $fullName = trim((string) ($row['full_name'] ?? $row['name'] ?? $row['contact_name'] ?? ''));
    $phone = ApiSupport::normalizePhone((string) ($row['phone_number'] ?? $row['mobile_number'] ?? $row['phone'] ?? ''));
    $email = trim((string) ($row['email'] ?? ''));
    $leadStage = trim((string) ($row['lead_stage'] ?? 'lead')) ?: 'lead';
    $leadStatus = trim((string) ($row['lead_status'] ?? 'new')) ?: 'new';
    $source = trim((string) ($row['source'] ?? 'API Import')) ?: 'API Import';
    $lostReason = trim((string) ($row['lost_reason'] ?? ''));
    $notes = trim((string) ($row['notes'] ?? $row['crm_notes'] ?? ''));
    $whatsappOptIn = filter_var($row['whatsapp_opt_in'] ?? $row['opt_in'] ?? false, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    $nextFollowUpAt = null;

    if (!empty($row['next_follow_up_at'])) {
        try {
            $nextFollowUpAt = (new DateTimeImmutable((string) $row['next_follow_up_at']))->format('Y-m-d H:i:s');
        } catch (Throwable $exception) {
            $nextFollowUpAt = null;
        }
    }

    if ($phone === '') {
        $skipped++;
        $results[] = ['status' => 'skipped', 'reason' => 'Missing phone number.'];
        continue;
    }

    if ($fullName === '') {
        $fullName = 'Unnamed Contact';
    }

    $existingStmt = $db->prepare('SELECT id FROM gd_user_contacts WHERE biz_id = ? AND phone_number = ? LIMIT 1');
    $existingStmt->bind_param('is', $bizId, $phone);
    $existingStmt->execute();
    $existing = $existingStmt->get_result()->fetch_assoc();

    $status = $leadStatus;
    $now = date('Y-m-d H:i:s');

    $contactPayload = [
        'group_id' => [$groupId > 0 ? $groupId : null, 'i'],
        'full_name' => [$fullName, 's'],
        'phone_number' => [$phone, 's'],
        'email' => [$email, 's'],
        'status' => [$status, 's'],
        'lead_stage' => [$leadStage, 's'],
        'lead_status' => [$leadStatus, 's'],
        'source' => [$source, 's'],
        'whatsapp_opt_in' => [$whatsappOptIn ? 1 : 0, 'i'],
        'next_follow_up_at' => [$nextFollowUpAt, 's'],
        'lost_reason' => [$lostReason, 's'],
        'crm_notes' => [$notes, 's'],
        'last_contacted_at' => [$now, 's'],
    ];

    if ($existing) {
        $contactId = (int) $existing['id'];

        $assignments = [];
        $types = '';
        $values = [];
        foreach ($contactPayload as $column => [$value, $type]) {
            if (!in_array($column, $contactColumns, true)) {
                continue;
            }

            if ($column === 'group_id') {
                $assignments[] = '`group_id` = COALESCE(?, `group_id`)';
            } else {
                $assignments[] = '`' . $column . '` = ?';
            }
            $types .= $type;
            $values[] = $value;
        }

        if (in_array('updated_at', $contactColumns, true)) {
            $assignments[] = '`updated_at` = NOW()';
        }

        $types .= 'ii';
        $values[] = $contactId;
        $values[] = $bizId;

        $updateStmt = $db->prepare('UPDATE gd_user_contacts SET ' . implode(', ', $assignments) . ' WHERE id = ? AND biz_id = ?');
        apiBindParams($updateStmt, $types, $values);
        $updateStmt->execute();
        $updated++;
    } else {
        $insertColumns = ['biz_id'];
        $placeholders = ['?'];
        $types = 'i';
        $values = [$bizId];

        foreach ($contactPayload as $column => [$value, $type]) {
            if (!in_array($column, $contactColumns, true)) {
                continue;
            }

            $insertColumns[] = $column;
            $placeholders[] = '?';
            $types .= $type;
            $values[] = $value;
        }

        foreach (['created_at', 'updated_at'] as $timestampColumn) {
            if (in_array($timestampColumn, $contactColumns, true)) {
                $insertColumns[] = $timestampColumn;
                $placeholders[] = 'NOW()';
            }
        }

        $insertStmt = $db->prepare(
            'INSERT INTO gd_user_contacts (`' . implode('`, `', $insertColumns) . '`) VALUES (' . implode(', ', $placeholders) . ')'
        );
        apiBindParams($insertStmt, $types, $values);
        $insertStmt->execute();
        $contactId = (int) $insertStmt->insert_id;
        $created++;
    }

    if ($groupId > 0) {
        $linkStmt = $db->prepare('INSERT IGNORE INTO gd_group_contacts (biz_id, group_id, contact_id, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())');
        $linkStmt->bind_param('iii', $bizId, $groupId, $contactId);
        $linkStmt->execute();
    }

    $results[] = [
        'status' => $existing ? 'updated' : 'created',
        'phone_number' => $phone,
        'full_name' => $fullName,
    ];
}

ApiSupport::jsonResponse([
    'ok' => true,
    'biz_id' => $bizId,
    'group_id' => $groupId > 0 ? $groupId : null,
    'created' => $created,
    'updated' => $updated,
    'skipped' => $skipped,
    'total' => count($rows),
    'results' => $results,
], 200);

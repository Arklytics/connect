<?php

declare(strict_types=1);

require_once __DIR__ . '/../db_conn.php';

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$payload = ApiSupport::requestJson();
$requestedBizId = Security::intFrom($payload['biz_id'] ?? ($_GET['biz_id'] ?? null));
$bizId = ApiSupport::requireBusinessApiKey($db, $requestedBizId);

try {
    ApiSupport::ensureGroupHierarchyColumns($db);
} catch (Throwable $exception) {
    ApiSupport::jsonResponse([
        'ok' => false,
        'error' => 'Group hierarchy is not installed. Run migrations first.',
    ], 500);
}

if ($method === 'GET') {
    $stmt = $db->prepare(
        'SELECT g.id, g.parent_id, g.group_name, parent.group_name AS parent_name, COUNT(DISTINCT gc.contact_id) AS contacts_count
         FROM gd_groups g
         LEFT JOIN gd_groups parent ON parent.id = g.parent_id
         LEFT JOIN gd_group_contacts gc ON gc.group_id = g.id AND gc.biz_id = g.biz_id
         WHERE g.biz_id = ?
         GROUP BY g.id, g.parent_id, g.group_name, parent.group_name
         ORDER BY CASE WHEN g.parent_id IS NULL THEN g.id ELSE g.parent_id END DESC,
                  CASE WHEN g.parent_id IS NULL THEN 0 ELSE 1 END,
                  g.group_name'
    );
    $stmt->bind_param('i', $bizId);
    $stmt->execute();
    $result = $stmt->get_result();
    $groups = [];
    while ($row = $result->fetch_assoc()) {
        $groups[] = [
            'id' => (int) ($row['id'] ?? 0),
            'parent_id' => !empty($row['parent_id']) ? (int) $row['parent_id'] : null,
            'group_name' => (string) ($row['group_name'] ?? ''),
            'parent_name' => $row['parent_name'] !== null ? (string) $row['parent_name'] : null,
            'contacts_count' => (int) ($row['contacts_count'] ?? 0),
        ];
    }

    ApiSupport::jsonResponse([
        'ok' => true,
        'biz_id' => $bizId,
        'groups' => $groups,
    ], 200);
}

if ($method !== 'POST') {
    ApiSupport::jsonResponse(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

$groupName = trim((string) ($payload['group_name'] ?? $payload['name'] ?? ''));
$parentId = Security::intFrom($payload['parent_id'] ?? null);

if ($groupName === '') {
    ApiSupport::jsonResponse(['ok' => false, 'error' => 'group_name is required.'], 422);
}

if ($parentId > 0) {
    $parentStmt = $db->prepare('SELECT id FROM gd_groups WHERE id = ? AND biz_id = ? AND parent_id IS NULL LIMIT 1');
    $parentStmt->bind_param('ii', $parentId, $bizId);
    $parentStmt->execute();
    if (!$parentStmt->get_result()->fetch_assoc()) {
        ApiSupport::jsonResponse(['ok' => false, 'error' => 'parent_id must be an existing main group.'], 422);
    }
}

$parentValue = $parentId > 0 ? $parentId : null;
if ($parentValue === null) {
    $stmt = $db->prepare('INSERT INTO gd_groups (biz_id, parent_id, group_name, created_at, updated_at) VALUES (?, NULL, ?, NOW(), NOW())');
    $stmt->bind_param('is', $bizId, $groupName);
} else {
    $stmt = $db->prepare('INSERT INTO gd_groups (biz_id, parent_id, group_name, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())');
    $stmt->bind_param('iis', $bizId, $parentValue, $groupName);
}
$stmt->execute();
$groupId = (int) $stmt->insert_id;

ApiSupport::jsonResponse([
    'ok' => true,
    'biz_id' => $bizId,
    'group' => [
        'id' => $groupId,
        'parent_id' => $parentValue,
        'group_name' => $groupName,
        'type' => $parentValue ? 'subgroup' : 'group',
    ],
], 201);

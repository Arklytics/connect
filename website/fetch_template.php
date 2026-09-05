<?php
include '../db_conn.php';
Auth::requireLogin();

header('Content-Type: application/json; charset=utf-8');

function wgFetchTemplateMediaHandle(array $meta): string
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

if (isset($_GET['template_id'])) {
    $templateId = Security::intFrom($_GET['template_id']);
    $bizId = Auth::requireLogin();
    ApiSupport::ensureTemplateMediaTable($db);
    
    $stmt = $db->prepare('SELECT message_title, message_body, subtitle, media_url, placeholders, buttons FROM gd_whatsapp_templates WHERE id = ? AND biz_id = ? LIMIT 1');
    $stmt->bind_param('ii', $templateId, $bizId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && mysqli_num_rows($result) > 0) {
        $template = mysqli_fetch_assoc($result);

        // Decode buttons if stored as JSON
        $buttons = [];
        if (!empty($template['buttons'])) {
            $buttonsData = json_decode($template['buttons'], true); // Decode JSON
            if (json_last_error() === JSON_ERROR_NONE) {
                $buttons = $buttonsData; // Assign decoded buttons
            } else {
                $buttons = ['error' => 'Invalid buttons JSON'];
            }
        }
        $placeholderMeta = json_decode((string) ($template['placeholders'] ?? ''), true);
        $headerType = is_array($placeholderMeta) ? strtoupper((string) ($placeholderMeta['header_type'] ?? '')) : '';
        $mediaUrl = trim((string) ($template['media_url'] ?? ''));
        if ($mediaUrl === '' && is_array($placeholderMeta)) {
            $mediaUrl = trim((string) ($placeholderMeta['header_media_url'] ?? ''));
        }
        if ($mediaUrl === '' && is_array($placeholderMeta) && in_array($headerType, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)) {
            $mediaHandle = wgFetchTemplateMediaHandle($placeholderMeta);
            if ($mediaHandle !== '') {
                $mediaStmt = $db->prepare('SELECT s3_url FROM gd_template_media WHERE biz_id = ? AND media_handle = ? AND s3_url <> "" ORDER BY id DESC LIMIT 1');
                if ($mediaStmt) {
                    $mediaStmt->bind_param('is', $bizId, $mediaHandle);
                    $mediaStmt->execute();
                    $media = $mediaStmt->get_result()->fetch_assoc();
                    $mediaUrl = trim((string) ($media['s3_url'] ?? ''));
                }
            }
        }

        // Prepare the response
        echo json_encode([
            'message_title' => $template['message_title'],
            'message_body' => $template['message_body'],
            'subtitle' => $template['subtitle'],
            'media_url' => $mediaUrl,
            'header_type' => $headerType,
            'needs_media_url' => in_array($headerType, ['IMAGE', 'VIDEO', 'DOCUMENT'], true) && $mediaUrl === '',
            'variable_requirements' => ApiSupport::templateVariableRequirements($template),
            'buttons' => $buttons, // Include parsed buttons
        ]);
    } else {
        echo json_encode(['error' => 'Template not found']);
    }
} else {
    echo json_encode(['error' => 'Invalid request']);
}
?>

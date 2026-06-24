<?php
include '../db_conn.php';
Auth::requireLogin();

header('Content-Type: application/json; charset=utf-8');

if (isset($_GET['template_id'])) {
    $templateId = Security::intFrom($_GET['template_id']);
    $bizId = Auth::requireLogin();
    
    $stmt = $db->prepare('SELECT message_title, message_body, subtitle, media_url, buttons FROM gd_whatsapp_templates WHERE id = ? AND biz_id = ? LIMIT 1');
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

        // Prepare the response
        echo json_encode([
            'message_title' => $template['message_title'],
            'message_body' => $template['message_body'],
            'subtitle' => $template['subtitle'],
            'media_url' => $template['media_url'],
            'buttons' => $buttons, // Include parsed buttons
        ]);
    } else {
        echo json_encode(['error' => 'Template not found']);
    }
} else {
    echo json_encode(['error' => 'Invalid request']);
}
?>

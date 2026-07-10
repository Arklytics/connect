<?php
include '../session.php';
include '../db_conn.php'; // Database connection

$biz_id = Auth::requireLogin(); // Business ID

include 'header.php';

$templateSyncError = '';
$templateRows = [];

$whatsapp_business_id = '';
$access_token = '';

try {
    $orderStmt = $db->prepare('SELECT whatsapp_id, auth_token FROM gd_orders WHERE id = ? LIMIT 1');
    $orderStmt->bind_param('i', $biz_id);
    $orderStmt->execute();
    $get4 = $orderStmt->get_result()->fetch_assoc();
    $whatsapp_business_id = $get4['whatsapp_id'] ?? '';
    $access_token = ($get4['auth_token'] ?? '') ?: AppSettings::getGlobal($db, 'META_ACCESS_TOKEN', Config::get('META_ACCESS_TOKEN', ''));

    $stmt = $db->prepare('SELECT * FROM gd_whatsapp_templates WHERE biz_id = ? ORDER BY id DESC');
    $stmt->bind_param('i', $biz_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $templateRows[] = $row;
    }

    if ($whatsapp_business_id !== '' && $access_token !== '') {
        $url = "https://graph.facebook.com/v18.0/$whatsapp_business_id/message_templates";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $access_token",
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $apiResponse = json_decode((string) $response, true);
        curl_close($ch);

        if (isset($apiResponse['data']) && is_array($apiResponse['data'])) {
            foreach ($templateRows as $row) {
                $templateSid = $row['template_id'] ?? '';
                if ($templateSid === '') {
                    continue;
                }

                foreach ($apiResponse['data'] as $template) {
                    if (($template['name'] ?? '') === ($row['template_name'] ?? '')) {
                        $templateStatus = $template['status'] ?? '';
                        if ($templateStatus !== '') {
                            $updateStmt = $db->prepare('UPDATE gd_whatsapp_templates SET status = ? WHERE template_id = ? AND biz_id = ?');
                            $updateStmt->bind_param('ssi', $templateStatus, $templateSid, $biz_id);
                            $updateStmt->execute();
                        }
                    }
                }
            }
        }
    }
} catch (Throwable $exception) {
    $templateSyncError = 'Templates are temporarily unavailable while MySQL reconnects. Refresh in a moment.';
}
?>

<div class="container-fluid">
    <div class="row bg-light">
        <div class="col-lg-2 col-md-3 p-0 wg-sidebar">
            <?php include 'sidebar.php'; ?>
        </div>

        <div class="col-lg-10 col-md-9 wg-main">
            <h4 class="mt-3"><i class="bi bi-list-columns-reverse"></i> View Templates</h4>

            <?php if ($templateSyncError !== ''): ?>
                <div class="alert alert-warning"><?php echo h($templateSyncError); ?></div>
            <?php endif; ?>

            <table class="table table-striped mt-4">
                <thead class="table-dark">
                    <tr>
                        <th>Sno</th>
                        <th>Preview</th>
                        <th>Template Name</th>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (!empty($templateRows)) {
                        foreach ($templateRows as $i => $get3) {
                    ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td style="width: 320px;">
                            <?php
                            $mediaUrl = trim((string) ($get3['media_url'] ?? ''));
                            $placeholderMeta = json_decode((string) ($get3['placeholders'] ?? ''), true);
                            $headerType = is_array($placeholderMeta) ? strtoupper(trim((string) ($placeholderMeta['header_type'] ?? ''))) : '';
                            $mediaKind = ApiSupport::mediaKind('', $mediaUrl);
                            ?>
                            <div class="border rounded bg-white p-2" style="max-width: 300px;">
                                <?php if ($mediaUrl !== ''): ?>
                                    <div class="mb-2 text-center">
                                        <?php if ($headerType === 'VIDEO' || $mediaKind === 'video'): ?>
                                            <video src="<?php echo h($mediaUrl); ?>" controls style="width:100%; max-height:150px; border-radius:6px;"></video>
                                        <?php elseif ($headerType === 'DOCUMENT' || $mediaKind === 'document'): ?>
                                            <a class="btn btn-light btn-sm" href="<?php echo h($mediaUrl); ?>" target="_blank" rel="noopener">
                                                <i class="bi bi-file-earmark-text me-1"></i> Open document
                                            </a>
                                        <?php else: ?>
                                            <img src="<?php echo h($mediaUrl); ?>" alt="Template media" style="max-width:100%; max-height:150px; border-radius:6px;">
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="small fw-semibold"><?php echo h($get3['message_title']); ?></div>
                                <div class="small text-muted" style="white-space: pre-wrap;"><?php echo h($get3['message_body']); ?></div>
                                <?php if (!empty($get3['subtitle'])): ?>
                                    <div class="small text-secondary mt-1"><?php echo h($get3['subtitle']); ?></div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><?php echo h($get3['template_name']); ?></td>
                        <td><?php echo h($get3['message_title']); ?></td>
                        <td><?php echo h((string) ($get3['category'] ?: 'Marketing')); ?></td>
                        <td><?php echo h($get3['status']); ?></td>
                    </tr>
                    <?php
                        }
                    }
                    if (empty($templateRows)) {
                        echo "<tr><td colspan='6' class='text-center'>No templates found</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

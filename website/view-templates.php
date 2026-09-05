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

$templateTotal = count($templateRows);
$approvedTotal = 0;
$pendingTotal = 0;
$rejectedTotal = 0;
foreach ($templateRows as $templateRow) {
    $status = strtoupper(trim((string) ($templateRow['status'] ?? '')));
    if ($status === 'APPROVED') {
        $approvedTotal++;
    } elseif ($status === 'REJECTED') {
        $rejectedTotal++;
    } else {
        $pendingTotal++;
    }
}
?>

<div class="container-fluid wg-shell">
    <div class="row">
        <div class="col-lg-2 col-md-3 p-0 wg-sidebar">
            <?php include 'sidebar.php'; ?>
        </div>

        <div class="col-lg-10 col-md-9 wg-main">
            <div class="wg-page-title">
                <div>
                    <h1>Template Library</h1>
                    <p>Review saved WhatsApp templates, approval status, categories, and message previews.</p>
                </div>
                <a class="btn btn-success" href="<?php echo h(app_url('business/new-template')); ?>">
                    <i class="bi bi-plus-circle me-1"></i> New Template
                </a>
            </div>

            <?php if ($templateSyncError !== ''): ?>
                <div class="alert alert-warning"><?php echo h($templateSyncError); ?></div>
            <?php endif; ?>

            <div class="wg-template-metrics mb-4">
                <div><span><?php echo h((string) $templateTotal); ?></span><small>Total templates</small></div>
                <div><span><?php echo h((string) $approvedTotal); ?></span><small>Approved</small></div>
                <div><span><?php echo h((string) $pendingTotal); ?></span><small>Pending review</small></div>
                <div><span><?php echo h((string) $rejectedTotal); ?></span><small>Rejected</small></div>
            </div>

            <div class="wg-card p-0 overflow-hidden">
                <div class="wg-table-toolbar">
                    <div>
                        <h5>Saved Templates</h5>
                        <p>Synced with WhatsApp where credentials are available.</p>
                    </div>
                    <a class="btn btn-light btn-sm" href="<?php echo h(app_url('business/upload-media')); ?>">
                        <i class="bi bi-cloud-upload me-1"></i> Media Library
                    </a>
                </div>

                <?php if (empty($templateRows)): ?>
                    <div class="wg-empty-state">
                        <i class="bi bi-layout-text-window-reverse"></i>
                        <h5>No templates yet</h5>
                        <p>Create your first marketing, utility, or authentication template to start sending structured WhatsApp messages.</p>
                        <a class="btn btn-success" href="<?php echo h(app_url('business/new-template')); ?>">Create Template</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped wg-template-table">
                            <thead>
                                <tr>
                                    <th>Template</th>
                                    <th>Preview</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($templateRows as $get3): ?>
                                    <?php
                                    $mediaUrl = trim((string) ($get3['media_url'] ?? ''));
                                    $placeholderMeta = json_decode((string) ($get3['placeholders'] ?? ''), true);
                                    $headerType = is_array($placeholderMeta) ? strtoupper(trim((string) ($placeholderMeta['header_type'] ?? ''))) : '';
                                    $mediaKind = ApiSupport::mediaKind('', $mediaUrl);
                                    $category = strtoupper(trim((string) ($get3['category'] ?: 'MARKETING')));
                                    $status = strtoupper(trim((string) ($get3['status'] ?? 'PENDING')));
                                    $statusClass = $status === 'APPROVED' ? 'success' : ($status === 'REJECTED' ? 'danger' : 'warning');
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="wg-template-name"><?php echo h($get3['template_name']); ?></div>
                                            <div class="text-muted small"><?php echo h($get3['message_title']); ?></div>
                                        </td>
                                        <td>
                                            <div class="wg-template-card-preview">
                                                <?php if ($mediaUrl !== ''): ?>
                                                    <div class="wg-template-media">
                                                        <?php if ($headerType === 'VIDEO' || $mediaKind === 'video'): ?>
                                                            <video src="<?php echo h($mediaUrl); ?>" controls></video>
                                                        <?php elseif ($headerType === 'DOCUMENT' || $mediaKind === 'document'): ?>
                                                            <a class="btn btn-light btn-sm" href="<?php echo h($mediaUrl); ?>" target="_blank" rel="noopener">
                                                                <i class="bi bi-file-earmark-text me-1"></i> Open document
                                                            </a>
                                                        <?php else: ?>
                                                            <img src="<?php echo h($mediaUrl); ?>" alt="Template media">
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="wg-template-message"><?php echo h($get3['message_body']); ?></div>
                                                <?php if (!empty($get3['subtitle'])): ?>
                                                    <div class="wg-template-footer"><?php echo h($get3['subtitle']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td><span class="wg-pill"><?php echo h(ucfirst(strtolower($category))); ?></span></td>
                                        <td><span class="badge bg-<?php echo h($statusClass); ?>"><?php echo h(ucfirst(strtolower($status))); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<?php
include '../session.php';
include '../db_conn.php';

$biz_id = Auth::requireLogin();

include 'header.php';

$message = '';
$message_type = 'success';
$mediaHandle = '';
$previewUrl = '';
$fileName = '';
$fileType = '';
$fileSize = 0;
$uploadAttempt = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::verifyCsrf();

    $appId = AppSettings::getGlobal($db, 'META_APP_ID', Config::get('META_APP_ID', ''));

    $stmt = $db->prepare('SELECT auth_token FROM gd_orders WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $biz_id);
    $stmt->execute();
    $business = $stmt->get_result()->fetch_assoc();
    $accessToken = ($business['auth_token'] ?? '') ?: AppSettings::getGlobal($db, 'META_ACCESS_TOKEN', Config::get('META_ACCESS_TOKEN', ''));

    if ($appId === '') {
        $message = 'META_APP_ID is missing in .env. Add your Meta App ID, then retry.';
        $message_type = 'danger';
    } elseif (empty($_FILES['media_file']) || $_FILES['media_file']['error'] !== UPLOAD_ERR_OK) {
        $message = 'Please choose an image, video, or document file.';
        $message_type = 'danger';
    } else {
        $file = $_FILES['media_file'];
        $fileName = basename((string) $file['name']);
        $tmpPath = (string) $file['tmp_name'];
        $fileSize = (int) $file['size'];
        $fileType = mime_content_type($tmpPath) ?: (string) $file['type'];
        $allowedTypes = [
            'image/jpeg', 'image/png',
            'video/mp4', 'video/3gpp',
            'application/pdf',
        ];

        if (!in_array($fileType, $allowedTypes, true)) {
            $message = 'Unsupported file type. Use JPG, PNG, MP4, 3GP, or PDF.';
            $message_type = 'danger';
        } else {
            $uploadDir = __DIR__ . '/uploads/media/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0775, true);
            }

            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $safeName = bin2hex(random_bytes(12)) . ($extension !== '' ? '.' . $extension : '');
            $localPath = $uploadDir . $safeName;
            $localSaved = false;
            $localError = '';
            $metaError = '';
            $handleSourcePath = $tmpPath;

            if (is_dir($uploadDir) && is_writable($uploadDir) && move_uploaded_file($tmpPath, $localPath)) {
                $localSaved = true;
                $previewUrl = app_public_url('website/uploads/media/' . $safeName);
            } else {
                $localError = 'Could not save uploaded file locally. Upload directory may be missing or not writable: ' . $uploadDir;
            }

            $uploadResult = ApiSupport::metaUploadMediaHandle(
                $appId,
                $accessToken,
                $localSaved ? $localPath : $handleSourcePath,
                $fileName,
                $fileType,
                $fileSize
            );

            if (!($uploadResult['ok'] ?? false)) {
                $metaError = (string) ($uploadResult['error'] ?? 'Unknown error.');
            } else {
                $mediaHandle = (string) ($uploadResult['handle'] ?? '');
            }

            $uploadAttempt = [
                'file_name' => $fileName,
                'file_type' => $fileType,
                'file_size' => $fileSize,
                'local_saved' => $localSaved,
                'local_error' => $localError,
                'meta_ok' => ($uploadResult['ok'] ?? false) === true,
                'meta_error' => $metaError,
                'media_handle' => $mediaHandle,
                'preview_url' => $previewUrl,
            ];

            if ($localSaved && $mediaHandle !== '') {
                $message = 'Media uploaded. Copy the media handle into your template header.';
                $message_type = 'success';
            } elseif ($mediaHandle !== '') {
                $message = 'Media handle generated. Local file save failed, but WhatsApp upload still succeeded.';
                $message_type = 'warning';
            } else {
                $message = trim($localError . ' ' . $metaError);
                $message_type = 'danger';
            }
        }
    }
}
?>

<div class="position-fixed top-0 end-0 p-3" style="z-index: 5;">
    <?php if ($message !== ''): ?>
        <div class="toast align-items-center text-bg-<?php echo h($message_type); ?> border-0 show" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body"><?php echo h($message); ?></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-2 col-md-3 p-0 wg-sidebar">
            <?php include 'sidebar.php'; ?>
        </div>

        <main class="col-lg-10 col-md-9 wg-main">
            <h4><i class="bi bi-cloud-upload"></i> Upload Template Media</h4>

            <form action="" method="post" enctype="multipart/form-data">
                <?php echo Security::csrfField(); ?>
                <div class="row">
                    <div class="col-md-8">
                        <label class="form-label" for="media_file">Image, Video, or Document</label>
                        <input type="file" name="media_file" id="media_file" class="form-control" accept=".jpg,.jpeg,.png,.mp4,.3gp,.pdf,image/jpeg,image/png,video/mp4,video/3gpp,application/pdf" required>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-cloud-upload me-1"></i> Generate Handle</button>
                    </div>
                </div>
                <p class="text-muted mb-0">Use this for template headers. The preview URL is only for your app preview; WhatsApp needs the media handle.</p>
            </form>

            <?php if ($mediaHandle !== '' || $previewUrl !== ''): ?>
                <div class="wg-card p-4 mt-4">
                    <h5 class="mb-3">Upload Result</h5>
                    <?php if ($mediaHandle !== ''): ?>
                        <label class="form-label">Media Handle</label>
                        <div class="input-group mb-3">
                            <input type="text" class="form-control" id="mediaHandle" value="<?php echo h($mediaHandle); ?>" readonly>
                            <button type="button" class="btn btn-light" onclick="copyValue('mediaHandle')"><i class="bi bi-clipboard"></i></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($previewUrl !== ''): ?>
                        <label class="form-label">Preview URL</label>
                        <div class="input-group mb-3">
                            <input type="text" class="form-control" id="previewUrl" value="<?php echo h($previewUrl); ?>" readonly>
                            <button type="button" class="btn btn-light" onclick="copyValue('previewUrl')"><i class="bi bi-clipboard"></i></button>
                        </div>
                    <?php endif; ?>

                    <p class="text-muted mb-0">
                        File: <?php echo h($fileName); ?> |
                        Type: <?php echo h($fileType); ?> |
                        Size: <?php echo h((string) round($fileSize / 1024, 2)); ?> KB
                    </p>
                </div>
            <?php endif; ?>

            <?php if (is_array($uploadAttempt)): ?>
                <div class="wg-card p-4 mt-4">
                    <h5 class="mb-3">Latest Upload Status</h5>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped align-middle mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>File</th>
                                    <th>Local Save</th>
                                    <th>Meta Handle</th>
                                    <th>Error</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><?php echo h((string) $uploadAttempt['file_name']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $uploadAttempt['local_saved'] ? 'success' : 'danger'; ?>">
                                            <?php echo $uploadAttempt['local_saved'] ? 'Saved' : 'Failed'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $uploadAttempt['meta_ok'] ? 'success' : 'danger'; ?>">
                                            <?php echo $uploadAttempt['meta_ok'] ? 'Generated' : 'Failed'; ?>
                                        </span>
                                        <?php if (!empty($uploadAttempt['media_handle'])): ?>
                                            <div class="small text-break mt-1"><?php echo h((string) $uploadAttempt['media_handle']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-danger">
                                        <?php echo h(trim((string) $uploadAttempt['local_error'] . ' ' . (string) $uploadAttempt['meta_error'])); ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<script>
function copyValue(id) {
  const input = document.getElementById(id);
  input.select();
  input.setSelectionRange(0, 99999);
  navigator.clipboard.writeText(input.value);
}
</script>

<?php include 'footer.php'; ?>

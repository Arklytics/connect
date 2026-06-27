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
                mkdir($uploadDir, 0775, true);
            }

            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $safeName = bin2hex(random_bytes(12)) . ($extension !== '' ? '.' . $extension : '');
            $localPath = $uploadDir . $safeName;

            if (move_uploaded_file($tmpPath, $localPath)) {
                $previewUrl = app_public_url('website/uploads/media/' . $safeName);
                $uploadResult = ApiSupport::metaUploadMediaHandle(
                    $appId,
                    $accessToken,
                    $localPath,
                    $fileName,
                    $fileType,
                    $fileSize
                );

                if (!($uploadResult['ok'] ?? false)) {
                    $apiError = (string) ($uploadResult['error'] ?? 'Unknown error.');
                    $message = 'Media saved locally, but handle generation failed: ' . $apiError;
                    $message_type = 'danger';
                } else {
                    $mediaHandle = (string) ($uploadResult['handle'] ?? '');
                    $message = 'Media uploaded. Copy the media handle into your template header.';
                    $message_type = 'success';
                }
            } else {
                $message = 'Could not save uploaded file locally.';
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

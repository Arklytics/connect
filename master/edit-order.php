<?php
include 'db_conn.php';
include 'session.php';
include 'header.php';

$message = '';
$message_type = 'success';
$masterId = Auth::requireMaster();
$businessId = Security::intFrom($_GET['id'] ?? null);

if ($businessId <= 0) {
    header('Location: ' . app_url('master/view-orders'));
    exit();
}

function gdMasterUploadedBusinessLogo(array $file, string &$message, string &$messageType): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        $message = 'Error uploading logo.';
        $messageType = 'danger';
        return '';
    }

    $fileType = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($fileType, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
        $message = 'Only image files are allowed.';
        $messageType = 'danger';
        return '';
    }

    $targetDir = __DIR__ . '/uploads/';
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    $safeName = bin2hex(random_bytes(16)) . '.' . $fileType;
    $targetFile = $targetDir . $safeName;

    if (!move_uploaded_file((string) ($file['tmp_name'] ?? ''), $targetFile)) {
        $message = 'Error uploading logo.';
        $messageType = 'danger';
        return '';
    }

    return 'uploads/' . $safeName;
}

function gdMasterLoadBusiness(mysqli $db, int $businessId, int $masterId): ?array
{
    $stmt = $db->prepare(
        'SELECT id, full_name, mobile_number, email, business_name, business_number, business_email,
                business_location, business_description, business_logo, status
           FROM gd_orders
          WHERE id = ? AND admin_id = ?
          LIMIT 1'
    );
    $stmt->bind_param('ii', $businessId, $masterId);
    $stmt->execute();
    $business = $stmt->get_result()->fetch_assoc();

    return is_array($business) ? $business : null;
}

$business = gdMasterLoadBusiness($db, $businessId, $masterId);

if (!$business) {
    header('Location: ' . app_url('master/view-orders'));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::verifyCsrf();

    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $mobileNumber = preg_replace('/\D+/', '', (string) ($_POST['mobile_number'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $businessName = trim((string) ($_POST['business_name'] ?? ''));
    $businessNumber = preg_replace('/\D+/', '', (string) ($_POST['business_number'] ?? ''));
    $businessEmail = trim((string) ($_POST['business_email'] ?? ''));
    $businessLocation = trim((string) ($_POST['business_location'] ?? ''));
    $businessDescription = trim((string) ($_POST['business_description'] ?? ''));
    $status = in_array((string) ($_POST['status'] ?? '0'), ['0', '1'], true) ? (string) $_POST['status'] : '0';

    if ($fullName === '' || $mobileNumber === '' || $businessName === '') {
        $message = 'Full name, mobile number, and business name are required.';
        $message_type = 'danger';
    } else {
        try {
            $logoPath = (string) ($business['business_logo'] ?? '');
            $uploadedLogo = isset($_FILES['business_logo'])
                ? gdMasterUploadedBusinessLogo($_FILES['business_logo'], $message, $message_type)
                : '';

            if ($message_type !== 'danger' && $uploadedLogo !== '') {
                if ($logoPath !== '') {
                    $oldLogoPath = __DIR__ . '/' . ltrim($logoPath, '/');
                    if (is_file($oldLogoPath)) {
                        @unlink($oldLogoPath);
                    }
                }
                $logoPath = $uploadedLogo;
            }

            if ($message_type !== 'danger') {
                if ($password !== '') {
                    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $db->prepare(
                        'UPDATE gd_orders
                            SET full_name = ?, mobile_number = ?, email = ?, password = ?, business_name = ?,
                                business_number = ?, business_email = ?, business_location = ?,
                                business_description = ?, business_logo = ?, status = ?
                          WHERE id = ? AND admin_id = ?'
                    );
                    $stmt->bind_param(
                        'sssssssssssii',
                        $fullName,
                        $mobileNumber,
                        $email,
                        $passwordHash,
                        $businessName,
                        $businessNumber,
                        $businessEmail,
                        $businessLocation,
                        $businessDescription,
                        $logoPath,
                        $status,
                        $businessId,
                        $masterId
                    );
                } else {
                    $stmt = $db->prepare(
                        'UPDATE gd_orders
                            SET full_name = ?, mobile_number = ?, email = ?, business_name = ?,
                                business_number = ?, business_email = ?, business_location = ?,
                                business_description = ?, business_logo = ?, status = ?
                          WHERE id = ? AND admin_id = ?'
                    );
                    $stmt->bind_param(
                        'ssssssssssii',
                        $fullName,
                        $mobileNumber,
                        $email,
                        $businessName,
                        $businessNumber,
                        $businessEmail,
                        $businessLocation,
                        $businessDescription,
                        $logoPath,
                        $status,
                        $businessId,
                        $masterId
                    );
                }

                $stmt->execute();
                $message = 'Business updated successfully.';
                $message_type = 'success';
                $business = gdMasterLoadBusiness($db, $businessId, $masterId) ?? $business;
            }
        } catch (Throwable $exception) {
            error_log('Business update failed: ' . $exception->getMessage());
            $message = 'Unable to update business right now: ' . $exception->getMessage();
            $message_type = 'danger';
        }
    }
}
?>

<div class="container-fluid wg-shell">
    <div class="row bg-light">
        <div class="col-lg-2 col-md-3 p-0 wg-sidebar">
            <?php include 'sidebar.php'; ?>
        </div>

        <div class="col-lg-10 col-md-9 wg-main">
            <?php if ($message !== ''): ?>
                <div class="alert alert-<?php echo h($message_type); ?> mt-3"><?php echo h($message); ?></div>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center mt-3">
                <h4 class="mb-0"><i class="bi bi-pencil-square"></i> Edit Business</h4>
                <a href="<?php echo h(app_url('master/view-orders')); ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>

            <form action="<?php echo h(app_url('master/edit-order?id=' . $businessId)); ?>" method="POST" enctype="multipart/form-data" class="mt-3">
                <?php echo Security::csrfField(); ?>

                <div class="row bg-light mt-2">
                    <div class="col-md-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-control p-2 shadow" name="full_name" required value="<?php echo h($business['full_name'] ?? ''); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Mobile Number</label>
                        <input type="number" class="form-control p-2 shadow" name="mobile_number" required value="<?php echo h($business['mobile_number'] ?? ''); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control p-2 shadow" name="email" value="<?php echo h($business['email'] ?? ''); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">New Password</label>
                        <input type="password" class="form-control p-2 shadow" name="password" placeholder="Leave blank to keep current">
                    </div>
                </div>

                <div class="row bg-light mt-2">
                    <div class="col-md-3">
                        <label class="form-label">Business Name</label>
                        <input type="text" class="form-control p-2 shadow" name="business_name" required value="<?php echo h($business['business_name'] ?? ''); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Business Number</label>
                        <input type="number" class="form-control p-2 shadow" name="business_number" value="<?php echo h($business['business_number'] ?? ''); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Business Email</label>
                        <input type="email" class="form-control p-2 shadow" name="business_email" value="<?php echo h($business['business_email'] ?? ''); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Location</label>
                        <input type="text" class="form-control p-2 shadow" name="business_location" value="<?php echo h($business['business_location'] ?? ''); ?>">
                    </div>
                </div>

                <div class="row bg-light mt-2">
                    <div class="col-md-9">
                        <label class="form-label">About Business</label>
                        <textarea class="form-control shadow rounded" name="business_description" rows="5"><?php echo h($business['business_description'] ?? ''); ?></textarea>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select class="form-control shadow" name="status">
                            <option value="0" <?php echo (string) ($business['status'] ?? '0') === '0' ? 'selected' : ''; ?>>In-active</option>
                            <option value="1" <?php echo (string) ($business['status'] ?? '0') === '1' ? 'selected' : ''; ?>>Activated</option>
                        </select>
                    </div>
                </div>

                <div class="row bg-light mt-2">
                    <div class="col-md-4">
                        <label class="form-label">Business Logo</label>
                        <input type="file" class="form-control" name="business_logo" accept="image/*">
                        <?php if (!empty($business['business_logo'])): ?>
                            <p class="small mt-2 mb-0">Current: <?php echo h($business['business_logo']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <button class="btn btn-success mt-3" type="submit">
                    <i class="bi bi-check2-circle me-1"></i> Update Business
                </button>
            </form>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

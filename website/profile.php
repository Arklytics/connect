<?php
declare(strict_types=1);

include '../session.php';
include '../db_conn.php';

$bizId = Auth::requireLogin();

include 'header.php';
$profileError = '';
$passwordMessage = '';
$passwordMessageType = 'success';
$db = Database::connectOrNull();
$profile = [];

if (!$db) {
    $profileError = 'Profile details could not be loaded because MySQL is not responding. Start MySQL in XAMPP, then refresh.';
} else {
    try {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_password') {
            Security::verifyCsrf();

            $currentPassword = (string) ($_POST['current_password'] ?? '');
            $newPassword = (string) ($_POST['new_password'] ?? '');
            $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

            if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
                $passwordMessage = 'Please fill all password fields.';
                $passwordMessageType = 'danger';
            } elseif (strlen($newPassword) < 6) {
                $passwordMessage = 'New password must be at least 6 characters.';
                $passwordMessageType = 'danger';
            } elseif (!hash_equals($newPassword, $confirmPassword)) {
                $passwordMessage = 'New password and confirmation do not match.';
                $passwordMessageType = 'danger';
            } else {
                $stmt = $db->prepare('SELECT password FROM gd_orders WHERE id = ? LIMIT 1');
                $stmt->bind_param('i', $bizId);
                $stmt->execute();
                $account = $stmt->get_result()->fetch_assoc();
                $storedPassword = (string) ($account['password'] ?? '');

                if ($storedPassword === '' || !password_verify($currentPassword, $storedPassword)) {
                    $passwordMessage = 'Current password is incorrect.';
                    $passwordMessageType = 'danger';
                } else {
                    $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
                    $stmt = $db->prepare('UPDATE gd_orders SET password = ? WHERE id = ?');
                    $stmt->bind_param('si', $passwordHash, $bizId);
                    $stmt->execute();

                    $passwordMessage = 'Password updated successfully.';
                    $passwordMessageType = 'success';
                }
            }
        }

        $stmt = $db->prepare('
            SELECT full_name, mobile_number, email, business_name, business_number, business_email,
                   business_location, business_description, business_logo, status,
                   whatsapp_id, phone_number_id, webhook_url
            FROM gd_orders
            WHERE id = ? LIMIT 1
        ');
        $stmt->bind_param('i', $bizId);
        $stmt->execute();
        $profile = $stmt->get_result()->fetch_assoc() ?: [];
    } catch (mysqli_sql_exception $exception) {
        $profileError = 'Profile details could not be loaded right now. Restart MySQL in XAMPP and try again.';
    }
}

$isConnected = (($profile['status'] ?? '0') == '1');
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-2 col-md-3 p-0 wg-sidebar">
            <?php include 'sidebar.php'; ?>
        </div>

        <main class="col-lg-10 col-md-9 wg-main">
            <div class="wg-page-title">
                <h1>Profile</h1>
                <p>Your workspace details, connection status, and WhatsApp setup at a glance.</p>
            </div>

            <?php if ($profileError !== ''): ?>
                <div class="alert alert-warning"><?php echo h($profileError); ?></div>
            <?php endif; ?>

            <?php if ($passwordMessage !== ''): ?>
                <div class="alert alert-<?php echo h($passwordMessageType); ?>"><?php echo h($passwordMessage); ?></div>
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-xl-4">
                    <div class="wg-card p-4 h-100">
                        <h5 class="mb-3">Account Status</h5>
                        <?php if (!$isConnected && (!empty($profile['whatsapp_id']) || !empty($profile['phone_number_id']))): ?>
                            <div class="alert alert-warning py-2">WhatsApp IDs are saved, but the connection is not fully activated yet.</div>
                        <?php endif; ?>
                        <?php if (!empty($profile['business_logo'])): ?>
                            <div class="mb-3">
 <img src="<?php echo h(app_url(ltrim((string) $profile['business_logo'], '/'))); ?>" alt="Business Logo" class="img-fluid rounded" style="max-height: 120px;">
                            </div>
                        <?php endif; ?>
                        <p class="mb-2"><strong>Business:</strong> <?php echo h($profile['business_name'] ?? ''); ?></p>
                        <p class="mb-2"><strong>Connection:</strong> <?php echo $isConnected ? 'Connected to WhatsApp' : 'Not connected yet'; ?></p>
                        <p class="mb-2"><strong>WhatsApp Business ID:</strong> <?php echo h(!empty($profile['whatsapp_id']) ? $profile['whatsapp_id'] : 'Not connected'); ?></p>
                        <p class="mb-2"><strong>Phone Number ID:</strong> <?php echo h(!empty($profile['phone_number_id']) ? $profile['phone_number_id'] : 'Not connected'); ?></p>
                        <p class="mb-0"><strong>Webhook URL:</strong> <?php echo h(!empty($profile['webhook_url']) ? $profile['webhook_url'] : app_public_url('incoming.php')); ?></p>
                    </div>
                </div>

                <div class="col-xl-8">
                    <div class="wg-card p-4 h-100">
                        <h5 class="mb-3">Business Details</h5>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="text-muted small">Full Name</div>
                                <div><?php echo h($profile['full_name'] ?? ''); ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small">Mobile Number</div>
                                <div><?php echo h($profile['mobile_number'] ?? ''); ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small">Email</div>
                                <div><?php echo h(!empty($profile['email']) ? $profile['email'] : 'Not provided'); ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small">Business Number</div>
                                <div><?php echo h(!empty($profile['business_number']) ? $profile['business_number'] : 'Not provided'); ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small">Business Email</div>
                                <div><?php echo h(!empty($profile['business_email']) ? $profile['business_email'] : 'Not provided'); ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small">Location</div>
                                <div><?php echo h(!empty($profile['business_location']) ? $profile['business_location'] : 'Not provided'); ?></div>
                            </div>
                            <div class="col-12">
                                <div class="text-muted small">Description</div>
                                <div><?php echo h(!empty($profile['business_description']) ? $profile['business_description'] : 'No description added yet.'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="wg-card p-4 mt-3">
                <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
                    <div>
                        <h5 class="mb-1">Settings</h5>
                        <p class="text-muted mb-0">Use this area for WhatsApp connection, billing, and future account settings.</p>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
 <a href="<?php echo h(app_url('business/connect-whatsapp')); ?>" class="btn btn-success">
                            <i class="bi bi-whatsapp me-1"></i> WhatsApp Connection
                        </a>
                        <button type="button" class="btn btn-outline-secondary" disabled>
                            <i class="bi bi-credit-card me-1"></i> Payment Settings Soon
                        </button>
                    </div>
                </div>
            </div>

            <div class="wg-card p-4 mt-3">
                <h5 class="mb-3">Change Password</h5>
                <form method="post" class="row g-3">
                    <?php echo Security::csrfField(); ?>
                    <input type="hidden" name="action" value="update_password">

                    <div class="col-md-4">
                        <label class="form-label" for="current_password">Current Password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="new_password">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" minlength="6" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="confirm_password">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="6" required>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-shield-lock me-1"></i> Update Password
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</div>

<?php include 'footer.php'; ?>

<?php
declare(strict_types=1);

include '../session.php';
include '../db_conn.php';
include 'header.php';

$bizId = Auth::requireLogin();
$profileError = '';
$db = Database::connectOrNull();
$profile = [];

if (!$db) {
    $profileError = 'Profile details could not be loaded because MySQL is not responding. Start MySQL in XAMPP, then refresh.';
} else {
    try {
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

            <div class="row g-3">
                <div class="col-xl-4">
                    <div class="wg-card p-4 h-100">
                        <h5 class="mb-3">Account Status</h5>
                        <?php if (!$isConnected && (!empty($profile['whatsapp_id']) || !empty($profile['phone_number_id']))): ?>
                            <div class="alert alert-warning py-2">WhatsApp IDs are saved, but the connection is not fully activated yet.</div>
                        <?php endif; ?>
                        <?php if (!empty($profile['business_logo'])): ?>
                            <div class="mb-3">
                                <img src="/wpi2/<?php echo h($profile['business_logo']); ?>" alt="Business Logo" class="img-fluid rounded" style="max-height: 120px;">
                            </div>
                        <?php endif; ?>
                        <p class="mb-2"><strong>Business:</strong> <?php echo h($profile['business_name'] ?? ''); ?></p>
                        <p class="mb-2"><strong>Connection:</strong> <?php echo $isConnected ? 'Connected to WhatsApp' : 'Not connected yet'; ?></p>
                        <p class="mb-2"><strong>WhatsApp Business ID:</strong> <?php echo h(!empty($profile['whatsapp_id']) ? $profile['whatsapp_id'] : 'Not connected'); ?></p>
                        <p class="mb-2"><strong>Phone Number ID:</strong> <?php echo h(!empty($profile['phone_number_id']) ? $profile['phone_number_id'] : 'Not connected'); ?></p>
                        <p class="mb-0"><strong>Webhook URL:</strong> <?php echo h(!empty($profile['webhook_url']) ? $profile['webhook_url'] : 'Not set'); ?></p>
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
                        <a href="/wpi2/business/connect-whatsapp" class="btn btn-success">
                            <i class="bi bi-whatsapp me-1"></i> WhatsApp Connection
                        </a>
                        <button type="button" class="btn btn-outline-secondary" disabled>
                            <i class="bi bi-credit-card me-1"></i> Payment Settings Soon
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include 'footer.php'; ?>

<?php
include 'session.php';
include 'db_conn.php';

$master_id = Auth::requireMaster();

include 'header.php';
$orderCount = 0;
$templateCount = 0;
$activeTokenCount = 0;
$activeBusinessCount = 0;
$dashboardError = '';

try {
    $stmt = $db->prepare('SELECT COUNT(*) AS total FROM gd_orders WHERE admin_id = ?');
    $stmt->bind_param('i', $master_id);
    $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc()) {
        $orderCount = (int) $row['total'];
    }

    $stmt = $db->prepare("SELECT COUNT(*) AS total FROM gd_orders WHERE admin_id = ? AND status = '1'");
    $stmt->bind_param('i', $master_id);
    $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc()) {
        $activeBusinessCount = (int) $row['total'];
    }

    $stmt = $db->prepare("SELECT COUNT(*) AS total FROM gd_orders WHERE admin_id = ? AND auth_token <> ''");
    $stmt->bind_param('i', $master_id);
    $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc()) {
        $activeTokenCount = (int) $row['total'];
    }

    $stmt = $db->prepare('SELECT COUNT(*) AS total
        FROM gd_whatsapp_templates t
        INNER JOIN gd_orders o ON o.id = t.biz_id
        WHERE o.admin_id = ?');
    $stmt->bind_param('i', $master_id);
    $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc()) {
        $templateCount = (int) $row['total'];
    }
} catch (mysqli_sql_exception $exception) {
    $dashboardError = 'Dashboard data could not be loaded right now. Restart MySQL in XAMPP and refresh this page.';
}
?>

<div class="container-fluid wg-shell">
    <div class="row">
        <div class="col-lg-2 col-md-3 p-0 wg-sidebar">
            <?php include 'sidebar.php'; ?>
        </div>

        <main class="col-lg-10 col-md-9 wg-main">
            <div class="wg-page-title">
                <h1>Master Dashboard</h1>
                <p>Monitor platform setup, orders, templates, and message operations.</p>
            </div>

            <?php if ($dashboardError !== ''): ?>
                <div class="alert alert-danger"><?php echo h($dashboardError); ?></div>
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-xl-3 col-md-6">
                    <div class="wg-card wg-stat-card">
                        <span class="icon"><i class="bi bi-bag-check"></i></span>
                        <div class="label">Orders</div>
                        <p class="value"><?php echo h((string) $orderCount); ?></p>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="wg-card wg-stat-card">
                        <span class="icon"><i class="bi bi-layout-text-window"></i></span>
                        <div class="label">Templates</div>
                        <p class="value"><?php echo h((string) $templateCount); ?></p>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="wg-card wg-stat-card">
                        <span class="icon"><i class="bi bi-key"></i></span>
                        <div class="label">API Settings</div>
                        <p class="value"><?php echo h((string) $activeTokenCount); ?></p>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="wg-card wg-stat-card">
                        <span class="icon"><i class="bi bi-shield-check"></i></span>
                        <div class="label">Active Businesses</div>
                        <p class="value"><?php echo h((string) $activeBusinessCount); ?></p>
                    </div>
                </div>
            </div>

            <div class="wg-card p-4 mt-4">
                <h5 class="mb-2">Operational Overview</h5>
                <p class="text-muted mb-0">Use the sidebar to create orders, review templates, configure tokens, and open reports.</p>
            </div>

            <div class="mt-4">
                <div class="wg-page-title mb-3">
                    <h1 style="font-size: 20px;">Quick Actions</h1>
                </div>
                <div class="wg-action-grid">
 <a class="wg-card wg-action-card" href="<?php echo h(app_url('master/new-order')); ?>">
                        <i class="bi bi-plus-circle"></i>
                        <span><strong>New Order</strong><span>Create business access</span></span>
                    </a>
 <a class="wg-card wg-action-card" href="<?php echo h(app_url('master/view-orders')); ?>">
                        <i class="bi bi-table"></i>
                        <span><strong>View Orders</strong><span>Review businesses</span></span>
                    </a>
 <a class="wg-card wg-action-card" href="<?php echo h(app_url('master/new-templates')); ?>">
                        <i class="bi bi-files"></i>
                        <span><strong>Templates</strong><span>Manage approvals</span></span>
                    </a>
 <a class="wg-card wg-action-card" href="<?php echo h(app_url('master/setting-token')); ?>">
                        <i class="bi bi-key"></i>
                        <span><strong>API Settings</strong><span>Connect WhatsApp and internal APIs</span></span>
                    </a>
 <a class="wg-card wg-action-card" href="<?php echo h(app_url('master/packages')); ?>">
                        <i class="bi bi-box-seam"></i>
                        <span><strong>Packages</strong><span>Assign plans to businesses</span></span>
                    </a>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include 'footer.php'; ?>

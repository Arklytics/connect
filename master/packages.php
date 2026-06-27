<?php
include 'session.php';
include 'header.php';

$master_id = Auth::requireMaster();
$db = Database::connectOrNull();
$message = '';
$message_type = 'success';
$packages = [
    'starter' => ['label' => 'Starter', 'limit' => 1000, 'price' => '0'],
    'growth' => ['label' => 'Growth', 'limit' => 5000, 'price' => '0'],
    'pro' => ['label' => 'Pro', 'limit' => 15000, 'price' => '0'],
];
$businesses = [];
$packageRequests = [];
$loadError = '';

function gdMasterColumns(mysqli $db, string $table): array
{
    $stmt = $db->prepare("SHOW COLUMNS FROM `$table`");
    $stmt->execute();
    $result = $stmt->get_result();
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    return $columns;
}

function gdMasterTableExists(mysqli $db, string $table): bool
{
    $stmt = $db->prepare(
        'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1'
    );
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $result = $stmt->get_result();

    return (bool) $result && $result->num_rows > 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::verifyCsrf();

    if (!$db) {
        $message = 'Database is not responding. Restart MySQL, then try again.';
        $message_type = 'danger';
    } else {
        $businessId = Security::intFrom($_POST['business_id'] ?? null);
        $packageKey = strtolower(trim((string) ($_POST['package_key'] ?? 'starter')));
        $customLimit = Security::intFrom($_POST['custom_message_limit'] ?? null);
        $packagePrice = trim((string) ($_POST['package_price'] ?? ''));
        $packageDays = max(1, Security::intFrom($_POST['package_days'] ?? 30));
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $package = $packages[$packageKey] ?? $packages['starter'];
        $limit = $customLimit > 0 ? $customLimit : (int) $package['limit'];

        $columns = gdMasterColumns($db, 'gd_orders');
        if (!in_array('package_name', $columns, true)) {
            $message = 'Run the package migration first, then refresh this page.';
            $message_type = 'warning';
        } elseif ($businessId <= 0) {
            $message = 'Please select a business.';
            $message_type = 'warning';
        } else {
            $updates = [
                'package_name' => $package['label'],
                'message_limit' => $limit,
                'messages_used' => 0,
                'package_started_at' => date('Y-m-d H:i:s'),
                'package_ends_at' => date('Y-m-d H:i:s', strtotime('+' . $packageDays . ' days')),
                'limit_request_status' => 'approved',
                'limit_request_note' => '',
                'limit_request_at' => date('Y-m-d H:i:s'),
            ];

            if ($packagePrice !== '' && in_array('package_price', $columns, true)) {
                $updates['package_price'] = $packagePrice;
            }

            $setParts = [];
            $types = '';
            $values = [];
            foreach ($updates as $column => $value) {
                $setParts[] = "`{$column}` = ?";
                $types .= is_int($value) ? 'i' : 's';
                $values[] = $value;
            }

            $sql = 'UPDATE gd_orders SET ' . implode(', ', $setParts) . ' WHERE id = ? AND admin_id = ?';
            $stmt = $db->prepare($sql);
            $types .= 'ii';
            $values[] = $businessId;
            $values[] = $master_id;
            $bind = [$types];
            foreach ($values as $i => $value) {
                $bind[] = &$values[$i];
            }
            call_user_func_array([$stmt, 'bind_param'], $bind);
            if ($stmt->execute()) {
                $message = 'Package updated successfully.';
                $message_type = 'success';
            } else {
                $message = 'Unable to update package.';
                $message_type = 'danger';
            }
        }
    }
}

if ($db) {
    try {
        $stmt = $db->prepare('SELECT id, business_name, package_name, message_limit, messages_used, package_ends_at FROM gd_orders WHERE admin_id = ? ORDER BY id DESC');
        $stmt->bind_param('i', $master_id);
        $stmt->execute();
        $businesses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        if (gdMasterTableExists($db, 'gd_package_requests')) {
            $stmt = $db->prepare('SELECT * FROM gd_package_requests ORDER BY id DESC');
            $stmt->execute();
            $packageRequests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
    } catch (mysqli_sql_exception $exception) {
        $loadError = 'Package data could not be loaded right now. Restart MySQL/Apache and refresh.';
    }
} else {
    $loadError = 'Database is not responding. Restart MySQL in XAMPP, then refresh this page.';
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

<div class="container-fluid wg-shell">
    <div class="row">
        <div class="col-lg-2 col-md-3 p-0 wg-sidebar">
            <?php include 'sidebar.php'; ?>
        </div>

        <div class="col-lg-10 col-md-9 wg-main">
            <div class="wg-page-title">
                <h1>Packages</h1>
                <p>Assign Starter, Growth, or Pro plans to businesses.</p>
            </div>

            <?php if ($loadError !== ''): ?>
                <div class="alert alert-warning"><?php echo h($loadError); ?></div>
            <?php endif; ?>

            <div class="wg-card p-4 mb-4">
                <form method="post">
                    <?php echo Security::csrfField(); ?>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Business</label>
                            <select name="business_id" class="form-control" required>
                                <option value="">--Select Business--</option>
                                <?php foreach ($businesses as $business): ?>
                                    <option value="<?php echo h((string) $business['id']); ?>"><?php echo h($business['business_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Package</label>
                            <select name="package_key" class="form-control" required>
                                <?php foreach ($packages as $key => $package): ?>
                                    <option value="<?php echo h($key); ?>"><?php echo h($package['label']); ?> (<?php echo h(number_format((int) $package['limit'])); ?> messages)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Duration Days</label>
                            <input type="number" name="package_days" class="form-control" min="1" value="30">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Custom Limit</label>
                            <input type="number" name="custom_message_limit" class="form-control" min="1" placeholder="Optional custom limit">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Package Price</label>
                            <input type="text" name="package_price" class="form-control" placeholder="Optional price">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Reason</label>
                            <textarea name="reason" class="form-control" rows="2" placeholder="Optional note"></textarea>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-success"><i class="bi bi-box-seam me-1"></i> Assign Package</button>
                    </div>
                </form>
            </div>

            <div class="wg-card p-4 mb-4">
                <h5 class="mb-3">Businesses</h5>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Business</th>
                                <th>Package</th>
                                <th>Usage</th>
                                <th>Ends</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($businesses)): ?>
                                <tr><td colspan="5" class="text-center">No businesses found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($businesses as $i => $business): ?>
                                    <tr>
                                        <td><?php echo $i + 1; ?></td>
                                        <td><?php echo h($business['business_name']); ?></td>
                                        <td><?php echo h($business['package_name'] ?? 'Not set'); ?></td>
                                        <td><?php echo h(number_format((int) ($business['messages_used'] ?? 0))); ?> / <?php echo h(number_format((int) ($business['message_limit'] ?? 0))); ?></td>
                                        <td><?php echo h($business['package_ends_at'] ?? '-'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="wg-card p-4 mb-4">
                <h5 class="mb-3">Limit Increase Requests</h5>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Business ID</th>
                                <th>Requested Limit</th>
                                <th>Status</th>
                                <th>Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($packageRequests)): ?>
                                <tr><td colspan="5" class="text-center">No requests found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($packageRequests as $i => $request): ?>
                                    <tr>
                                        <td><?php echo $i + 1; ?></td>
                                        <td><?php echo h((string) $request['biz_id']); ?></td>
                                        <td><?php echo h(number_format((int) $request['requested_limit'])); ?></td>
                                        <td><?php echo h(ucfirst((string) ($request['status'] ?? 'pending'))); ?></td>
                                        <td><?php echo h((string) ($request['reason'] ?? '-')); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

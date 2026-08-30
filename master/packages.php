<?php
include 'session.php';
include 'header.php';

$master_id = Auth::requireMaster();
$db = Database::connectOrNull();
$message = '';
$message_type = 'success';
$packages = [
    'starter' => ['label' => 'Starter', 'marketing_limit' => 500, 'utility_limit' => 500, 'price' => '0'],
    'growth' => ['label' => 'Growth', 'marketing_limit' => 2500, 'utility_limit' => 2500, 'price' => '0'],
    'pro' => ['label' => 'Pro', 'marketing_limit' => 7500, 'utility_limit' => 7500, 'price' => '0'],
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
        $marketingLimit = max(0, Security::intFrom($_POST['marketing_message_limit'] ?? null));
        $utilityLimit = max(0, Security::intFrom($_POST['utility_message_limit'] ?? null));
        $marketingPrice = trim((string) ($_POST['marketing_package_price'] ?? ''));
        $utilityPrice = trim((string) ($_POST['utility_package_price'] ?? ''));
        $packageDays = max(1, Security::intFrom($_POST['package_days'] ?? 30));
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $package = $packages[$packageKey] ?? $packages['starter'];
        if ($marketingLimit <= 0 && $utilityLimit <= 0) {
            $marketingLimit = (int) ($package['marketing_limit'] ?? 0);
            $utilityLimit = (int) ($package['utility_limit'] ?? 0);
        }
        $limit = $marketingLimit + $utilityLimit;
        if ($limit <= 0 && $customLimit > 0) {
            $limit = $customLimit;
        }

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

            if (in_array('marketing_message_limit', $columns, true)) {
                $updates['marketing_message_limit'] = $marketingLimit;
            }
            if (in_array('utility_message_limit', $columns, true)) {
                $updates['utility_message_limit'] = $utilityLimit;
            }
            if (in_array('marketing_messages_used', $columns, true)) {
                $updates['marketing_messages_used'] = 0;
            }
            if (in_array('utility_messages_used', $columns, true)) {
                $updates['utility_messages_used'] = 0;
            }
            if ($marketingPrice !== '' && in_array('marketing_package_price', $columns, true)) {
                $updates['marketing_package_price'] = $marketingPrice;
            }
            if ($utilityPrice !== '' && in_array('utility_package_price', $columns, true)) {
                $updates['utility_package_price'] = $utilityPrice;
            }
            if (in_array('package_price', $columns, true)) {
                $updates['package_price'] = (string) ((float) ($marketingPrice !== '' ? $marketingPrice : 0) + (float) ($utilityPrice !== '' ? $utilityPrice : 0));
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
        $orderColumns = gdMasterColumns($db, 'gd_orders');
        $selectColumns = ['id', 'business_name', 'package_name', 'message_limit', 'messages_used', 'package_ends_at'];
        foreach (['marketing_message_limit', 'utility_message_limit', 'marketing_messages_used', 'utility_messages_used'] as $column) {
            if (in_array($column, $orderColumns, true)) {
                $selectColumns[] = $column;
            }
        }
        $stmt = $db->prepare('SELECT `' . implode('`, `', $selectColumns) . '` FROM gd_orders WHERE admin_id = ? ORDER BY id DESC');
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
                                    <?php $totalLimit = (int) ($package['marketing_limit'] ?? 0) + (int) ($package['utility_limit'] ?? 0); ?>
                                    <option value="<?php echo h($key); ?>"><?php echo h($package['label']); ?> (<?php echo h(number_format($totalLimit)); ?> all messages)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Duration Days</label>
                            <input type="number" name="package_days" class="form-control" min="1" value="30">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Marketing Messages</label>
                            <input type="number" name="marketing_message_limit" class="form-control" min="0" placeholder="Marketing limit">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Utility Messages</label>
                            <input type="number" name="utility_message_limit" class="form-control" min="0" placeholder="Utility limit">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Marketing Price</label>
                            <input type="number" name="marketing_package_price" class="form-control" min="0" step="0.01" placeholder="Marketing price">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Utility Price</label>
                            <input type="number" name="utility_package_price" class="form-control" min="0" step="0.01" placeholder="Utility price">
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
                                <th>All Messages</th>
                                <th>Marketing</th>
                                <th>Utility</th>
                                <th>Ends</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($businesses)): ?>
                                <tr><td colspan="7" class="text-center">No businesses found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($businesses as $i => $business): ?>
                                    <tr>
                                        <td><?php echo $i + 1; ?></td>
                                        <td><?php echo h($business['business_name']); ?></td>
                                        <td><?php echo h($business['package_name'] ?? 'Not set'); ?></td>
                                        <td><?php echo h(number_format((int) ($business['messages_used'] ?? 0))); ?> / <?php echo h(number_format((int) ($business['message_limit'] ?? 0))); ?></td>
                                        <td><?php echo h(number_format((int) ($business['marketing_messages_used'] ?? 0))); ?> / <?php echo h(number_format((int) ($business['marketing_message_limit'] ?? 0))); ?></td>
                                        <td><?php echo h(number_format((int) ($business['utility_messages_used'] ?? 0))); ?> / <?php echo h(number_format((int) ($business['utility_message_limit'] ?? 0))); ?></td>
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

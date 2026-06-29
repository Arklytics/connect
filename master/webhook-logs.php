<?php
include 'db_conn.php';
include 'session.php';
include 'header.php';

$adminId = Auth::requireMaster();

function masterFetchAll(mysqli $db, string $sql, string $types = '', array $params = []): array
{
    $stmt = $db->prepare($sql);
    if ($types !== '') {
        $bind = [$types];
        foreach ($params as $index => $value) {
            $bind[] = &$params[$index];
        }
        $stmt->bind_param(...$bind);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    return $rows;
}

function masterNormalizeDate(string $value, string $fallback): string
{
    $value = trim($value);
    if ($value === '') {
        return $fallback;
    }

    $timestamp = strtotime($value);
    return $timestamp !== false ? date('Y-m-d', $timestamp) : $fallback;
}

function masterResolveDateRange(string $period, string $fromDate, string $toDate): array
{
    $today = date('Y-m-d');

    return match ($period) {
        'today' => [$today, $today],
        'this_week' => [date('Y-m-d', strtotime('monday this week')), $today],
        'this_month' => [date('Y-m-01'), $today],
        'custom' => [
            masterNormalizeDate($fromDate, date('Y-m-01')),
            masterNormalizeDate($toDate, $today),
        ],
        default => [date('Y-m-d', strtotime('-30 days')), $today],
    };
}

function masterBuildSearchClause(string $needle, array $columns): array
{
    $needle = trim($needle);
    if ($needle === '') {
        return ['', ''];
    }

    $parts = [];
    foreach ($columns as $column) {
        $parts[] = $column . ' LIKE ?';
    }

    return ['(' . implode(' OR ', $parts) . ')', '%' . $needle . '%'];
}

function masterBusinessOptions(mysqli $db, int $adminId): array
{
    $stmt = $db->prepare('SELECT id, business_name FROM gd_orders WHERE admin_id = ? ORDER BY business_name ASC');
    $stmt->bind_param('i', $adminId);
    $stmt->execute();

    $rows = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    return $rows;
}

$period = strtolower(trim((string) ($_GET['period'] ?? 'last_30_days')));
$fromDate = (string) ($_GET['from_date'] ?? '');
$toDate = (string) ($_GET['to_date'] ?? '');
$businessId = max(0, (int) ($_GET['biz_id'] ?? 0));
$search = trim((string) ($_GET['search'] ?? ''));

[$from, $to] = masterResolveDateRange($period, $fromDate, $toDate);

$businesses = masterBusinessOptions($db, $adminId);
$webhookLogs = [];
$messageLogs = [];
$webhookStats = [
    'total' => 0,
    'changes' => 0,
    'messages' => 0,
    'statuses' => 0,
];
$messageStats = [
    'total' => 0,
    'sent' => 0,
    'delivered' => 0,
    'read' => 0,
    'failed' => 0,
];

if (Crm::tableColumns($db, 'gd_webhook_logs')) {
    $sql = '
        SELECT
            l.*,
            o.business_name
        FROM gd_webhook_logs l
        LEFT JOIN gd_orders o ON o.id = l.biz_id
        WHERE (o.admin_id = ? OR l.biz_id IS NULL)
          AND DATE(COALESCE(l.webhook_at, l.created_at)) BETWEEN ? AND ?';
    $types = 'iss';
    $params = [$adminId, $from, $to];

    if ($businessId > 0) {
        $sql .= ' AND l.biz_id = ?';
        $types .= 'i';
        $params[] = $businessId;
    }

    [$searchClause, $searchValue] = masterBuildSearchClause($search, [
        'l.from_phone',
        'l.message_id',
        'l.message_text',
        'l.notes',
        'l.event_type',
        'l.direction',
        'o.business_name',
    ]);
    if ($searchClause !== '') {
        $sql .= ' AND ' . $searchClause;
        $types .= str_repeat('s', count(explode(' OR ', trim($searchClause, '()'))));
        foreach (explode(' OR ', trim($searchClause, '()')) as $ignored) {
            $params[] = $searchValue;
        }
    }

    $sql .= ' ORDER BY l.id DESC';
    $webhookLogs = masterFetchAll($db, $sql, $types, $params);

    foreach ($webhookLogs as $log) {
        $webhookStats['total']++;
        $eventType = strtolower((string) ($log['event_type'] ?? ''));
        if ($eventType === 'change') {
            $webhookStats['changes']++;
        } elseif ($eventType === 'message') {
            $webhookStats['messages']++;
        } elseif ($eventType === 'status') {
            $webhookStats['statuses']++;
        }
    }
}

if (Crm::tableColumns($db, 'gd_sent_messages')) {
    $sql = '
        SELECT
            s.*,
            o.business_name
        FROM gd_sent_messages s
        LEFT JOIN gd_orders o ON o.id = s.biz_id
        WHERE o.admin_id = ?
          AND DATE(COALESCE(s.sent_at, s.created_at)) BETWEEN ? AND ?';
    $types = 'iss';
    $params = [$adminId, $from, $to];

    if ($businessId > 0) {
        $sql .= ' AND s.biz_id = ?';
        $types .= 'i';
        $params[] = $businessId;
    }

    [$searchClause, $searchValue] = masterBuildSearchClause($search, [
        's.phone_number',
        's.message_title',
        's.message_body',
        's.error_message',
        's.message_id',
        'o.business_name',
    ]);
    if ($searchClause !== '') {
        $sql .= ' AND ' . $searchClause;
        $types .= str_repeat('s', count(explode(' OR ', trim($searchClause, '()'))));
        foreach (explode(' OR ', trim($searchClause, '()')) as $ignored) {
            $params[] = $searchValue;
        }
    }

    $sql .= ' ORDER BY s.id DESC';
    $messageLogs = masterFetchAll($db, $sql, $types, $params);

    foreach ($messageLogs as $message) {
        $messageStats['total']++;
        $status = strtolower((string) ($message['status'] ?? ''));
        $delivery = strtolower((string) ($message['delivery_status'] ?? ''));

        if ($status === 'failed' || $delivery === 'failed') {
            $messageStats['failed']++;
        } elseif ($delivery === 'read') {
            $messageStats['read']++;
        } elseif ($delivery === 'delivered') {
            $messageStats['delivered']++;
        } elseif ($delivery === 'sent' || $status === 'sent' || $status === 'success') {
            $messageStats['sent']++;
        }
    }
}
?>

<div class="container-fluid wg-shell">
    <div class="row">
        <div class="col-lg-2 col-md-3 p-0 wg-sidebar">
            <?php include 'sidebar.php'; ?>
        </div>

        <main class="col-lg-10 col-md-9 wg-main">
            <div class="wg-page-title">
                <h1>Webhook Logs</h1>
                <p>Review incoming webhook events, message delivery updates, and outbound message history in one place.</p>
            </div>

            <form method="get" class="form-panel">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Period</label>
                        <select name="period" class="form-select">
                            <?php foreach ([
                                'today' => 'Today',
                                'this_week' => 'This Week',
                                'this_month' => 'This Month',
                                'last_30_days' => 'Last 30 Days',
                                'custom' => 'Custom',
                            ] as $value => $label): ?>
                                <option value="<?php echo h($value); ?>" <?php echo $period === $value ? 'selected' : ''; ?>><?php echo h($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">From</label>
                        <input type="date" name="from_date" class="form-control" value="<?php echo h($from); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">To</label>
                        <input type="date" name="to_date" class="form-control" value="<?php echo h($to); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Business</label>
                        <select name="biz_id" class="form-select">
                            <option value="0">All Businesses</option>
                            <?php foreach ($businesses as $business): ?>
                                <option value="<?php echo h((string) $business['id']); ?>" <?php echo (int) $business['id'] === $businessId ? 'selected' : ''; ?>>
                                    <?php echo h($business['business_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" value="<?php echo h($search); ?>" placeholder="Phone, ID, text">
                    </div>
                </div>
                <div class="mt-3">
                    <button class="btn btn-success" type="submit"><i class="bi bi-funnel me-1"></i> Apply Filters</button>
                </div>
            </form>

            <div class="row g-3 mt-1">
                <div class="col-xl-3 col-md-6">
                    <div class="wg-card wg-stat-card">
                        <span class="icon"><i class="bi bi-broadcast"></i></span>
                        <div class="label">Webhook Events</div>
                        <p class="value"><?php echo h((string) $webhookStats['total']); ?></p>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="wg-card wg-stat-card">
                        <span class="icon"><i class="bi bi-chat-left-text"></i></span>
                        <div class="label">Inbound Messages</div>
                        <p class="value"><?php echo h((string) $webhookStats['messages']); ?></p>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="wg-card wg-stat-card">
                        <span class="icon"><i class="bi bi-arrow-repeat"></i></span>
                        <div class="label">Delivery Updates</div>
                        <p class="value"><?php echo h((string) $webhookStats['statuses']); ?></p>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="wg-card wg-stat-card">
                        <span class="icon"><i class="bi bi-send-check"></i></span>
                        <div class="label">Outbound Messages</div>
                        <p class="value"><?php echo h((string) $messageStats['total']); ?></p>
                    </div>
                </div>
            </div>

            <div class="wg-card p-4 mt-4">
                <h5 class="mb-3">Incoming Webhook Log</h5>
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Time</th>
                                <th>Business</th>
                                <th>Type</th>
                                <th>Direction</th>
                                <th>From</th>
                                <th>Message ID</th>
                                <th>Delivery</th>
                                <th>Text</th>
                                <th>Notes</th>
                                <th>Payload</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$webhookLogs): ?>
                                <tr><td colspan="11" class="text-center text-muted">No webhook logs found for the selected filters.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($webhookLogs as $index => $log): ?>
                                <tr>
                                    <td><?php echo h((string) ($index + 1)); ?></td>
                                    <td><?php echo h((string) ($log['webhook_at'] ?? $log['created_at'] ?? '-')); ?></td>
                                    <td><?php echo h((string) ($log['business_name'] ?? 'Unassigned')); ?></td>
                                    <td><span class="badge bg-secondary text-uppercase"><?php echo h((string) ($log['event_type'] ?? 'message')); ?></span></td>
                                    <td><span class="badge bg-info text-dark text-uppercase"><?php echo h((string) ($log['direction'] ?? 'inbound')); ?></span></td>
                                    <td><?php echo h((string) ($log['from_phone'] ?? '-')); ?></td>
                                    <td><?php echo h((string) ($log['message_id'] ?? '-')); ?></td>
                                    <td><?php echo h((string) ($log['delivery_status'] ?? '-')); ?></td>
                                    <td><?php echo h(trim((string) ($log['message_text'] ?? '')) !== '' ? $log['message_text'] : '-'); ?></td>
                                    <td><?php echo h((string) ($log['notes'] ?? '-')); ?></td>
                                    <td>
                                        <details>
                                            <summary>View</summary>
                                            <pre class="small bg-light p-2 rounded mt-2 mb-0" style="max-width: 420px; white-space: pre-wrap;"><?php
                                                $payload = json_decode((string) ($log['payload_json'] ?? ''), true);
                                                echo h(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: (string) ($log['payload_json'] ?? ''));
                                            ?></pre>
                                        </details>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="wg-card p-4 mt-4 mb-4">
                <h5 class="mb-3">Complete Message Delivery Log</h5>
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Time</th>
                                <th>Business</th>
                                <th>Phone</th>
                                <th>Template</th>
                                <th>Title</th>
                                <th>Send Status</th>
                                <th>Delivery</th>
                                <th>Message ID</th>
                                <th>Error</th>
                                <th>Body</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$messageLogs): ?>
                                <tr><td colspan="11" class="text-center text-muted">No outbound messages found for the selected filters.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($messageLogs as $index => $message): ?>
                                <?php
                                    $sendStatus = strtolower((string) ($message['status'] ?? 'pending'));
                                    $delivery = strtolower((string) ($message['delivery_status'] ?? 'pending'));
                                    $sendBadge = $sendStatus === 'failed' ? 'danger' : (($sendStatus === 'success' || $sendStatus === 'sent') ? 'success' : 'secondary');
                                    $deliveryBadge = match ($delivery) {
                                        'delivered' => 'success',
                                        'read' => 'info',
                                        'sent' => 'primary',
                                        'failed' => 'danger',
                                        default => 'warning',
                                    };
                                ?>
                                <tr>
                                    <td><?php echo h((string) ($index + 1)); ?></td>
                                    <td><?php echo h((string) ($message['sent_at'] ?? $message['created_at'] ?? '-')); ?></td>
                                    <td><?php echo h((string) ($message['business_name'] ?? 'Unassigned')); ?></td>
                                    <td><?php echo h((string) ($message['phone_number'] ?? '-')); ?></td>
                                    <td><?php echo h((string) ($message['template_id'] ?? '-')); ?></td>
                                    <td><?php echo h((string) ($message['message_title'] ?? '-')); ?></td>
                                    <td><span class="badge bg-<?php echo h($sendBadge); ?> text-uppercase"><?php echo h($sendStatus); ?></span></td>
                                    <td><span class="badge bg-<?php echo h($deliveryBadge); ?> text-uppercase"><?php echo h($delivery); ?></span></td>
                                    <td><?php echo h((string) ($message['message_id'] ?? '-')); ?></td>
                                    <td><?php echo h((string) ($message['error_message'] ?? '-')); ?></td>
                                    <td>
                                        <details>
                                            <summary>View</summary>
                                            <pre class="small bg-light p-2 rounded mt-2 mb-0" style="max-width: 420px; white-space: pre-wrap;"><?php echo h((string) ($message['message_body'] ?? '-')); ?></pre>
                                        </details>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include 'footer.php'; ?>

<?php
include 'db_conn.php';
include 'session.php';
include 'header.php';

$adminId = Auth::requireMaster();

ApiSupport::ensureApiCallLogTable($db);

function masterApiFetchAll(mysqli $db, string $sql, string $types = '', array $params = []): array
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

function masterApiPrettyJson(?string $json): string
{
    $json = trim((string) $json);
    if ($json === '') {
        return '';
    }

    $decoded = json_decode($json, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return $pretty !== false ? $pretty : $json;
    }

    return $json;
}

function masterApiJsonHref(?string $json): string
{
    return 'data:application/json;charset=utf-8,' . rawurlencode(trim((string) $json));
}

function masterApiDate(string $value, string $fallback): string
{
    $timestamp = strtotime(trim($value));
    return $timestamp !== false ? date('Y-m-d', $timestamp) : $fallback;
}

$today = date('Y-m-d');
$from = masterApiDate((string) ($_GET['from_date'] ?? ''), date('Y-m-d', strtotime('-30 days')));
$to = masterApiDate((string) ($_GET['to_date'] ?? ''), $today);
$businessId = max(0, (int) ($_GET['biz_id'] ?? 0));
$status = strtolower(trim((string) ($_GET['status'] ?? 'all')));
$endpoint = trim((string) ($_GET['endpoint'] ?? ''));
$search = trim((string) ($_GET['search'] ?? ''));

$businesses = masterApiFetchAll(
    $db,
    'SELECT id, business_name, api_key_prefix, api_key_last4, api_enabled
     FROM gd_orders
     WHERE admin_id = ?
     ORDER BY business_name ASC',
    'i',
    [$adminId]
);

$where = ['o.admin_id = ?', 'DATE(COALESCE(l.started_at, l.created_at)) BETWEEN ? AND ?'];
$types = 'iss';
$params = [$adminId, $from, $to];

if ($businessId > 0) {
    $where[] = 'l.biz_id = ?';
    $types .= 'i';
    $params[] = $businessId;
}

if ($status === 'success') {
    $where[] = 'l.ok = 1';
} elseif ($status === 'failed') {
    $where[] = '(l.ok = 0 OR COALESCE(l.status_code, 0) >= 400)';
}

if ($endpoint !== '') {
    $where[] = 'l.endpoint LIKE ?';
    $types .= 's';
    $params[] = '%' . $endpoint . '%';
}

if ($search !== '') {
    $where[] = '(l.endpoint LIKE ? OR l.method LIKE ? OR l.request_json LIKE ? OR l.response_json LIKE ? OR l.error_message LIKE ? OR o.business_name LIKE ?)';
    $types .= 'ssssss';
    for ($i = 0; $i < 6; $i++) {
        $params[] = '%' . $search . '%';
    }
}

$whereSql = implode(' AND ', $where);
$logs = masterApiFetchAll(
    $db,
    "SELECT l.*, o.business_name
     FROM gd_api_call_logs l
     INNER JOIN gd_orders o ON o.id = l.biz_id
     WHERE {$whereSql}
     ORDER BY l.id DESC
     LIMIT 300",
    $types,
    $params
);

$stats = ['total' => 0, 'success' => 0, 'failed' => 0, 'templates' => 0];
foreach ($logs as $log) {
    $stats['total']++;
    $isOk = (int) ($log['ok'] ?? 0) === 1 && (int) ($log['status_code'] ?? 0) < 400;
    $isOk ? $stats['success']++ : $stats['failed']++;
    if (str_contains((string) ($log['endpoint'] ?? ''), '/api/templates')) {
        $stats['templates']++;
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
                <h1>Business API Calls</h1>
                <p>Select a business and inspect API requests, responses, endpoint status, and errors.</p>
            </div>

            <form method="get" class="form-panel">
                <div class="row g-3">
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label">Business</label>
                        <select name="biz_id" class="form-select">
                            <option value="0">All Businesses</option>
                            <?php foreach ($businesses as $business): ?>
                                <option value="<?php echo h((string) $business['id']); ?>" <?php echo (int) $business['id'] === $businessId ? 'selected' : ''; ?>>
                                    <?php echo h((string) $business['business_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label">From</label>
                        <input type="date" name="from_date" class="form-control" value="<?php echo h($from); ?>">
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label">To</label>
                        <input type="date" name="to_date" class="form-control" value="<?php echo h($to); ?>">
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <?php foreach (['all' => 'All', 'success' => 'Success', 'failed' => 'Failed'] as $value => $label): ?>
                                <option value="<?php echo h($value); ?>" <?php echo $status === $value ? 'selected' : ''; ?>><?php echo h($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label">Endpoint</label>
                        <input type="text" name="endpoint" class="form-control" value="<?php echo h($endpoint); ?>" placeholder="/api/templates/create">
                    </div>
                    <div class="col-md-9">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" value="<?php echo h($search); ?>" placeholder="Business, endpoint, request, response, or error">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button class="btn btn-success w-100" type="submit"><i class="bi bi-funnel me-1"></i> Apply Filters</button>
                    </div>
                </div>
            </form>

            <div class="row g-3 mt-1">
                <div class="col-xl-3 col-md-6">
                    <div class="wg-card wg-stat-card"><span class="icon"><i class="bi bi-activity"></i></span><div class="label">API Calls</div><p class="value"><?php echo h((string) $stats['total']); ?></p></div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="wg-card wg-stat-card"><span class="icon"><i class="bi bi-check2-circle"></i></span><div class="label">Success</div><p class="value"><?php echo h((string) $stats['success']); ?></p></div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="wg-card wg-stat-card"><span class="icon"><i class="bi bi-exclamation-triangle"></i></span><div class="label">Failed</div><p class="value"><?php echo h((string) $stats['failed']); ?></p></div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="wg-card wg-stat-card"><span class="icon"><i class="bi bi-layout-text-window"></i></span><div class="label">Template Calls</div><p class="value"><?php echo h((string) $stats['templates']); ?></p></div>
                </div>
            </div>

            <div class="wg-card p-4 mt-4 mb-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <h5 class="mb-0">API Call Detail</h5>
                    <span class="text-muted small">Showing latest <?php echo h((string) count($logs)); ?> matching calls</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Time</th>
                                <th>Business</th>
                                <th>Method</th>
                                <th>Endpoint</th>
                                <th>Status</th>
                                <th>IP</th>
                                <th>Error</th>
                                <th>Request / Response</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$logs): ?>
                                <tr><td colspan="9" class="text-center text-muted py-4">No API calls found for the selected filters.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($logs as $index => $log): ?>
                                <?php
                                    $code = (int) ($log['status_code'] ?? 0);
                                    $ok = (int) ($log['ok'] ?? 0) === 1 && $code < 400;
                                    $badge = $ok ? 'success' : ($code >= 500 ? 'danger' : ($code >= 400 ? 'warning' : 'secondary'));
                                ?>
                                <tr>
                                    <td><?php echo h((string) ($index + 1)); ?></td>
                                    <td><?php echo h((string) ($log['started_at'] ?? $log['created_at'] ?? '-')); ?></td>
                                    <td>
                                        <div class="fw-semibold"><?php echo h((string) ($log['business_name'] ?? 'Unknown')); ?></div>
                                        <div class="small text-muted">Biz ID: <?php echo h((string) ($log['biz_id'] ?? '-')); ?></div>
                                    </td>
                                    <td><span class="badge bg-dark"><?php echo h((string) ($log['method'] ?? '-')); ?></span></td>
                                    <td><code><?php echo h((string) ($log['endpoint'] ?? '-')); ?></code></td>
                                    <td><span class="badge bg-<?php echo h($badge); ?>"><?php echo h($code > 0 ? (string) $code : 'OPEN'); ?></span></td>
                                    <td><?php echo h((string) ($log['ip_address'] ?? '-')); ?></td>
                                    <td><?php echo h(trim((string) ($log['error_message'] ?? '')) !== '' ? (string) $log['error_message'] : '-'); ?></td>
                                    <td>
                                        <details>
                                            <summary>View details</summary>
                                            <div class="d-flex flex-wrap gap-2 mt-2 mb-2">
                                                <a class="btn btn-outline-secondary btn-sm" href="<?php echo h(masterApiJsonHref((string) ($log['request_json'] ?? ''))); ?>" download="api-request-<?php echo h((string) $log['id']); ?>.json">Request JSON</a>
                                                <a class="btn btn-outline-secondary btn-sm" href="<?php echo h(masterApiJsonHref((string) ($log['response_json'] ?? ''))); ?>" download="api-response-<?php echo h((string) $log['id']); ?>.json">Response JSON</a>
                                            </div>
                                            <div class="mb-2">
                                                <small class="text-muted d-block mb-1">Request</small>
                                                <pre class="small bg-light p-2 rounded mb-0" style="max-width: 560px; white-space: pre-wrap;"><?php echo h(masterApiPrettyJson((string) ($log['request_json'] ?? '')) ?: '-'); ?></pre>
                                            </div>
                                            <div>
                                                <small class="text-muted d-block mb-1">Response</small>
                                                <pre class="small bg-light p-2 rounded mb-0" style="max-width: 560px; white-space: pre-wrap;"><?php echo h(masterApiPrettyJson((string) ($log['response_json'] ?? '')) ?: '-'); ?></pre>
                                            </div>
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

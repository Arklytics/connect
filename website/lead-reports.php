<?php
include '../session.php';
include '../db_conn.php';

$biz_id = Auth::requireLogin();
include 'header.php';

function gdTableColumns(mysqli $db, string $table): array
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

function gdResolveReportRange(string $period, string $fromDate, string $toDate): array
{
    return match ($period) {
        'today' => [date('Y-m-d'), date('Y-m-d')],
        'this_week' => [date('Y-m-d', strtotime('monday this week')), date('Y-m-d')],
        'this_month' => [date('Y-m-01'), date('Y-m-d')],
        'last_30_days' => [date('Y-m-d', strtotime('-30 days')), date('Y-m-d')],
        'custom' => [$fromDate, $toDate],
        default => [date('Y-m-01'), date('Y-m-d')],
    };
}

function gdBindParams(mysqli_stmt $stmt, string $types, array $params): void
{
    if ($params === []) {
        return;
    }

    $bindParams = [$types];
    foreach ($params as $key => $value) {
        $bindParams[] = &$params[$key];
    }

    call_user_func_array([$stmt, 'bind_param'], $bindParams);
}

$period = strtolower((string) ($_GET['period'] ?? 'this_month'));
$from_input = Security::dateFrom($_GET['from_date'] ?? null, date('Y-m-01'));
$to_input = Security::dateFrom($_GET['to_date'] ?? null, date('Y-m-d'));
[$from_date, $to_date] = gdResolveReportRange($period, $from_input, $to_input);
$lead_status = strtolower(trim((string) ($_GET['lead_status'] ?? 'all')));
$lead_temperature = strtolower(trim((string) ($_GET['lead_temperature'] ?? 'all')));

$reportError = null;
$contactColumns = [];
$hasTemperature = false;
$leadTotals = [];
$leads = null;

try {
    $contactColumns = gdTableColumns($db, 'gd_user_contacts');
    $hasTemperature = in_array('lead_temperature', $contactColumns, true);
    $contactTemperatureExpr = $hasTemperature ? 'COALESCE(c.lead_temperature, "")' : '""';
    $contactStatusExpr = 'LOWER(COALESCE(c.lead_status, c.status, ""))';
    $contactDateExpr = 'DATE(COALESCE(c.created_at, c.updated_at))';

    $leadBaseSql = "
        FROM gd_user_contacts c
        WHERE c.biz_id = ?
          AND {$contactDateExpr} BETWEEN ? AND ?
    ";

    $leadFilterSql = $leadBaseSql;
    $leadParams = [$biz_id, $from_date, $to_date];
    $leadTypes = 'iss';

    if ($lead_status !== 'all') {
        $leadFilterSql .= ' AND ' . $contactStatusExpr . ' = ?';
        $leadParams[] = $lead_status;
        $leadTypes .= 's';
    }

    if ($lead_temperature !== 'all' && $hasTemperature) {
        $leadFilterSql .= ' AND LOWER(' . $contactTemperatureExpr . ') = ?';
        $leadParams[] = $lead_temperature;
        $leadTypes .= 's';
    }

    $leadCountStmt = $db->prepare("
        SELECT
            COUNT(*) AS total_leads,
            SUM(CASE WHEN LOWER(COALESCE(c.lead_status, c.status, '')) = 'won' THEN 1 ELSE 0 END) AS won_leads,
            SUM(CASE WHEN LOWER(COALESCE(c.lead_status, c.status, '')) = 'lost' THEN 1 ELSE 0 END) AS lost_leads,
            SUM(CASE WHEN LOWER({$contactTemperatureExpr}) = 'hot' THEN 1 ELSE 0 END) AS hot_leads,
            SUM(CASE WHEN LOWER({$contactTemperatureExpr}) = 'warm' THEN 1 ELSE 0 END) AS warm_leads,
            SUM(CASE WHEN LOWER({$contactTemperatureExpr}) = 'cold' THEN 1 ELSE 0 END) AS cold_leads
        {$leadBaseSql}
    ");
    $leadCountStmt->bind_param('iss', $biz_id, $from_date, $to_date);
    $leadCountStmt->execute();
    $leadTotals = $leadCountStmt->get_result()->fetch_assoc() ?: [];

    $leadStmt = $db->prepare("
        SELECT c.*
        {$leadFilterSql}
        ORDER BY c.id DESC
    ");
    gdBindParams($leadStmt, $leadTypes, $leadParams);
    $leadStmt->execute();
    $leads = $leadStmt->get_result();
} catch (mysqli_sql_exception $exception) {
    $reportError = 'MySQL is temporarily unavailable. Please restart XAMPP MySQL and try again.';
}
?>

<div class="container-fluid">
    <div class="row bg-light">
        <div class="col-lg-2 col-md-3 p-0 wg-sidebar">
            <?php include 'sidebar.php'; ?>
        </div>

        <div class="col-lg-10 col-md-9 wg-main">
            <div class="d-flex flex-wrap gap-3 align-items-center justify-content-between mt-3">
                <div>
                    <h4 class="mb-1"><i class="bi bi-people-fill"></i> Lead Reports</h4>
                    <div class="text-muted">Review how many leads you received and how they are classified.</div>
                </div>
                <a class="btn btn-outline-success" href="<?php echo h(app_url('business/reports')); ?>">
                    <i class="bi bi-grid-1x2-fill me-1"></i> Back to Reports
                </a>
            </div>

            <?php if ($reportError !== null): ?>
                <div class="alert alert-warning mt-3"><?php echo h($reportError); ?></div>
            <?php else: ?>
                <form method="GET" class="card shadow-sm border-0 mt-3">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Period</label>
                                <select name="period" class="form-control">
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
                            <div class="col-md-3">
                                <label class="form-label">From</label>
                                <input type="date" name="from_date" class="form-control" value="<?php echo h($from_date); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">To</label>
                                <input type="date" name="to_date" class="form-control" value="<?php echo h($to_date); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Lead Status</label>
                                <select name="lead_status" class="form-control">
                                    <?php foreach (['all' => 'All', 'new' => 'New', 'contacted' => 'Contacted', 'qualified' => 'Qualified', 'won' => 'Won', 'lost' => 'Lost'] as $value => $label): ?>
                                        <option value="<?php echo h($value); ?>" <?php echo $lead_status === $value ? 'selected' : ''; ?>><?php echo h($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Lead Temperature</label>
                                <select name="lead_temperature" class="form-control">
                                    <?php foreach (['all' => 'All', 'hot' => 'Hot', 'warm' => 'Warm', 'cold' => 'Cold'] as $value => $label): ?>
                                        <option value="<?php echo h($value); ?>" <?php echo $lead_temperature === $value ? 'selected' : ''; ?>><?php echo h($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i> Apply Filters</button>
                        </div>
                    </div>
                </form>

                <div class="row g-3 mt-2">
                    <div class="col-md-2"><div class="card shadow-sm border-0 h-100"><div class="card-body"><div class="text-muted small">Leads</div><div class="fs-3 fw-bold"><?php echo h((string) ($leadTotals['total_leads'] ?? 0)); ?></div></div></div></div>
                    <div class="col-md-2"><div class="card shadow-sm border-0 h-100"><div class="card-body"><div class="text-muted small">Won</div><div class="fs-3 fw-bold text-success"><?php echo h((string) ($leadTotals['won_leads'] ?? 0)); ?></div></div></div></div>
                    <div class="col-md-2"><div class="card shadow-sm border-0 h-100"><div class="card-body"><div class="text-muted small">Lost</div><div class="fs-3 fw-bold text-danger"><?php echo h((string) ($leadTotals['lost_leads'] ?? 0)); ?></div></div></div></div>
                    <div class="col-md-2"><div class="card shadow-sm border-0 h-100"><div class="card-body"><div class="text-muted small">Hot</div><div class="fs-3 fw-bold text-danger"><?php echo h((string) ($leadTotals['hot_leads'] ?? 0)); ?></div></div></div></div>
                    <div class="col-md-2"><div class="card shadow-sm border-0 h-100"><div class="card-body"><div class="text-muted small">Warm</div><div class="fs-3 fw-bold text-warning"><?php echo h((string) ($leadTotals['warm_leads'] ?? 0)); ?></div></div></div></div>
                    <div class="col-md-2"><div class="card shadow-sm border-0 h-100"><div class="card-body"><div class="text-muted small">Cold</div><div class="fs-3 fw-bold text-primary"><?php echo h((string) ($leadTotals['cold_leads'] ?? 0)); ?></div></div></div></div>
                </div>

                <div class="card shadow-sm border-0 mt-4 mb-4">
                    <div class="card-body">
                        <h5 class="mb-3">Lead Performance Report</h5>
                        <div class="table-responsive">
                            <table class="table table-striped align-middle">
                                <thead class="table-dark">
                                    <tr>
                                        <th>#</th>
                                        <th>Name</th>
                                        <th>Phone</th>
                                        <th>Lead Stage</th>
                                        <th>Lead Status</th>
                                        <th>Temperature</th>
                                        <th>Source</th>
                                        <th>Created</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($leads !== null && $leads->num_rows > 0): ?>
                                        <?php $i = 0; while ($row = $leads->fetch_assoc()): $i++; ?>
                                            <?php
                                            $temp = strtolower((string) ($row['lead_temperature'] ?? 'cold'));
                                            $tempBadge = match ($temp) {
                                                'hot' => 'danger',
                                                'warm' => 'warning',
                                                default => 'primary',
                                            };
                                            $status = strtolower((string) ($row['lead_status'] ?? $row['status'] ?? 'new'));
                                            $statusBadge = match ($status) {
                                                'won' => 'success',
                                                'lost' => 'danger',
                                                'contacted' => 'info',
                                                'qualified' => 'warning',
                                                default => 'secondary',
                                            };
                                            ?>
                                            <tr>
                                                <td><?php echo $i; ?></td>
                                                <td><?php echo h($row['full_name']); ?></td>
                                                <td><?php echo h($row['phone_number']); ?></td>
                                                <td><span class="badge bg-secondary text-uppercase"><?php echo h($row['lead_stage'] ?? 'lead'); ?></span></td>
                                                <td><span class="badge bg-<?php echo h($statusBadge); ?> text-uppercase"><?php echo h($status); ?></span></td>
                                                <td><span class="badge bg-<?php echo h($tempBadge); ?> text-uppercase"><?php echo h($temp); ?></span></td>
                                                <td><?php echo h($row['source'] ?? 'Manual'); ?></td>
                                                <td><?php echo h($row['created_at'] ?? $row['updated_at'] ?? ''); ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="8" class="text-center">No leads found for the selected filters.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

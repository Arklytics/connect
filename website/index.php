<?php
include '../session.php';
include '../db_conn.php';

$biz_id = Auth::requireLogin();

include 'header.php';
$totalMessages = 0;
$successfulMessages = 0;
$failedMessages = 0;
$dashboardError = '';
$connectionLabel = 'Not connected yet';
$dbProfile = null;
$db = Database::connectOrNull();
$contactColumns = [];
$hasCrmColumns = false;
$totalContacts = 0;
$wonContacts = 0;
$lostContacts = 0;
$dueFollowUps = 0;
$packageName = 'No Package';
$messageLimit = 0;
$messagesUsed = 0;
$messagesRemaining = 0;
$limitRequestStatus = 'none';
$limitRequestNote = '';
$packageEndsAt = null;

if (!$db) {
    $dashboardError = 'Dashboard counts could not be loaded because MySQL is not responding. Start MySQL in XAMPP, then refresh.';
} else {
try {
    $stmt = $db->prepare('SELECT COUNT(*) AS total FROM gd_sent_messages WHERE biz_id = ?');
    $stmt->bind_param('i', $biz_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $totalMessages = (int) $row['total'];
    }

    $stmt = $db->prepare("SELECT COUNT(*) AS total FROM gd_sent_messages WHERE biz_id = ? AND status = 'success'");
    $stmt->bind_param('i', $biz_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $successfulMessages = (int) $row['total'];
    }

    $stmt = $db->prepare("SELECT COUNT(*) AS total FROM gd_sent_messages WHERE biz_id = ? AND status = 'failed'");
    $stmt->bind_param('i', $biz_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $failedMessages = (int) $row['total'];
    }
    } catch (mysqli_sql_exception $exception) {
        $dashboardError = 'Dashboard counts could not be loaded. Restart MySQL in XAMPP if this keeps happening.';
    }

    try {
        $stmt = $db->prepare('SELECT package_name, message_limit, messages_used, limit_request_status, limit_request_note, package_ends_at FROM gd_orders WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $biz_id);
        $stmt->execute();
        $packageRow = $stmt->get_result()->fetch_assoc() ?: [];
        $packageName = (string) ($packageRow['package_name'] ?? $packageName);
        $messageLimit = (int) ($packageRow['message_limit'] ?? 0);
        $messagesUsed = (int) ($packageRow['messages_used'] ?? 0);
        $messagesRemaining = max(0, $messageLimit - $messagesUsed);
        $limitRequestStatus = (string) ($packageRow['limit_request_status'] ?? 'none');
        $limitRequestNote = (string) ($packageRow['limit_request_note'] ?? '');
        $packageEndsAt = $packageRow['package_ends_at'] ?? null;
    } catch (mysqli_sql_exception $exception) {
        $packageName = 'No Package';
    }

    $columnStmt = $db->prepare('SHOW COLUMNS FROM gd_user_contacts');
    if ($columnStmt && $columnStmt->execute()) {
        $columnResult = $columnStmt->get_result();
        while ($column = $columnResult->fetch_assoc()) {
            $contactColumns[] = $column['Field'];
        }
        $hasCrmColumns = in_array('lead_status', $contactColumns, true) || in_array('lead_stage', $contactColumns, true);
    }

    if ($hasCrmColumns) {
        try {
            $stmt = $db->prepare('SELECT COUNT(*) AS total FROM gd_user_contacts WHERE biz_id = ?');
            $stmt->bind_param('i', $biz_id);
            $stmt->execute();
            if ($row = $stmt->get_result()->fetch_assoc()) {
                $totalContacts = (int) $row['total'];
            }

            $stmt = $db->prepare("SELECT COUNT(*) AS total FROM gd_user_contacts WHERE biz_id = ? AND lead_status = 'won'");
            $stmt->bind_param('i', $biz_id);
            $stmt->execute();
            if ($row = $stmt->get_result()->fetch_assoc()) {
                $wonContacts = (int) $row['total'];
            }

            $stmt = $db->prepare("SELECT COUNT(*) AS total FROM gd_user_contacts WHERE biz_id = ? AND lead_status = 'lost'");
            $stmt->bind_param('i', $biz_id);
            $stmt->execute();
            if ($row = $stmt->get_result()->fetch_assoc()) {
                $lostContacts = (int) $row['total'];
            }

            $stmt = $db->prepare("SELECT COUNT(*) AS total FROM gd_user_contacts WHERE biz_id = ? AND next_follow_up_at IS NOT NULL AND next_follow_up_at <= NOW()");
            $stmt->bind_param('i', $biz_id);
            $stmt->execute();
            if ($row = $stmt->get_result()->fetch_assoc()) {
                $dueFollowUps = (int) $row['total'];
            }
        } catch (mysqli_sql_exception $exception) {
            $hasCrmColumns = false;
        }
    }
}

if ($db) {
    try {
        $stmt = $db->prepare('SELECT status, whatsapp_id, phone_number_id FROM gd_orders WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $biz_id);
        $stmt->execute();
        $dbProfile = $stmt->get_result()->fetch_assoc() ?: [];
        if ((($dbProfile['status'] ?? '0') == '1')) {
            $connectionLabel = 'Connected to WhatsApp';
        }
    } catch (mysqli_sql_exception $exception) {
        $dbProfile = null;
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
                <div>
                    <h1>Business Dashboard</h1>
                    <p>Track message usage, templates, and contact activity from your workspace.</p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a class="btn btn-light" href="<?php echo h(app_url('business/create-contact')); ?>">
                        <i class="bi bi-person-plus me-1"></i> Add Lead
                    </a>
                    <a class="btn btn-success" href="<?php echo h(app_url('business/send-messages')); ?>">
                        <i class="bi bi-send me-1"></i> Send Message
                    </a>
                </div>
            </div>

            <?php if ($dashboardError !== ''): ?>
                <div class="alert alert-warning"><?php echo h($dashboardError); ?></div>
            <?php endif; ?>

            <?php
                $percent = $messageLimit > 0 ? (int) round(min(1, $messagesUsed / max(1, $messageLimit)) * 100) : 0;
                $successRate = $totalMessages > 0 ? (int) round(($successfulMessages / max(1, $totalMessages)) * 100) : 0;
            ?>
            <section class="wg-dashboard-hero mb-4">
                <div class="wg-dashboard-hero-main">
                    <span class="wg-kicker">Workspace Health</span>
                    <h2><?php echo h($connectionLabel); ?></h2>
                    <p>Monitor usage, leads, follow-ups, and WhatsApp delivery from one business dashboard.</p>
                    <div class="wg-dashboard-actions">
                        <a class="btn btn-success" href="<?php echo h(app_url('business/send-messages')); ?>">
                            <i class="bi bi-send me-1"></i> Send Campaign
                        </a>
                        <a class="btn btn-light" href="<?php echo h(app_url('business/add-contacts-group')); ?>">
                            <i class="bi bi-upload me-1"></i> Import Leads
                        </a>
                    </div>
                </div>
                <div class="wg-usage-panel">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <div>
                            <span class="wg-kicker">Package</span>
                            <h3><?php echo h($packageName); ?></h3>
                        </div>
                        <a class="btn btn-light btn-sm" href="<?php echo h(app_url('business/payments')); ?>">Plans</a>
                    </div>
                    <div class="wg-usage-number"><?php echo h(number_format($messagesRemaining)); ?></div>
                    <div class="text-muted small">messages remaining</div>
                    <div class="progress mt-3" style="height: 9px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo h((string) $percent); ?>%;" aria-valuenow="<?php echo h((string) $percent); ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div class="wg-usage-meta">
                        <span><?php echo h(number_format($messagesUsed)); ?> used</span>
                        <span><?php echo h(number_format($messageLimit)); ?> limit</span>
                    </div>
                    <?php if (!empty($packageEndsAt)): ?>
                        <div class="text-muted small mt-2">Ends: <?php echo h((string) $packageEndsAt); ?></div>
                    <?php endif; ?>
                    <?php if ($limitRequestStatus !== 'none'): ?>
                        <div class="wg-status-note mt-3">
                            Limit request: <?php echo h(ucfirst($limitRequestStatus)); ?>
                            <?php if ($limitRequestNote !== ''): ?>
                                <span><?php echo h($limitRequestNote); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <div class="row g-3">
                <?php if ($hasCrmColumns): ?>
                <div class="col-xl-3 col-md-6">
                    <div class="wg-card wg-stat-card">
                        <span class="icon"><i class="bi bi-people-fill"></i></span>
                        <div class="label">Connect Contacts</div>
                        <p class="value"><?php echo h((string) $totalContacts); ?></p>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="wg-card wg-stat-card">
                        <span class="icon"><i class="bi bi-check2-circle"></i></span>
                        <div class="label">Won Leads</div>
                        <p class="value"><?php echo h((string) $wonContacts); ?></p>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="wg-card wg-stat-card">
                        <span class="icon"><i class="bi bi-x-circle"></i></span>
                        <div class="label">Lost Leads</div>
                        <p class="value"><?php echo h((string) $lostContacts); ?></p>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="wg-card wg-stat-card">
                        <span class="icon"><i class="bi bi-alarm"></i></span>
                        <div class="label">Due Follow-Ups</div>
                        <p class="value"><?php echo h((string) $dueFollowUps); ?></p>
                    </div>
                </div>
                <?php endif; ?>
                <div class="col-xl-4 col-md-6">
                    <div class="wg-card wg-stat-card">
                        <span class="icon"><i class="bi bi-chat-square-text"></i></span>
                        <div class="label">Sent Messages</div>
                        <p class="value"><?php echo h((string) $totalMessages); ?></p>
                    </div>
                </div>
                <div class="col-xl-4 col-md-6">
                    <div class="wg-card wg-stat-card">
                        <span class="icon"><i class="bi bi-send-check"></i></span>
                        <div class="label">Success Rate</div>
                        <p class="value"><?php echo h((string) $successRate); ?>%</p>
                    </div>
                </div>
                <div class="col-xl-4 col-md-6">
                    <div class="wg-card wg-stat-card">
                        <span class="icon"><i class="bi bi-graph-up-arrow"></i></span>
                        <div class="label">Failed</div>
                        <p class="value"><?php echo h((string) $failedMessages); ?></p>
                    </div>
                </div>
            </div>

            <?php if ($hasCrmColumns && $totalContacts === 0): ?>
                <div class="wg-card p-4 mt-4 border-warning">
                    <div class="d-flex flex-wrap gap-3 align-items-center justify-content-between">
                        <div>
                            <h5 class="mb-1">Your Connect workspace is ready</h5>
                            <p class="text-muted mb-0">No contacts are in this business account yet. Add a lead manually or import a sheet to populate the premium CRM view.</p>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <a class="btn btn-success" href="<?php echo h(app_url('business/create-contact')); ?>">
                                <i class="bi bi-person-plus me-1"></i> Add Lead
                            </a>
                            <a class="btn btn-outline-success" href="<?php echo h(app_url('business/add-contacts-group')); ?>">
                                <i class="bi bi-file-earmark-spreadsheet me-1"></i> Import Leads
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="row g-3 mt-2">
                <div class="col-lg-7">
                    <div class="wg-card p-4 h-100">
                        <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                            <h5 class="mb-0">CRM Setup</h5>
                            <span class="badge bg-light text-dark border"><?php echo $hasCrmColumns && $totalContacts > 0 ? 'Active' : 'Ready'; ?></span>
                        </div>
                        <div class="wg-checklist">
                            <a href="<?php echo h(app_url('business/create-group')); ?>">
                                <i class="bi bi-diagram-3"></i>
                                <span><strong>Organize groups</strong><small>Create lists and subgroups for targeted campaigns.</small></span>
                            </a>
                            <a href="<?php echo h(app_url('business/new-template')); ?>">
                                <i class="bi bi-file-earmark-plus"></i>
                                <span><strong>Prepare templates</strong><small>Build approved WhatsApp messages for repeat use.</small></span>
                            </a>
                            <a href="<?php echo h(app_url('business/lead-reports')); ?>">
                                <i class="bi bi-clipboard-data"></i>
                                <span><strong>Review lead reports</strong><small>Track lead outcomes and pending follow-ups.</small></span>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="wg-card p-4 h-100">
                        <div class="d-flex flex-wrap gap-2 align-items-start justify-content-between mb-3">
                            <div>
                                <h5 class="mb-1">Connection Status</h5>
                                <p class="text-muted mb-0"><?php echo h($connectionLabel); ?></p>
                            </div>
                            <a class="btn btn-outline-success btn-sm" href="<?php echo h(app_url('business/connect-whatsapp')); ?>">
                                <i class="bi bi-whatsapp me-1"></i> Manage
                            </a>
                        </div>
                        <div class="wg-mini-metrics">
                            <div><span><?php echo h(number_format($totalMessages)); ?></span><small>Total sent</small></div>
                            <div><span><?php echo h(number_format($successfulMessages)); ?></span><small>Delivered ok</small></div>
                            <div><span><?php echo h(number_format($failedMessages)); ?></span><small>Failed</small></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <div class="wg-page-title mb-3">
                    <h1 style="font-size: 20px;">Connect Quick Actions</h1>
                </div>
                <div class="wg-action-grid">
                    <a class="wg-card wg-action-card" href="<?php echo h(app_url('business/create-contact')); ?>">
                        <i class="bi bi-person-plus"></i>
                        <span><strong>Contacts</strong><span>Manage leads and status</span></span>
                    </a>
                    <a class="wg-card wg-action-card" href="<?php echo h(app_url('business/add-contacts-group')); ?>">
                        <i class="bi bi-file-earmark-spreadsheet"></i>
                        <span><strong>Import Contacts</strong><span>Upload leads in bulk</span></span>
                    </a>
                    <a class="wg-card wg-action-card" href="<?php echo h(app_url('business/create-group')); ?>">
                        <i class="bi bi-people"></i>
                        <span><strong>Contact Groups</strong><span>Organize contact lists</span></span>
                    </a>
                    <a class="wg-card wg-action-card" href="<?php echo h(app_url('business/profile')); ?>">
                        <i class="bi bi-gear"></i>
                        <span><strong>Settings</strong><span>WhatsApp and billing</span></span>
                    </a>
                    <a class="wg-card wg-action-card" href="<?php echo h(app_url('business/profile')); ?>">
                        <i class="bi bi-person-badge"></i>
                        <span><strong>Profile</strong><span>View your details</span></span>
                    </a>
                    <a class="wg-card wg-action-card" href="<?php echo h(app_url('business/whatsapp-sequences')); ?>">
                        <i class="bi bi-diagram-3"></i>
                        <span><strong>WhatsApp Sequences</strong><span>Build structured follow-up plans</span></span>
                    </a>
                    <a class="wg-card wg-action-card" href="<?php echo h(app_url('business/send-messages')); ?>">
                        <i class="bi bi-send"></i>
                        <span><strong>Send Messages</strong><span>Launch a campaign</span></span>
                    </a>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include 'footer.php'; ?>

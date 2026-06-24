<?php
include 'header.php';
include '../session.php';

$totalMessages = 0;
$successfulMessages = 0;
$failedMessages = 0;
$biz_id = Auth::requireLogin();
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
                <h1>Business Dashboard</h1>
                <p>Track message usage, templates, and contact activity from your workspace.</p>
            </div>

            <?php if ($dashboardError !== ''): ?>
                <div class="alert alert-warning"><?php echo h($dashboardError); ?></div>
            <?php endif; ?>

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
                        <div class="label">Successful</div>
                        <p class="value"><?php echo h((string) $successfulMessages); ?></p>
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
                            <a class="btn btn-success" href="/wpi2/business/create-contact">
                                <i class="bi bi-person-plus me-1"></i> Add Lead
                            </a>
                            <a class="btn btn-outline-success" href="/wpi2/business/add-contacts-group">
                                <i class="bi bi-file-earmark-spreadsheet me-1"></i> Import Leads
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="wg-card p-4 mt-4">
                <h5 class="mb-2">Workspace Overview</h5>
                <p class="text-muted mb-0">Create contact groups, prepare templates, and send messages from the tools in the sidebar.</p>
            </div>

            <div class="wg-card p-4 mt-4">
                <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
                    <div>
                        <h5 class="mb-1">Connection Status</h5>
                        <p class="text-muted mb-0"><?php echo h($connectionLabel); ?></p>
                    </div>
                    <a class="btn btn-outline-success" href="/wpi2/business/profile">
                        <i class="bi bi-person-badge me-1"></i> Open Profile
                    </a>
                </div>
            </div>

            <div class="mt-4">
                <div class="wg-page-title mb-3">
                    <h1 style="font-size: 20px;">Connect Quick Actions</h1>
                </div>
                <div class="wg-action-grid">
                    <a class="wg-card wg-action-card" href="/wpi2/business/create-contact">
                        <i class="bi bi-person-plus"></i>
                        <span><strong>Contacts</strong><span>Manage leads and status</span></span>
                    </a>
                    <a class="wg-card wg-action-card" href="/wpi2/business/add-contacts-group">
                        <i class="bi bi-file-earmark-spreadsheet"></i>
                        <span><strong>Import Contacts</strong><span>Upload leads in bulk</span></span>
                    </a>
                    <a class="wg-card wg-action-card" href="/wpi2/business/create-group">
                        <i class="bi bi-people"></i>
                        <span><strong>Contact Groups</strong><span>Organize contact lists</span></span>
                    </a>
                    <a class="wg-card wg-action-card" href="/wpi2/business/profile">
                        <i class="bi bi-gear"></i>
                        <span><strong>Settings</strong><span>WhatsApp and billing</span></span>
                    </a>
                    <a class="wg-card wg-action-card" href="/wpi2/business/profile">
                        <i class="bi bi-person-badge"></i>
                        <span><strong>Profile</strong><span>View your details</span></span>
                    </a>
                    <a class="wg-card wg-action-card" href="/wpi2/business/send-messages">
                        <i class="bi bi-send"></i>
                        <span><strong>Send Messages</strong><span>Launch a campaign</span></span>
                    </a>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include 'footer.php'; ?>

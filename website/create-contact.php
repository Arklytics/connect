<?php

use PhpOffice\PhpSpreadsheet\IOFactory;

include '../session.php';
include '../db_conn.php';

function gdTableExists(mysqli $db, string $table): bool
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        return false;
    }

    $escapedTable = $db->real_escape_string($table);
    $result = $db->query("SHOW TABLES LIKE '{$escapedTable}'");

    return $result ? (bool) $result->num_rows : false;
}

function gdTableColumns(mysqli $db, string $table): array
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        return [];
    }

    $stmt = $db->prepare("SHOW COLUMNS FROM `$table`");
    $stmt->execute();
    $result = $stmt->get_result();
    $columns = [];

    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }

    return $columns;
}

function gdTruthy(mixed $value): int
{
    if (is_bool($value)) {
        return $value ? 1 : 0;
    }

    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true) ? 1 : 0;
}

function gdNormalizePhone(string $phone): string
{
    return ApiSupport::normalizePhone($phone);
}

function gdBindParams(mysqli_stmt $stmt, string $types, array &$values): bool
{
    $params = [$types];
    foreach ($values as $key => &$value) {
        $params[] = &$values[$key];
    }

    return $stmt->bind_param(...$params);
}

function gdDynamicInsert(mysqli $db, string $table, array $data): bool
{
    if (!$data) {
        return false;
    }

    $columns = array_keys($data);
    $placeholders = implode(',', array_fill(0, count($columns), '?'));
    $sql = 'INSERT INTO ' . $table . ' (`' . implode('`,`', $columns) . '`) VALUES (' . $placeholders . ')';
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $types = '';
    $values = [];
    foreach ($data as $value) {
        if ($value === null) {
            $value = null;
        }
        $values[] = $value;
        if (is_int($value) || is_bool($value)) {
            $types .= 'i';
        } elseif (is_float($value)) {
            $types .= 'd';
        } else {
            $types .= 's';
        }
    }

    if (!gdBindParams($stmt, $types, $values)) {
        return false;
    }

    return (bool) $stmt->execute();
}

function gdDynamicUpdate(mysqli $db, string $table, array $data, string $whereSql, array $whereParams): bool
{
    if (!$data) {
        return false;
    }

    $assignments = [];
    $values = [];
    $types = '';

    foreach ($data as $column => $value) {
        $assignments[] = "`$column` = ?";
        $values[] = $value;
        if (is_int($value) || is_bool($value)) {
            $types .= 'i';
        } elseif (is_float($value)) {
            $types .= 'd';
        } else {
            $types .= 's';
        }
    }

    $sql = 'UPDATE ' . $table . ' SET ' . implode(', ', $assignments) . ' WHERE ' . $whereSql;
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return false;
    }

    foreach ($whereParams as $param) {
        $values[] = $param;
        if (is_int($param) || is_bool($param)) {
            $types .= 'i';
        } elseif (is_float($param)) {
            $types .= 'd';
        } else {
            $types .= 's';
        }
    }

    if (!gdBindParams($stmt, $types, $values)) {
        return false;
    }

    return (bool) $stmt->execute();
}

$biz_id = Auth::requireLogin();

include 'header.php';
$contactColumns = gdTableColumns($db, 'gd_user_contacts');
$followupTableExists = gdTableExists($db, 'gd_contact_followups');
$contactsHaveCrm = in_array('lead_status', $contactColumns, true) || in_array('lead_stage', $contactColumns, true);
$hasTemperature = in_array('lead_temperature', $contactColumns, true);
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::verifyCsrf();

    if (isset($_POST['save_contact'])) {
        $full_name = trim((string) ($_POST['full_name'] ?? ''));
        $phone_number = gdNormalizePhone((string) ($_POST['mobile_number'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $group_id = Security::intFrom($_POST['group_id'] ?? null);

        $lead_stage = trim((string) ($_POST['lead_stage'] ?? 'lead'));
        $lead_status = trim((string) ($_POST['lead_status'] ?? 'new'));
        $source = trim((string) ($_POST['source'] ?? 'Manual'));
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $lost_reason = trim((string) ($_POST['lost_reason'] ?? ''));
        $next_follow_up_at = trim((string) ($_POST['next_follow_up_at'] ?? ''));
        $whatsapp_opt_in = gdTruthy($_POST['whatsapp_opt_in'] ?? 0);

        if ($full_name === '' || $phone_number === '') {
            $message = 'Full name and mobile number are required.';
            $message_type = 'danger';
        } elseif ($group_id <= 0) {
            $message = 'Please select a group before saving a contact.';
            $message_type = 'warning';
        } else {
            $existingStmt = $db->prepare('SELECT id FROM gd_user_contacts WHERE phone_number = ? AND biz_id = ? LIMIT 1');
            $existingStmt->bind_param('si', $phone_number, $biz_id);
            $existingStmt->execute();
            $existing = $existingStmt->get_result()->fetch_assoc();

            $record = [
                'biz_id' => $biz_id,
                'group_id' => $group_id,
                'full_name' => $full_name,
                'phone_number' => $phone_number,
                'email' => $email,
            ];

            $optionalMap = [
                'status' => $lead_status,
                'lead_stage' => $lead_stage,
                'lead_status' => $lead_status,
                'source' => $source !== '' ? $source : 'Manual',
                'whatsapp_opt_in' => $whatsapp_opt_in,
                'last_contacted_at' => date('Y-m-d H:i:s'),
                'crm_notes' => $notes !== '' ? $notes : null,
                'next_follow_up_at' => $next_follow_up_at !== '' ? $next_follow_up_at : null,
                'lost_reason' => $lead_status === 'lost' ? ($lost_reason !== '' ? $lost_reason : null) : null,
                'won_at' => $lead_status === 'won' ? date('Y-m-d H:i:s') : null,
                'lost_at' => $lead_status === 'lost' ? date('Y-m-d H:i:s') : null,
            ];

            foreach ($optionalMap as $column => $value) {
                if (in_array($column, $contactColumns, true)) {
                    $record[$column] = $value;
                }
            }

            if ($existing) {
                $contact_id = (int) $existing['id'];
                $updateRecord = $record;
                unset($updateRecord['biz_id']);
                if (gdDynamicUpdate($db, 'gd_user_contacts', $updateRecord, 'id = ? AND biz_id = ?', [$contact_id, $biz_id])) {
                    $message = 'Contact updated successfully.';
                    $message_type = 'success';
                } else {
                    $message = 'Unable to update contact.';
                    $message_type = 'danger';
                }
            } else {
                $record['created_at'] = date('Y-m-d H:i:s');
                $record['updated_at'] = date('Y-m-d H:i:s');
                if (gdDynamicInsert($db, 'gd_user_contacts', $record)) {
                    $contact_id = (int) $db->insert_id;
                    $stmt2 = $db->prepare('INSERT INTO gd_group_contacts (biz_id, group_id, contact_id) VALUES (?, ?, ?)');
                    $stmt2->bind_param('iii', $biz_id, $group_id, $contact_id);
                    if ($stmt2->execute()) {
                        $message = 'Contact saved successfully.';
                        $message_type = 'success';
                    } else {
                        $message = 'Contact saved but could not attach to group.';
                        $message_type = 'warning';
                    }
                } else {
                    $message = 'Unable to save contact.';
                    $message_type = 'danger';
                }
            }
        }
    }

    if (isset($_POST['queue_followup']) && $followupTableExists) {
        $contact_id = Security::intFrom($_POST['contact_id'] ?? null);
        $sequence_name = trim((string) ($_POST['sequence_name'] ?? 'WhatsApp nurture sequence'));
        $first_follow_up_at = trim((string) ($_POST['first_follow_up_at'] ?? ''));
        $step_gap_days = max(1, (int) ($_POST['step_gap_days'] ?? 2));
        $steps = max(1, min(10, (int) ($_POST['steps'] ?? 3)));
        $followup_notes = trim((string) ($_POST['followup_notes'] ?? ''));

        if ($contact_id <= 0 || $first_follow_up_at === '') {
            $message = 'Select a contact and first follow-up time.';
            $message_type = 'warning';
        } else {
            $contactStmt = $db->prepare('SELECT id, full_name FROM gd_user_contacts WHERE id = ? AND biz_id = ? LIMIT 1');
            $contactStmt->bind_param('ii', $contact_id, $biz_id);
            $contactStmt->execute();
            $contact = $contactStmt->get_result()->fetch_assoc();

            if (!$contact) {
                $message = 'Contact not found.';
                $message_type = 'danger';
            } else {
                $start = new DateTimeImmutable($first_follow_up_at);
                $created = 0;

                for ($step = 1; $step <= $steps; $step++) {
                    $scheduledAt = $start->modify('+' . (($step - 1) * $step_gap_days) . ' day')->format('Y-m-d H:i:s');
                    $stmt = $db->prepare('INSERT INTO gd_contact_followups (biz_id, contact_id, channel, sequence_name, step_no, scheduled_at, status, message, notes, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                    $channel = 'whatsapp';
                    $status = 'pending';
                    $messageText = '';
                    $now = date('Y-m-d H:i:s');
                    $stmt->bind_param(
                        'iississssss',
                        $biz_id,
                        $contact_id,
                        $channel,
                        $sequence_name,
                        $step,
                        $scheduledAt,
                        $status,
                        $messageText,
                        $followup_notes,
                        $now,
                        $now
                    );
                    if ($stmt->execute()) {
                        $created++;
                    }
                }

                if (in_array('next_follow_up_at', $contactColumns, true)) {
                    $stmt = $db->prepare('UPDATE gd_user_contacts SET next_follow_up_at = ?, whatsapp_opt_in = 1 WHERE id = ? AND biz_id = ?');
                    $stmt->bind_param('sii', $first_follow_up_at, $contact_id, $biz_id);
                    $stmt->execute();
                }

                $message = $created . ' follow-up steps queued for ' . $contact['full_name'] . '.';
                $message_type = 'success';
            }
        }
    }
}

$groups = [];
$stmt = $db->prepare('SELECT id, group_name FROM gd_groups WHERE biz_id = ? ORDER BY group_name');
$stmt->bind_param('i', $biz_id);
$stmt->execute();
$groupResult = $stmt->get_result();
while ($row = mysqli_fetch_assoc($groupResult)) {
    $groups[] = $row;
}

$contactSelect = ['id', 'full_name', 'phone_number', 'email'];
foreach (['lead_stage', 'lead_status', 'status', 'next_follow_up_at', 'whatsapp_opt_in', 'group_id'] as $column) {
    if (in_array($column, $contactColumns, true)) {
        $contactSelect[] = $column;
    }
}

$contacts = [];
$contactSql = 'SELECT ' . implode(', ', array_map(fn ($column) => '`' . $column . '`', $contactSelect)) . ' FROM gd_user_contacts WHERE biz_id = ? ORDER BY id DESC LIMIT 25';
$stmt = $db->prepare($contactSql);
$stmt->bind_param('i', $biz_id);
$stmt->execute();
$contactResult = $stmt->get_result();
while ($row = mysqli_fetch_assoc($contactResult)) {
    $contacts[] = $row;
}

$recentFollowUps = [];
if ($followupTableExists) {
    $stmt = $db->prepare('SELECT f.*, c.full_name, c.phone_number FROM gd_contact_followups f INNER JOIN gd_user_contacts c ON c.id = f.contact_id WHERE f.biz_id = ? ORDER BY f.id DESC LIMIT 10');
    $stmt->bind_param('i', $biz_id);
    $stmt->execute();
    $followResult = $stmt->get_result();
    while ($row = mysqli_fetch_assoc($followResult)) {
        $recentFollowUps[] = $row;
    }
}
?>

<div class="position-fixed top-0 end-0 p-3" style="z-index: 5;">
    <?php if (!empty($message)): ?>
        <div class="toast align-items-center text-bg-<?php echo h($message_type); ?> border-0 show" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <?php echo h($message); ?>
                </div>
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
                <h1>Connect Contacts</h1>
                <p>Manage manual leads, bulk imports, stages, and WhatsApp follow-up sequences.</p>
            </div>

            <?php if ($contactsHaveCrm): ?>
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="wg-card wg-stat-card">
                            <div class="label">Total Contacts</div>
                            <p class="value"><?php echo h((string) count($contacts)); ?></p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="wg-card wg-stat-card">
                            <div class="label">WhatsApp Opt-In</div>
                            <p class="value"><?php echo h((string) count(array_filter($contacts, fn ($c) => !empty($c['whatsapp_opt_in'])))); ?></p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="wg-card wg-stat-card">
                            <div class="label">Won</div>
                            <p class="value"><?php echo h((string) count(array_filter($contacts, fn ($c) => ($c['lead_status'] ?? $c['status'] ?? '') === 'won'))); ?></p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="wg-card wg-stat-card">
                            <div class="label">Lost</div>
                            <p class="value"><?php echo h((string) count(array_filter($contacts, fn ($c) => ($c['lead_status'] ?? $c['status'] ?? '') === 'lost'))); ?></p>
                        </div>
                    </div>
                    <?php if ($hasTemperature): ?>
                        <div class="col-md-3">
                            <div class="wg-card wg-stat-card">
                                <div class="label">Hot Leads</div>
                                <p class="value"><?php echo h((string) count(array_filter($contacts, fn ($c) => ($c['lead_temperature'] ?? '') === 'hot'))); ?></p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="wg-card wg-stat-card">
                                <div class="label">Warm Leads</div>
                                <p class="value"><?php echo h((string) count(array_filter($contacts, fn ($c) => ($c['lead_temperature'] ?? '') === 'warm'))); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="wg-card p-4 mb-4">
                <h5 class="mb-3">Sequence Plan</h5>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="border rounded-4 p-3 h-100 bg-white">
                            <div class="fw-bold text-success mb-1">Hot</div>
                            <div class="small text-muted mb-2">Replies within 2 hours</div>
                            <div>Switch to active sales mode. Reply fast with pricing, availability, or a demo link.</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded-4 p-3 h-100 bg-white">
                            <div class="fw-bold text-warning mb-1">Warm</div>
                            <div class="small text-muted mb-2">Replies within 24 hours</div>
                            <div>Use a helpful reminder, answer objections, and keep the conversation moving.</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded-4 p-3 h-100 bg-white">
                            <div class="fw-bold text-danger mb-1">Cold</div>
                            <div class="small text-muted mb-2">No response for 2 days+</div>
                            <div>Send a gentle follow-up, then one value message, then a polite close-out.</div>
                        </div>
                    </div>
                </div>
                <div class="alert alert-light border mt-3 mb-0">
                    Suggested 2-day no-response message: <strong>"Hi, just checking in on my last message. If you still need help, I'm here. No rush at all."</strong>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="wg-card p-4">
                        <h5 class="mb-3">Add / Update Contact</h5>
                        <form action="" method="POST" class="row g-3">
                            <?php echo Security::csrfField(); ?>
                            <input type="hidden" name="save_contact" value="1">
                            <div class="col-md-4">
                                <label class="form-label">Group</label>
                                <select class="form-control p-2 shadow" name="group_id" required>
                                    <option value="">--Select Group--</option>
                                    <?php foreach ($groups as $group): ?>
                                        <option value="<?php echo h($group['id']); ?>"><?php echo h($group['group_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control p-2 shadow" name="full_name" required placeholder="Enter Full Name">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Mobile Number</label>
                                <input type="text" class="form-control p-2 shadow" name="mobile_number" required placeholder="Enter Mobile Number">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control p-2 shadow" name="email" placeholder="Enter Email">
                            </div>

                            <?php if ($contactsHaveCrm): ?>
                                <div class="col-md-4">
                                    <label class="form-label">Lead Stage</label>
                                    <select class="form-control p-2 shadow" name="lead_stage">
                                        <option value="lead">Lead</option>
                                        <option value="opportunity">Opportunity</option>
                                        <option value="customer">Customer</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Lead Status</label>
                                    <select class="form-control p-2 shadow" name="lead_status">
                                        <option value="new">New</option>
                                        <option value="contacted">Contacted</option>
                                        <option value="qualified">Qualified</option>
                                        <option value="won">Won</option>
                                        <option value="lost">Lost</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Source</label>
                                    <input type="text" class="form-control p-2 shadow" name="source" placeholder="Manual, Website, Ads">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">WhatsApp Opt-In</label>
                                    <select class="form-control p-2 shadow" name="whatsapp_opt_in">
                                        <option value="1">Yes</option>
                                        <option value="0">No</option>
                                    </select>
                                </div>
                                <?php if ($hasTemperature): ?>
                                    <div class="col-md-4">
                                        <label class="form-label">Lead Temperature</label>
                                        <input type="text" class="form-control p-2 shadow" value="Auto from replies" disabled>
                                    </div>
                                <?php endif; ?>
                                <div class="col-md-4">
                                    <label class="form-label">Next Follow-Up</label>
                                    <input type="datetime-local" class="form-control p-2 shadow" name="next_follow_up_at">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Lost Reason</label>
                                    <input type="text" class="form-control p-2 shadow" name="lost_reason" placeholder="Only if lost">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Notes</label>
                                    <textarea class="form-control p-2 shadow" name="notes" rows="3" placeholder="Lead notes, call summary, objections..."></textarea>
                                </div>
                            <?php else: ?>
                                <div class="col-12">
                                    <div class="alert alert-info mb-0">
                                        Connect fields are not enabled in the database yet. The basic contact form still works, but lead stages and follow-ups will appear after the database update is applied.
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="col-12">
                                <button class="btn btn-success mt-2" type="submit"><i class="bi bi-cloud-download-fill"></i> Save Contact</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="wg-card p-4">
                        <h5 class="mb-3">Queue WhatsApp Follow-Up</h5>
                        <?php if ($followupTableExists && $contactsHaveCrm): ?>
                            <form action="" method="POST" class="row g-3">
                                <?php echo Security::csrfField(); ?>
                                <input type="hidden" name="queue_followup" value="1">
                                <div class="col-12">
                                    <label class="form-label">Contact</label>
                                    <select class="form-control p-2 shadow" name="contact_id" required>
                                        <option value="">Choose Contact</option>
                                        <?php foreach ($contacts as $contact): ?>
                                            <option value="<?php echo h($contact['id']); ?>"><?php echo h($contact['full_name']); ?> - <?php echo h($contact['phone_number']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Sequence Name</label>
                                    <input type="text" class="form-control p-2 shadow" name="sequence_name" value="WhatsApp nurture sequence">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">First Follow-Up</label>
                                    <input type="datetime-local" class="form-control p-2 shadow" name="first_follow_up_at" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Steps</label>
                                    <input type="number" class="form-control p-2 shadow" name="steps" value="3" min="1" max="10">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Gap Days</label>
                                    <input type="number" class="form-control p-2 shadow" name="step_gap_days" value="2" min="1" max="30">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Notes</label>
                                    <textarea class="form-control p-2 shadow" name="followup_notes" rows="3" placeholder="Sequence notes or WhatsApp script"></textarea>
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-outline-success w-100" type="submit"><i class="bi bi-whatsapp"></i> Queue Sequence</button>
                                </div>
                            </form>
                            <p class="text-muted mt-3 mb-0" style="font-size: 13px;">These are queued as records now, so a WhatsApp sender can deliver them automatically later.</p>
                        <?php else: ?>
                            <div class="alert alert-info mb-0">
                                Follow-up queue appears after the database update and follow-up table are available.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="wg-card p-4 mt-4">
                <h5 class="mb-3">Latest Contacts</h5>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <tr>
                            <th>S.no</th>
                            <th>Full Name</th>
                            <th>Phone Number</th>
                            <th>Email</th>
                            <?php if ($contactsHaveCrm): ?>
                                <th>Stage</th>
                                <th>Status</th>
                                <?php if ($hasTemperature): ?>
                                    <th>Temperature</th>
                                <?php endif; ?>
                                <th>Follow-Up</th>
                            <?php endif; ?>
                        </tr>
                        <?php foreach ($contacts as $i => $contact): ?>
                            <tr>
                                <td><?php echo $i + 1; ?></td>
                                <td><?php echo h($contact['full_name']); ?></td>
                                <td><?php echo h($contact['phone_number']); ?></td>
                                <td><?php echo h($contact['email'] ?? ''); ?></td>
                                <?php if ($contactsHaveCrm): ?>
                                    <td><?php echo h($contact['lead_stage'] ?? '-'); ?></td>
                                    <td><?php echo h($contact['lead_status'] ?? ($contact['status'] ?? '-')); ?></td>
                                    <?php if ($hasTemperature): ?>
                                        <td><?php echo h(ucfirst((string) ($contact['lead_temperature'] ?? 'cold'))); ?></td>
                                    <?php endif; ?>
                                    <td><?php echo h($contact['next_follow_up_at'] ?? '-'); ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($contacts)): ?>
                            <tr>
                                <td colspan="<?php echo $contactsHaveCrm ? ($hasTemperature ? 8 : 7) : 4; ?>" class="text-center">No contacts found</td>
                            </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>

            <?php if ($followupTableExists && !empty($recentFollowUps)): ?>
                <div class="wg-card p-4 mt-4">
                    <h5 class="mb-3">Queued Follow-Ups</h5>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <tr>
                                <th>#</th>
                                <th>Contact</th>
                                <th>Sequence</th>
                                <th>Step</th>
                                <th>Scheduled</th>
                                <th>Status</th>
                            </tr>
                            <?php foreach ($recentFollowUps as $i => $followUp): ?>
                                <tr>
                                    <td><?php echo $i + 1; ?></td>
                                    <td><?php echo h($followUp['full_name']); ?></td>
                                    <td><?php echo h($followUp['sequence_name']); ?></td>
                                    <td><?php echo h($followUp['step_no']); ?></td>
                                    <td><?php echo h($followUp['scheduled_at']); ?></td>
                                    <td><?php echo h($followUp['status']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

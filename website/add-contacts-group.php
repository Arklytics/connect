<?php

use PhpOffice\PhpSpreadsheet\IOFactory;

include '../session.php';
include '../db_conn.php';
require_once __DIR__ . '/../vendor/autoload.php';

function wgImportTableColumns(mysqli $db, string $table): array
{
    $columns = [];
    $result = $db->query('SHOW COLUMNS FROM `' . $db->real_escape_string($table) . '`');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
    }

    return $columns;
}

function wgNormalizeImportHeader(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '_', $value);
    return trim((string) $value, '_');
}

function wgNormalizeImportPhone(string $value): string
{
    return ApiSupport::normalizePhone($value);
}

function wgImportTruthy($value): bool
{
    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'y', 'on'], true);
}

function wgImportValue(array $payload, array $keys, string $default = ''): string
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $payload) && trim((string) $payload[$key]) !== '') {
            return trim((string) $payload[$key]);
        }
    }

    return $default;
}

function wgMapImportRow(array $headers, array $row): array
{
    $payload = [];
    foreach ($headers as $column => $header) {
        if ($header !== '') {
            $payload[$header] = trim((string) ($row[$column] ?? ''));
        }
    }

    return $payload;
}

function wgParseImportDate(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    try {
        return (new DateTimeImmutable($value))->format('Y-m-d H:i:s');
    } catch (Throwable $exception) {
        return null;
    }
}

function wgBindAndExecute(mysqli_stmt $stmt, array $types, array $values): bool
{
    if ($types) {
        $bindValues = [];
        foreach ($values as $index => $value) {
            $bindValues[$index] = $value;
        }

        $bindRefs = [];
        foreach (array_keys($bindValues) as $index) {
            $bindRefs[$index] = &$bindValues[$index];
        }

        $stmt->bind_param(implode('', $types), ...$bindRefs);
    }

    return $stmt->execute();
}

function wgDynamicInsert(mysqli $db, string $table, array $record): int
{
    $columns = array_keys($record);
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $sql = 'INSERT INTO `' . $table . '` (`' . implode('`, `', $columns) . '`) VALUES (' . $placeholders . ')';
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return 0;
    }

    $types = array_map(fn ($value) => is_int($value) ? 'i' : 's', array_values($record));
    if (!wgBindAndExecute($stmt, $types, array_values($record))) {
        return 0;
    }

    return (int) $stmt->insert_id;
}

function wgDynamicUpdate(mysqli $db, string $table, array $record, int $contactId, int $bizId): bool
{
    $assignments = [];
    foreach (array_keys($record) as $column) {
        $assignments[] = '`' . $column . '` = ?';
    }

    $sql = 'UPDATE `' . $table . '` SET ' . implode(', ', $assignments) . ' WHERE id = ? AND biz_id = ?';
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $values = array_values($record);
    $values[] = $contactId;
    $values[] = $bizId;
    $types = array_map(fn ($value) => is_int($value) ? 'i' : 's', $values);

    return wgBindAndExecute($stmt, $types, $values);
}

function wgUpsertImportedContact(mysqli $db, int $bizId, int $groupId, array $payload, array $contactColumns, array $groupContactColumns): string
{
    if (!$contactColumns) {
        return 'skipped';
    }

    $phone = wgNormalizeImportPhone(wgImportValue($payload, ['phone_number', 'mobile_number', 'mobile', 'phone', 'contact_number']));
    if ($phone === '') {
        return 'skipped';
    }

    $fullName = wgImportValue($payload, ['full_name', 'name', 'contact_name', 'customer_name'], 'Unnamed Contact');
    $leadStatus = wgImportValue($payload, ['lead_status', 'status'], 'new');
    $leadStage = wgImportValue($payload, ['lead_stage'], 'lead');
    $now = date('Y-m-d H:i:s');

    $record = [
        'biz_id' => $bizId,
        'group_id' => $groupId,
        'full_name' => $fullName,
        'phone_number' => $phone,
        'email' => wgImportValue($payload, ['email', 'email_address']),
        'status' => $leadStatus,
        'lead_stage' => $leadStage,
        'lead_status' => $leadStatus,
        'source' => wgImportValue($payload, ['source'], 'Import'),
        'whatsapp_opt_in' => wgImportTruthy(wgImportValue($payload, ['whatsapp_opt_in', 'opt_in', 'wa_opt_in'])) ? 1 : 0,
        'last_contacted_at' => $now,
        'next_follow_up_at' => wgParseImportDate(wgImportValue($payload, ['next_follow_up_at', 'follow_up_at'])),
        'lost_reason' => wgImportValue($payload, ['lost_reason']),
        'crm_notes' => wgImportValue($payload, ['notes', 'crm_notes']),
        'won_at' => strtolower($leadStatus) === 'won' ? $now : null,
        'lost_at' => strtolower($leadStatus) === 'lost' ? $now : null,
        'created_at' => $now,
        'updated_at' => $now,
    ];

    $record = array_intersect_key($record, array_flip($contactColumns));

    $existingStmt = $db->prepare('SELECT id FROM gd_user_contacts WHERE biz_id = ? AND phone_number = ? LIMIT 1');
    $existingStmt->bind_param('is', $bizId, $phone);
    $existingStmt->execute();
    $existing = $existingStmt->get_result()->fetch_assoc();

    if ($existing) {
        unset($record['biz_id'], $record['created_at']);
        $contactId = (int) $existing['id'];
        if (!wgDynamicUpdate($db, 'gd_user_contacts', $record, $contactId, $bizId)) {
            return 'skipped';
        }
        $status = 'updated';
    } else {
        $contactId = wgDynamicInsert($db, 'gd_user_contacts', $record);
        if ($contactId <= 0) {
            return 'skipped';
        }
        $status = 'created';
    }

    $linkCheck = $db->prepare('SELECT id FROM gd_group_contacts WHERE biz_id = ? AND group_id = ? AND contact_id = ? LIMIT 1');
    $linkCheck->bind_param('iii', $bizId, $groupId, $contactId);
    $linkCheck->execute();
    if (!$linkCheck->get_result()->fetch_assoc()) {
        $link = [
            'biz_id' => $bizId,
            'group_id' => $groupId,
            'contact_id' => $contactId,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $link = array_intersect_key($link, array_flip($groupContactColumns));
        wgDynamicInsert($db, 'gd_group_contacts', $link);
    }

    return $status;
}

function wgDownloadContactSampleCsv(): void
{
    $headers = [
        'full_name',
        'phone_number',
        'email',
        'lead_stage',
        'lead_status',
        'source',
        'next_follow_up_at',
        'whatsapp_opt_in',
        'notes',
    ];
    $sampleRow = [
        'John Doe',
        '+919876543210',
        'john@example.com',
        'lead',
        'new',
        'Website',
        date('Y-m-d H:i:s', strtotime('+2 days')),
        '1',
        'Interested in the starter plan',
    ];

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="contact-import-sample.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fputcsv($output, $headers);
    fputcsv($output, $sampleRow);
    fclose($output);
    exit;
}

function wgEnsureContactImportTable(mysqli $db): void
{
    $db->query("
        CREATE TABLE IF NOT EXISTS gd_contact_imports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            biz_id INT NOT NULL,
            parent_group_id INT NULL,
            subgroup_id INT NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            created_count INT NOT NULL DEFAULT 0,
            updated_count INT NOT NULL DEFAULT 0,
            skipped_count INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            INDEX idx_biz_imports (biz_id),
            INDEX idx_subgroup_imports (subgroup_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

$biz_id = Auth::requireLogin();
try {
    ApiSupport::ensureGroupHierarchyColumns($db);
} catch (Throwable $exception) {
    error_log('Group hierarchy ensure failed: ' . $exception->getMessage());
}
wgEnsureContactImportTable($db);

if (isset($_GET['sample_csv'])) {
    wgDownloadContactSampleCsv();
}

include 'header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_import') {
    Security::verifyCsrf();
    $importId = Security::intFrom($_POST['import_id'] ?? null);
    if ($importId > 0) {
        $stmt = $db->prepare('DELETE FROM gd_contact_imports WHERE id = ? AND biz_id = ?');
        $stmt->bind_param('ii', $importId, $biz_id);
        $stmt->execute();
        $message = 'Import record deleted.';
        $message_type = 'success';
    }
}

// Handle Excel Import Submission
if (isset($_POST['import'])) {
    Security::verifyCsrf();
    $parent_group_id = Security::intFrom($_POST['parent_group'] ?? null);
    $group_id = Security::intFrom($_POST['subgroup'] ?? null);

    $groupStmt = $db->prepare('SELECT id, parent_id FROM gd_groups WHERE id = ? AND biz_id = ? AND parent_id = ? LIMIT 1');
    $groupStmt->bind_param('iii', $group_id, $biz_id, $parent_group_id);
    $groupStmt->execute();
    $groupExists = $groupStmt->get_result()->fetch_assoc();

    if (!$groupExists) {
        $message = 'Please select a valid parent group and subgroup.';
        $message_type = 'warning';
    } elseif (!empty($_FILES['file']['tmp_name']) && ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $inputFileName = $_FILES['file']['tmp_name'];
        $extension = strtolower(pathinfo((string) ($_FILES['file']['name'] ?? ''), PATHINFO_EXTENSION));
        $allowedExtensions = ['csv', 'txt', 'xls', 'xlsx'];

        try {
            if (!in_array($extension, $allowedExtensions, true)) {
                throw new RuntimeException('Upload a CSV, XLS, or XLSX file.');
            }

            // Load Excel file
            $spreadsheet = IOFactory::load($inputFileName);
            $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
            $headerRow = array_shift($sheetData) ?: [];
            $headers = [];
            foreach ($headerRow as $column => $value) {
                $headers[$column] = wgNormalizeImportHeader((string) $value);
            }

            $created = 0;
            $updated = 0;
            $skipped = 0;
            $contactColumns = wgImportTableColumns($db, 'gd_user_contacts');
            $groupContactColumns = wgImportTableColumns($db, 'gd_group_contacts');

            foreach ($sheetData as $row) {
                $payload = wgMapImportRow($headers, $row);
                $status = wgUpsertImportedContact($db, $biz_id, $group_id, $payload, $contactColumns, $groupContactColumns);

                if ($status === 'created') {
                    $created++;
                } elseif ($status === 'updated') {
                    $updated++;
                } else {
                    $skipped++;
                }
            }

            $message = "Imported {$created} new contact(s), updated {$updated}, skipped {$skipped}.";
            $message_type = ($created + $updated) > 0 ? 'success' : 'warning';

            $fileName = substr((string) ($_FILES['file']['name'] ?? 'contacts'), 0, 255);
            $historyStmt = $db->prepare('
                INSERT INTO gd_contact_imports (biz_id, parent_group_id, subgroup_id, file_name, created_count, updated_count, skipped_count, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ');
            $historyStmt->bind_param('iiisiii', $biz_id, $parent_group_id, $group_id, $fileName, $created, $updated, $skipped);
            $historyStmt->execute();
        } catch (Throwable $e) {
            $message = 'Error reading contact file: ' . $e->getMessage();
            $message_type = 'danger';
        }
    } else {
        $message = "No file uploaded.";
        $message_type = "warning";
    }
}

$parentGroups = [];
$subgroupsByParent = [];
$stmt = $db->prepare('
    SELECT g.id, g.parent_id, g.group_name
    FROM gd_groups g
    WHERE g.biz_id = ?
    ORDER BY g.parent_id IS NOT NULL, g.group_name
');
$stmt->bind_param('i', $biz_id);
$stmt->execute();
$groupsResult = $stmt->get_result();
while ($group = $groupsResult->fetch_assoc()) {
    if (empty($group['parent_id'])) {
        $parentGroups[] = $group;
    } else {
        $subgroupsByParent[(int) $group['parent_id']][] = $group;
    }
}

$imports = [];
$stmt = $db->prepare('
    SELECT i.*, parent.group_name AS parent_name, subgroup.group_name AS subgroup_name
    FROM gd_contact_imports i
    LEFT JOIN gd_groups parent ON parent.id = i.parent_group_id AND parent.biz_id = i.biz_id
    LEFT JOIN gd_groups subgroup ON subgroup.id = i.subgroup_id AND subgroup.biz_id = i.biz_id
    WHERE i.biz_id = ?
    ORDER BY i.id DESC
    LIMIT 50
');
$stmt->bind_param('i', $biz_id);
$stmt->execute();
$importsResult = $stmt->get_result();
while ($row = $importsResult->fetch_assoc()) {
    $imports[] = $row;
}
?>

<div class="position-fixed bottom-0 end-0 p-3 wg-footer-toast-container">
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
        <main class="col-lg-10 col-md-9 wg-main">
            <div class="wg-page-title">
                <div>
                    <h1>Import Contacts</h1>
                    <p>Import contacts into a selected subgroup for cleaner campaign targeting.</p>
                </div>
                <a class="btn btn-outline-success" href="<?php echo h(app_url('business/add-contacts-group?sample_csv=1')); ?>"><i class="bi bi-download me-1"></i> Sample CSV</a>
            </div>

            <div class="wg-import-layout">
                <section class="wg-card p-0 overflow-hidden">
                    <div class="wg-table-toolbar">
                        <div>
                            <h5>Import Files</h5>
                            <p>Review recent uploads and the subgroup they were imported into.</p>
                        </div>
                    </div>
                    <?php if (empty($imports)): ?>
                        <div class="wg-empty-state">
                            <i class="bi bi-file-earmark-spreadsheet"></i>
                            <h5>No import files yet</h5>
                            <p>Upload a CSV or Excel file from the panel on the right.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table wg-template-table">
                                <thead>
                                    <tr>
                                        <th>File Name</th>
                                        <th>Group</th>
                                        <th>Imported</th>
                                        <th>Skipped</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($imports as $import): ?>
                                        <tr>
                                            <td>
                                                <div class="wg-template-name"><?php echo h($import['file_name']); ?></div>
                                                <div class="text-muted small"><?php echo h((string) ($import['created_at'] ?? '')); ?></div>
                                            </td>
                                            <td><?php echo h((string) ($import['parent_name'] ?? 'Deleted group')); ?> / <?php echo h((string) ($import['subgroup_name'] ?? 'Deleted subgroup')); ?></td>
                                            <td><span class="wg-pill"><?php echo h((string) ((int) $import['created_count'] + (int) $import['updated_count'])); ?></span></td>
                                            <td><?php echo h((string) $import['skipped_count']); ?></td>
                                            <td>
                                                <form method="POST" class="m-0">
                                                    <?php echo Security::csrfField(); ?>
                                                    <input type="hidden" name="action" value="delete_import">
                                                    <input type="hidden" name="import_id" value="<?php echo h((string) $import['id']); ?>">
                                                    <button class="btn btn-light btn-sm text-danger" type="submit"><i class="bi bi-trash"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>

                <aside class="wg-card wg-import-panel">
                    <div class="wg-section-heading">
                        <span><i class="bi bi-cloud-upload"></i></span>
                        <div>
                            <h5>Upload Contacts</h5>
                            <p>Choose parent group first, then import into one subgroup.</p>
                        </div>
                    </div>
                    <form action="" method="POST" enctype="multipart/form-data">
                        <?php echo Security::csrfField(); ?>
                        <label class="form-label" for="parentGroupImport">Parent Group</label>
                        <select class="form-control" id="parentGroupImport" name="parent_group" required>
                            <option value="">Select parent group</option>
                            <?php foreach ($parentGroups as $parent): ?>
                                <option value="<?php echo h((string) $parent['id']); ?>"><?php echo h($parent['group_name']); ?></option>
                            <?php endforeach; ?>
                        </select>

                        <label class="form-label mt-3" for="subgroupImport">Subgroup</label>
                        <select class="form-control" id="subgroupImport" name="subgroup" required>
                            <option value="">Select subgroup</option>
                            <?php foreach ($subgroupsByParent as $parentId => $subgroups): ?>
                                <?php foreach ($subgroups as $subgroup): ?>
                                    <option class="d-none" value="<?php echo h((string) $subgroup['id']); ?>" data-parent-id="<?php echo h((string) $parentId); ?>" disabled><?php echo h($subgroup['group_name']); ?></option>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </select>

                        <label class="form-label mt-3" for="contactImportFile">Import File</label>
                        <input type="file" id="contactImportFile" name="file" class="form-control" accept=".csv,.txt,.xls,.xlsx" required>
                        <div class="form-text mt-2">CSV, XLS, or XLSX. Use headers like full_name, phone_number, email, lead_status, notes.</div>

                        <button class="btn btn-success w-100 mt-3" name="import" type="submit"><i class="bi bi-cloud-upload-fill me-1"></i> Import Contacts</button>
                    </form>
                </aside>
            </div>
        </main>
    </div>
</div>

<script>
document.getElementById('parentGroupImport')?.addEventListener('change', function () {
    const parentId = this.value;
    const subgroupSelect = document.getElementById('subgroupImport');
    subgroupSelect.value = '';
    subgroupSelect.querySelectorAll('option[data-parent-id]').forEach((option) => {
        const matches = option.getAttribute('data-parent-id') === parentId;
        option.classList.toggle('d-none', !matches);
        option.disabled = !matches;
    });
});
</script>

<?php include 'footer.php'; ?>

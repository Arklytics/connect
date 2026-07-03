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
    return preg_replace('/[^\d+]/', '', trim($value));
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

$biz_id = Auth::requireLogin();

if (isset($_GET['sample_csv'])) {
    wgDownloadContactSampleCsv();
}

include 'header.php';

// Handle Excel Import Submission
if (isset($_POST['import'])) {
    Security::verifyCsrf();
    $group_id = Security::intFrom($_POST['group'] ?? null);

    $groupStmt = $db->prepare('SELECT id FROM gd_groups WHERE id = ? AND biz_id = ? LIMIT 1');
    $groupStmt->bind_param('ii', $group_id, $biz_id);
    $groupStmt->execute();
    $groupExists = $groupStmt->get_result()->fetch_assoc();

    if (!$groupExists) {
        $message = 'Please select a valid contact group.';
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
        } catch (Throwable $e) {
            $message = 'Error reading contact file: ' . $e->getMessage();
            $message_type = 'danger';
        }
    } else {
        $message = "No file uploaded.";
        $message_type = "warning";
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

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-2 col-md-3 p-0 wg-sidebar">
            <?php include 'sidebar.php'; ?>
        </div>
        <div class="col-lg-10 col-md-9 wg-main">
            <h4 class="mt-3"><i class="bi bi-file-earmark-spreadsheet"></i> Import Contacts</h4>
            <div class="d-flex justify-content-end mt-2">
                <a class="btn btn-outline-success" href="<?php echo h(app_url('business/add-contacts-group?sample_csv=1')); ?>">
                    <i class="bi bi-download me-1"></i> Download Sample CSV
                </a>
            </div>
            <form action="" method="POST" enctype="multipart/form-data" class="mt-3">
                <?php echo Security::csrfField(); ?>
                <div class="row bg-light mt-2">
                    <div class="col-md-3">
                        <select class="form-control" name="group" required>
                            <option value="">--Select Group--</option>
                            <?php
                            $biz_id = Auth::requireLogin();
                            $stmt = $db->prepare('SELECT id, group_name FROM gd_groups WHERE biz_id = ? ORDER BY group_name');
                            $stmt->bind_param('i', $biz_id);
                            $stmt->execute();
                            $sql3 = $stmt->get_result();
                            while ($get3 = mysqli_fetch_assoc($sql3)) {
                                echo "<option value='" . h($get3['id']) . "'>" . h($get3['group_name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <div class="alert alert-info mb-0">Upload CSV or Excel. First row should contain headers like full_name, phone_number, email, lead_status, notes.</div>
                    </div>
                    <div class="col-md-3">
                        <input type="file" name="file" class="form-control" accept=".csv,.txt,.xls,.xlsx" required />
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-success w-100" name="import" type="submit"><i class="bi bi-cloud-upload-fill"></i> Import</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

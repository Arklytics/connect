<?php
include 'db_conn.php'; // Database connection
include 'session.php';
include 'header.php';

$loadError = '';
$message = '';
$message_type = 'success';

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

function gdMasterColumnExists(mysqli $db, string $table, string $column): bool
{
    $stmt = $db->prepare(
        'SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1'
    );
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $result = $stmt->get_result();

    return (bool) $result && $result->num_rows > 0;
}

function gdMasterDeleteBusinessRows(mysqli $db, string $table, int $businessId): void
{
    try {
        if (!gdMasterTableExists($db, $table) || !gdMasterColumnExists($db, $table, 'biz_id')) {
            return;
        }

        $stmt = $db->prepare("DELETE FROM `$table` WHERE biz_id = ?");
        $stmt->bind_param('i', $businessId);
        $stmt->execute();
    } catch (Throwable $exception) {
        error_log("Business related cleanup failed for {$table}: " . $exception->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_business_id'])) {
    Security::verifyCsrf();

    $deleteId = Security::intFrom($_POST['delete_business_id'] ?? null);
    $mid = Auth::requireMaster();

    try {
        $stmt = $db->prepare('SELECT id, business_logo FROM gd_orders WHERE id = ? AND admin_id = ? LIMIT 1');
        $stmt->bind_param('ii', $deleteId, $mid);
        $stmt->execute();
        $business = $stmt->get_result()->fetch_assoc();

        if (!$business) {
            $message = 'Business not found.';
            $message_type = 'warning';
        } else {
            foreach ([
                'gd_contact_followups',
                'gd_ai_knowledge_sections',
                'gd_whatsapp_sequence_plans',
                'gd_sent_messages',
                'gd_whatsapp_templates',
                'gd_group_contacts',
                'gd_user_contacts',
                'gd_groups',
                'gd_package_requests',
            ] as $table) {
                gdMasterDeleteBusinessRows($db, $table, $deleteId);
            }

            $stmt = $db->prepare('DELETE FROM gd_orders WHERE id = ? AND admin_id = ?');
            $stmt->bind_param('ii', $deleteId, $mid);
            $stmt->execute();

            if ($stmt->affected_rows < 1) {
                throw new RuntimeException('No gd_orders row was deleted.');
            }

            if (!empty($business['business_logo'])) {
                $logoPath = __DIR__ . '/' . ltrim((string) $business['business_logo'], '/');
                if (is_file($logoPath)) {
                    @unlink($logoPath);
                }
            }

            $message = 'Business deleted successfully.';
            $message_type = 'success';
        }
    } catch (Throwable $exception) {
        error_log('Business delete failed: ' . $exception->getMessage());
        $message = 'Unable to delete business right now: ' . $exception->getMessage();
        $message_type = 'danger';
    }
}
?>


<div class="container-fluid wg-shell">
    <div class="row bg-light">
    <div class="col-lg-2 col-md-3 p-0 wg-sidebar">
            <?php include 'sidebar.php';?>
        </div>

        <div class="col-lg-10 col-md-9 wg-main">

                <?php if ($message !== ''): ?>
                    <div class="alert alert-<?php echo h($message_type); ?> mt-3"><?php echo h($message); ?></div>
                <?php endif; ?>

                <h4 class="mt-3"><i class="bi bi-list-columns-reverse"></i> View Orders</h4>

                <table class="table table-striped mt-4">

                    <tr>
                        <th>Sno</th>
                        <th>Business name</th>
                        <th>Contact</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>

                    <?php
                    $i=0;
                    $mid = Auth::requireMaster();
                    try {
                        $stmt = $db->prepare('SELECT id, business_name, business_number, business_location, status FROM gd_orders WHERE admin_id = ? ORDER BY id DESC');
                        $stmt->bind_param('i', $mid);
                        $stmt->execute();
                        $sql3 = $stmt->get_result();
                        while($get3 = mysqli_fetch_assoc($sql3))
                        {
                            $i++;

                            ?>


                                <tr>
                                        <td><?php echo $i;?></td>
                                        <td><?php echo h($get3['business_name']);?></td>
                                        <td><?php echo h($get3['business_number']);?></td>
                                        <td><?php echo h($get3['business_location']);?></td>
                                        <td>
                                            <?php
                                            $status = $get3['status'];
                                            if($status==0)
                                            {
                                                echo 'In-active';
                                            }
                                            else
                                            {
                                                echo 'Activated';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <form method="post" onsubmit="return confirm('Delete this business and all related data?');">
                                                <?php echo Security::csrfField(); ?>
                                                <input type="hidden" name="delete_business_id" value="<?php echo h((string) $get3['id']); ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">
                                                    <i class="bi bi-trash me-1"></i> Delete
                                                </button>
                                            </form>
                                        </td>
                                </tr>


                            <?php
                        }
                    } catch (mysqli_sql_exception $exception) {
                        $loadError = 'Orders could not be loaded right now. Restart MySQL in XAMPP and refresh this page.';
                    }
                    ?>

                    <?php if ($loadError !== ''): ?>
                        <tr><td colspan="6" class="text-center text-danger"><?php echo h($loadError); ?></td></tr>
                    <?php endif; ?>

                </table>

        </div>
    </div>
</div>

<?php include 'footer.php';?>

<?php
include '../session.php';
include '../db_conn.php';

$biz_id = Auth::requireLogin();
try {
    ApiSupport::ensureGroupHierarchyColumns($db);
} catch (Throwable $exception) {
    error_log('Group hierarchy ensure failed: ' . $exception->getMessage());
}

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

$contactColumns = gdTableColumns($db, 'gd_user_contacts');
?> 


<div class="container-fluid">
    <div class="row bg-light">
    <div class="col-lg-2 col-md-3 p-0 wg-sidebar">
            <?php include 'sidebar.php';?>
        </div>

        <div class="col-lg-10 col-md-9 wg-main">

                <h4 class="mt-3"><i class="bi bi-list-columns-reverse"></i> Group Contacts</h4>

                <table class="table table-striped mt-4">

                    <tr>
                        <th>Sno</th>
                        <th>Full name</th>
                        <th>Phone Number</th>
                        <th>email</th>
                        <?php if (in_array('lead_stage', $contactColumns, true)): ?>
                            <th>Lead Stage</th>
                            <th>Lead Status</th>
                            <th>Follow-Up</th>
                        <?php else: ?>
                            <th>Status</th>
                        <?php endif; ?>
                    </tr>

                    <?php
                    $i=0;
                    $biz_id = Auth::requireLogin();
                    $group_id = Security::intFrom($_GET['group_id'] ?? null);
                    if ($group_id <= 0) {
                        $targetGroupIds = [0];
                    } else {
                        $targetGroupIds = ApiSupport::groupTargetIds($db, (int) $biz_id, (int) $group_id, true);
                        if (empty($targetGroupIds)) {
                            $targetGroupIds = [0];
                        }
                    }
                    $selectFields = ['full_name', 'phone_number', 'email', 'status'];
                    foreach (['lead_stage', 'lead_status', 'next_follow_up_at'] as $field) {
                        if (in_array($field, $contactColumns, true)) {
                            $selectFields[] = $field;
                        }
                    }

                    $placeholders = implode(',', array_fill(0, count($targetGroupIds), '?'));
                    $types = 'i' . str_repeat('i', count($targetGroupIds));
                    $values = array_merge([(int) $biz_id], $targetGroupIds);
                    $stmt = $db->prepare('SELECT DISTINCT contact_id FROM gd_group_contacts WHERE biz_id = ? AND group_id IN (' . $placeholders . ')');
                    $bind = [$types];
                    foreach ($values as $index => $value) {
                        $bind[] = &$values[$index];
                    }
                    $stmt->bind_param(...$bind);
                    $stmt->execute();
                    $sql3 = $stmt->get_result();
                    while($get3 = mysqli_fetch_assoc($sql3))
                    {
                        $contact_id = $get3['contact_id'];
                        $contactStmt = $db->prepare('SELECT ' . implode(', ', $selectFields) . ' FROM gd_user_contacts WHERE id = ? AND biz_id = ? LIMIT 1');
                        $contactStmt->bind_param('ii', $contact_id, $biz_id);
                        $contactStmt->execute();
                        $get4 = $contactStmt->get_result()->fetch_assoc();
                        if (!$get4) {
                            continue;
                        }
                        $i++;

                        ?>


                            <tr>
                                    <td><?php echo $i;?></td>
                                    <td><?php echo h($get4['full_name']);?></td>
                                    <td><?php echo h($get4['phone_number']);?></td>
                                    <td><?php echo h($get4['email']);?></td>
                                    <?php if (in_array('lead_stage', $contactColumns, true)): ?>
                                        <td><?php echo h($get4['lead_stage'] ?? '-');?></td>
                                        <td><?php echo h($get4['lead_status'] ?? $get4['status'] ?? '-');?></td>
                                        <td><?php echo h($get4['next_follow_up_at'] ?? '-');?></td>
                                    <?php else: ?>
                                        <td><?php echo h($get4['status']);?></td>
                                    <?php endif; ?>
                            </tr>


                        <?php
                    }
                    ?>

                </table>

        </div>
    </div>
</div>

<?php include 'footer.php';?>

<?php
include '../session.php';
include '../db_conn.php';

$biz_id = Auth::requireLogin();

include 'header.php';

try {
    ApiSupport::ensureGroupHierarchyColumns($db);
} catch (Throwable $exception) {
    error_log('Group hierarchy ensure failed: ' . $exception->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::verifyCsrf();

    $group = trim((string) ($_POST['group_name'] ?? ''));
    $parentId = Security::intFrom($_POST['parent_id'] ?? null);
    if ($parentId > 0) {
        $parentStmt = $db->prepare('SELECT id FROM gd_groups WHERE id = ? AND biz_id = ? AND parent_id IS NULL LIMIT 1');
        $parentStmt->bind_param('ii', $parentId, $biz_id);
        $parentStmt->execute();
        if (!$parentStmt->get_result()->fetch_assoc()) {
            $parentId = 0;
        }
    }

    if ($parentId > 0) {
        $stmt = $db->prepare('INSERT INTO gd_groups (biz_id, parent_id, group_name, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())');
        $stmt->bind_param('iis', $biz_id, $parentId, $group);
    } else {
        $stmt = $db->prepare('INSERT INTO gd_groups (biz_id, parent_id, group_name, created_at, updated_at) VALUES (?, NULL, ?, NOW(), NOW())');
        $stmt->bind_param('is', $biz_id, $group);
    }

    if ($group !== '' && $stmt->execute()) {
        $message = $parentId > 0 ? "New subgroup saved!" : "New group saved!";
        $message_type = "success";
    } else {
        $message = "Unable to save group.";
        $message_type = "danger";
    }
}
?>


<!-- Toast notification for success/error messages -->
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
            <h4 class="mt-3"><i class="bi bi-ui-checks"></i> Groups & Subgroups</h4>
            <form action="" method="POST" enctype="multipart/form-data" class="mt-3">
                <?php echo Security::csrfField(); ?>
                <div class="row bg-light mt-2 g-2 p-2">
                    <div class="col-md-4">
                        <input type="text" class="form-control p-2 shadow" name="group_name" required placeholder="Group or subgroup name">
                    </div>
                    <div class="col-md-4">
                        <select class="form-control p-2 shadow" name="parent_id">
                            <option value="">No parent - create main group</option>
                            <?php
                            $parentStmt = $db->prepare('SELECT id, group_name FROM gd_groups WHERE biz_id = ? AND parent_id IS NULL ORDER BY group_name');
                            $parentStmt->bind_param('i', $biz_id);
                            $parentStmt->execute();
                            $parents = $parentStmt->get_result();
                            while ($parent = $parents->fetch_assoc()) {
                                ?>
                                <option value="<?php echo h($parent['id']); ?>">Subgroup under <?php echo h($parent['group_name']); ?></option>
                                <?php
                            }
                            ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                    <button class="btn btn-success" type="submit"><i class="bi bi-cloud-download-fill"></i> Submit</button>
                    </div>
                    
                </div>
               
              
            </form>

            <div class="row mt-3">
    <table class="table table-striped">
        <tr>
            <th>S.no</th>
            <th>Group Name</th>
            <th>Total Contacts</th>
            <th>Actions</th>
        </tr>
        <?php
        $i = 0;
        $biz_id = Auth::requireLogin();

        // Fetch all groups for the business
        $stmt = $db->prepare('
            SELECT g.id, g.parent_id, g.group_name, parent.group_name AS parent_name
            FROM gd_groups g
            LEFT JOIN gd_groups parent ON parent.id = g.parent_id
            WHERE g.biz_id = ?
            ORDER BY CASE WHEN g.parent_id IS NULL THEN g.id ELSE g.parent_id END DESC,
                     CASE WHEN g.parent_id IS NULL THEN 0 ELSE 1 END,
                     g.group_name
        ');
        $stmt->bind_param('i', $biz_id);
        $stmt->execute();
        $groupQuery = $stmt->get_result();
        while ($group = mysqli_fetch_assoc($groupQuery)) {
            $i++;
            $group_id = $group['id'];

            $targetIds = ApiSupport::groupTargetIds($db, (int) $biz_id, (int) $group_id, true);
            $placeholders = implode(',', array_fill(0, count($targetIds), '?'));
            $types = 'i' . str_repeat('i', count($targetIds));
            $values = array_merge([(int) $biz_id], $targetIds);
            $countStmt = $db->prepare('SELECT COUNT(DISTINCT contact_id) AS total FROM gd_group_contacts WHERE biz_id = ? AND group_id IN (' . $placeholders . ')');
            $bind = [$types];
            foreach ($values as $index => $value) {
                $bind[] = &$values[$index];
            }
            $countStmt->bind_param(...$bind);
            $countStmt->execute();
            $contactCountQuery = $countStmt->get_result();
            $contactCountResult = mysqli_fetch_assoc($contactCountQuery);
            $contactCount = $contactCountResult['total'] ?? 0;
        ?>
            <tr>
                <td><?php echo $i; ?></td>
                <td>
                    <?php echo !empty($group['parent_id']) ? '-- ' : ''; ?><?php echo h($group['group_name']); ?>
                    <div class="small text-muted"><?php echo !empty($group['parent_id']) ? 'Subgroup of ' . h($group['parent_name'] ?? '') : 'Main group'; ?></div>
                </td>
                <td>
                    <?php echo $contactCount; ?>
                   
                </td>
                <td>
    <a href="<?php echo h(app_url('business/add-contacts-group')); ?>" class="btn btn-primary">Add</a>
    <a href="<?php echo h(app_url('business/view-contacts?group_id=' . $group['id'])); ?>" class="btn btn-success">View</a>
                </td>
            </tr>
        <?php } ?>
    </table>
</div>

        </div>
    </div>


</div>

<?php include 'footer.php'; ?> 

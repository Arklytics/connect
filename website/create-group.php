<?php
include '../session.php';
include '../db_conn.php';

$biz_id = Auth::requireLogin();

include 'header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::verifyCsrf();

    $group = trim((string) ($_POST['group_name'] ?? ''));
    $stmt = $db->prepare('INSERT INTO gd_groups (biz_id, group_name) VALUES (?, ?)');
    $stmt->bind_param('is', $biz_id, $group);

    if ($group !== '' && $stmt->execute()) {
        $message = "New Group Saved!";
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
            <h4 class="mt-3"><i class="bi bi-ui-checks"></i> Add New Group</h4>
            <form action="" method="POST" enctype="multipart/form-data" class="mt-3">
                <?php echo Security::csrfField(); ?>
                <div class="row bg-light mt-2">
                    <div class="col-md-4">
                        <input type="text" class="form-control p-2 shadow" name="group_name" required placeholder="Enter New Group name!">
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
        $stmt = $db->prepare('SELECT id, group_name FROM gd_groups WHERE biz_id = ? ORDER BY id DESC');
        $stmt->bind_param('i', $biz_id);
        $stmt->execute();
        $groupQuery = $stmt->get_result();
        while ($group = mysqli_fetch_assoc($groupQuery)) {
            $i++;
            $group_id = $group['id'];

            // Fetch contact count for the group
            $countStmt = $db->prepare('SELECT COUNT(*) AS total FROM gd_group_contacts WHERE biz_id = ? AND group_id = ?');
            $countStmt->bind_param('ii', $biz_id, $group_id);
            $countStmt->execute();
            $contactCountQuery = $countStmt->get_result();
            $contactCountResult = mysqli_fetch_assoc($contactCountQuery);
            $contactCount = $contactCountResult['total'] ?? 0;
        ?>
            <tr>
                <td><?php echo $i; ?></td>
                <td><?php echo h($group['group_name']); ?></td>
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

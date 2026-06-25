<?php
include 'session.php';
include 'header.php';

$master_id = Auth::requireMaster();
$templates = [];
$templateError = '';
$db = Database::connectOrNull();

if (!$db) {
    $templateError = 'Database is not responding. Restart MySQL in XAMPP, then refresh this page.';
} else {
try {
    $stmt = $db->prepare("SELECT t.template_name, t.message_title, t.status, o.business_name
        FROM gd_orders o
        INNER JOIN gd_whatsapp_templates t ON t.biz_id = o.id
        WHERE o.admin_id = ?
        ORDER BY t.id DESC
        LIMIT 100");
    $stmt->bind_param('i', $master_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $templates[] = $row;
    }
} catch (mysqli_sql_exception $exception) {
    $templateError = 'Templates could not be loaded right now. Please try again after restarting MySQL/Apache.';
}
}
?>



<div class="container-fluid wg-shell">
    <div class="row bg-light">
    <div class="col-lg-2 col-md-3 p-0 wg-sidebar">
            <?php include 'sidebar.php';?>
        </div>

        <div class="col-lg-10 col-md-9 wg-main">

                <h4 class="mt-3"><i class="bi bi-list-columns-reverse"></i> View Templates</h4>

                <table class="table table-striped mt-4">

                    <tr>
                        <th>Sno</th>
                        <th>Business name</th>
                        <th>Template name</th>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Status</th>
                    </tr>

                    <?php if ($templateError !== ''): ?>
                        <tr><td colspan="6" class="text-center text-danger"><?php echo h($templateError); ?></td></tr>
                    <?php elseif (empty($templates)): ?>
                        <tr><td colspan="6" class="text-center">No templates found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($templates as $i => $get3): ?>
                            <tr>
                                    <td><?php echo $i + 1;?></td>
                                    <td><?php echo h($get3['business_name']); ?></td>
                                    <td><?php echo h($get3['template_name']);?></td>
                                    <td><?php echo h($get3['message_title']);?></td>
                                    <td><?php echo 'Marketing';?></td>
                                    <td>
                                        <?php
                                       echo h($get3['status']);
                                        ?>
                                    </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </table>

        </div>
    </div>
</div>

<?php include 'footer.php';?>

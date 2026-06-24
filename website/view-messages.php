<?php
include '../db_conn.php'; // Database connection
include '../session.php';
include 'header.php';

// Default date range (last 7 days)
$from_date = Security::dateFrom($_GET['from_date'] ?? null, date('Y-m-d', strtotime('-7 days')));
$to_date = Security::dateFrom($_GET['to_date'] ?? null, date('Y-m-d'));
$biz_id = Auth::requireLogin();

?>

<div class="container-fluid">
    <div class="row bg-light">
        <div class="col-lg-2 col-md-3 p-0 wg-sidebar">
            <?php include 'sidebar.php'; ?>
        </div>

        <div class="col-lg-10 col-md-9 wg-main">
            <h4 class="mt-3"><i class="bi bi-list-columns-reverse"></i> View Messages</h4>

            <!-- Date Filter Form -->
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="from_date" class="form-label">From Date</label>
                    <input type="date" class="form-control" name="from_date" value="<?php echo h($from_date); ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="to_date" class="form-label">To Date</label>
                    <input type="date" class="form-control" name="to_date" value="<?php echo h($to_date); ?>" required>
                </div>
                <div class="col-md-4 mt-4">
                    <button type="submit" class="btn btn-primary">Filter</button>
                </div>
            </form>

            <table class="table table-striped mt-4">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Phone Number</th>
                        <th>Template Name</th>
                        <th>Message Title</th>
                        <th>Status</th>
                        <th>Sent Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $db->prepare("SELECT s.*, t.template_name FROM gd_sent_messages s 
                              LEFT JOIN gd_whatsapp_templates t ON s.template_id = t.id
                              WHERE s.biz_id = ?
                              AND DATE(s.sent_at) BETWEEN ? AND ?
                              ORDER BY s.id DESC");
                    $stmt->bind_param('iss', $biz_id, $from_date, $to_date);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $count = 1;

                    if (mysqli_num_rows($result) > 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            echo "<tr>
                                    <td>{$count}</td>
                                    <td>" . h($row['phone_number']) . "</td>
                                    <td>" . h($row['template_name']) . "</td>
                                    <td>" . h($row['message_title']) . "</td>
                                    <td><span class='badge bg-" . ($row['status'] == 'success' ? "success" : "danger") . "'>" . h($row['status']) . "</span></td>
                                    <td>" . h(date('d-m-Y H:i:s', strtotime($row['sent_at']))) . "</td>
                                  </tr>";
                            $count++;
                        }
                    } else {
                        echo "<tr><td colspan='6' class='text-center'>No messages found</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<?php
include 'db_conn.php'; // Database connection
include 'session.php';
include 'header.php';

$loadError = '';
?>


    <div class="container-fluid wg-shell">
    <div class="row bg-light">
    <div class="col-lg-2 col-md-3 p-0 wg-sidebar">
            <?php include 'sidebar.php';?>
        </div>

        <div class="col-lg-10 col-md-9 wg-main">

                <h4 class="mt-3"><i class="bi bi-list-columns-reverse"></i> View Orders</h4>

                <table class="table table-striped mt-4">

                    <tr>
                        <th>Sno</th>
                        <th>Business name</th>
                        <th>Contact</th>
                        <th>Location</th>
                        <th>Status</th>
                    </tr>

                    <?php
                    $i=0;
                    $mid = Auth::requireMaster();
                    try {
                        $stmt = $db->prepare('SELECT business_name, business_number, business_location, status FROM gd_orders WHERE admin_id = ? ORDER BY id DESC');
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
                                </tr>


                            <?php
                        }
                    } catch (mysqli_sql_exception $exception) {
                        $loadError = 'Orders could not be loaded right now. Restart MySQL in XAMPP and refresh this page.';
                    }
                    ?>

                    <?php if ($loadError !== ''): ?>
                        <tr><td colspan="5" class="text-center text-danger"><?php echo h($loadError); ?></td></tr>
                    <?php endif; ?>

                </table>

        </div>
    </div>
</div>

<?php include 'footer.php';?>

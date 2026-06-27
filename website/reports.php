<?php
include '../session.php';
include '../db_conn.php';

Auth::requireLogin();
include 'header.php';
?>

<div class="container-fluid">
    <div class="row bg-light">
        <div class="col-lg-2 col-md-3 p-0 wg-sidebar">
            <?php include 'sidebar.php'; ?>
        </div>

        <div class="col-lg-10 col-md-9 wg-main">
            <div class="d-flex flex-wrap gap-3 align-items-center justify-content-between mt-3">
                <div>
                    <h4 class="mb-1"><i class="bi bi-clipboard-data-fill"></i> Reports</h4>
                    <div class="text-muted">Choose the report type you want to review.</div>
                </div>
            </div>

            <div class="row g-3 mt-2">
                <div class="col-md-6">
                    <a href="<?php echo h(app_url('business/view-messages')); ?>" class="text-decoration-none">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <div class="text-muted small mb-2">Messages</div>
                                        <h5 class="mb-2">Message Reports</h5>
                                        <div class="text-dark">Track sent, delivered, read, pending, and failed counts with filters.</div>
                                    </div>
                                    <div class="fs-1 text-primary"><i class="bi bi-chat-dots-fill"></i></div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col-md-6">
                    <a href="<?php echo h(app_url('business/lead-reports')); ?>" class="text-decoration-none">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <div class="text-muted small mb-2">Leads</div>
                                        <h5 class="mb-2">Lead Reports</h5>
                                        <div class="text-dark">Review won, lost, hot, warm, and cold lead performance with filters.</div>
                                    </div>
                                    <div class="fs-1 text-success"><i class="bi bi-people-fill"></i></div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

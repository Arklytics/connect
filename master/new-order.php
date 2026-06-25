<?php
include 'db_conn.php'; // Database connection
include 'session.php';
include 'header.php';

// Initialize message variables
$message = '';
$message_type = '';
$loadError = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    Security::verifyCsrf();

    // Retrieve form data
    $admin_id = Auth::requireMaster();
    $full_name = trim((string) ($_POST['full_name'] ?? ''));
    $mobile_number = preg_replace('/\D+/', '', (string) ($_POST['mobile_number'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = password_hash((string) ($_POST['password'] ?? ''), PASSWORD_BCRYPT);
    $business_name = trim((string) ($_POST['business_name'] ?? ''));
    $business_number = preg_replace('/\D+/', '', (string) ($_POST['business_number'] ?? ''));
    $business_email = trim((string) ($_POST['business_email'] ?? ''));
    $business_location = trim((string) ($_POST['business_location'] ?? ''));
    $business_description = trim((string) ($_POST['business_description'] ?? ''));

    // Handle file upload for business logo
    $business_logo = '';
    if (isset($_FILES['business_logo']) && $_FILES['business_logo']['error'] == 0) {
        $target_dir = __DIR__ . "/uploads/";
        $file_type = strtolower(pathinfo((string) $_FILES["business_logo"]["name"], PATHINFO_EXTENSION));
        $safe_name = bin2hex(random_bytes(16)) . "." . $file_type;
        $target_file = $target_dir . $safe_name;

        // Validate file type (allow only images)
        if (in_array($file_type, ['jpg', 'jpeg', 'png', 'gif'])) {
            if (move_uploaded_file($_FILES["business_logo"]["tmp_name"], $target_file)) {
                $business_logo = "uploads/" . $safe_name;
            } else {
                $message = "Error uploading logo.";
                $message_type = "danger";
            }
        } else {
            $message = "Only image files are allowed.";
            $message_type = "danger";
        }
    }

    // Insert data into the database
    try {
        $stmt = $db->prepare("INSERT INTO gd_orders 
                (admin_id, full_name, mobile_number, email, password, business_name, business_number, business_email, business_location, business_description, business_logo) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('issssssssss', $admin_id, $full_name, $mobile_number, $email, $password, $business_name, $business_number, $business_email, $business_location, $business_description, $business_logo);

        if ($stmt->execute()) {
            $message = "Order added successfully!";
            $message_type = "success";
        } else {
            $message = "Unable to add order.";
            $message_type = "danger";
        }
    } catch (mysqli_sql_exception $exception) {
        $message = "Database temporarily unavailable. Restart MySQL in XAMPP and try again.";
        $message_type = "danger";
    }
}
?>

<!-- Toasts for messages -->
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

<!-- HTML Form -->
<div class="container-fluid wg-shell">
    <div class="row">
        <div class="col-lg-2 col-md-3 p-0 wg-sidebar">
            <?php include 'sidebar.php'; ?>
        </div>
        <div class="col-lg-10 col-md-9 wg-main">
            <h4 class="mt-3"><i class="bi bi-ui-checks"></i> Add New Order</h4>
            <form action="" method="POST" enctype="multipart/form-data" class="mt-3">
                <?php echo Security::csrfField(); ?>
                <div class="row bg-light mt-2">
                    <div class="col-md-3">
                        <input type="text" class="form-control p-2 shadow" name="full_name" required placeholder="Enter Full Name">
                    </div>
                    <div class="col-md-3">
                        <input type="number" class="form-control p-2 shadow" name="mobile_number" required placeholder="Enter Mobile Number">
                    </div>
                    <div class="col-md-3">
                        <input type="email" class="form-control p-2 shadow" name="email" placeholder="Enter Email">
                    </div>
                    <div class="col-md-3">
                        <input type="password" class="form-control p-2 shadow" name="password" required placeholder="Password">
                    </div>
                </div>
                <div class="row bg-light mt-2">
                    <div class="col-md-3">
                        <input type="text" class="form-control p-2 shadow" name="business_name" required placeholder="Enter Business Name">
                    </div>
                    <div class="col-md-3">
                        <input type="number" class="form-control p-2 shadow" name="business_number" required placeholder="Enter Business Number">
                    </div>
                    <div class="col-md-3">
                        <input type="email" class="form-control p-2 shadow" name="business_email" required placeholder="Enter Business Email">
                    </div>
                    <div class="col-md-3">
                        <input type="text" class="form-control p-2 shadow" name="business_location" required placeholder="Location">
                    </div>
                </div>
                <div class="row bg-light mt-2">
                    <div class="col-md-12">
                        <textarea class="form-control" name="business_description" rows="5" placeholder="About Business"></textarea>
                    </div>
                </div>
                <div class="row bg-light mt-2">
                    <div class="col-md-3">
                        <input type="file" class="form-control" name="business_logo">
                        <p style="font-size: 12px;">Upload Business Logo</p>
                    </div>
                </div>
                <button class="btn btn-success" type="submit"><i class="bi bi-cloud-download-fill"></i> Submit</button>
            </form>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

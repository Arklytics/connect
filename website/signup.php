<?php

declare(strict_types=1);

include '../db_conn.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::verifyCsrf();

    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $mobile = preg_replace('/\D+/', '', (string) ($_POST['mobile_number'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $businessName = trim((string) ($_POST['business_name'] ?? ''));
    $businessNumber = preg_replace('/\D+/', '', (string) ($_POST['business_number'] ?? ''));
    $businessEmail = trim((string) ($_POST['business_email'] ?? ''));
    $businessLocation = trim((string) ($_POST['business_location'] ?? ''));

    if ($fullName === '' || $mobile === '' || $password === '' || $businessName === '') {
        $message = 'Please fill all required fields.';
    } else {
        $stmt = $db->prepare('SELECT id FROM gd_orders WHERE mobile_number = ? LIMIT 1');
        $stmt->bind_param('s', $mobile);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();

        if ($existing) {
            $message = 'An account already exists with this mobile number.';
        } else {
            $adminId = '1';
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            $description = '';
            $logo = '';
            $status = 0;
            $token = '';
            $wabaId = '';
            $phoneNumberId = '';
            $webhookUrl = '';

            $stmt = $db->prepare('INSERT INTO gd_orders (admin_id, full_name, mobile_number, email, password, business_name, business_number, business_email, business_location, business_description, business_logo, status, auth_token, whatsapp_id, phone_number_id, webhook_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('sssssssssssissss', $adminId, $fullName, $mobile, $email, $passwordHash, $businessName, $businessNumber, $businessEmail, $businessLocation, $description, $logo, $status, $token, $wabaId, $phoneNumberId, $webhookUrl);

            if ($stmt->execute()) {
                Auth::login((int) mysqli_insert_id($db));
                header('Location: ' . app_url('business/connect-whatsapp'));
                exit();
            }

            $message = 'Unable to create account. Please try again.';
        }
    }
}
?>

<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="<?php echo h(app_url('master/css/style.css')); ?>" rel="stylesheet">
    <title>WhatsGrow - Business Signup</title>
  </head>
  <body class="wg-login-body">
    <main class="wg-login-shell">
      <div class="wg-login-form-wrap">
        <div class="wg-login-card" style="max-width: 620px;">
          <div class="wg-brand text-dark">
            <img class="wg-brand-logo" src="<?php echo h(app_url('website/uploads/connect-logo.png')); ?>" alt="Connect logo">
            <span>Business Signup</span>
          </div>
          <h2>Create business account</h2>
          <p class="subtitle">Create your workspace, then connect your WhatsApp Business account.</p>

          <?php if ($message !== ''): ?>
            <div class="alert alert-danger"><?php echo h($message); ?></div>
          <?php endif; ?>

          <form action="" method="post">
            <?php echo Security::csrfField(); ?>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label" for="full_name">Full Name</label>
                <input type="text" class="form-control" id="full_name" name="full_name" required>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label" for="mobile_number">Mobile Number</label>
                <input type="tel" inputmode="numeric" class="form-control" id="mobile_number" name="mobile_number" required>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label" for="email">Email</label>
                <input type="email" class="form-control" id="email" name="email">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label" for="password">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label" for="business_name">Business Name</label>
                <input type="text" class="form-control" id="business_name" name="business_name" required>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label" for="business_number">Business Number</label>
                <input type="tel" inputmode="numeric" class="form-control" id="business_number" name="business_number">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label" for="business_email">Business Email</label>
                <input type="email" class="form-control" id="business_email" name="business_email">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label" for="business_location">Location</label>
                <input type="text" class="form-control" id="business_location" name="business_location">
              </div>
            </div>
            <button type="submit" class="btn btn-success w-100">
              <i class="bi bi-person-plus me-1"></i> Create Account
            </button>
            <div class="text-center mt-3">
              <a href="<?php echo h(app_url('business/login')); ?>" class="text-decoration-none">Already have an account? Sign in</a>
            </div>
          </form>
        </div>
      </div>
    </main>
  </body>
</html>

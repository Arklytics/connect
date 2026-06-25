<?php

declare(strict_types=1);

include 'db_conn.php';

$message = '';

if (isset($_POST['login'])) {
    Security::verifyCsrf();

    $mobile = preg_replace('/\D+/', '', (string) ($_POST['mobile'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    try {
        $stmt = $db->prepare('SELECT id, password FROM gd_admin WHERE admin_number = ? LIMIT 1');
        $stmt->bind_param('s', $mobile);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();

        $storedPassword = (string) ($admin['password'] ?? '');
        $validPassword = $admin && (
            password_verify($password, $storedPassword) ||
            hash_equals($storedPassword, $password)
        );

        if ($validPassword) {
            Auth::loginMaster((int) $admin['id']);
            header('Location: ' . app_url('master'));
            exit();
        }

        $message = 'Invalid mobile number or password.';
    } catch (mysqli_sql_exception $exception) {
        $message = 'Database temporarily unavailable while signing in. Restart MySQL in XAMPP and try again.';
    }
}
?>

<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css"
      rel="stylesheet"
      integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC"
      crossorigin="anonymous"
    >
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="<?php echo h(app_url('master/css/style.css')); ?>" rel="stylesheet">
    <title>WhatsGrow - Master Login</title>
  </head>
  <body class="wg-login-body">
    <main class="wg-login-shell">
      <div class="wg-login-form-wrap">
        <div class="wg-login-card">
          <div class="wg-brand text-dark">
            <img class="wg-brand-logo" src="<?php echo h(app_url('master/uploads/connect-logo.png')); ?>" alt="Connect logo">
            <span>Master Console</span>
          </div>
          <h2>Welcome back</h2>
          <p class="subtitle">Sign in with your admin mobile number.</p>

          <?php if ($message !== ''): ?>
            <div class="alert alert-danger"><?php echo h($message); ?></div>
          <?php endif; ?>

          <form action="" method="post">
            <?php echo Security::csrfField(); ?>
            <div class="mb-3">
              <label for="mb" class="form-label">Mobile Number</label>
              <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-phone"></i></span>
                <input type="tel" inputmode="numeric" class="form-control" id="mb" name="mobile" placeholder="Enter mobile number" required>
              </div>
            </div>
            <div class="mb-4">
              <label for="password" class="form-label">Password</label>
              <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-lock"></i></span>
                <input type="password" class="form-control" id="password" name="password" placeholder="Enter password" required>
              </div>
            </div>
            <button type="submit" name="login" class="btn btn-success w-100">
              <i class="bi bi-box-arrow-in-right me-1"></i> Sign in
            </button>
          </form>
        </div>
      </div>
    </main>
    <script
      src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"
      integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM"
      crossorigin="anonymous"
    ></script>
  </body>
</html>

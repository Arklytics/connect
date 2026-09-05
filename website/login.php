<?php

declare(strict_types=1);

include '../db_conn.php';

$message = '';
$messageType = 'danger';

Security::startSession();

function forgotNormalizePhone(string $phone): string
{
    return ApiSupport::normalizePhone($phone);
}

function forgotStoredMobile(string $phone): string
{
    return ltrim(forgotNormalizePhone($phone), '+');
}

function forgotPendingData(): array
{
    $pending = $_SESSION['forgot_password_pending'] ?? [];
    if (!is_array($pending)) {
        return [];
    }

    if ((int) ($pending['expires_at'] ?? 0) < time()) {
        unset($_SESSION['forgot_password_pending']);
        return [];
    }

    return $pending;
}

function forgotSendWhatsappOtp(mysqli $db, string $to, string $otp): array
{
    $apiKey = trim((string) AppSettings::getGlobal($db, 'SIGNUP_OTP_API_KEY', Config::get('SIGNUP_OTP_API_KEY', '')));
    if ($apiKey === '') {
        return [
            'ok' => false,
            'error' => 'WhatsApp OTP sender is not configured. Add SIGNUP_OTP_API_KEY from the business WhatsApp Connection API key.',
        ];
    }

    $bizId = (int) trim((string) AppSettings::getGlobal($db, 'SIGNUP_OTP_BIZ_ID', Config::get('SIGNUP_OTP_BIZ_ID', '0')));
    $templateName = trim((string) AppSettings::getGlobal($db, 'SIGNUP_OTP_TEMPLATE', Config::get('SIGNUP_OTP_TEMPLATE', 'otp_for_connect')));
    $language = trim((string) AppSettings::getGlobal($db, 'SIGNUP_OTP_LANGUAGE', Config::get('SIGNUP_OTP_LANGUAGE', 'en_US')));
    $payload = [
        'kind' => 'authentication',
        'template_name' => $templateName !== '' ? $templateName : 'otp_for_connect',
        'language' => $language !== '' ? $language : 'en_US',
        'to' => $to,
        'otp' => $otp,
    ];

    if ($bizId > 0) {
        $payload['biz_id'] = $bizId;
    }

    $requestJson = ApiSupport::encodeJson($payload);
    $curl = curl_init(rtrim(app_public_url(''), '/') . '/api/whatsapp/send');
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $requestJson !== null ? $requestJson : json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($curl);
    $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);

    $responseBody = is_string($response) ? $response : '';
    $decoded = json_decode($responseBody, true);
    $ok = $curlError === '' && $httpCode >= 200 && $httpCode < 300 && is_array($decoded) && !empty($decoded['ok']);

    return [
        'ok' => $ok,
        'http_code' => $httpCode,
        'error' => $curlError !== ''
            ? 'cURL error: ' . $curlError
            : (is_array($decoded) ? (string) ($decoded['error'] ?? 'OTP API request failed.') : trim($responseBody)),
    ];
}

$forgotMode = isset($_GET['forgot']) || !empty(forgotPendingData());

if (isset($_POST['login'])) {
    Security::verifyCsrf();

    $mobile = preg_replace('/\D+/', '', (string) ($_POST['mobile'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $stmt = $db->prepare('SELECT id, password FROM gd_orders WHERE mobile_number = ? LIMIT 1');
    $stmt->bind_param('s', $mobile);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, (string) $user['password'])) {
        Auth::login((int) $user['id']);
        header('Location: ' . app_url('business'));
        exit();
    }

    $message = 'Invalid mobile number or password.';
    $stmt->close();
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::verifyCsrf();

    $action = trim((string) ($_POST['action'] ?? ''));

    if ($action === 'forgot_request' || $action === 'forgot_resend') {
        $mobile = '';
        $whatsappNumber = '';

        if ($action === 'forgot_resend') {
            $pending = forgotPendingData();
            $mobile = (string) ($pending['mobile_number'] ?? '');
            $whatsappNumber = $mobile !== '' ? '+' . $mobile : '';
        } else {
            $whatsappNumber = forgotNormalizePhone((string) ($_POST['mobile'] ?? ''));
            $mobile = forgotStoredMobile($whatsappNumber);
        }

        if ($mobile === '' || strlen($mobile) < 10) {
            $message = 'Please enter a valid WhatsApp registered number.';
            $forgotMode = true;
        } else {
            $stmt = $db->prepare('SELECT id FROM gd_orders WHERE mobile_number = ? LIMIT 1');
            $stmt->bind_param('s', $mobile);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();

            if (!$user) {
                $message = 'No account found with this WhatsApp number.';
                $forgotMode = true;
            } else {
                $otpCode = (string) random_int(100000, 999999);
                $sendResult = forgotSendWhatsappOtp($db, $whatsappNumber, $otpCode);

                if (!($sendResult['ok'] ?? false)) {
                    $message = 'Could not send WhatsApp OTP: ' . (string) ($sendResult['error'] ?? 'Unknown error');
                    $forgotMode = true;
                } else {
                    $_SESSION['forgot_password_pending'] = [
                        'biz_id' => (int) $user['id'],
                        'mobile_number' => $mobile,
                        'otp_hash' => hash('sha256', $otpCode),
                        'expires_at' => time() + 600,
                    ];
                    $message = $action === 'forgot_resend'
                        ? 'A new OTP was sent to your WhatsApp number.'
                        : 'OTP sent to your WhatsApp number. Enter it below to reset your password.';
                    $messageType = 'success';
                    $forgotMode = true;
                }
            }
        }
    } elseif ($action === 'forgot_verify') {
        $pending = forgotPendingData();
        $otp = preg_replace('/\D+/', '', (string) ($_POST['otp'] ?? ''));
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if (empty($pending)) {
            $message = 'Your OTP session expired. Please request a new OTP.';
            $forgotMode = true;
        } elseif ($otp === '' || !hash_equals((string) ($pending['otp_hash'] ?? ''), hash('sha256', $otp))) {
            $message = 'Invalid OTP. Please check WhatsApp and try again.';
            $forgotMode = true;
        } elseif (strlen($newPassword) < 6) {
            $message = 'Password must be at least 6 characters.';
            $forgotMode = true;
        } elseif ($newPassword !== $confirmPassword) {
            $message = 'Passwords do not match.';
            $forgotMode = true;
        } else {
            $bizId = (int) ($pending['biz_id'] ?? 0);
            $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmt = $db->prepare('UPDATE gd_orders SET password = ? WHERE id = ? LIMIT 1');
            $stmt->bind_param('si', $passwordHash, $bizId);

            if ($stmt->execute()) {
                unset($_SESSION['forgot_password_pending']);
                $message = 'Password updated. Please sign in with your new password.';
                $messageType = 'success';
                $forgotMode = false;
            } else {
                $message = 'Unable to update password. Please try again.';
                $forgotMode = true;
            }
        }
    }
}

$pendingForgot = forgotPendingData();
$showForgotOtpStep = $forgotMode && !empty($pendingForgot);
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
    <title>Arklytics Connect - Business Login</title>
  </head>
  <body class="wg-login-body">
    <main class="wg-login-shell">
      <div class="wg-login-form-wrap">
        <div class="wg-login-card">
          <div class="wg-brand text-dark">
            <img class="wg-brand-logo" src="<?php echo h(app_url('website/uploads/connect-logo.png')); ?>" alt="Connect logo">
            <span>Business Login</span>
          </div>

          <?php if ($forgotMode): ?>
            <h2>Reset password</h2>
            <p class="subtitle">Use your WhatsApp registered number to verify your account.</p>
          <?php else: ?>
            <h2>Welcome back</h2>
            <p class="subtitle">Access your Arklytics Connect business workspace.</p>
          <?php endif; ?>

          <?php if ($message !== ''): ?>
            <div class="alert alert-<?php echo h($messageType); ?>"><?php echo h($message); ?></div>
          <?php endif; ?>

          <form action="" method="post">
            <?php echo Security::csrfField(); ?>
            <?php if ($forgotMode): ?>
              <?php if ($showForgotOtpStep): ?>
                <input type="hidden" name="action" value="forgot_verify">
                <div class="mb-3">
                  <label class="form-label">WhatsApp Registered Number</label>
                  <input type="text" class="form-control" value="<?php echo h('+' . (string) ($pendingForgot['mobile_number'] ?? '')); ?>" disabled>
                </div>
                <div class="mb-3">
                  <label for="otp" class="form-label">WhatsApp OTP</label>
                  <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-shield-check"></i></span>
                    <input type="text" inputmode="numeric" maxlength="6" class="form-control" id="otp" name="otp" placeholder="Enter 6-digit OTP" required autofocus>
                  </div>
                </div>
                <div class="mb-3">
                  <label for="new_password" class="form-label">New Password</label>
                  <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control" id="new_password" name="new_password" placeholder="Enter new password" required>
                  </div>
                </div>
                <div class="mb-4">
                  <label for="confirm_password" class="form-label">Confirm Password</label>
                  <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-lock-fill"></i></span>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
                  </div>
                </div>
                <button type="submit" class="btn btn-success w-100">
                  <i class="bi bi-check2-circle me-1"></i> Update Password
                </button>
                <button type="submit" name="action" value="forgot_resend" class="btn btn-light w-100 mt-2">
                  <i class="bi bi-arrow-clockwise me-1"></i> Resend OTP
                </button>
              <?php else: ?>
                <input type="hidden" name="action" value="forgot_request">
                <div class="mb-4">
                  <label for="forgot_mobile" class="form-label">WhatsApp Registered Number</label>
                  <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-whatsapp"></i></span>
                    <input type="tel" inputmode="tel" class="form-control" id="forgot_mobile" name="mobile" placeholder="+91 98765 43210" required autofocus>
                  </div>
                </div>
                <button type="submit" class="btn btn-success w-100">
                  <i class="bi bi-send me-1"></i> Send Reset OTP
                </button>
              <?php endif; ?>
              <div class="text-center mt-3">
                <a href="<?php echo h(app_url('business/login')); ?>" class="text-decoration-none">Back to sign in</a>
              </div>
            <?php else: ?>
              <div class="mb-3">
                <label for="mb" class="form-label">Mobile Number</label>
                <div class="input-group">
                  <span class="input-group-text bg-white"><i class="bi bi-phone"></i></span>
                  <input type="tel" inputmode="numeric" class="form-control" id="mb" name="mobile" placeholder="Enter mobile number" required>
                </div>
              </div>
              <div class="mb-2">
                <label for="password" class="form-label">Password</label>
                <div class="input-group">
                  <span class="input-group-text bg-white"><i class="bi bi-lock"></i></span>
                  <input type="password" class="form-control" id="password" name="password" placeholder="Enter password" required>
                </div>
              </div>
              <div class="text-end mb-4">
                <a href="<?php echo h(app_url('business/login')); ?>?forgot=1" class="text-decoration-none small">Forgot password?</a>
              </div>
              <button type="submit" name="login" class="btn btn-success w-100">
                <i class="bi bi-box-arrow-in-right me-1"></i> Sign in
              </button>
            <?php endif; ?>
            <div class="text-center mt-4">
              <p class="text-muted mb-2">New business?</p>
              <a href="<?php echo h(app_url('business/signup')); ?>" class="btn btn-light w-100">
                <i class="bi bi-person-plus me-1"></i> Create Account
              </a>
              <div class="small text-muted mt-3">
                <a href="<?php echo h(app_url('privacy-policy')); ?>" class="text-decoration-none me-2">Privacy Policy</a>
                <a href="<?php echo h(app_url('terms-conditions')); ?>" class="text-decoration-none me-2">Terms</a>
                <a href="<?php echo h(app_url('crm-privacy')); ?>" class="text-decoration-none">CRM Privacy</a>
              </div>
            </div>
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

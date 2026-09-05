<?php

declare(strict_types=1);

include '../db_conn.php';

$message = '';
$messageType = 'danger';
$pendingSignup = [];

Security::startSession();

function signupNormalizePhone(string $phone): string
{
    return ApiSupport::normalizePhone($phone);
}

function signupStoredMobile(string $phone): string
{
    return ltrim(signupNormalizePhone($phone), '+');
}

function signupPendingData(): array
{
    $pending = $_SESSION['signup_pending'] ?? [];
    if (!is_array($pending)) {
        return [];
    }

    if ((int) ($pending['expires_at'] ?? 0) < time()) {
        unset($_SESSION['signup_pending']);
        return [];
    }

    return $pending;
}

function signupOtpComponents(string $otp): array
{
    return [
        [
            'type' => 'body',
            'parameters' => [
                [
                    'type' => 'text',
                    'text' => $otp,
                ],
            ],
        ],
        [
            'type' => 'button',
            'sub_type' => 'url',
            'index' => '0',
            'parameters' => [
                [
                    'type' => 'text',
                    'text' => $otp,
                ],
            ],
        ],
    ];
}

function signupSendWhatsappOtp(mysqli $db, string $to, string $otp): array
{
    $phoneNumberId = trim((string) AppSettings::getGlobal($db, 'SIGNUP_OTP_PHONE_NUMBER_ID', Config::get('SIGNUP_OTP_PHONE_NUMBER_ID', '')));
    if ($phoneNumberId === '') {
        $phoneNumberId = trim((string) AppSettings::getGlobal($db, 'META_PHONE_NUMBER_ID', Config::get('META_PHONE_NUMBER_ID', '')));
    }

    $accessToken = trim((string) AppSettings::getGlobal($db, 'SIGNUP_OTP_ACCESS_TOKEN', Config::get('SIGNUP_OTP_ACCESS_TOKEN', '')));
    if ($accessToken === '') {
        $accessToken = trim((string) AppSettings::getGlobal($db, 'META_ACCESS_TOKEN', Config::get('META_ACCESS_TOKEN', '')));
    }

    if ($phoneNumberId === '' || $accessToken === '') {
        return [
            'ok' => false,
            'error' => 'WhatsApp OTP sender is not configured. Add SIGNUP_OTP_PHONE_NUMBER_ID and SIGNUP_OTP_ACCESS_TOKEN, or META_PHONE_NUMBER_ID and META_ACCESS_TOKEN.',
        ];
    }

    $templateName = trim((string) AppSettings::getGlobal($db, 'SIGNUP_OTP_TEMPLATE', Config::get('SIGNUP_OTP_TEMPLATE', 'login_otp')));
    $language = trim((string) AppSettings::getGlobal($db, 'SIGNUP_OTP_LANGUAGE', Config::get('SIGNUP_OTP_LANGUAGE', 'en_US')));
    $payload = ApiSupport::whatsappTemplatePayload($to, $templateName !== '' ? $templateName : 'login_otp', $language !== '' ? $language : 'en_US', signupOtpComponents($otp));

    return ApiSupport::whatsappSendRequest($phoneNumberId, $accessToken, $payload);
}

$pendingSignup = signupPendingData();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::verifyCsrf();

    $action = trim((string) ($_POST['action'] ?? 'request_otp'));
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $whatsappNumber = signupNormalizePhone((string) ($_POST['whatsapp_number'] ?? $_POST['mobile_number'] ?? ''));
    $mobile = signupStoredMobile($whatsappNumber);
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $businessName = trim((string) ($_POST['business_name'] ?? ''));
    $businessNumber = $mobile;
    $businessEmail = trim((string) ($_POST['business_email'] ?? ''));
    $businessLocation = trim((string) ($_POST['business_location'] ?? ''));
    $otp = preg_replace('/\D+/', '', (string) ($_POST['otp'] ?? ''));

    if ($action === 'verify_otp') {
        $pendingSignup = signupPendingData();

        if (empty($pendingSignup)) {
            $message = 'Your OTP session expired. Please request a new OTP.';
        } elseif ($otp === '' || !hash_equals((string) ($pendingSignup['otp_hash'] ?? ''), hash('sha256', $otp))) {
            $message = 'Invalid OTP. Please check WhatsApp and try again.';
        } else {
            $data = is_array($pendingSignup['data'] ?? null) ? $pendingSignup['data'] : [];
            $fullName = (string) ($data['full_name'] ?? '');
            $mobile = (string) ($data['mobile_number'] ?? '');
            $email = (string) ($data['email'] ?? '');
            $passwordHash = (string) ($data['password_hash'] ?? '');
            $businessName = (string) ($data['business_name'] ?? '');
            $businessNumber = (string) ($data['business_number'] ?? $mobile);
            $businessEmail = (string) ($data['business_email'] ?? '');
            $businessLocation = (string) ($data['business_location'] ?? '');

            $stmt = $db->prepare('SELECT id FROM gd_orders WHERE mobile_number = ? LIMIT 1');
            $stmt->bind_param('s', $mobile);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();

            if ($existing) {
                unset($_SESSION['signup_pending']);
                $message = 'An account already exists with this WhatsApp number.';
            } else {
                $adminId = '1';
                $description = '';
                $logo = '';
                $status = 0;
                $token = '';
                $wabaId = '';
                $phoneNumberId = '';
                $webhookUrl = app_public_url('incoming.php');

                $stmt = $db->prepare('INSERT INTO gd_orders (admin_id, full_name, mobile_number, email, password, business_name, business_number, business_email, business_location, business_description, business_logo, status, auth_token, whatsapp_id, phone_number_id, webhook_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('sssssssssssissss', $adminId, $fullName, $mobile, $email, $passwordHash, $businessName, $businessNumber, $businessEmail, $businessLocation, $description, $logo, $status, $token, $wabaId, $phoneNumberId, $webhookUrl);

                if ($stmt->execute()) {
                    unset($_SESSION['signup_pending']);
                    Auth::login((int) mysqli_insert_id($db));
                    header('Location: ' . app_url('business/connect-whatsapp'));
                    exit();
                }

                $message = 'Unable to create account. Please try again.';
            }
        }
    } elseif ($action === 'resend_otp') {
        $pendingSignup = signupPendingData();
        $data = is_array($pendingSignup['data'] ?? null) ? $pendingSignup['data'] : [];
        $mobile = (string) ($data['mobile_number'] ?? '');
        $whatsappNumber = $mobile !== '' ? '+' . $mobile : '';

        if (empty($pendingSignup) || $whatsappNumber === '') {
            $message = 'Your OTP session expired. Please request a new OTP.';
        } else {
            $otpCode = (string) random_int(100000, 999999);
            $sendResult = signupSendWhatsappOtp($db, $whatsappNumber, $otpCode);

            if (!($sendResult['ok'] ?? false)) {
                $message = 'Could not resend WhatsApp OTP: ' . (string) ($sendResult['failure_reason'] ?? $sendResult['error'] ?? 'Unknown error');
            } else {
                $_SESSION['signup_pending']['otp_hash'] = hash('sha256', $otpCode);
                $_SESSION['signup_pending']['expires_at'] = time() + 600;
                $pendingSignup = signupPendingData();
                $message = 'A new OTP was sent to your WhatsApp registered number.';
                $messageType = 'success';
            }
        }
    } elseif ($businessName === '' || $businessLocation === '' || $mobile === '' || $password === '') {
        $message = 'Please fill business name, location, WhatsApp number, and password.';
    } elseif (strlen($mobile) < 10) {
        $message = 'Please enter a valid WhatsApp registered number with country code.';
    } else {
        $stmt = $db->prepare('SELECT id FROM gd_orders WHERE mobile_number = ? LIMIT 1');
        $stmt->bind_param('s', $mobile);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();

        if ($existing) {
            $message = 'An account already exists with this WhatsApp number.';
        } else {
            $otpCode = (string) random_int(100000, 999999);
            $sendResult = signupSendWhatsappOtp($db, $whatsappNumber, $otpCode);

            if (!($sendResult['ok'] ?? false)) {
                $message = 'Could not send WhatsApp OTP: ' . (string) ($sendResult['failure_reason'] ?? $sendResult['error'] ?? 'Unknown error');
            } else {
                $_SESSION['signup_pending'] = [
                    'otp_hash' => hash('sha256', $otpCode),
                    'expires_at' => time() + 600,
                    'data' => [
                        'full_name' => $fullName !== '' ? $fullName : $businessName,
                        'mobile_number' => $mobile,
                        'email' => $email,
                        'password_hash' => password_hash($password, PASSWORD_BCRYPT),
                        'business_name' => $businessName,
                        'business_number' => $businessNumber,
                        'business_email' => $businessEmail !== '' ? $businessEmail : $email,
                        'business_location' => $businessLocation,
                    ],
                ];
                $pendingSignup = signupPendingData();
                $message = 'OTP sent to your WhatsApp registered number. Enter it below to complete signup.';
                $messageType = 'success';
            }
        }
    }
}

$pendingData = is_array($pendingSignup['data'] ?? null) ? $pendingSignup['data'] : [];
$showOtpStep = !empty($pendingSignup);
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
    <title>Arklytics Connect - Business Signup</title>
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
          <p class="subtitle">Use your WhatsApp registered number to create and verify your workspace.</p>

          <?php if ($message !== ''): ?>
            <div class="alert alert-<?php echo h($messageType); ?>"><?php echo h($message); ?></div>
          <?php endif; ?>

          <form action="" method="post">
            <?php echo Security::csrfField(); ?>
            <?php if ($showOtpStep): ?>
              <input type="hidden" name="action" value="verify_otp">
              <div class="mb-3">
                <label class="form-label">WhatsApp Registered Number</label>
                <input type="text" class="form-control" value="<?php echo h('+' . (string) ($pendingData['mobile_number'] ?? '')); ?>" disabled>
              </div>
              <div class="mb-4">
                <label class="form-label" for="otp">WhatsApp OTP</label>
                <input type="text" inputmode="numeric" maxlength="6" class="form-control" id="otp" name="otp" placeholder="Enter 6-digit OTP" required autofocus>
              </div>
              <button type="submit" class="btn btn-success w-100">
                <i class="bi bi-shield-check me-1"></i> Verify & Create Account
              </button>
              <button type="submit" name="action" value="resend_otp" class="btn btn-light w-100 mt-2">
                <i class="bi bi-arrow-clockwise me-1"></i> Resend OTP
              </button>
            <?php else: ?>
              <input type="hidden" name="action" value="request_otp">
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label class="form-label" for="business_name">Business Name</label>
                  <input type="text" class="form-control" id="business_name" name="business_name" value="<?php echo h($businessName ?? ''); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label" for="business_location">Location</label>
                  <input type="text" class="form-control" id="business_location" name="business_location" value="<?php echo h($businessLocation ?? ''); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label" for="whatsapp_number">WhatsApp Registered Number</label>
                  <input type="tel" inputmode="tel" class="form-control" id="whatsapp_number" name="whatsapp_number" placeholder="+91 98765 43210" value="<?php echo h($whatsappNumber ?? ''); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label" for="password">Password</label>
                  <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label" for="full_name">Contact Name</label>
                  <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo h($fullName ?? ''); ?>">
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label" for="email">Email</label>
                  <input type="email" class="form-control" id="email" name="email" value="<?php echo h($email ?? ''); ?>">
                </div>
              </div>
              <button type="submit" class="btn btn-success w-100">
                <i class="bi bi-whatsapp me-1"></i> Send WhatsApp OTP
              </button>
            <?php endif; ?>
            <div class="text-center mt-3">
              <a href="<?php echo h(app_url('business/login')); ?>" class="text-decoration-none">Already have an account? Sign in</a>
            </div>
            <div class="text-center mt-3 small text-muted">
              <a href="<?php echo h(app_url('privacy-policy')); ?>" class="text-decoration-none me-2">Privacy Policy</a>
              <a href="<?php echo h(app_url('terms-conditions')); ?>" class="text-decoration-none me-2">Terms</a>
              <a href="<?php echo h(app_url('crm-privacy')); ?>" class="text-decoration-none">CRM Privacy</a>
            </div>
          </form>
        </div>
      </div>
    </main>
  </body>
</html>

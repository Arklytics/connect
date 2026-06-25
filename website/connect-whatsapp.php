<?php
include '../session.php';
include '../db_conn.php';

$biz_id = Auth::requireLogin();

include 'header.php';
$message = '';
$message_type = 'success';
$appId = trim((string) Config::get('META_APP_ID', ''));
$configId = trim((string) Config::get('META_CONFIG_ID', ''));
$appSecret = trim((string) Config::get('META_APP_SECRET', ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::verifyCsrf();

    $code = trim((string) ($_POST['code'] ?? ''));
    $accessTokenInput = trim((string) ($_POST['access_token'] ?? ''));
    $wabaId = trim((string) ($_POST['waba_id'] ?? ''));
    $phoneNumberId = trim((string) ($_POST['phone_number_id'] ?? ''));
    $accessToken = '';
    $hasIds = ($wabaId !== '' && $phoneNumberId !== '');

    if ($accessTokenInput !== '') {
        $accessToken = $accessTokenInput;
    } elseif ($code !== '' && $appId !== '' && $appSecret !== '') {
        $tokenUrl = 'https://graph.facebook.com/v18.0/oauth/access_token'
            . '?client_id=' . rawurlencode($appId)
            . '&client_secret=' . rawurlencode($appSecret)
            . '&code=' . rawurlencode($code);

        $ch = curl_init($tokenUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $response = curl_exec($ch);
        $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $tokenData = json_decode((string) $response, true);
        if ($curlError === '' && $httpStatus >= 200 && $httpStatus < 300) {
            $accessToken = (string) ($tokenData['access_token'] ?? '');
        } else {
            $message = 'Signup completed, but token exchange failed: ' . ($tokenData['error']['message'] ?? $curlError ?: 'Unknown error');
            $message_type = 'warning';
        }
    }

    if ($wabaId === '' && $phoneNumberId === '' && $accessToken === '') {
        $message = 'No WhatsApp account data was received. Complete Embedded Signup and try again.';
        $message_type = 'danger';
    } else {
        if ($accessToken !== '') {
            $stmt = $db->prepare("UPDATE gd_orders SET auth_token = ?, whatsapp_id = COALESCE(NULLIF(?, ''), whatsapp_id), phone_number_id = COALESCE(NULLIF(?, ''), phone_number_id), status = ? WHERE id = ?");
            $status = $hasIds ? '1' : '0';
            $stmt->bind_param('ssssi', $accessToken, $wabaId, $phoneNumberId, $status, $biz_id);
        } else {
            $stmt = $db->prepare("UPDATE gd_orders SET whatsapp_id = COALESCE(NULLIF(?, ''), whatsapp_id), phone_number_id = COALESCE(NULLIF(?, ''), phone_number_id), status = ? WHERE id = ?");
            $status = $hasIds ? '1' : '0';
            $stmt->bind_param('sssi', $wabaId, $phoneNumberId, $status, $biz_id);
        }

        $saved = $stmt->execute();
        if ($saved && $message_type !== 'warning' && $hasIds) {
            $message = 'Your business is now connected to WhatsApp.';
            $message_type = 'success';
        } elseif ($saved && $hasIds) {
            $message .= ' IDs were saved. Add token manually if needed.';
        } elseif ($saved) {
            $message = 'WhatsApp token saved, but WABA ID and Phone Number ID are still missing.';
            $message_type = 'warning';
        } else {
            $message = 'Could not save WhatsApp connection details.';
            $message_type = 'danger';
        }
    }
}

$stmt = $db->prepare('SELECT business_name, auth_token, whatsapp_id, phone_number_id, status FROM gd_orders WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $biz_id);
$stmt->execute();
$business = $stmt->get_result()->fetch_assoc() ?: [];
$isConnected = (($business['status'] ?? '0') == '1');
?>

<div class="position-fixed top-0 end-0 p-3" style="z-index: 5;">
    <?php if ($message !== ''): ?>
        <div class="toast align-items-center text-bg-<?php echo h($message_type); ?> border-0 show" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body"><?php echo h($message); ?></div>
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

        <main class="col-lg-10 col-md-9 wg-main">
            <h4><i class="bi bi-link-45deg"></i> Connect WhatsApp</h4>

            <?php if ($appId === '' || $configId === ''): ?>
                <div class="alert alert-warning">
                    Add <strong>META_APP_ID</strong> and <strong>META_CONFIG_ID</strong> in <code>.env</code> to enable Embedded Signup.
                </div>
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-lg-5">
                    <div class="wg-card p-4">
                        <h5 class="mb-3">Current Connection</h5>
                        <?php if ($isConnected): ?>
                            <div class="alert alert-success py-2">Your business is now connected to WhatsApp.</div>
                        <?php elseif (!empty($business['auth_token']) || !empty($business['whatsapp_id']) || !empty($business['phone_number_id'])): ?>
                            <div class="alert alert-warning py-2">Connection is incomplete. Please add the missing WhatsApp IDs below.</div>
                        <?php endif; ?>
                        <p class="mb-2"><strong>Business:</strong> <?php echo h($business['business_name'] ?? ''); ?></p>
                        <p class="mb-2"><strong>WhatsApp Business ID:</strong> <?php echo h(!empty($business['whatsapp_id']) ? $business['whatsapp_id'] : 'Not connected'); ?></p>
                        <p class="mb-2"><strong>Phone Number ID:</strong> <?php echo h(!empty($business['phone_number_id']) ? $business['phone_number_id'] : 'Not connected'); ?></p>
                        <p class="mb-0"><strong>Status:</strong> <?php echo $isConnected ? 'Connected' : 'Waiting for IDs'; ?></p>
    <a href="<?php echo h(app_url('business/profile')); ?>" class="btn btn-outline-success mt-3">
                            <i class="bi bi-person-badge me-1"></i> View Profile
                        </a>
                    </div>
                </div>

                <div class="col-lg-7">
                    <form method="post" id="connectForm">
                        <?php echo Security::csrfField(); ?>
                        <input type="hidden" name="code" id="signupCode">
                        <input type="hidden" name="access_token" id="signupAccessToken">

                        <h5 class="mb-3">Embedded Signup</h5>
                        <p class="text-muted">The business owner signs in with Meta, selects or creates a WhatsApp Business Account, connects a phone number, and returns the IDs to this app.</p>
                        <button type="button" class="btn btn-primary" onclick="launchWhatsAppSignup()" <?php echo ($appId === '' || $configId === '') ? 'disabled' : ''; ?>>
                            <i class="bi bi-whatsapp me-1"></i> Start WhatsApp Signup
                        </button>
                        <input type="hidden" name="waba_id" id="wabaId" value="<?php echo h($business['whatsapp_id'] ?? ''); ?>">
                        <input type="hidden" name="phone_number_id" id="phoneNumberId" value="<?php echo h($business['phone_number_id'] ?? ''); ?>">
                        <div id="signupStatus" class="small text-muted mt-3"></div>
                        <div class="mt-3">
                            <p class="text-muted small mb-0">If Meta does not return the IDs automatically, your master admin can sync them from the control panel.</p>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
window.fbAsyncInit = function () {
  FB.init({
    appId: "<?php echo h($appId); ?>",
    autoLogAppEvents: true,
    xfbml: true,
    version: "v18.0"
  });
};

let waitingForSignupPayload = false;
let signupSubmitTimer = null;

function maybeSubmitSignupForm() {
  const code = document.getElementById("signupCode").value;
  const accessToken = document.getElementById("signupAccessToken").value;
  const wabaId = document.getElementById("wabaId").value;
  const phoneNumberId = document.getElementById("phoneNumberId").value;

  if ((code || accessToken) && wabaId && phoneNumberId) {
    document.getElementById("signupStatus").textContent = "Saving WhatsApp connection details...";
    document.getElementById("connectForm").submit();
    return true;
  }

  return false;
}

window.addEventListener("message", function (event) {
  const origin = String(event.origin || "");
  if (!origin.includes("facebook.com")) {
    return;
  }

  let data = event.data;
  if (typeof data === "string") {
    try {
      data = JSON.parse(data);
    } catch (error) {
      return;
    }
  }

  const eventType = data.type || data.event || data.name || '';
  if (eventType !== "WA_EMBEDDED_SIGNUP" && eventType !== "whatsapp_embedded_signup") {
    return;
  }

  const payload = data.data || data.payload || data.response || data;
  const wabaId = payload.waba_id || payload.wabaId || payload.whatsapp_business_account_id || '';
  const phoneNumberId = payload.phone_number_id || payload.phoneNumberId || payload.phone_number || '';

  if (wabaId) {
    document.getElementById("wabaId").value = wabaId;
  }
  if (phoneNumberId) {
    document.getElementById("phoneNumberId").value = phoneNumberId;
  }

  if (waitingForSignupPayload) {
    document.getElementById("signupStatus").textContent = "WhatsApp details received. Finishing connection...";
    if (maybeSubmitSignupForm()) {
      waitingForSignupPayload = false;
    }
  }
});

function launchWhatsAppSignup() {
  waitingForSignupPayload = true;
  document.getElementById("signupStatus").textContent = "Opening Meta embedded signup...";
  FB.login(function (response) {
    if (!response.authResponse) {
      waitingForSignupPayload = false;
      clearTimeout(signupSubmitTimer);
      document.getElementById("signupStatus").textContent = "Signup was cancelled.";
      return;
    }

    document.getElementById("signupCode").value = response.authResponse.code || "";
    document.getElementById("signupAccessToken").value = response.authResponse.accessToken || "";
    clearTimeout(signupSubmitTimer);
    signupSubmitTimer = setTimeout(function () {
      if (!maybeSubmitSignupForm()) {
        const code = document.getElementById("signupCode").value;
        const accessToken = document.getElementById("signupAccessToken").value;
        if (code || accessToken) {
          document.getElementById("signupStatus").textContent = "Meta returned a login result, but the WhatsApp IDs are not ready yet. Your admin can sync them from the master panel.";
        } else {
          document.getElementById("signupStatus").textContent = "Waiting for WhatsApp IDs from Meta. If nothing arrives, try the signup again.";
        }
      }
    }, 2500);
  }, {
    config_id: "<?php echo h($configId); ?>",
    response_type: "code",
    override_default_response_type: true,
    extras: {
      setup: {}
    }
  });
}
</script>
<script async defer crossorigin="anonymous" src="https://connect.facebook.net/en_US/sdk.js"></script>

<?php include 'footer.php'; ?>

<?php
include '../session.php';
include '../db_conn.php';

$biz_id = Auth::requireLogin();

include 'header.php';
$message = '';
$message_type = 'success';
$appId = AppSettings::getGlobal($db, 'META_APP_ID', Config::get('META_APP_ID', ''));
$configId = AppSettings::getGlobal($db, 'META_CONFIG_ID', Config::get('META_CONFIG_ID', ''));
$appSecret = AppSettings::getGlobal($db, 'META_APP_SECRET', Config::get('META_APP_SECRET', ''));
$defaultWebhookUrl = app_public_url('incoming.php');

function metaGetJson(string $url, string $accessToken): array
{
    $separator = str_contains($url, '?') ? '&' : '?';
    $ch = curl_init($url . $separator . 'access_token=' . rawurlencode($accessToken));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $payload = json_decode((string) $response, true);

    return [
        'ok' => $curlError === '' && $httpStatus >= 200 && $httpStatus < 300 && is_array($payload),
        'http_status' => $httpStatus,
        'error' => (string) ($payload['error']['message'] ?? $curlError),
        'payload' => is_array($payload) ? $payload : [],
    ];
}

function extractWhatsAppAccountFromPayload(array $payload, array &$results): void
{
    $wabaId = trim((string) ($payload['id'] ?? ''));
    $phoneNumbers = $payload['phone_numbers']['data'] ?? $payload['phone_numbers'] ?? null;

    if ($wabaId !== '' && is_array($phoneNumbers)) {
        if ($results['whatsapp_id'] === '') {
            $results['whatsapp_id'] = $wabaId;
        }

        foreach ($phoneNumbers as $phoneNumber) {
            if (!is_array($phoneNumber)) {
                continue;
            }

            $phoneNumberId = trim((string) ($phoneNumber['id'] ?? ''));
            if ($results['phone_number_id'] === '' && $phoneNumberId !== '') {
                $results['phone_number_id'] = $phoneNumberId;
            }

            if (!empty($phoneNumber['status'])) {
                $results['phone_number_status'] = (string) $phoneNumber['status'];
            }
        }
    }

    foreach ($payload as $value) {
        if (is_array($value)) {
            extractWhatsAppAccountFromPayload($value, $results);
        }
    }
}

function fetchMetaWhatsAppDetails(string $accessToken, string $knownWabaId = ''): array
{
    $results = [
        'whatsapp_id' => '',
        'phone_number_id' => '',
        'phone_number_status' => '',
        'error' => '',
    ];

    $urls = [];
    if ($knownWabaId !== '') {
        $urls[] = 'https://graph.facebook.com/v23.0/' . rawurlencode($knownWabaId) . '/phone_numbers?fields=id,display_phone_number,verified_name,status';
    }

    $urls[] = 'https://graph.facebook.com/v23.0/me?fields=whatsapp_business_accounts{id,name,phone_numbers{id,display_phone_number,verified_name,status}}';
    $urls[] = 'https://graph.facebook.com/v23.0/me/businesses?fields=id,name,owned_whatsapp_business_accounts{id,name,phone_numbers{id,display_phone_number,verified_name,status}},client_whatsapp_business_accounts{id,name,phone_numbers{id,display_phone_number,verified_name,status}}';

    foreach ($urls as $url) {
        $metaResponse = metaGetJson($url, $accessToken);
        if (!$metaResponse['ok']) {
            if ($results['error'] === '') {
                $results['error'] = $metaResponse['error'] !== '' ? $metaResponse['error'] : 'Meta API did not return WhatsApp account data.';
            }
            continue;
        }

        if ($knownWabaId !== '' && str_contains($url, '/' . rawurlencode($knownWabaId) . '/phone_numbers')) {
            $phoneNumbers = $metaResponse['payload']['data'] ?? [];
            if (is_array($phoneNumbers)) {
                foreach ($phoneNumbers as $phoneNumber) {
                    if (!is_array($phoneNumber)) {
                        continue;
                    }

                    $phoneNumberId = trim((string) ($phoneNumber['id'] ?? ''));
                    if ($results['whatsapp_id'] === '') {
                        $results['whatsapp_id'] = $knownWabaId;
                    }
                    if ($results['phone_number_id'] === '' && $phoneNumberId !== '') {
                        $results['phone_number_id'] = $phoneNumberId;
                    }
                    if (!empty($phoneNumber['status'])) {
                        $results['phone_number_status'] = (string) $phoneNumber['status'];
                    }
                }
            }
        } else {
            extractWhatsAppAccountFromPayload($metaResponse['payload'], $results);
        }

        if ($results['whatsapp_id'] !== '' && $results['phone_number_id'] !== '') {
            $results['error'] = '';
            return $results;
        }
    }

    if ($results['whatsapp_id'] === '' || $results['phone_number_id'] === '') {
        $results['error'] = 'Meta did not return both WhatsApp Business ID and Phone Number ID.';
    }

    return $results;
}

function exchangeEmbeddedSignupCode(string $code, string $appId, string $appSecret): array
{
    if ($code === '' || $appId === '' || $appSecret === '') {
        return ['access_token' => '', 'error' => ''];
    }

    $tokenUrl = 'https://graph.facebook.com/v23.0/oauth/access_token'
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
    if ($curlError === '' && $httpStatus >= 200 && $httpStatus < 300 && is_array($tokenData)) {
        return [
            'access_token' => (string) ($tokenData['access_token'] ?? ''),
            'error' => '',
        ];
    }

    return [
        'access_token' => '',
        'error' => (string) ($tokenData['error']['message'] ?? $curlError ?: 'Unknown token exchange error'),
    ];
}

function registerPhoneNumber($phoneNumberId, $accessToken, $pin = '123456')
{
    $url = "https://graph.facebook.com/v23.0/{$phoneNumberId}/register";

    $data = [
        "messaging_product" => "whatsapp",
        "pin" => $pin
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer {$accessToken}",
            "Content-Type: application/json"
        ],
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $decoded = json_decode((string) $response, true);
    return [
        'http' => $http,
        'ok' => $curlError === '' && $http >= 200 && $http < 300,
        'response' => is_array($decoded) ? $decoded : $response,
        'error' => (string) ($decoded['error']['message'] ?? $curlError),
    ];
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::verifyCsrf();

    $code = trim((string) ($_POST['code'] ?? ''));
    $accessTokenInput = trim((string) ($_POST['access_token'] ?? ''));
    $wabaId = trim((string) ($_POST['waba_id'] ?? ''));
    $phoneNumberId = trim((string) ($_POST['phone_number_id'] ?? ''));
    $globalAccessToken = trim((string) AppSettings::getGlobal($db, 'META_ACCESS_TOKEN', Config::get('META_ACCESS_TOKEN', '')));
    $tokenExchange = exchangeEmbeddedSignupCode($code, $appId, $appSecret);
    $accessToken = trim((string) ($tokenExchange['access_token'] ?? ''));

    if ($accessToken === '' && $globalAccessToken !== '') {
        $accessToken = $globalAccessToken;
    } elseif ($accessToken === '' && $accessTokenInput !== '') {
        $accessToken = $accessTokenInput;
    }

    if (($tokenExchange['error'] ?? '') !== '' && $globalAccessToken === '') {
        $message = 'Signup completed, but token exchange failed: ' . $tokenExchange['error'];
        $message_type = 'warning';
    }

    if ($accessToken !== '') {
        $metaDetails = fetchMetaWhatsAppDetails($accessToken, $wabaId);
        if ($wabaId === '') {
            $wabaId = $metaDetails['whatsapp_id'];
        }
        if ($phoneNumberId === '') {
            $phoneNumberId = $metaDetails['phone_number_id'];
        }

        if ($metaDetails['error'] !== '' && ($wabaId === '' || $phoneNumberId === '')) {
            $message = trim(($message !== '' ? $message . ' ' : '') . $metaDetails['error']);
            if ($message_type !== 'warning') {
                $message_type = 'warning';
            }
        }
    }

    $registrationOk = false;
    if (!empty($phoneNumberId) && $accessToken !== '') {
        $register = registerPhoneNumber($phoneNumberId, $accessToken);
        $registrationOk = !empty($register['ok']);
        $registrationError = strtolower((string) ($register['error'] ?? ''));
        if (!$registrationOk && str_contains($registrationError, 'already registered')) {
            $registrationOk = true;
        }

        error_log("REGISTER RESPONSE: " . json_encode($register));

        if (!$registrationOk) {
            $message = "Phone number found, but registration failed: " . ($register['error'] !== '' ? $register['error'] : json_encode($register['response']));
            $message_type = "warning";
        }
    }

    $hasIds = ($wabaId !== '' && $phoneNumberId !== '');
    $isRegistered = $hasIds && $registrationOk;

    if ($wabaId === '' && $phoneNumberId === '' && $accessToken === '') {
        $message = 'No WhatsApp account data was received. Complete Embedded Signup and try again.';
        $message_type = 'danger';
    } else {
        if ($accessToken !== '') {
            $stmt = $db->prepare("UPDATE gd_orders SET auth_token = ?, whatsapp_id = COALESCE(NULLIF(?, ''), whatsapp_id), phone_number_id = COALESCE(NULLIF(?, ''), phone_number_id), webhook_url = COALESCE(NULLIF(webhook_url, ''), ?), status = ? WHERE id = ?");
            $status = $isRegistered ? '1' : '0';
            $stmt->bind_param('sssssi', $accessToken, $wabaId, $phoneNumberId, $defaultWebhookUrl, $status, $biz_id);
        } else {
            $stmt = $db->prepare("UPDATE gd_orders SET whatsapp_id = COALESCE(NULLIF(?, ''), whatsapp_id), phone_number_id = COALESCE(NULLIF(?, ''), phone_number_id), webhook_url = COALESCE(NULLIF(webhook_url, ''), ?), status = ? WHERE id = ?");
            $status = $isRegistered ? '1' : '0';
            $stmt->bind_param('ssssi', $wabaId, $phoneNumberId, $defaultWebhookUrl, $status, $biz_id);
        }

        $saved = $stmt->execute();
        if ($saved && $message_type !== 'warning' && $isRegistered) {
            $message = 'Your business is now connected to WhatsApp.';
            $message_type = 'success';
        } elseif ($saved && $hasIds) {
            $message = trim($message . ' IDs were saved, but Meta still reports the phone number as pending.');
        } elseif ($saved) {
            $message = 'WhatsApp token saved, but WABA ID and Phone Number ID are still missing.';
            $message_type = 'warning';
        } else {
            $message = 'Could not save WhatsApp connection details.';
            $message_type = 'danger';
        }
    }
}

$stmt = $db->prepare('SELECT business_name, auth_token, whatsapp_id, phone_number_id, webhook_url, status FROM gd_orders WHERE id = ? LIMIT 1');
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
                    Add the Connect API values in <strong>Master Settings</strong> to enable Embedded Signup.
                </div>
            <?php else: ?>
                <div class="alert alert-success py-2">
                    Connect API is configured for this workspace.
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
                        <p class="mb-2"><strong>Connect App ID:</strong> <?php echo h(!empty($appId) ? AppSettings::masked($appId) : 'Not set'); ?></p>
                        <p class="mb-2"><strong>Connect Config ID:</strong> <?php echo h(!empty($configId) ? AppSettings::masked($configId) : 'Not set'); ?></p>
                        <p class="mb-2"><strong>API Token:</strong> <?php echo h(AppSettings::masked((string) AppSettings::getGlobal($db, 'API_TOKEN', '')) ?: 'Not set'); ?></p>
                        <p class="mb-2"><strong>WhatsApp Business ID:</strong> <?php echo h(!empty($business['whatsapp_id']) ? $business['whatsapp_id'] : 'Not connected'); ?></p>
                        <p class="mb-2"><strong>Phone Number ID:</strong> <?php echo h(!empty($business['phone_number_id']) ? $business['phone_number_id'] : 'Not connected'); ?></p>
                        <p class="mb-2"><strong>Webhook URL:</strong> <?php echo h(!empty($business['webhook_url']) ? $business['webhook_url'] : $defaultWebhookUrl); ?></p>
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
                            <p class="text-muted small mb-0">If Meta does not return the IDs automatically, your master admin can sync them from the control panel. The webhook callback is auto-set to your project endpoint.</p>
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
  if (code || accessToken || wabaId || phoneNumberId) {
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
    maybeSubmitSignupForm();
    waitingForSignupPayload = false;
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
    document.getElementById("signupStatus").textContent = "Meta login completed. Waiting for WhatsApp account IDs...";
    signupSubmitTimer = setTimeout(function () {
      waitingForSignupPayload = false;
      maybeSubmitSignupForm();
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

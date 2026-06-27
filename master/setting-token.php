<?php
include 'session.php';
include 'header.php';

$master_id = Auth::requireMaster();
$pendingOrders = [];
$activeOrders = [];
$loadError = '';
$message = '';
$message_type = 'success';
$db = Database::connectOrNull();
$defaultWebhookUrl = app_public_url('incoming.php');
$appSettings = [
    'connect_app_id' => '',
    'connect_app_secret' => '',
    'connect_config_id' => '',
    'connect_verify_token' => '',
    'whatsapp_access_token' => '',
    'api_token' => '',
];

if ($db) {
    $appSettings = [
        'connect_app_id' => AppSettings::getGlobal($db, 'META_APP_ID', ''),
        'connect_app_secret' => AppSettings::getGlobal($db, 'META_APP_SECRET', ''),
        'connect_config_id' => AppSettings::getGlobal($db, 'META_CONFIG_ID', ''),
        'connect_verify_token' => AppSettings::getGlobal($db, 'META_VERIFY_TOKEN', ''),
        'whatsapp_access_token' => AppSettings::getGlobal($db, 'META_ACCESS_TOKEN', ''),
        'api_token' => AppSettings::getGlobal($db, 'API_TOKEN', ''),
    ];
}

function fetchMetaWhatsAppDetails(string $accessToken): array
{
    $results = [
        'whatsapp_id' => '',
        'phone_number_id' => '',
        'error' => '',
    ];

    $urls = [
        'https://graph.facebook.com/v18.0/me?fields=whatsapp_business_accounts{id,name,phone_numbers{id,display_phone_number,verified_name}}&access_token=' . rawurlencode($accessToken),
    ];

    foreach ($urls as $url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $response = curl_exec($ch);
        $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError !== '' || $httpStatus < 200 || $httpStatus >= 300) {
            $decoded = json_decode((string) $response, true);
            $results['error'] = (string) ($decoded['error']['message'] ?? $curlError ?? 'Unknown Meta API error');
            return $results;
        }

        $payload = json_decode((string) $response, true);
        $accounts = $payload['whatsapp_business_accounts']['data'] ?? $payload['whatsapp_business_accounts'] ?? [];
        if (!is_array($accounts)) {
            $accounts = [];
        }

        foreach ($accounts as $account) {
            $wabaId = trim((string) ($account['id'] ?? ''));
            $phoneNumbers = $account['phone_numbers']['data'] ?? $account['phone_numbers'] ?? [];
            if (!is_array($phoneNumbers)) {
                $phoneNumbers = [];
            }

            $phoneNumberId = '';
            foreach ($phoneNumbers as $phoneNumber) {
                $phoneNumberId = trim((string) ($phoneNumber['id'] ?? ''));
                if ($phoneNumberId !== '') {
                    break;
                }
            }

            if ($wabaId !== '') {
                $results['whatsapp_id'] = $wabaId;
            }
            if ($phoneNumberId !== '') {
                $results['phone_number_id'] = $phoneNumberId;
            }

            if ($results['whatsapp_id'] !== '' && $results['phone_number_id'] !== '') {
                return $results;
            }
        }
    }

    if ($results['whatsapp_id'] === '' || $results['phone_number_id'] === '') {
        $results['error'] = 'Meta did not return both WhatsApp Business ID and Phone Number ID.';
    }

    return $results;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $db) {
    Security::verifyCsrf();

    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'save_app_settings') {
        $values = [
            'META_APP_ID' => trim((string) ($_POST['connect_app_id'] ?? '')),
            'META_APP_SECRET' => trim((string) ($_POST['connect_app_secret'] ?? '')),
            'META_CONFIG_ID' => trim((string) ($_POST['connect_config_id'] ?? '')),
            'META_VERIFY_TOKEN' => trim((string) ($_POST['connect_verify_token'] ?? '')),
            'META_ACCESS_TOKEN' => trim((string) ($_POST['whatsapp_access_token'] ?? '')),
            'API_TOKEN' => trim((string) ($_POST['api_token'] ?? '')),
        ];

        AppSettings::setGlobal($db, $values);
        $message = 'App settings saved successfully.';
        $message_type = 'success';
    } else {
        $token = trim((string) ($_POST['auth_token'] ?? ''));
        $whatsapp_id = trim((string) ($_POST['whatsapp_id'] ?? ''));
        $phonenumber_id = trim((string) ($_POST['phonenumber_id'] ?? ''));
        $webhook_url = trim((string) ($_POST['webhook_url'] ?? ''));
        $business_id = Security::intFrom($_POST['business_id'] ?? null);

        if ($action === 'sync_meta') {
            $stmt = $db->prepare('SELECT auth_token FROM gd_orders WHERE id = ? AND admin_id = ? LIMIT 1');
            $stmt->bind_param('ii', $business_id, $master_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc() ?: [];
            $savedToken = trim((string) ($row['auth_token'] ?? ''));

            if ($business_id > 0 && $savedToken !== '') {
                $details = fetchMetaWhatsAppDetails($savedToken);
                if ($details['error'] === '' && $details['whatsapp_id'] !== '' && $details['phone_number_id'] !== '') {
                    $stmt = $db->prepare("UPDATE gd_orders SET whatsapp_id = ?, phone_number_id = ?, status = '1' WHERE id = ? AND admin_id = ?");
                    $stmt->bind_param('ssii', $details['whatsapp_id'], $details['phone_number_id'], $business_id, $master_id);
                    if ($stmt->execute()) {
                        $message = 'WhatsApp IDs synced from Meta successfully.';
                        $message_type = 'success';
                    } else {
                        $message = 'Could not save WhatsApp IDs from Meta.';
                        $message_type = 'danger';
                    }
                } else {
                    $message = $details['error'] !== '' ? $details['error'] : 'Meta did not return WhatsApp details.';
                    $message_type = 'warning';
                }
            } else {
                $message = 'Save a token first, then sync from Meta.';
                $message_type = 'warning';
            }
        } else {
            $webhook_url = $webhook_url !== '' ? $webhook_url : $defaultWebhookUrl;
            $stmt = $db->prepare("UPDATE gd_orders SET auth_token = ?, whatsapp_id = ?, phone_number_id = ?, webhook_url = ?, status = '1' WHERE id = ? AND admin_id = ?");
            $stmt->bind_param('ssssii', $token, $whatsapp_id, $phonenumber_id, $webhook_url, $business_id, $master_id);

            if($business_id > 0 && $stmt->execute())
            {
                $message = "API Integrated Successfully!";
                $message_type = "success";
            } else {
                $message = "Unable to integrate API.";
                $message_type = "danger";
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $message = "Database is not responding. Restart MySQL in XAMPP, then try again.";
    $message_type = "danger";
}

if (!$db) {
    $loadError = 'Database is not responding. Restart MySQL in XAMPP, then refresh this page.';
} else {
try {
    $stmt = $db->prepare("SELECT id, business_name FROM gd_orders WHERE admin_id = ? AND status = '0' ORDER BY id DESC LIMIT 100");
    $stmt->bind_param('i', $master_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $pendingOrders[] = $row;
    }

    $stmt = $db->prepare("SELECT id, business_name, auth_token, whatsapp_id, phone_number_id, webhook_url, status FROM gd_orders WHERE admin_id = ? ORDER BY id DESC LIMIT 100");
    $stmt->bind_param('i', $master_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $activeOrders[] = $row;
    }
} catch (mysqli_sql_exception $exception) {
    $loadError = 'Credential data could not be loaded right now. Please try again after restarting MySQL/Apache.';
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



<div class="container-fluid wg-shell">
    <div class="row">
        <div class="col-lg-2 col-md-3 p-0 wg-sidebar">
            <?php include 'sidebar.php'; ?>
        </div>

        <div class="col-lg-10 col-md-9 wg-main">

        <div class="row g-3">
            <div class="col-lg-5">
                <div class="wg-card p-4 h-100">
                    <h4 class="mb-3"><i class="bi bi-gear-fill"></i> API Docs</h4>
                    <div class="mb-3">
                        <h6 class="mb-1">Connect API</h6>
                        <p class="small text-muted mb-2">Used for embedded signup, app setup, and webhook verification.</p>
                        <ul class="small mb-0 ps-3">
                            <li><strong>App ID</strong> identifies your Meta app.</li>
                            <li><strong>App Secret</strong> is used for secure token exchange.</li>
                            <li><strong>Config ID</strong> powers embedded signup.</li>
                            <li><strong>Verify Token</strong> must match the webhook callback check.</li>
                        </ul>
                    </div>
                    <div class="mb-3">
                        <h6 class="mb-1">WhatsApp API</h6>
                        <p class="small text-muted mb-2">Used for message sending, templates, and internal API authorization.</p>
                        <ul class="small mb-0 ps-3">
                            <li><strong>Access Token</strong> sends WhatsApp messages through Meta.</li>
                            <li><strong>API Token</strong> protects your internal contact and send APIs.</li>
                            <li><strong>Webhook URL</strong> should point to <code>incoming.php</code>.</li>
                        </ul>
                    </div>
                    <div class="alert alert-info small mb-0">
                        Keep tokens and secrets private. Update them here when you rotate credentials.
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <form action="" method="post" class="wg-card p-4 mb-3">
                    <?php echo Security::csrfField(); ?>
                    <input type="hidden" name="action" value="save_app_settings">
                    <h4 class="mb-3"><i class="bi bi-shield-lock"></i> Connect API and WhatsApp API</h4>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Connect App ID</label>
                            <input type="text" name="connect_app_id" class="form-control" value="<?php echo h($appSettings['connect_app_id']); ?>" placeholder="Meta App ID">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Connect Config ID</label>
                            <input type="text" name="connect_config_id" class="form-control" value="<?php echo h($appSettings['connect_config_id']); ?>" placeholder="Embedded signup config id">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Connect App Secret</label>
                            <input type="password" name="connect_app_secret" class="form-control" value="<?php echo h($appSettings['connect_app_secret']); ?>" placeholder="Meta App Secret">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Connect Verify Token</label>
                            <input type="text" name="connect_verify_token" class="form-control" value="<?php echo h($appSettings['connect_verify_token']); ?>" placeholder="Webhook verify token">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">WhatsApp Access Token</label>
                            <input type="password" name="whatsapp_access_token" class="form-control" value="<?php echo h($appSettings['whatsapp_access_token']); ?>" placeholder="Long-lived access token">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Internal API Token</label>
                            <input type="password" name="api_token" class="form-control" value="<?php echo h($appSettings['api_token']); ?>" placeholder="Internal API token">
                        </div>
                    </div>
                    <div class="mt-3">
                        <button class="btn btn-success w-100 shadow" type="submit">
                            <i class="bi bi-save2 me-1"></i> Save API Settings
                        </button>
                    </div>
                </form>

                <form action="" method="post" class="wg-card p-4">
                    <?php echo Security::csrfField(); ?>
                    <h4 class="mb-3"><i class="bi bi-building"></i> Business WhatsApp Integration</h4>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Business</label>
                            <select class="form-control" name="business_id" required>
                                <option value="">--Select Business--</option>
                                <?php foreach ($pendingOrders as $get4): ?>
                                    <option value="<?php echo h($get4['id']);?>"><?php echo h($get4['business_name']);?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Auth Token</label>
                            <input type="text" name="auth_token" class="form-control" required placeholder="WhatsApp token">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">WhatsApp ID</label>
                            <input type="text" name="whatsapp_id" class="form-control" required placeholder="WABA ID">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone Number ID</label>
                            <input type="text" name="phonenumber_id" class="form-control" required placeholder="Phone Number ID">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Webhook URL</label>
                            <input type="url" name="webhook_url" class="form-control" placeholder="Webhook URL" value="<?php echo h($defaultWebhookUrl); ?>">
                            <div class="form-text">Usually your public <code>incoming.php</code> endpoint.</div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button class="btn btn-primary w-100 shadow" type="submit">
                            <i class="bi bi-cloud-download-fill"></i> Save Business Integration
                        </button>
                    </div>
                </form>
            </div>
        </div>


    <div class="row mt-3 m-1">
        <h4><i class="bi bi-key"></i> API Settings</h4>
        <table class="table table-striped">
            <tr>
                <th>S.no</th>
                <th>Business Name</th>
                <th>Auth Token</th>
                <th>WhatsApp ID</th>
                <th>Phone Number ID</th>
                <th>Webhook URL</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
            <?php
            if ($loadError !== ''):
            ?>
                <tr><td colspan="8" class="text-center text-danger"><?php echo h($loadError); ?></td></tr>
            <?php elseif (empty($activeOrders)): ?>
                <tr><td colspan="8" class="text-center">No businesses found.</td></tr>
            <?php else: ?>
                <?php foreach ($activeOrders as $i => $get5): ?>
                <tr>
                <td><?php echo $i + 1;?></td>
                <td><?php echo h($get5['business_name']);?></td>
                <td><?php echo h(!empty($get5['auth_token']) ? 'Saved' : 'Missing');?></td>
                <td><?php echo h(!empty($get5['whatsapp_id']) ? $get5['whatsapp_id'] : 'Not connected');?></td>
                <td><?php echo h(!empty($get5['phone_number_id']) ? $get5['phone_number_id'] : 'Not connected');?></td>
                <td><?php echo h(!empty($get5['webhook_url']) ? $get5['webhook_url'] : 'Not set');?></td>
                <td><?php echo h(($get5['status'] ?? '0') == '1' ? 'Connected' : 'Incomplete');?></td>
                <td>
                    <form method="post" class="d-inline">
                        <?php echo Security::csrfField(); ?>
                        <input type="hidden" name="action" value="sync_meta">
                        <input type="hidden" name="business_id" value="<?php echo h((string) $get5['id']); ?>">
                        <button type="submit" class="btn btn-sm btn-outline-primary" <?php echo empty($get5['auth_token']) ? 'disabled' : ''; ?>>
                            Sync from Meta
                        </button>
                    </form>
                </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </table>
    </div>

        </div>


        


    </div>


</div>

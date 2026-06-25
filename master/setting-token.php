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

        <h4 class="mt-2"><i class="bi bi-list-columns-reverse"></i> Add Token</h4>

        <form action="" method="post">
        <?php echo Security::csrfField(); ?>

        <div class="row d-flex justify-content-center mb-4">
            <div class="col-md-6">
                <select class="form-control" name="business_id">
                    <option>--Select Business--</option>
                    <?php
                    foreach ($pendingOrders as $get4)
                    {
                        ?>

                        <option value="<?php echo h($get4['id']);?>"><?php echo h($get4['business_name']);?></option>

                        <?php
                    }
                    ?>
                </select>
            </div>
        </div>



        <div class="row d-flex justify-content-center mb-4">
            <div class="col-md-6">
                <input type="text" name="auth_token" class="form-control p-2 shadow" required placeholder="Auth Token" />
            </div>
        </div>

        <div class="row d-flex justify-content-center mb-4">
            <div class="col-md-6">
                <input type="text" name="whatsapp_id" class="form-control p-2 shadow" required placeholder="Whatsapp ID" />
            </div>
        </div>

        <div class="row d-flex justify-content-center mb-4">
            <div class="col-md-6">
                <input type="text" name="phonenumber_id" class="form-control p-2 shadow" required placeholder="Phonenumber ID" />
            </div>
        </div>

        <div class="row d-flex justify-content-center mb-4">
            <div class="col-md-6">
                <input type="text" name="webhook_url" class="form-control p-2 shadow" required placeholder="webhook Url" />
            </div>
        </div>

        <div class="row d-flex justify-content-center">
            <div class="col-md-6">
                <button class="btn btn-success w-100 shadow" type="submit">
                    <i class="bi bi-cloud-download-fill"></i> Submit
                </button>
            </div>
        </div>
    </form>


    <div class="row mt-3 m-1">
        <h4><i class="bi bi-key"></i> API Tokens</h4>
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

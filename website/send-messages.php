<?php
include '../session.php';
include '../db_conn.php';

$biz_id = Auth::requireLogin();

include 'header.php';
?>
<?php
if (isset($_POST['send'])) {
    Security::verifyCsrf();

    $biz_id = Auth::requireLogin();
    $template_id = Security::intFrom($_POST['template_id'] ?? null);
    $group_id = Security::intFrom($_POST['group_id'] ?? null);

    // Fetch template details
    $stmt = $db->prepare('SELECT * FROM gd_whatsapp_templates WHERE id = ? AND biz_id = ? LIMIT 1');
    $stmt->bind_param('ii', $template_id, $biz_id);
    $stmt->execute();
    $templateData = $stmt->get_result()->fetch_assoc();

    if (!$templateData) {
        die("<script>alert('Template not found!');</script>");
    }

    $tempname = $templateData['template_name'];
    $messageTitle = $templateData['message_title'];
    $messageBody = $templateData['message_body'];
    $subtitle = $templateData['subtitle'];
    $placeholderData = json_decode((string) ($templateData['placeholders'] ?? ''), true);
    $languageCode = is_array($placeholderData) ? (string) ($placeholderData['payload']['language'] ?? 'en_US') : 'en_US';
    if ($languageCode === '') {
        $languageCode = 'en_US';
    }

    // Fetch group members
    $stmt = $db->prepare(
        'SELECT DISTINCT c.id, c.full_name, c.phone_number
         FROM gd_user_contacts c
         LEFT JOIN gd_group_contacts gc ON gc.contact_id = c.id AND gc.biz_id = c.biz_id
         WHERE c.biz_id = ? AND (c.group_id = ? OR gc.group_id = ?)'
    );
    $stmt->bind_param('iii', $biz_id, $group_id, $group_id);
    $stmt->execute();
    $groupQuery = $stmt->get_result();

    if (mysqli_num_rows($groupQuery) == 0) {
        die("<script>alert('No members found in the group!');</script>");
    }

    // Fetch WhatsApp credentials
    $stmt = $db->prepare('SELECT phone_number_id, auth_token FROM gd_orders WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $biz_id);
    $stmt->execute();
    $get4 = $stmt->get_result()->fetch_assoc();

    if (!$get4 || empty($get4['phone_number_id'])) {
        die("<script>alert('WhatsApp credentials not found!');</script>");
    }

    $whatsappToken = $get4['auth_token'] ?: AppSettings::getGlobal($db, 'META_ACCESS_TOKEN', Config::get('META_ACCESS_TOKEN', ''));
    $phoneNumberId = $get4['phone_number_id'];

    $successCount = 0;
    $errorMessages = [];
    $packageStatus = ApiSupport::businessPackageStatus($db, $biz_id);

    while ($member = mysqli_fetch_assoc($groupQuery)) {
        $packageStatus = ApiSupport::businessPackageStatus($db, $biz_id);
        if (($packageStatus['enabled'] ?? false) && (int) ($packageStatus['remaining'] ?? 0) <= 0) {
            $errorMessages[] = 'Message limit exhausted. Please request a package upgrade.';
            break;
        }

        $phone = $member['phone_number'];
    
        // Ensure phone number starts with country code (+91)
        if (!preg_match('/^\+\d+$/', $phone)) {
            $phone = "+91" . $phone; // Default country code
        }
    
        if (empty($phone)) {
            $errorMessages[] = "Skipping empty phone number.";
            continue;
        }
    
        // WhatsApp API URL
        $url = "https://graph.facebook.com/v18.0/$phoneNumberId/messages";
    
        // WhatsApp message payload
        $data = [
            "messaging_product" => "whatsapp",
            "recipient_type" => "individual",
            "to" => $phone,
            "type" => "template",
            "template" => [
                "name" => $tempname,
                "language" => ["code" => $languageCode]
            ]
        ];
    
        // Send request using cURL
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "Authorization: Bearer $whatsappToken"
            ],
        ]);
    
        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
    
        // Decode response
        $decodedResponse = json_decode($response, true);
        
        // Extract message ID
        $messageId = $decodedResponse['messages'][0]['id'] ?? NULL;
    
        if ($http_code == 200 && $messageId) {
            $status = 'success';
            $deliveryStatus = 'sent';
            $successCount++;
            $errorMsg = NULL;
            ApiSupport::consumeMessageCredit($db, $biz_id);
        } else {
            $status = 'failed';
            $deliveryStatus = 'failed';
            $errorMsg = $decodedResponse['error']['message'] ?? 'Unknown error';
            $errorMessages[] = "Failed to send to $phone - Error: $errorMsg";
        }
        
        ApiSupport::storeSentMessage(
            $db,
            (int) $biz_id,
            (string) $phone,
            $template_id,
            (string) $messageTitle,
            (string) $messageBody,
            $status,
            $deliveryStatus,
            $errorMsg,
            $messageId,
            $status === 'success' ? date('Y-m-d H:i:s') : null
        );
    }
    

    // Show success or error message
    if ($successCount > 0) {

    

        echo "<script>alert('Messages sent successfully to $successCount recipients!');</script>";
    }
    if (!empty($errorMessages)) {
        echo "<script>alert('Some messages failed:\\n" . implode("\\n", $errorMessages) . "');</script>";
    }
}
?>




<div class="container-fluid">
    <div class="row">
        <div class="col-lg-2 col-md-3 p-0 wg-sidebar">
            <?php include 'sidebar.php'; ?>
        </div>

        <div class="col-lg-5 col-md-9 wg-main">
            <h4 class="mt-2"><i class="bi bi-send"></i> Send Messages</h4>
            <div class="alert alert-info py-2">
                This page sends the selected template to a contact group. Sequence planning now lives on the dedicated WhatsApp Sequence Planner page.
            </div>
            <form action="" method="post">
                <?php echo Security::csrfField(); ?>
                <div class="row">
                    <div class="mb-3">
                        <select id="templateDropdown" name="template_id" class="form-control">
                            <option>--Select Template--</option>
                            <?php
                            $biz_id = Auth::requireLogin();
                            $stmt = $db->prepare('SELECT * FROM gd_whatsapp_templates WHERE biz_id = ? ORDER BY id DESC');
                            $stmt->bind_param('i', $biz_id);
                            $stmt->execute();
                            $sql3 = $stmt->get_result();
                            while ($get3 = mysqli_fetch_assoc($sql3)) {
                                // Pass template data as JSON in a data attribute
                                $templateData = htmlspecialchars(json_encode([
                                    'message_title' => $get3['message_title'],
                                    'message_body' => $get3['message_body'],
                                    'media_url' => $get3['media_url'],
                                    'subtitle' => $get3['subtitle']
                                ]));
                                ?>
                                <option value="<?php echo h($get3['id']); ?>" data-template='<?php echo $templateData; ?>'>
                                    <?php echo h($get3['template_name']); ?>
                                </option>
                                <?php
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="mb-3">
                        <select name="group_id" class="form-control">
                            <option>--Select Group--</option>
                            <?php
                            $biz_id = Auth::requireLogin();
                            $stmt = $db->prepare('SELECT id, group_name FROM gd_groups WHERE biz_id = ? ORDER BY group_name');
                            $stmt->bind_param('i', $biz_id);
                            $stmt->execute();
                            $sql3 = $stmt->get_result();
                            while ($get3 = mysqli_fetch_assoc($sql3)) {
                                ?>
                                <option value="<?php echo h($get3['id']); ?>">
                                    <?php echo h($get3['group_name']); ?>
                                </option>
                                <?php
                            }
                            ?>
                        </select>
                    </div>
                </div>
                
                <button class="btn btn-success" name="send"><i class="bi bi-send-check me-1"></i> Send Message</button>
            </form>
        </div>

        <div class="col-lg-5 col-md-9 wg-main">
            <h5 class="mt-2"><i class="bi bi-phone"></i> Preview</h5>
            <div class="border p-3 shadow-sm bg-light rounded" style="width: 100%; max-width: 400px; margin: 0 auto;">
                <div class="whats-header border-bottom p-3 rounded">
                    <i class="bi bi-building"></i><b> Arklytics Connect</b> <i class="bi bi-patch-check-fill text-primary"></i>
                </div>
                <div class="whatsapp-message p-3 position-relative">
                    <div id="previewMediaUrl" class="mb-2 text-center"></div>
                    <h6 id="previewTitle" class="text-primary mb-2">[Message Title]</h6>
                    <p id="previewBody" class="mb-2">[Message Body]</p>
                    <h6 id="previewSubtitle" class="text-secondary mb-2">[Sub Title]</h6>
                    <div id="previewButtons" class="mt-3"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('templateDropdown').addEventListener('change', function () {
        const templateId = this.value;

        if (templateId) {
            fetch(`fetch_template?template_id=${templateId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        console.error(data.error);
                        return;
                    }

                    // Update the preview section
                    document.getElementById('previewTitle').textContent = data.message_title || '[Message Title]';
                    document.getElementById('previewBody').textContent = data.message_body || '[Message Body]';
                    document.getElementById('previewSubtitle').textContent = data.subtitle || '[Sub Title]';

                    const mediaPreviewContainer = document.getElementById('previewMediaUrl');
                    mediaPreviewContainer.innerHTML = ''; // Clear previous content

                    if (data.media_url) {
                        const img = document.createElement('img');
                        img.src = data.media_url;
                        img.alt = 'Media Preview';
                        img.style.maxWidth = '100%';
                        img.style.borderRadius = '5px';
                        img.onerror = () => {
                            mediaPreviewContainer.textContent = 'Invalid media URL.';
                        };
                        mediaPreviewContainer.appendChild(img);
                    } else {
                        mediaPreviewContainer.textContent = '[No Media Available]';
                    }

                    // Handle buttons
                    const buttonsContainer = document.getElementById('previewButtons');
                    buttonsContainer.innerHTML = ''; // Clear previous buttons

                    if (data.buttons && Array.isArray(data.buttons)) {
                        data.buttons.forEach(button => {
                            if (button.name && button.link) {
                                const btn = document.createElement('a');
                                btn.href = button.link;
                                btn.textContent = button.name;
                                btn.className = 'btn btn-primary btn-sm me-2'; // Bootstrap button style
                                btn.target = '_blank'; // Open in new tab
                                buttonsContainer.appendChild(btn);
                            } else {
                                console.warn('Button missing name or link:', button);
                            }
                        });
                    } else {
                        buttonsContainer.textContent = '[No Buttons Available]';
                    }
                })
                .catch(error => console.error('Error fetching template:', error));
        } else {
            // Reset preview if no template is selected
            document.getElementById('previewTitle').textContent = '[Message Title]';
            document.getElementById('previewBody').textContent = '[Message Body]';
            document.getElementById('previewSubtitle').textContent = '[Sub Title]';
            document.getElementById('previewMediaUrl').innerHTML = '[No Media Available]';
            document.getElementById('previewButtons').innerHTML = ''; // Clear buttons
        }
    });
</script>


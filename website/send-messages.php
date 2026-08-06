<?php
include '../session.php';
include '../db_conn.php';

$biz_id = Auth::requireLogin();

include 'header.php';

try {
    ApiSupport::ensureGroupHierarchyColumns($db);
} catch (Throwable $exception) {
    error_log('Group hierarchy ensure failed: ' . $exception->getMessage());
}
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
    $templateSend = ApiSupport::buildTemplateSendComponents($templateData);
    $languageCode = is_array($placeholderData) ? (string) ($placeholderData['payload']['language'] ?? 'en_US') : 'en_US';
    if ($languageCode === '') {
        $languageCode = 'en_US';
    }

    if (is_array($templateSend) && !empty($templateSend['error'])) {
        die("<script>alert('" . addslashes((string) $templateSend['error']) . "');</script>");
    }
    $templateComponents = is_array($templateSend) ? ($templateSend['components'] ?? []) : [];

    // Fetch group members. Main groups include contacts in their subgroups.
    $targetGroupIds = ApiSupport::groupTargetIds($db, (int) $biz_id, (int) $group_id, true);
    if (empty($targetGroupIds)) {
        die("<script>alert('Group not found!');</script>");
    }
    $placeholders = implode(',', array_fill(0, count($targetGroupIds), '?'));
    $types = 'i' . str_repeat('i', count($targetGroupIds)) . str_repeat('i', count($targetGroupIds));
    $values = array_merge([(int) $biz_id], $targetGroupIds, $targetGroupIds);
    $stmt = $db->prepare(
        'SELECT DISTINCT c.id, c.full_name, c.phone_number
         FROM gd_user_contacts c
         LEFT JOIN gd_group_contacts gc ON gc.contact_id = c.id AND gc.biz_id = c.biz_id
         WHERE c.biz_id = ? AND (c.group_id IN (' . $placeholders . ') OR gc.group_id IN (' . $placeholders . '))'
    );
    $bind = [$types];
    foreach ($values as $index => $value) {
        $bind[] = &$values[$index];
    }
    $stmt->bind_param(...$bind);
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

        $phone = ApiSupport::normalizePhone((string) $member['phone_number']);
    
        if (empty($phone)) {
            $errorMessages[] = "Skipping empty phone number.";
            continue;
        }
    
        // WhatsApp API URL
        $url = "https://graph.facebook.com/" . ApiSupport::GRAPH_VERSION . "/$phoneNumberId/messages";
    
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

    if (!empty($templateComponents)) {
        $data['template']['components'] = $templateComponents;
    }
    
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
        $curl_error = curl_error($curl);
        curl_close($curl);
    
        // Decode response
        $decodedResponse = json_decode((string) $response, true);
        
        // Extract message ID
        $messageId = $decodedResponse['messages'][0]['id'] ?? NULL;
        $requestJson = ApiSupport::encodeJson($data);
        $responseJson = is_string($response) && $response !== ''
            ? (json_last_error() === JSON_ERROR_NONE ? ApiSupport::encodeJson($decodedResponse) : $response)
            : null;
    
        if ($http_code == 200 && $messageId) {
            $status = 'success';
            $deliveryStatus = 'sent';
            $successCount++;
            $errorMsg = NULL;
            ApiSupport::consumeMessageCredit($db, $biz_id);
        } else {
            $status = 'failed';
            $deliveryStatus = 'failed';
            $errorMsg = $curl_error !== ''
                ? 'cURL error: ' . $curl_error
                : ($decodedResponse['error']['message'] ?? ('WhatsApp API returned HTTP ' . $http_code));
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
            $status === 'success' ? date('Y-m-d H:i:s') : null,
            $requestJson,
            $responseJson,
            (int) $http_code,
            $errorMsg
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
                This page sends the selected template to a main group or subgroup. Main groups include contacts in all subgroups.
            </div>
            <form action="" method="post" id="sendMessageForm">
                <?php echo Security::csrfField(); ?>
                <div class="row">
                    <div class="mb-3">
                        <select id="templateDropdown" name="template_id" class="form-control" required>
                            <option value="">--Select Template--</option>
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
                                    'subtitle' => $get3['subtitle'],
                                    'header_type' => (function ($placeholders) {
                                        $decoded = json_decode((string) $placeholders, true);
                                        return is_array($decoded) ? strtoupper((string) ($decoded['header_type'] ?? '')) : '';
                                    })($get3['placeholders'] ?? ''),
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
                        <select id="groupDropdown" name="group_id" class="form-control" required>
                            <option value="">--Select Group or Subgroup--</option>
                            <?php
                            $biz_id = Auth::requireLogin();
                            $stmt = $db->prepare('
                                SELECT g.id, g.parent_id, g.group_name, parent.group_name AS parent_name
                                FROM gd_groups g
                                LEFT JOIN gd_groups parent ON parent.id = g.parent_id
                                WHERE g.biz_id = ?
                                ORDER BY CASE WHEN g.parent_id IS NULL THEN g.id ELSE g.parent_id END DESC,
                                         CASE WHEN g.parent_id IS NULL THEN 0 ELSE 1 END,
                                         g.group_name
                            ');
                            $stmt->bind_param('i', $biz_id);
                            $stmt->execute();
                            $sql3 = $stmt->get_result();
                            while ($get3 = mysqli_fetch_assoc($sql3)) {
                                ?>
                                <option value="<?php echo h($get3['id']); ?>">
                                    <?php echo h(!empty($get3['parent_id']) ? (($get3['parent_name'] ?? '') . ' / ' . $get3['group_name']) : ($get3['group_name'] . ' (includes subgroups)')); ?>
                                </option>
                                <?php
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Send Scope</label>
                        <div class="d-flex flex-wrap gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="send_scope" id="sendScopeAll" value="all" checked>
                                <label class="form-check-label" for="sendScopeAll">All contacts</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="send_scope" id="sendScopePartial" value="partial">
                                <label class="form-check-label" for="sendScopePartial">Partial range</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row d-none" id="partialRangeFields">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="rangeStart">Start contact no.</label>
                        <input type="number" class="form-control" id="rangeStart" name="range_start" min="1" value="1" placeholder="1">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="rangeEnd">End contact no.</label>
                        <input type="number" class="form-control" id="rangeEnd" name="range_end" min="1" placeholder="30">
                    </div>
                    <div class="col-12">
                        <div class="small text-muted mb-3">Example: use 1 to 30 now, then 31 to 60 later. Contacts are counted in the selected group order.</div>
                    </div>
                </div>
                
                <button class="btn btn-success" name="send" id="sendMessageButton"><i class="bi bi-send-check me-1"></i> Send Message</button>
            </form>

            <div class="card border-0 shadow-sm mt-3 d-none" id="sendProgressCard">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong id="sendProgressTitle">Sending messages</strong>
                        <span class="small text-muted" id="sendProgressCount">0 / 0</span>
                    </div>
                    <div class="progress" style="height: 14px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" id="sendProgressBar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                    </div>
                    <div class="small text-muted mt-2" id="sendProgressStatus">Preparing recipients...</div>
                    <div class="small text-danger mt-2 d-none" id="sendProgressErrors"></div>
                </div>
            </div>
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
    const sendForm = document.getElementById('sendMessageForm');
    const sendButton = document.getElementById('sendMessageButton');
    const progressCard = document.getElementById('sendProgressCard');
    const progressBar = document.getElementById('sendProgressBar');
    const progressCount = document.getElementById('sendProgressCount');
    const progressStatus = document.getElementById('sendProgressStatus');
    const progressErrors = document.getElementById('sendProgressErrors');
    const partialRangeFields = document.getElementById('partialRangeFields');
    const sendScopeInputs = document.querySelectorAll('input[name="send_scope"]');

    function selectedSendScope() {
        const selected = document.querySelector('input[name="send_scope"]:checked');
        return selected ? selected.value : 'all';
    }

    function syncRangeFields() {
        const isPartial = selectedSendScope() === 'partial';
        partialRangeFields.classList.toggle('d-none', !isPartial);
        document.getElementById('rangeStart').required = isPartial;
        document.getElementById('rangeEnd').required = isPartial;
    }

    sendScopeInputs.forEach((input) => input.addEventListener('change', syncRangeFields));
    syncRangeFields();

    function setProgress(done, total, sent, failed) {
        const percent = total > 0 ? Math.min(100, Math.round((done / total) * 100)) : 0;
        progressBar.style.width = percent + '%';
        progressBar.setAttribute('aria-valuenow', String(percent));
        progressBar.textContent = percent + '%';
        progressCount.textContent = `${done} / ${total}`;
        progressStatus.textContent = `Sent: ${sent} | Failed: ${failed}`;
    }

    async function postBatch(formData) {
        const response = await fetch('<?php echo h(app_url('business/send-message-batch')); ?>', {
            method: 'POST',
            body: formData,
            headers: {
                'Accept': 'application/json'
            }
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.ok) {
            throw new Error(data.error || 'Unable to send messages.');
        }
        return data;
    }

    if (sendForm) {
        sendForm.addEventListener('submit', async function (event) {
            event.preventDefault();

            if (!sendForm.reportValidity()) {
                return;
            }

            if (selectedSendScope() === 'partial') {
                const start = Number(document.getElementById('rangeStart').value || 0);
                const end = Number(document.getElementById('rangeEnd').value || 0);
                if (start <= 0 || end <= 0 || end < start) {
                    progressCard.classList.remove('d-none');
                    progressErrors.classList.remove('d-none');
                    progressErrors.textContent = 'Enter a valid partial range. End contact no. must be greater than or equal to start contact no.';
                    progressStatus.textContent = 'Range needs correction.';
                    return;
                }
            }

            progressCard.classList.remove('d-none');
            progressErrors.classList.add('d-none');
            progressErrors.textContent = '';
            progressBar.classList.add('bg-success', 'progress-bar-animated');
            progressBar.classList.remove('bg-danger');
            setProgress(0, 0, 0, 0);
            sendButton.disabled = true;
            sendButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Sending';

            const baseData = new FormData(sendForm);
            let totalSent = 0;
            let totalFailed = 0;
            let offset = 0;
            const batchSize = 5;

            try {
                const prepareData = new FormData();
                prepareData.append('_csrf_token', baseData.get('_csrf_token'));
                prepareData.append('template_id', baseData.get('template_id'));
                prepareData.append('group_id', baseData.get('group_id'));
                prepareData.append('limit', String(batchSize));
                prepareData.append('action', 'prepare');
                prepareData.append('send_scope', baseData.get('send_scope') || 'all');
                prepareData.append('range_start', baseData.get('range_start') || '');
                prepareData.append('range_end', baseData.get('range_end') || '');

                const prepared = await postBatch(prepareData);
                const total = Number(prepared.total || 0);
                setProgress(0, total, 0, 0);
                if ((baseData.get('send_scope') || 'all') === 'partial') {
                    progressStatus.textContent = `Preparing range ${prepared.range_start} to ${prepared.range_end}`;
                }

                while (offset < total) {
                    const batchData = new FormData();
                    batchData.append('_csrf_token', baseData.get('_csrf_token'));
                    batchData.append('template_id', baseData.get('template_id'));
                    batchData.append('group_id', baseData.get('group_id'));
                    batchData.append('limit', String(batchSize));
                    batchData.append('offset', String(offset));
                    batchData.append('action', 'send');
                    batchData.append('send_scope', baseData.get('send_scope') || 'all');
                    batchData.append('range_start', baseData.get('range_start') || '');
                    batchData.append('range_end', baseData.get('range_end') || '');

                    const result = await postBatch(batchData);
                    offset = Number(result.offset || (offset + batchSize));
                    totalSent += Number(result.sent || 0);
                    totalFailed += Number(result.failed || 0);
                    setProgress(Math.min(offset, total), total, totalSent, totalFailed);

                    if (Array.isArray(result.errors) && result.errors.length > 0) {
                        progressErrors.classList.remove('d-none');
                        progressErrors.textContent = result.errors.join(' ');
                    }

                    if (result.done) {
                        break;
                    }
                }

                progressBar.classList.remove('progress-bar-animated');
                progressStatus.textContent = `Completed. Sent: ${totalSent} | Failed: ${totalFailed}`;
            } catch (error) {
                progressBar.classList.remove('bg-success');
                progressBar.classList.add('bg-danger');
                progressErrors.classList.remove('d-none');
                progressErrors.textContent = error.message;
                progressStatus.textContent = 'Stopped before completion.';
            } finally {
                sendButton.disabled = false;
                sendButton.innerHTML = '<i class="bi bi-send-check me-1"></i> Send Message';
            }
        });
    }

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
                        const headerType = String(data.header_type || '').toUpperCase();
                        if (headerType === 'VIDEO' || /\.(mp4|3gp)(\?|$)/i.test(data.media_url)) {
                            const video = document.createElement('video');
                            video.src = data.media_url;
                            video.controls = true;
                            video.style.width = '100%';
                            video.style.maxHeight = '220px';
                            video.style.borderRadius = '5px';
                            mediaPreviewContainer.appendChild(video);
                        } else if (headerType === 'DOCUMENT' || /\.pdf(\?|$)/i.test(data.media_url)) {
                            const link = document.createElement('a');
                            link.href = data.media_url;
                            link.target = '_blank';
                            link.rel = 'noopener';
                            link.className = 'btn btn-light btn-sm';
                            link.textContent = 'Open document';
                            mediaPreviewContainer.appendChild(link);
                        } else {
                            const img = document.createElement('img');
                            img.src = data.media_url;
                            img.alt = 'Media Preview';
                            img.style.maxWidth = '100%';
                            img.style.borderRadius = '5px';
                            img.onerror = () => {
                                mediaPreviewContainer.textContent = 'Invalid media URL.';
                            };
                            mediaPreviewContainer.appendChild(img);
                        }
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

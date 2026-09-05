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

$templates = [];
$stmt = $db->prepare('SELECT * FROM gd_whatsapp_templates WHERE biz_id = ? ORDER BY id DESC');
$stmt->bind_param('i', $biz_id);
$stmt->execute();
$templateResult = $stmt->get_result();
while ($row = $templateResult->fetch_assoc()) {
    $templates[] = $row;
}

$parentGroups = [];
$subgroupsByParent = [];
$stmt = $db->prepare('
    SELECT g.id, g.parent_id, g.group_name, parent.group_name AS parent_name
    FROM gd_groups g
    LEFT JOIN gd_groups parent ON parent.id = g.parent_id
    WHERE g.biz_id = ?
    ORDER BY g.parent_id IS NOT NULL, g.group_name
');
$stmt->bind_param('i', $biz_id);
$stmt->execute();
$groupResult = $stmt->get_result();
while ($row = $groupResult->fetch_assoc()) {
    if (empty($row['parent_id'])) {
        $parentGroups[] = $row;
    } else {
        $subgroupsByParent[(int) $row['parent_id']][] = $row;
    }
}

function wgTemplateMediaHandle(array $meta): string
{
    $mediaHandle = trim((string) ($meta['header_media_handle'] ?? ''));
    if ($mediaHandle !== '') {
        return $mediaHandle;
    }

    foreach ((array) ($meta['payload']['components'] ?? []) as $component) {
        if (!is_array($component) || strtoupper(trim((string) ($component['type'] ?? ''))) !== 'HEADER') {
            continue;
        }

        $handles = $component['example']['header_handle'] ?? [];
        if (is_array($handles) && trim((string) ($handles[0] ?? '')) !== '') {
            return trim((string) $handles[0]);
        }
    }

    return '';
}

function wgHydrateTemplateMediaUrl(mysqli $db, int $bizId, array $template): array
{
    $meta = json_decode((string) ($template['placeholders'] ?? ''), true);
    if (!is_array($meta)) {
        return $template;
    }

    $headerType = strtoupper(trim((string) ($meta['header_type'] ?? 'NONE')));
    if (!in_array($headerType, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)) {
        return $template;
    }

    $mediaUrl = trim((string) ($meta['header_media_url'] ?? $template['media_url'] ?? ''));
    if ($mediaUrl !== '') {
        return $template;
    }

    $mediaHandle = wgTemplateMediaHandle($meta);
    if ($mediaHandle === '') {
        return $template;
    }

    $stmt = $db->prepare('SELECT s3_url FROM gd_template_media WHERE biz_id = ? AND media_handle = ? AND s3_url <> "" ORDER BY id DESC LIMIT 1');
    if (!$stmt) {
        return $template;
    }

    $stmt->bind_param('is', $bizId, $mediaHandle);
    $stmt->execute();
    $media = $stmt->get_result()->fetch_assoc();
    $s3Url = trim((string) ($media['s3_url'] ?? ''));
    if ($s3Url === '') {
        return $template;
    }

    $meta['header_media_url'] = $s3Url;
    $template['media_url'] = $s3Url;
    $template['placeholders'] = ApiSupport::encodeJson($meta) ?? (string) ($template['placeholders'] ?? '');
    $updateStmt = $db->prepare('UPDATE gd_whatsapp_templates SET media_url = ?, placeholders = ?, updated_at = NOW() WHERE id = ? AND biz_id = ?');
    if ($updateStmt) {
        $templateJson = (string) $template['placeholders'];
        $templateId = (int) ($template['id'] ?? 0);
        $updateStmt->bind_param('ssii', $s3Url, $templateJson, $templateId, $bizId);
        $updateStmt->execute();
    }

    return $template;
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
    $templateData = wgHydrateTemplateMediaUrl($db, (int) $biz_id, $templateData);

    $tempname = $templateData['template_name'];
    $messageTitle = $templateData['message_title'];
    $messageBody = $templateData['message_body'];
    $subtitle = $templateData['subtitle'];
    $packageCategory = ApiSupport::packageMessageCategory((string) ($templateData['category'] ?? ''));
    $placeholderData = json_decode((string) ($templateData['placeholders'] ?? ''), true);
    $templateSend = ApiSupport::buildTemplateSendComponents($templateData, ApiSupport::templateSendValuesFromInput($_POST));
    $languageCode = is_array($placeholderData) ? (string) ($placeholderData['payload']['language'] ?? 'en_US') : 'en_US';
    if ($languageCode === '') {
        $languageCode = 'en_US';
    }

    if (is_array($templateSend) && !empty($templateSend['error'])) {
        die("<script>alert('" . addslashes((string) $templateSend['error']) . "');</script>");
    }

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
        $packageStatus = ApiSupport::businessPackageStatus($db, $biz_id, $packageCategory);
        if (($packageStatus['enabled'] ?? false) && (int) ($packageStatus['remaining'] ?? 0) <= 0) {
            $errorMessages[] = 'Message limit exhausted. Please request a package upgrade.';
            break;
        }

        $phone = ApiSupport::normalizePhone((string) $member['phone_number']);
    
        if (empty($phone)) {
            $errorMessages[] = "Skipping empty phone number.";
            continue;
        }

        $memberSendValues = ApiSupport::templateSendValuesFromInput($_POST);
        $memberSendValues['_recipient'] = [
            'contact_id' => $member['id'] ?? null,
            'full_name' => $member['full_name'] ?? '',
            'name' => $member['full_name'] ?? '',
            'phone_number' => $phone,
            'phone' => $phone,
        ];
        $memberTemplateSend = ApiSupport::buildTemplateSendComponents($templateData, $memberSendValues);
        if (!empty($memberTemplateSend['error'])) {
            $errorMessages[] = "Failed to send to $phone - Error: " . (string) $memberTemplateSend['error'];
            continue;
        }
        $memberTemplateComponents = is_array($memberTemplateSend['components'] ?? null) ? $memberTemplateSend['components'] : [];
    
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

    if (!empty($memberTemplateComponents)) {
        $data['template']['components'] = $memberTemplateComponents;
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
            ApiSupport::consumeMessageCredit($db, $biz_id, 1, $packageCategory);
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




<div class="container-fluid wg-shell">
    <div class="row">
        <div class="col-lg-2 col-md-3 p-0 wg-sidebar">
            <?php include 'sidebar.php'; ?>
        </div>

        <main class="col-lg-10 col-md-9 wg-main">
            <div class="wg-page-title">
                <div>
                    <h1>Send Messages</h1>
                    <p>Compose a WhatsApp campaign, choose recipients, and track batch progress.</p>
                </div>
            </div>

            <div class="wg-send-layout">
                <section class="wg-card wg-send-panel">
            <form action="" method="post" id="sendMessageForm">
                <?php echo Security::csrfField(); ?>
                <input type="hidden" id="templateDropdown" name="template_id" required>
                <input type="hidden" id="recipientMode" name="recipient_mode" value="subgroups">

                <div class="wg-form-section">
                    <div class="wg-section-heading">
                        <span><i class="bi bi-file-earmark-text"></i></span>
                        <div>
                            <h5>Template</h5>
                            <p>Search by template name and select the approved message.</p>
                        </div>
                    </div>
                    <label class="form-label" for="templateSearch">Select Template</label>
                    <div class="wg-search-select">
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input type="search" class="form-control" id="templateSearch" placeholder="Search template">
                        </div>
                        <div class="wg-search-options" id="templateOptions">
                            <?php if (empty($templates)): ?>
                                <div class="wg-search-empty">No templates found.</div>
                            <?php else: ?>
                                <?php foreach ($templates as $template): ?>
                                    <button type="button" class="wg-search-option" data-template-id="<?php echo h((string) $template['id']); ?>" data-template-name="<?php echo h($template['template_name']); ?>">
                                        <span><?php echo h($template['template_name']); ?></span>
                                        <small><?php echo h((string) ($template['category'] ?? 'Template')); ?></small>
                                    </button>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="row d-none" id="templateVariableFields">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Template Variable Values</label>
                        <div class="form-text mb-2">Use contact fields like {{name}}, {{phone}}, or {{email}}, or type fixed text.</div>
                        <div id="templateVariableInputs" class="row g-2"></div>
                    </div>
                </div>
                <datalist id="contactVariableSuggestions">
                    <option value="{{name}}">
                    <option value="{{fullname}}">
                    <option value="{{phone}}">
                    <option value="{{mobile}}">
                    <option value="{{email}}">
                    <option value="{{full_name}}">
                    <option value="{{phone_number}}">
                    <option value="Order ID">
                    <option value="Payment URL">
                </datalist>

                <div class="row d-none" id="templateMediaUrlFields">
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="headerMediaUrlInput">Header Media URL</label>
                        <input type="url" class="form-control" id="headerMediaUrlInput" name="header_media_url" placeholder="https://example.com/image.jpg">
                        <div class="form-text">Required for image, video, or document header templates when no saved media URL is available.</div>
                    </div>
                </div>

                <div class="wg-form-section mt-3">
                    <div class="wg-section-heading">
                        <span><i class="bi bi-people"></i></span>
                        <div>
                            <h5>Recipients</h5>
                            <p>Select a parent group, then choose one or more related subgroups.</p>
                        </div>
                    </div>
                    <div class="wg-subgroup-picker" id="subgroupPicker">
                        <label class="form-label mt-3" for="parentGroupDropdown">Parent Group</label>
                        <select id="parentGroupDropdown" class="form-control">
                            <option value="">Select parent group</option>
                            <?php foreach ($parentGroups as $parent): ?>
                                <option value="<?php echo h((string) $parent['id']); ?>"><?php echo h($parent['group_name']); ?></option>
                            <?php endforeach; ?>
                        </select>

                        <label class="form-label mt-3">Subgroups</label>
                        <div class="dropdown w-100">
                            <button class="btn btn-light dropdown-toggle wg-checkbox-dropdown" type="button" id="subgroupDropdownButton" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                                Select subgroups
                            </button>
                            <div class="dropdown-menu wg-subgroup-menu w-100" aria-labelledby="subgroupDropdownButton" id="subgroupCheckboxList">
                                <div class="wg-search-empty" data-empty-state>Select a parent group first.</div>
                                <?php foreach ($subgroupsByParent as $parentId => $subgroups): ?>
                                    <?php foreach ($subgroups as $subgroup): ?>
                                        <label class="dropdown-item wg-checkbox-option d-none" data-parent-id="<?php echo h((string) $parentId); ?>">
                                            <input class="form-check-input" type="checkbox" name="subgroup_ids[]" value="<?php echo h((string) $subgroup['id']); ?>">
                                            <span><?php echo h($subgroup['group_name']); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
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
                
                <button class="btn btn-success mt-3" id="sendMessageButton"><i class="bi bi-send-check me-1"></i> Send Message</button>
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
                </section>

                <aside class="wg-card wg-send-preview">
            <h5 class="mt-2"><i class="bi bi-phone"></i> Preview</h5>
            <div class="wg-template-preview" style="width: 100%; max-width: 400px; margin: 0 auto;">
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
                </aside>
            </div>
        </main>
    </div>
</div>

<script>
    const sendForm = document.getElementById('sendMessageForm');
    const sendButton = document.getElementById('sendMessageButton');
    const templateInput = document.getElementById('templateDropdown');
    const templateSearch = document.getElementById('templateSearch');
    const templateOptions = document.getElementById('templateOptions');
    const recipientMode = document.getElementById('recipientMode');
    const subgroupPicker = document.getElementById('subgroupPicker');
    const parentGroupDropdown = document.getElementById('parentGroupDropdown');
    const subgroupCheckboxList = document.getElementById('subgroupCheckboxList');
    const subgroupDropdownButton = document.getElementById('subgroupDropdownButton');
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

    function selectedSubgroupIds() {
        return Array.from(document.querySelectorAll('input[name="subgroup_ids[]"]:checked')).map((input) => input.value);
    }

    function updateSubgroupButtonLabel() {
        const count = selectedSubgroupIds().length;
        subgroupDropdownButton.textContent = count > 0 ? `${count} subgroup${count === 1 ? '' : 's'} selected` : 'Select subgroups';
    }

    function renderSubgroupOptions(parentId) {
        let visible = 0;
        const emptyState = subgroupCheckboxList.querySelector('[data-empty-state]');
        subgroupCheckboxList.querySelectorAll('.wg-checkbox-option').forEach((item) => {
            const input = item.querySelector('input');
            const matches = parentId !== '' && item.getAttribute('data-parent-id') === String(parentId);
            item.classList.toggle('d-none', !matches);
            if (!matches && input) {
                input.checked = false;
            }
            if (matches) {
                visible++;
            }
        });

        if (!parentId) {
            if (emptyState) {
                emptyState.textContent = 'Select a parent group first.';
                emptyState.classList.remove('d-none');
            }
            subgroupCheckboxList.classList.remove('show');
            subgroupDropdownButton.setAttribute('aria-expanded', 'false');
            updateSubgroupButtonLabel();
            return;
        }

        if (visible === 0) {
            if (emptyState) {
                emptyState.textContent = 'No subgroups under this parent.';
                emptyState.classList.remove('d-none');
            }
            subgroupCheckboxList.classList.add('show');
            subgroupDropdownButton.setAttribute('aria-expanded', 'true');
            updateSubgroupButtonLabel();
            return;
        }

        if (emptyState) {
            emptyState.classList.add('d-none');
        }
        subgroupCheckboxList.classList.add('show');
        subgroupDropdownButton.setAttribute('aria-expanded', 'true');
        updateSubgroupButtonLabel();
    }

    function filterTemplateOptions() {
        const query = templateSearch.value.trim().toLowerCase();
        let visible = 0;
        templateOptions.querySelectorAll('.wg-search-option').forEach((option) => {
            const text = option.textContent.toLowerCase();
            const match = query === '' || text.includes(query);
            option.classList.toggle('d-none', !match);
            if (match) {
                visible++;
            }
        });
        templateOptions.classList.toggle('has-no-results', visible === 0);
    }

    function addTemplateVariableInput(container, name, label) {
        const wrapper = document.createElement('div');
        wrapper.className = 'col-md-6';

        const inputLabel = document.createElement('label');
        inputLabel.className = 'form-label';
        inputLabel.textContent = label;

        const input = document.createElement('input');
        input.type = 'text';
        input.name = name;
        input.className = 'form-control';
        input.required = true;
        input.setAttribute('list', 'contactVariableSuggestions');
        input.placeholder = '{{name}}, {{phone}}, {{email}}, or fixed text';

        const help = document.createElement('div');
        help.className = 'form-text';
        help.textContent = 'Use a contact token or enter fixed content like order ID, URL, amount, city, or custom text.';

        wrapper.appendChild(inputLabel);
        wrapper.appendChild(input);
        wrapper.appendChild(help);
        container.appendChild(wrapper);
    }

    function renderTemplateVariableFields(requirements) {
        const fields = document.getElementById('templateVariableFields');
        const inputs = document.getElementById('templateVariableInputs');
        inputs.innerHTML = '';

        const header = Array.isArray(requirements?.header) ? requirements.header : [];
        const body = Array.isArray(requirements?.body) ? requirements.body : [];
        const buttons = Array.isArray(requirements?.buttons) ? requirements.buttons : [];

        header.forEach((number) => addTemplateVariableInput(inputs, `header_values[${number}]`, `Header {{${number}}}`));
        body.forEach((number) => addTemplateVariableInput(inputs, `body_values[${number}]`, `Body {{${number}}}`));
        buttons.forEach((button) => {
            const index = Number(button.index || 0);
            (Array.isArray(button.numbers) ? button.numbers : []).forEach((number) => {
                addTemplateVariableInput(inputs, `button_values[${index}][${number}]`, `${button.text || 'Button'} {{${number}}}`);
            });
        });

        fields.classList.toggle('d-none', inputs.children.length === 0);
    }

    function appendTemplateVariableValues(source, target) {
        for (const [key, value] of source.entries()) {
            if (/^(header_values|body_values|button_values)\[/.test(key)) {
                target.append(key, value);
            }
        }
        if (source.get('header_media_url')) {
            target.append('header_media_url', source.get('header_media_url'));
        }
    }

    function syncTemplateMediaField(data) {
        const fields = document.getElementById('templateMediaUrlFields');
        const input = document.getElementById('headerMediaUrlInput');
        const required = Boolean(data?.needs_media_url);
        fields.classList.toggle('d-none', !required);
        input.required = required;
        if (!required) {
            input.value = '';
        }
    }

    sendScopeInputs.forEach((input) => input.addEventListener('change', syncRangeFields));
    syncRangeFields();
    recipientMode.value = 'subgroups';
    parentGroupDropdown?.addEventListener('change', function () {
        renderSubgroupOptions(this.value);
    });
    subgroupCheckboxList?.querySelectorAll('input[name="subgroup_ids[]"]').forEach((input) => {
        input.addEventListener('change', updateSubgroupButtonLabel);
    });
    templateSearch?.addEventListener('input', filterTemplateOptions);
    templateOptions?.querySelectorAll('.wg-search-option').forEach((option) => {
        option.addEventListener('click', function () {
            templateInput.value = this.getAttribute('data-template-id') || '';
            templateSearch.value = this.getAttribute('data-template-name') || '';
            templateOptions.querySelectorAll('.wg-search-option').forEach((item) => item.classList.remove('active'));
            this.classList.add('active');
            loadTemplatePreview(templateInput.value);
        });
    });

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

            if (!templateInput.value) {
                progressCard.classList.remove('d-none');
                progressErrors.classList.remove('d-none');
                progressErrors.textContent = 'Select a template.';
                progressStatus.textContent = 'Template needs correction.';
                return;
            }

            if (selectedSubgroupIds().length === 0) {
                progressCard.classList.remove('d-none');
                progressErrors.classList.remove('d-none');
                progressErrors.textContent = 'Select at least one subgroup.';
                progressStatus.textContent = 'Recipients need correction.';
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
                prepareData.append('limit', String(batchSize));
                prepareData.append('action', 'prepare');
                prepareData.append('recipient_mode', baseData.get('recipient_mode') || 'all');
                selectedSubgroupIds().forEach((id) => prepareData.append('subgroup_ids[]', id));
                prepareData.append('send_scope', baseData.get('send_scope') || 'all');
                prepareData.append('range_start', baseData.get('range_start') || '');
                prepareData.append('range_end', baseData.get('range_end') || '');
                appendTemplateVariableValues(baseData, prepareData);

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
                    batchData.append('limit', String(batchSize));
                    batchData.append('offset', String(offset));
                    batchData.append('action', 'send');
                    batchData.append('recipient_mode', baseData.get('recipient_mode') || 'all');
                    selectedSubgroupIds().forEach((id) => batchData.append('subgroup_ids[]', id));
                    batchData.append('send_scope', baseData.get('send_scope') || 'all');
                    batchData.append('range_start', baseData.get('range_start') || '');
                    batchData.append('range_end', baseData.get('range_end') || '');
                    appendTemplateVariableValues(baseData, batchData);

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

    function loadTemplatePreview(templateId) {
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
                    renderTemplateVariableFields(data.variable_requirements || {});
                    syncTemplateMediaField(data);

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
            renderTemplateVariableFields({});
            syncTemplateMediaField({});
        }
    }
</script>

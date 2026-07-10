<?php
include '../session.php';
include '../db_conn.php';

$biz_id = Auth::requireLogin();

include 'header.php';

$message = '';
$message_type = 'success';
$apiPayloadPreview = '';
$mediaUploadError = '';
$mediaUploadWarning = '';
$uploadedMediaPreviewUrl = '';

function normalizeTemplateName(string $name): string
{
    $name = strtolower(trim($name));
    $name = preg_replace('/[^a-z0-9_]+/', '_', $name) ?? '';
    $name = preg_replace('/_+/', '_', $name) ?? '';
    return trim($name, '_');
}

function variableNumbers(string $text): array
{
    preg_match_all('/{{\s*(\d+)\s*}}|\[\s*(\d+)\s*\]/', $text, $matches, PREG_SET_ORDER);
    $numbers = [];
    foreach ($matches as $match) {
        $numbers[] = isset($match[1]) && $match[1] !== '' ? (int) $match[1] : (int) ($match[2] ?? 0);
    }
    $numbers = array_values(array_unique($numbers));
    sort($numbers);
    return $numbers;
}

function normalizeTemplateText(string $text): string
{
    $text = trim($text);
    $text = preg_replace('/{{\s*(\d+)\s*}}/', '{{$1}}', $text) ?? $text;
    $text = preg_replace('/\[\s*(\d+)\s*\]/', '{{$1}}', $text) ?? $text;
    $text = preg_replace('/(?<!\{)\{\s*(\d+)\s*\}(?!\})/', '{{$1}}', $text) ?? $text;
    return $text;
}

function sequentialVariableError(array $numbers, string $label): string
{
    if (empty($numbers)) {
        return '';
    }

    $expected = range(1, count($numbers));
    return $numbers === $expected ? '' : $label . ' variables must start at {{1}} and continue without gaps.';
}

function templateExampleValue(array $samples, int $number): string
{
    return trim((string) ($samples[$number] ?? $samples[(string) $number] ?? ''));
}

function validTemplateUrlExample(string $url): bool
{
    return filter_var($url, FILTER_VALIDATE_URL) !== false && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::verifyCsrf();

    $template_name = normalizeTemplateName((string) ($_POST['template_name'] ?? ''));
    $category = strtoupper(trim((string) ($_POST['category'] ?? 'MARKETING')));
    $language = trim((string) ($_POST['language'] ?? 'en_US'));
    $header_type = strtoupper(trim((string) ($_POST['header_type'] ?? 'NONE')));
    $header_text = normalizeTemplateText((string) ($_POST['header_text'] ?? ''));
    $header_media_handle = trim((string) ($_POST['header_media_handle'] ?? ''));
    $header_media_url = trim((string) ($_POST['header_media_url'] ?? ''));
    $header_media_file = $_FILES['header_media_file'] ?? null;
    $body_text = normalizeTemplateText((string) ($_POST['body_text'] ?? ''));
    $footer_text = trim((string) ($_POST['footer_text'] ?? ''));
    $auth_button_text = trim((string) ($_POST['auth_button_text'] ?? 'Copy code')) ?: 'Copy code';
    $auth_expiration_minutes = Security::intFrom($_POST['auth_expiration_minutes'] ?? 10, 10);
    $auth_add_security = !empty($_POST['auth_add_security']);
    $header_sample = trim((string) ($_POST['header_sample'] ?? ''));
    $body_samples = $_POST['body_samples'] ?? [];
    $buttonsInput = $_POST['buttons'] ?? [];

    $components = [];
    $buttons = [];

    $stmt = $db->prepare('SELECT whatsapp_id, auth_token FROM gd_orders WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $biz_id);
    $stmt->execute();
    $business = $stmt->get_result()->fetch_assoc() ?: [];

    $access_token = ($business['auth_token'] ?? '') ?: AppSettings::getGlobal($db, 'META_ACCESS_TOKEN', Config::get('META_ACCESS_TOKEN', ''));
    $whatsapp_business_id = trim((string) ($business['whatsapp_id'] ?? ''));
    $appId = trim((string) AppSettings::getGlobal($db, 'META_APP_ID', Config::get('META_APP_ID', '')));

    if (is_array($header_media_file) && (int) ($header_media_file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $fileName = basename((string) $header_media_file['name']);
        $tmpPath = (string) $header_media_file['tmp_name'];
        $fileSize = (int) $header_media_file['size'];
        $fileType = mime_content_type($tmpPath) ?: (string) ($header_media_file['type'] ?? '');
        $allowedTypes = [
            'image/jpeg', 'image/png',
            'video/mp4', 'video/3gpp',
            'application/pdf',
        ];

        if ($appId === '' || $access_token === '') {
            $mediaUploadError = 'Meta App ID or access token is missing. Add API credentials first.';
        } elseif (!in_array($fileType, $allowedTypes, true)) {
            $mediaUploadError = 'Unsupported file type. Use JPG, PNG, MP4, 3GP, or PDF.';
        } else {
            $s3Upload = ApiSupport::s3UploadFile($tmpPath, $fileName, $fileType);
            if (!($s3Upload['ok'] ?? false)) {
                $mediaUploadError = (string) ($s3Upload['error'] ?? 'Unknown S3 upload error.');
            } else {
                $uploadedMediaPreviewUrl = (string) ($s3Upload['url'] ?? '');

                $uploadResult = ApiSupport::metaUploadMediaHandle(
                    (string) $appId,
                    (string) $access_token,
                    $tmpPath,
                    $fileName,
                    $fileType,
                    $fileSize
                );

                if (!($uploadResult['ok'] ?? false)) {
                    $metaError = (string) ($uploadResult['error'] ?? 'Unknown error.');
                    $mediaUploadError = $mediaUploadError !== '' ? trim($mediaUploadError . ' ' . $metaError) : $metaError;
                } else {
                    $header_media_handle = (string) ($uploadResult['handle'] ?? '');
                    if ($uploadedMediaPreviewUrl !== '') {
                        $header_media_url = $uploadedMediaPreviewUrl;
                    } else {
                        $mediaUploadWarning = 'Media handle generated, but S3 preview file could not be saved.';
                    }
                }
            }
        }
    }

    if ($template_name === '' || ($category !== 'AUTHENTICATION' && $body_text === '')) {
        $message = $category === 'AUTHENTICATION' ? 'Template name is required.' : 'Template name and body are required.';
        $message_type = 'danger';
    } else {
        $validationErrors = [];

        if ($mediaUploadError !== '') {
            $validationErrors[] = $mediaUploadError;
        }

        if ($category === 'AUTHENTICATION') {
            $header_type = 'NONE';
            $header_text = '';
            $header_media_handle = '';
            $header_media_url = '';
            $body_text = 'Your verification code is {{1}}';
            $footer_text = '';
            $buttonsInput = [];
            $auth_expiration_minutes = max(1, min(90, $auth_expiration_minutes));
        }

        $headerVariableNumbers = variableNumbers($header_text);
        $bodyVariableNumbers = variableNumbers($body_text);
        $bodySequenceError = sequentialVariableError($bodyVariableNumbers, 'Body');
        if ($bodySequenceError !== '') {
            $validationErrors[] = $bodySequenceError;
        }

        if ($category === 'AUTHENTICATION') {
            $body = [
                'type' => 'BODY',
                'add_security_recommendation' => $auth_add_security,
            ];
            $components[] = $body;

            $components[] = [
                'type' => 'FOOTER',
                'code_expiration_minutes' => $auth_expiration_minutes,
            ];

            $buttons[] = [
                'type' => 'OTP',
                'otp_type' => 'COPY_CODE',
                'text' => $auth_button_text,
            ];

            $components[] = [
                'type' => 'BUTTONS',
                'buttons' => $buttons,
            ];
        } elseif ($header_type === 'TEXT' && $header_text !== '') {
            $header = [
                'type' => 'HEADER',
                'format' => 'TEXT',
                'text' => $header_text,
            ];

            if (count($headerVariableNumbers) > 1) {
                $validationErrors[] = 'Text header can contain only one variable.';
            } elseif (!empty($headerVariableNumbers) && $header_sample === '') {
                $validationErrors[] = 'Header variable example is required.';
            } elseif (!empty($headerVariableNumbers)) {
                $header['example'] = ['header_text' => [$header_sample]];
            }

            $components[] = $header;
        } elseif (in_array($header_type, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)) {
            if ($header_media_handle === '') {
                $validationErrors[] = ucfirst(strtolower($header_type)) . ' header requires a WhatsApp media handle for template review.';
            } else {
                $components[] = [
                    'type' => 'HEADER',
                    'format' => $header_type,
                    'example' => [
                        'header_handle' => [$header_media_handle],
                    ],
                ];
            }
        }

        if ($category !== 'AUTHENTICATION') {
            $body = [
                'type' => 'BODY',
                'text' => $body_text,
            ];

            if (!empty($bodyVariableNumbers)) {
                $sampleRow = [];
                foreach ($bodyVariableNumbers as $number) {
                    $sampleRow[] = templateExampleValue(is_array($body_samples) ? $body_samples : [], $number);
                }

                if (in_array('', $sampleRow, true)) {
                    $validationErrors[] = 'Every body variable needs an example value.';
                } else {
                  $body['example'] = ['body_text' => [$sampleRow]];
                }
            }

            $components[] = $body;
        }

        if ($category !== 'AUTHENTICATION' && $footer_text !== '') {
            $components[] = [
                'type' => 'FOOTER',
                'text' => $footer_text,
            ];
        }

        foreach (($category === 'AUTHENTICATION' ? [] : $buttonsInput) as $button) {
            if (!is_array($button)) {
                continue;
            }

            $buttonType = strtoupper(trim((string) ($button['type'] ?? '')));
            $buttonText = trim((string) ($button['text'] ?? ''));
            $buttonValue = normalizeTemplateText((string) ($button['value'] ?? ''));

            if ($buttonType === '' || $buttonText === '') {
                continue;
            }

            if ($buttonType === 'URL' && $buttonValue !== '') {
                $urlButton = [
                    'type' => 'URL',
                    'text' => $buttonText,
                    'url' => $buttonValue,
                ];

                $buttonNumbers = variableNumbers($buttonValue);
                if (!empty($buttonNumbers)) {
                    if (count($buttonNumbers) > 1 || $buttonNumbers !== [1]) {
                        $validationErrors[] = 'Dynamic URL buttons can use only {{1}}.';
                    } else {
                        $urlExample = preg_replace('/{{\s*1\s*}}/', templateExampleValue(is_array($body_samples) ? $body_samples : [], 1) ?: 'sample', $buttonValue) ?? $buttonValue;
                        $urlButton['example'] = [$urlExample];
                        if (!validTemplateUrlExample($urlExample)) {
                            $validationErrors[] = 'URL button example must be a valid http or https URL.';
                        }
                    }
                } elseif (!validTemplateUrlExample($buttonValue)) {
                    $validationErrors[] = 'URL button must be a valid http or https URL.';
                }

                $buttons[] = $urlButton;
            } elseif ($buttonType === 'PHONE_NUMBER' && $buttonValue !== '') {
                $buttons[] = [
                    'type' => 'PHONE_NUMBER',
                    'text' => $buttonText,
                    'phone_number' => $buttonValue,
                ];
            } elseif ($buttonType === 'QUICK_REPLY') {
                $buttons[] = [
                    'type' => 'QUICK_REPLY',
                    'text' => $buttonText,
                ];
            }
        }

        if ($category !== 'AUTHENTICATION' && !empty($buttons)) {
            $components[] = [
                'type' => 'BUTTONS',
                'buttons' => $buttons,
            ];
        }

        $payload = [
            'name' => $template_name,
            'category' => $category,
            'language' => $language,
            'components' => $components,
        ];
        $apiPayloadPreview = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (!empty($validationErrors)) {
            $message = implode(' ', $validationErrors);
            $message_type = 'danger';
        } else {
        if ($whatsapp_business_id === '' || $access_token === '') {
            $message = 'WhatsApp Business ID or access token is missing. Add API credentials first.';
            $message_type = 'danger';
        } else {
            $ch = curl_init("https://graph.facebook.com/v18.0/{$whatsapp_business_id}/message_templates");
            curl_setopt_array($ch, [
                CURLOPT_HTTPHEADER => [
                    "Authorization: Bearer {$access_token}",
                    'Content-Type: application/json',
                ],
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
            ]);

            $response = curl_exec($ch);
            $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            $apiResponse = json_decode((string) $response, true);

            if ($curlError !== '') {
                $message = 'WhatsApp API request failed: ' . $curlError;
                $message_type = 'danger';
            } elseif ($httpStatus < 200 || $httpStatus >= 300 || isset($apiResponse['error'])) {
                $apiError = $apiResponse['error']['message'] ?? 'Unexpected WhatsApp API error.';
                $apiDetails = $apiResponse['error']['error_data']['details'] ?? $apiResponse['error']['error_user_msg'] ?? '';
                if (is_string($apiDetails) && trim($apiDetails) !== '') {
                    $apiError .= ' Details: ' . trim($apiDetails);
                }
                $message = 'WhatsApp API rejected the template: ' . $apiError;
                $message_type = 'danger';
            } else {
                $template_id = (string) ($apiResponse['id'] ?? '');
                $status = (string) ($apiResponse['status'] ?? 'PENDING');
                $apiCategory = (string) ($apiResponse['category'] ?? $category);
                $buttonsJson = json_encode($buttons);
                $placeholdersJson = json_encode([
                    'header_type' => $header_type,
                    'header_text' => $header_text,
                    'header_sample' => $header_sample,
                    'header_media_handle' => $header_media_handle,
                    'header_media_url' => $header_media_url,
                    'body_samples' => $body_samples,
                    'body_placeholder_numbers' => $bodyVariableNumbers,
                    'auth_button_text' => $auth_button_text,
                    'auth_expiration_minutes' => $auth_expiration_minutes,
                    'auth_add_security' => $auth_add_security,
                    'buttons' => $buttons,
                    'payload' => $payload,
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                $stmt = $db->prepare('INSERT INTO gd_whatsapp_templates (biz_id, template_id, template_name, message_title, message_body, placeholders, subtitle, media_url, status, category, buttons) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $message_title = $header_type === 'TEXT' ? $header_text : ($header_type !== 'NONE' ? ucfirst(strtolower($header_type)) . ' Header' : 'Template');
                $media_url = $header_media_url;
                $stmt->bind_param('issssssssss', $biz_id, $template_id, $template_name, $message_title, $body_text, $placeholdersJson, $footer_text, $media_url, $status, $apiCategory, $buttonsJson);

                if ($stmt->execute()) {
                    $message = 'Template created on WhatsApp Cloud API and saved locally.';
                    $message_type = 'success';
                    if ($mediaUploadWarning !== '') {
                        $message = $message . ' ' . $mediaUploadWarning;
                        $message_type = 'warning';
                    }
                } else {
                    $message = 'Template created on WhatsApp Cloud API, but local save failed.';
                    $message_type = 'warning';
                }
            }
        }
        }
    }
}
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

        <div class="col-lg-7 col-md-9 wg-main">
            <h4><i class="bi bi-cloud-plus"></i> Create Cloud API Template</h4>
            <div class="alert alert-info">
                For image, video, or document headers, choose a file here or upload it first. Files are stored in S3 and the app generates the WhatsApp media handle.
    <a href="<?php echo h(app_url('business/upload-media')); ?>" class="alert-link">Upload media</a>
            </div>
            <form action="" method="post" id="templateForm" enctype="multipart/form-data">
                <?php echo Security::csrfField(); ?>

                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label" for="template_name">Template Name</label>
                        <input type="text" name="template_name" id="template_name" class="form-control" required placeholder="order_update_1">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="category">Category</label>
                        <select name="category" id="category" class="form-control">
                            <option value="MARKETING">Marketing</option>
                            <option value="UTILITY">Utility</option>
                            <option value="AUTHENTICATION">Authentication</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="language">Language</label>
                        <select name="language" id="language" class="form-control">
                            <option value="en_US">English (US)</option>
                            <option value="en">English</option>
                            <option value="hi">Hindi</option>
                            <option value="te">Telugu</option>
                        </select>
                    </div>
                </div>

                <div class="row auth-template-field d-none">
                    <div class="col-md-4">
                        <label class="form-label" for="auth_button_text">OTP Button Text</label>
                        <input type="text" name="auth_button_text" id="auth_button_text" class="form-control" value="Copy code" maxlength="25" oninput="renderTemplateBuilder()">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="auth_expiration_minutes">Code Expiry Minutes</label>
                        <input type="number" name="auth_expiration_minutes" id="auth_expiration_minutes" class="form-control" value="10" min="1" max="90" oninput="renderTemplateBuilder()">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="auth_add_security" id="auth_add_security" checked onchange="renderTemplateBuilder()">
                            <label class="form-check-label" for="auth_add_security">Add security recommendation</label>
                        </div>
                    </div>
                </div>

                <div class="row standard-template-field">
                    <div class="col-md-4">
                        <label class="form-label" for="header_type">Header Type</label>
                        <select name="header_type" id="header_type" class="form-control" onchange="toggleHeader()">
                            <option value="NONE">None</option>
                            <option value="TEXT">Text</option>
                            <option value="IMAGE">Image</option>
                            <option value="VIDEO">Video</option>
                            <option value="DOCUMENT">Document</option>
                        </select>
                    </div>
                    <div class="col-md-8 header-text-field d-none">
                        <label class="form-label" for="header_text">Header Text</label>
                        <input type="text" name="header_text" id="header_text" class="form-control" placeholder="Hello {{1}}" oninput="renderTemplateBuilder()">
                    </div>
                    <div class="col-md-12 header-text-field d-none">
                        <label class="form-label" for="header_sample">Header Variable Example</label>
                        <input type="text" name="header_sample" id="header_sample" class="form-control" placeholder="Example value for {{1}}">
                    </div>
                    <div class="col-md-7 header-media-field d-none">
                        <label class="form-label" for="header_media_handle">Media Handle</label>
                        <input type="text" name="header_media_handle" id="header_media_handle" class="form-control" placeholder="WhatsApp uploaded media handle" oninput="renderTemplateBuilder()">
                    </div>
                    <div class="col-md-5 header-media-field d-none">
                        <label class="form-label" for="header_media_url">Preview URL</label>
                        <input type="url" name="header_media_url" id="header_media_url" class="form-control" placeholder="https://example.com/image.jpg" oninput="renderTemplateBuilder()">
                    </div>
                    <div class="col-md-12 header-media-field d-none mt-2">
                        <label class="form-label" for="header_media_file">Upload Media File</label>
                        <input type="file" name="header_media_file" id="header_media_file" class="form-control" accept=".jpg,.jpeg,.png,.mp4,.3gp,.pdf,image/jpeg,image/png,video/mp4,video/3gpp,application/pdf" onchange="renderTemplateBuilder()">
                    </div>
                </div>

                <div class="row standard-template-field">
                    <div class="col-md-12">
                        <label class="form-label" for="body_text">Body Text</label>
                        <textarea name="body_text" id="body_text" class="form-control" rows="7" placeholder="Hi {{1}}, your order {{2}} is ready." oninput="renderTemplateBuilder()"></textarea>
                    </div>
                </div>

                <div id="variableSamples" class="row"></div>

                <div class="row standard-template-field">
                    <div class="col-md-12">
                        <label class="form-label" for="footer_text">Footer Text</label>
                        <input type="text" name="footer_text" id="footer_text" class="form-control" placeholder="Thank you for choosing us" oninput="renderTemplateBuilder()">
                    </div>
                </div>

                <div class="row standard-template-field">
                    <div class="col-md-12">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h5 class="mb-0">Buttons</h5>
                            <button type="button" class="btn btn-light btn-sm" onclick="addButton()"><i class="bi bi-plus-circle me-1"></i> Add Button</button>
                        </div>
                        <div id="button-container"></div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary mt-2"><i class="bi bi-cloud-upload me-1"></i> Create on WhatsApp</button>
            </form>
        </div>

        <div class="col-lg-3 col-md-9 wg-main">
            <h5><i class="bi bi-phone"></i> Template Preview</h5>
            <div class="border p-3 shadow-sm bg-light">
                <div id="previewMediaUrl" class="mb-3 text-center"></div>
                <h6 id="previewTitle" class="text-primary">[Header]</h6>
                <p id="previewBody">[Body]</p>
                <h6 id="previewSubtitle" class="text-secondary">[Footer]</h6>
                <div id="previewButtons" class="mt-3"></div>
            </div>

            <h5 class="mt-4"><i class="bi bi-code-square"></i> API Payload</h5>
            <pre class="wg-code-preview" id="payloadPreview"><?php echo h($apiPayloadPreview ?: '{}'); ?></pre>
        </div>
    </div>
</div>

<script>
let buttonCounter = 0;

function variableNumbers(text) {
  const matches = [...(text || '').matchAll(/{{\s*(\d+)\s*}}|\[\s*(\d+)\s*\]/g)].map(match => Number(match[1] || match[2]));
  return [...new Set(matches)].sort((a, b) => a - b);
}

function normalizeTemplateText(text) {
  const open = String.fromCharCode(123, 123);
  const close = String.fromCharCode(125, 125);

  return (text || '')
    .trim()
    .replace(/\{\{\s*(\d+)\s*\}\}/g, function (_, number) {
      return open + number + close;
    })
    .replace(/\[\s*(\d+)\s*\]/g, function (_, number) {
      return open + number + close;
    })
    .replace(/(^|[^{])\{\s*(\d+)\s*\}(?!})/g, function (_, prefix, number) {
      return prefix + open + number + close;
    });
}

function sampleValuesByNumber() {
  const samples = {};
  document.querySelectorAll('#variableSamples input').forEach(function (input) {
    samples[input.dataset.variable] = input.value;
  });
  return samples;
}

function toggleHeader() {
  if (document.getElementById('category').value === 'AUTHENTICATION') {
    renderTemplateBuilder();
    return;
  }

  const type = document.getElementById('header_type').value;
  const showText = type === 'TEXT';
  const showMedia = ['IMAGE', 'VIDEO', 'DOCUMENT'].includes(type);
  document.querySelectorAll('.header-text-field').forEach(function (field) {
    field.classList.toggle('d-none', !showText);
  });
  document.querySelectorAll('.header-media-field').forEach(function (field) {
    field.classList.toggle('d-none', !showMedia);
  });
  renderTemplateBuilder();
}

function renderTemplateBuilder() {
  const category = document.getElementById('category').value;
  const headerType = document.getElementById('header_type').value;
  const headerText = normalizeTemplateText(document.getElementById('header_text').value);
  const headerMediaUrl = document.getElementById('header_media_url').value;
  const headerMediaFile = document.getElementById('header_media_file');
  const bodyText = normalizeTemplateText(document.getElementById('body_text').value);
  const footerText = document.getElementById('footer_text').value;
  const mediaPreview = document.getElementById('previewMediaUrl');
  const sampleWrap = document.getElementById('variableSamples');
  const existing = {};
  const isAuth = category === 'AUTHENTICATION';

  document.querySelectorAll('.auth-template-field').forEach(function (field) {
    field.classList.toggle('d-none', !isAuth);
  });
  document.querySelectorAll('.standard-template-field').forEach(function (field) {
    field.classList.toggle('d-none', isAuth);
  });

  document.getElementById('body_text').required = !isAuth;

  if (isAuth) {
    sampleWrap.innerHTML = '';
    mediaPreview.innerHTML = '';
    document.getElementById('previewTitle').classList.add('d-none');
    document.getElementById('previewBody').textContent = 'Your verification code is 123456';
    document.getElementById('previewSubtitle').textContent = `This code expires in ${document.getElementById('auth_expiration_minutes').value || 10} minutes.`;
    const preview = document.getElementById('previewButtons');
    preview.innerHTML = '';
    const el = document.createElement('button');
    el.type = 'button';
    el.className = 'btn btn-primary btn-sm me-2 mb-2';
    el.textContent = document.getElementById('auth_button_text').value || 'Copy code';
    preview.appendChild(el);
    renderPayloadPreview();
    return;
  }

  sampleWrap.querySelectorAll('input').forEach(function (input) {
    existing[input.dataset.variable] = input.value;
  });

  sampleWrap.innerHTML = '';
  variableNumbers(bodyText).forEach(function (number) {
    const col = document.createElement('div');
    col.className = 'col-md-6';
    col.innerHTML = `
      <label class="form-label">Body {{${number}}} Example</label>
      <input type="text" class="form-control" name="body_samples[${number}]" data-variable="${number}" value="${existing[number] || ''}" placeholder="Sample value for {{${number}}}" oninput="renderPayloadPreview()">
    `;
    sampleWrap.appendChild(col);
  });

  mediaPreview.innerHTML = '';

  if (headerType === 'TEXT') {
    document.getElementById('previewTitle').classList.remove('d-none');
    document.getElementById('previewTitle').textContent = headerText || '[Header]';
  } else if (headerType === 'IMAGE') {
    document.getElementById('previewTitle').classList.add('d-none');
    if (headerMediaFile.files && headerMediaFile.files[0]) {
      const reader = new FileReader();
      reader.onload = function (event) {
        mediaPreview.innerHTML = `<img src="${event.target.result}" alt="Image preview" style="max-width:100%; max-height:220px; border-radius:8px;">`;
      };
      reader.readAsDataURL(headerMediaFile.files[0]);
    } else {
      mediaPreview.innerHTML = headerMediaUrl
        ? `<img src="${headerMediaUrl}" alt="Image preview" style="max-width:100%; max-height:220px; border-radius:8px;">`
        : '<div class="text-muted small">Image header preview</div>';
    }
  } else if (headerType === 'VIDEO') {
    document.getElementById('previewTitle').classList.add('d-none');
    if (headerMediaFile.files && headerMediaFile.files[0]) {
      const reader = new FileReader();
      reader.onload = function (event) {
        mediaPreview.innerHTML = `<video src="${event.target.result}" controls style="width:100%; max-height:220px; border-radius:8px;"></video>`;
      };
      reader.readAsDataURL(headerMediaFile.files[0]);
    } else {
      mediaPreview.innerHTML = headerMediaUrl
        ? `<video src="${headerMediaUrl}" controls style="width:100%; max-height:220px; border-radius:8px;"></video>`
        : '<div class="text-muted small">Video header preview</div>';
    }
  } else if (headerType === 'DOCUMENT') {
    document.getElementById('previewTitle').classList.add('d-none');
    if (headerMediaFile.files && headerMediaFile.files[0]) {
      mediaPreview.innerHTML = `<div class="text-muted small">${headerMediaFile.files[0].name}</div>`;
    } else {
      mediaPreview.innerHTML = headerMediaUrl
        ? `<a class="btn btn-light btn-sm" href="${headerMediaUrl}" target="_blank"><i class="bi bi-file-earmark-text me-1"></i> Open document</a>`
        : '<div class="text-muted small">Document header preview</div>';
    }
  } else {
    document.getElementById('previewTitle').classList.add('d-none');
  }

  document.getElementById('previewBody').textContent = bodyText || '[Body]';
  document.getElementById('previewSubtitle').textContent = footerText || '[Footer]';
  renderButtonsPreview();
  renderPayloadPreview();
}

function addButton() {
  buttonCounter++;
  const wrap = document.createElement('div');
  wrap.className = 'wg-button-row';
  wrap.innerHTML = `
    <div class="row">
      <div class="col-md-4">
        <select class="form-control" name="buttons[${buttonCounter}][type]" onchange="renderTemplateBuilder()">
          <option value="QUICK_REPLY">Quick Reply</option>
          <option value="URL">Website URL</option>
          <option value="PHONE_NUMBER">Phone Number</option>
        </select>
      </div>
      <div class="col-md-4">
        <input class="form-control" name="buttons[${buttonCounter}][text]" placeholder="Button text" oninput="renderTemplateBuilder()">
      </div>
      <div class="col-md-3">
        <input class="form-control" name="buttons[${buttonCounter}][value]" placeholder="URL or phone" oninput="renderTemplateBuilder()">
      </div>
      <div class="col-md-1">
        <button type="button" class="btn btn-danger w-100" onclick="this.closest('.wg-button-row').remove(); renderTemplateBuilder();"><i class="bi bi-x"></i></button>
      </div>
    </div>
  `;
  document.getElementById('button-container').appendChild(wrap);
}

function collectButtons() {
  const buttons = [];
  document.querySelectorAll('.wg-button-row').forEach(function (row) {
    const type = row.querySelector('select').value;
    const text = row.querySelector('input[name$="[text]"]').value;
    const value = normalizeTemplateText(row.querySelector('input[name$="[value]"]').value);
    if (!text) {
      return;
    }
    const button = { type, text };
    if (type === 'URL') {
      button.url = value;
    }
    if (type === 'PHONE_NUMBER') {
      button.phone_number = value;
    }
    buttons.push(button);
  });
  return buttons;
}

function renderButtonsPreview() {
  const preview = document.getElementById('previewButtons');
  preview.innerHTML = '';
  collectButtons().forEach(function (button) {
    const el = document.createElement('button');
    el.type = 'button';
    el.className = 'btn btn-primary btn-sm me-2 mb-2';
    el.textContent = button.text;
    preview.appendChild(el);
  });
}

function renderPayloadPreview() {
  const name = document.getElementById('template_name').value.toLowerCase().replace(/[^a-z0-9_]+/g, '_').replace(/_+/g, '_').replace(/^_|_$/g, '');
  const category = document.getElementById('category').value;
  const language = document.getElementById('language').value;
  const isAuth = category === 'AUTHENTICATION';
  const headerType = document.getElementById('header_type').value;
  const headerText = normalizeTemplateText(document.getElementById('header_text').value);
  const headerSample = document.getElementById('header_sample').value;
  const headerMediaHandle = document.getElementById('header_media_handle').value;
  const bodyText = normalizeTemplateText(document.getElementById('body_text').value);
  const footerText = document.getElementById('footer_text').value;
  const bodySamples = sampleValuesByNumber();
  const components = [];

  if (isAuth) {
    components.push({
      type: 'BODY',
      add_security_recommendation: document.getElementById('auth_add_security').checked
    });
    components.push({
      type: 'FOOTER',
      code_expiration_minutes: Number(document.getElementById('auth_expiration_minutes').value || 10)
    });
    components.push({
      type: 'BUTTONS',
      buttons: [{
        type: 'OTP',
        otp_type: 'COPY_CODE',
        text: document.getElementById('auth_button_text').value || 'Copy code'
      }]
    });

    document.getElementById('payloadPreview').textContent = JSON.stringify({ name, category, language, components }, null, 2);
    return;
  }

  if (headerType === 'TEXT' && headerText) {
    const header = { type: 'HEADER', format: 'TEXT', text: headerText };
    if (variableNumbers(headerText).length) {
      header.example = { header_text: [headerSample || '<header_example_required>'] };
    }
    components.push(header);
  } else if (['IMAGE', 'VIDEO', 'DOCUMENT'].includes(headerType)) {
    components.push({
      type: 'HEADER',
      format: headerType,
      example: {
        header_handle: headerMediaHandle ? [headerMediaHandle] : ['<media_handle_required>']
      }
    });
  }
  if (bodyText) {
    const body = { type: 'BODY', text: bodyText };
    const numbers = variableNumbers(bodyText);
    if (numbers.length) {
      body.example = {
        body_text: [numbers.map(function (number) {
          return bodySamples[number] || '<example_required>';
        })]
      };
    }
    components.push(body);
  }
  if (footerText) {
    components.push({ type: 'FOOTER', text: footerText });
  }
  const buttons = collectButtons();
  if (buttons.length) {
    components.push({ type: 'BUTTONS', buttons });
  }

  document.getElementById('payloadPreview').textContent = JSON.stringify({ name, category, language, components }, null, 2);
}

document.getElementById('template_name').addEventListener('input', renderPayloadPreview);
document.getElementById('category').addEventListener('change', renderTemplateBuilder);
document.getElementById('language').addEventListener('change', renderPayloadPreview);
document.getElementById('auth_button_text').addEventListener('input', renderTemplateBuilder);
document.getElementById('auth_expiration_minutes').addEventListener('input', renderTemplateBuilder);
document.getElementById('auth_add_security').addEventListener('change', renderTemplateBuilder);
document.getElementById('header_sample').addEventListener('input', renderPayloadPreview);
document.getElementById('header_media_file').addEventListener('change', renderTemplateBuilder);
document.getElementById('header_text').addEventListener('blur', function () {
  this.value = normalizeTemplateText(this.value);
  renderTemplateBuilder();
});
document.getElementById('body_text').addEventListener('blur', function () {
  this.value = normalizeTemplateText(this.value);
  renderTemplateBuilder();
});
renderTemplateBuilder();
</script>

<?php include 'footer.php'; ?>

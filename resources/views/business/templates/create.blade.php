@extends('layouts.business')

@section('title', 'New Template')

@section('content')
  <div class="row g-4">
    <div class="col-lg-8">
      <div class="card shadow-sm border-0">
        <div class="card-header bg-white">
          <strong>Add Template</strong>
        </div>
        <div class="card-body">
          <div class="alert alert-info">
            For image, video, or document headers, use the file picker below. The app will upload the file to Meta and generate the media handle for you.
          </div>

          <form action="{{ route('business.templates.store') }}" method="post" enctype="multipart/form-data" id="templateForm">
            @csrf

            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label" for="template_name">Template Name</label>
                <input type="text" name="template_name" id="template_name" class="form-control" required placeholder="order_update_1" value="{{ old('template_name') }}">
              </div>
              <div class="col-md-3">
                <label class="form-label" for="category">Category</label>
                <select name="category" id="category" class="form-control">
                  @foreach (['MARKETING' => 'Marketing', 'UTILITY' => 'Utility', 'AUTHENTICATION' => 'Authentication'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('category', 'MARKETING') === $value)>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label" for="language">Language</label>
                <select name="language" id="language" class="form-control">
                  @foreach (['en_US' => 'English (US)', 'en' => 'English', 'hi' => 'Hindi', 'te' => 'Telugu'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('language', 'en_US') === $value)>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
            </div>

            <div class="row g-3 mt-1">
              <div class="col-md-4">
                <label class="form-label" for="header_type">Header Type</label>
                <select name="header_type" id="header_type" class="form-control" onchange="toggleHeader()">
                  @foreach (['NONE' => 'None', 'TEXT' => 'Text', 'IMAGE' => 'Image', 'VIDEO' => 'Video', 'DOCUMENT' => 'Document'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('header_type', 'NONE') === $value)>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-8 header-text-field d-none">
                <label class="form-label" for="header_text">Header Text</label>
                <input type="text" name="header_text" id="header_text" class="form-control" placeholder="Hello @{{1}}" value="{{ old('header_text') }}" oninput="renderTemplateBuilder()">
              </div>
              <div class="col-md-12 header-text-field d-none">
                <label class="form-label" for="header_sample">Header Variable Example</label>
                <input type="text" name="header_sample" id="header_sample" class="form-control" placeholder="Example value for @{{1}}" value="{{ old('header_sample') }}">
              </div>
              <div class="col-md-7 header-media-field d-none">
                <label class="form-label" for="header_media_handle">Media Handle</label>
                <input type="text" name="header_media_handle" id="header_media_handle" class="form-control" placeholder="WhatsApp uploaded media handle" value="{{ old('header_media_handle') }}" oninput="renderTemplateBuilder()">
              </div>
              <div class="col-md-5 header-media-field d-none">
                <label class="form-label" for="header_media_url">Preview URL</label>
                <input type="url" name="header_media_url" id="header_media_url" class="form-control" placeholder="https://example.com/image.jpg" value="{{ old('header_media_url') }}" oninput="renderTemplateBuilder()">
              </div>
              <div class="col-md-12 header-media-field d-none">
                <label class="form-label" for="header_media_file">Upload Media File</label>
                <input type="file" name="header_media_file" id="header_media_file" class="form-control" accept=".jpg,.jpeg,.png,.mp4,.3gp,.pdf,image/jpeg,image/png,video/mp4,video/3gpp,application/pdf" onchange="renderTemplateBuilder()">
              </div>
              <div class="col-md-12 header-media-field d-none">
                <div class="d-flex align-items-center justify-content-between mb-2">
                  <label class="form-label mb-0">Saved Media</label>
                </div>
                @if (empty($mediaLibrary ?? []))
                  <div class="text-muted small">No saved media yet.</div>
                @else
                  <div class="row g-2">
                    @foreach (($mediaLibrary ?? []) as $media)
                      @php
                        $mediaUrl = (string) ($media['s3_url'] ?? '');
                        $mediaHandle = (string) ($media['media_handle'] ?? '');
                        $kind = \ApiSupport::mediaKind((string) ($media['mime_type'] ?? ''), $mediaUrl);
                      @endphp
                      <div class="col-md-4">
                        <div class="border rounded p-2 h-100 bg-light">
                          <div class="text-center mb-2" style="height:90px;">
                            @if ($kind === 'image')
                              <img src="{{ $mediaUrl }}" alt="{{ $media['original_name'] }}" style="max-width:100%; max-height:90px; border-radius:6px;">
                            @elseif ($kind === 'video')
                              <video src="{{ $mediaUrl }}" style="width:100%; max-height:90px; border-radius:6px;"></video>
                            @else
                              <div class="small text-muted pt-4"><i class="bi bi-file-earmark-text me-1"></i> Document</div>
                            @endif
                          </div>
                          <div class="small fw-semibold text-truncate" title="{{ $media['original_name'] }}">{{ $media['original_name'] }}</div>
                          <button type="button" class="btn btn-light btn-sm w-100 mt-2" data-media-url="{{ $mediaUrl }}" data-media-handle="{{ $mediaHandle }}" onclick="useSavedMedia(this)">Use</button>
                        </div>
                      </div>
                    @endforeach
                  </div>
                @endif
              </div>
            </div>

            <div class="row g-3 mt-1">
              <div class="col-md-12">
                <label class="form-label" for="body_text">Body Text</label>
                <textarea name="body_text" id="body_text" class="form-control" rows="7" required placeholder="Hi @{{1}}, your order @{{2}} is ready." oninput="renderTemplateBuilder()">{{ old('body_text') }}</textarea>
              </div>
            </div>

            <div id="variableSamples" class="row g-3 mt-1"></div>

            <div class="row g-3 mt-1">
              <div class="col-md-12">
                <label class="form-label" for="footer_text">Footer Text</label>
                <input type="text" name="footer_text" id="footer_text" class="form-control" placeholder="Thank you for choosing us" value="{{ old('footer_text') }}" oninput="renderTemplateBuilder()">
              </div>
            </div>

            <div class="row g-3 mt-1">
              <div class="col-md-12">
                <div class="d-flex align-items-center justify-content-between mb-2">
                  <h5 class="mb-0">Buttons</h5>
                  <button type="button" class="btn btn-light btn-sm" onclick="addButton()"><i class="bi bi-plus-circle me-1"></i> Add Button</button>
                </div>
                <div id="button-container"></div>
              </div>
            </div>

            <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-cloud-upload me-1"></i> Create on WhatsApp</button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card shadow-sm border-0">
        <div class="card-header bg-white">
          <strong>Template Preview</strong>
        </div>
        <div class="card-body">
          <div class="border p-3 shadow-sm bg-light rounded">
            <div id="previewMediaUrl" class="mb-3 text-center"></div>
            <h6 id="previewTitle" class="text-primary">[Header]</h6>
            <p id="previewBody">[Body]</p>
            <h6 id="previewSubtitle" class="text-secondary">[Footer]</h6>
            <div id="previewButtons" class="mt-3"></div>
          </div>

          <h5 class="mt-4"><i class="bi bi-code-square"></i> API Payload</h5>
          <pre class="bg-dark text-light p-3 rounded small" id="payloadPreview">{}</pre>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
<script>
  let buttonCounter = 0;

  function variableNumbers(text) {
    const placeholderRegex = new RegExp('\\{\\{\\s*(\\d+)\\s*\\}\\}|\\[\\s*(\\d+)\\s*\\]', 'g');
    const matches = [...(text || '').matchAll(placeholderRegex)].map(match => Number(match[1] || match[2]));
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
    const type = document.getElementById('header_type').value;
    const showText = type === 'TEXT';
    const showMedia = ['IMAGE', 'VIDEO', 'DOCUMENT'].includes(type);
    document.querySelectorAll('.header-text-field').forEach((field) => field.classList.toggle('d-none', !showText));
    document.querySelectorAll('.header-media-field').forEach((field) => field.classList.toggle('d-none', !showMedia));
    renderTemplateBuilder();
  }

  function collectButtons() {
    const buttons = [];
    document.querySelectorAll('.wg-button-row').forEach((row) => {
      const type = row.querySelector('select').value;
      const text = row.querySelector('input[name$="[text]"]').value;
      const value = normalizeTemplateText(row.querySelector('input[name$="[value]"]').value);
      if (!text) {
        return;
      }

      const button = { type, text, value };
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
    collectButtons().forEach((button) => {
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
    const headerType = document.getElementById('header_type').value;
    const headerText = normalizeTemplateText(document.getElementById('header_text').value);
    const headerSample = document.getElementById('header_sample').value;
    const headerMediaHandle = document.getElementById('header_media_handle').value;
    const bodyText = normalizeTemplateText(document.getElementById('body_text').value);
    const footerText = document.getElementById('footer_text').value;
    const bodySamples = sampleValuesByNumber();
    const components = [];

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
          header_handle: headerMediaHandle ? [headerMediaHandle] : ['<media_handle_required>'],
        },
      });
    }

    if (bodyText) {
      const body = { type: 'BODY', text: bodyText };
      const numbers = variableNumbers(bodyText);
      if (numbers.length) {
        body.example = {
          body_text: [numbers.map(function (number) {
            return bodySamples[number] || '<example_required>';
          })],
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

  function renderTemplateBuilder() {
    const headerType = document.getElementById('header_type').value;
    const headerText = normalizeTemplateText(document.getElementById('header_text').value);
    const headerMediaUrl = document.getElementById('header_media_url').value;
    const headerMediaFile = document.getElementById('header_media_file');
    const bodyText = normalizeTemplateText(document.getElementById('body_text').value);
    const footerText = document.getElementById('footer_text').value;
    const mediaPreview = document.getElementById('previewMediaUrl');
    const sampleWrap = document.getElementById('variableSamples');
    const existing = {};

    sampleWrap.querySelectorAll('input').forEach((input) => {
      existing[input.dataset.variable] = input.value;
    });

    sampleWrap.innerHTML = '';
    variableNumbers(bodyText).forEach((number) => {
      const col = document.createElement('div');
      col.className = 'col-md-6';
      col.innerHTML = `
        <label class="form-label">Body ${number} Example</label>
        <input type="text" class="form-control" name="body_samples[${number}]" data-variable="${number}" value="${existing[number] || ''}" placeholder="Sample value for &#123;&#123;${number}&#125;&#125;" oninput="renderPayloadPreview()">
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

  function useSavedMedia(button) {
    document.getElementById('header_media_handle').value = button.dataset.mediaHandle || '';
    document.getElementById('header_media_url').value = button.dataset.mediaUrl || '';
    const fileInput = document.getElementById('header_media_file');
    if (fileInput) {
      fileInput.value = '';
    }
    renderTemplateBuilder();
  }

  function addButton() {
    buttonCounter++;
    const wrap = document.createElement('div');
    wrap.className = 'wg-button-row mb-3';
    wrap.innerHTML = `
      <div class="row g-2">
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

  document.getElementById('template_name').addEventListener('input', renderPayloadPreview);
  document.getElementById('category').addEventListener('change', renderPayloadPreview);
  document.getElementById('language').addEventListener('change', renderPayloadPreview);
  document.getElementById('header_sample').addEventListener('input', renderPayloadPreview);
  document.getElementById('header_media_file').addEventListener('change', renderTemplateBuilder);
  document.getElementById('body_text').addEventListener('input', renderTemplateBuilder);
  document.getElementById('footer_text').addEventListener('input', renderTemplateBuilder);
  document.getElementById('header_text').addEventListener('input', renderTemplateBuilder);
  document.getElementById('header_media_handle').addEventListener('input', renderTemplateBuilder);
  document.getElementById('header_media_url').addEventListener('input', renderTemplateBuilder);
  document.getElementById('body_text').addEventListener('blur', function () {
    this.value = normalizeTemplateText(this.value);
    renderTemplateBuilder();
  });
  document.getElementById('header_text').addEventListener('blur', function () {
    this.value = normalizeTemplateText(this.value);
    renderTemplateBuilder();
  });

  toggleHeader();
</script>
@endpush

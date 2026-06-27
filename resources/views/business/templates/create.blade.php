@extends('layouts.business')

@section('title', 'New Template')

@section('content')
  <div class="row">
    <div class="col-md-7 mt-3">
      <h4><i class="bi bi-list-stars"></i> Add Template</h4>
      <form action="{{ route('business.templates.store') }}" method="post" enctype="multipart/form-data">
        @csrf
        <input type="text" name="template_name" class="form-control p-2 shadow mb-4" required placeholder="Template name">
        <input type="text" name="message_title" class="form-control p-2 shadow mb-4" required placeholder="Message Title" id="messageTitle">
        <textarea class="form-control mb-4" name="message" rows="8" placeholder="Message Body" id="messageBody"></textarea>
        <input type="text" name="placeholder" class="form-control p-2 shadow mb-4" placeholder="Placeholders">
        <input type="text" name="subtitle" class="form-control p-2 shadow mb-4" placeholder="Sub title" id="subtitle">
        <input type="url" name="media_url" class="form-control p-2 shadow mb-2" placeholder="Media Url" id="mediaUrl">
        <input type="file" name="media_file" class="form-control p-2 shadow mb-4" accept="image/*" id="mediaFile">
        <h5>Buttons</h5>
        <div id="button-container"></div>
        <button type="button" class="btn btn-light mt-2" id="addButton">+ Add Button</button>
        <button type="submit" class="btn btn-primary mt-4 d-block">Submit Template</button>
      </form>
    </div>
    <div class="col-md-3">
      <h5 class="mt-4">Preview</h5>
      <div class="border p-3 shadow-sm bg-light">
        <div id="previewMediaUrl" class="mb-2 text-center"></div>
        <h6 id="previewTitle" class="text-primary">[Message Title]</h6>
        <p id="previewBody">[Message Body]</p>
        <h6 id="previewSubtitle" class="text-secondary">[Sub Title]</h6>
        <div id="previewButtons" class="mt-3"></div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
<script>
  let buttonCounter = 0;
  const messageTitle = document.getElementById('messageTitle');
  const messageBody = document.getElementById('messageBody');
  const subtitle = document.getElementById('subtitle');
  const mediaUrl = document.getElementById('mediaUrl');
  const mediaFile = document.getElementById('mediaFile');
  const buttonContainer = document.getElementById('button-container');
  const addButton = document.getElementById('addButton');
  const previewTitle = document.getElementById('previewTitle');
  const previewBody = document.getElementById('previewBody');
  const previewSubtitle = document.getElementById('previewSubtitle');
  const previewMediaUrl = document.getElementById('previewMediaUrl');
  const previewButtons = document.getElementById('previewButtons');

  const updatePreview = () => {
    previewTitle.textContent = messageTitle.value || '[Message Title]';
    previewBody.textContent = messageBody.value || '[Message Body]';
    previewSubtitle.textContent = subtitle.value || '[Sub Title]';
    previewMediaUrl.innerHTML = '';

    if (mediaFile.files && mediaFile.files[0]) {
      const file = mediaFile.files[0];
      const reader = new FileReader();
      reader.onload = () => {
        const img = document.createElement('img');
        img.src = reader.result;
        img.alt = 'Media Preview';
        img.style.maxWidth = '100%';
        img.style.maxHeight = '200px';
        previewMediaUrl.appendChild(img);
      };
      reader.readAsDataURL(file);
      return;
    }

    if (mediaUrl.value) {
      const img = document.createElement('img');
      img.src = mediaUrl.value;
      img.alt = 'Media Preview';
      img.style.maxWidth = '100%';
      img.style.maxHeight = '200px';
      previewMediaUrl.appendChild(img);
    }
  };

  const collectButtons = () => {
    const buttons = [];
    document.querySelectorAll('.template-button-row').forEach((row) => {
      const type = row.querySelector('select').value;
      const text = row.querySelector('input[name$="[name]"]').value;
      const link = row.querySelector('input[name$="[link]"]').value;
      if (!text) {
        return;
      }

      const button = { type, name: text, link };
      buttons.push(button);
    });

    return buttons;
  };

  const renderButtonsPreview = () => {
    previewButtons.innerHTML = '';
    collectButtons().forEach((button) => {
      const el = document.createElement('button');
      el.type = 'button';
      el.className = 'btn btn-primary btn-sm me-2 mb-2';
      el.textContent = button.name;
      previewButtons.appendChild(el);
    });
  };

  const renderPayloadPreview = () => {
    const payload = {
      name: messageTitle.value.toLowerCase().replace(/[^a-z0-9_]+/g, '_').replace(/_+/g, '_').replace(/^_|_$/g, ''),
      category: 'MARKETING',
      language: 'en_US',
      components: [],
    };
    document.getElementById('payloadPreview')?.textContent = JSON.stringify(payload, null, 2);
  };

  const syncPreview = () => {
    updatePreview();
    renderButtonsPreview();
    renderPayloadPreview();
  };

  [messageTitle, messageBody, subtitle, mediaUrl].forEach((field) => field.addEventListener('input', syncPreview));
  mediaFile.addEventListener('change', syncPreview);

  addButton.addEventListener('click', () => {
    buttonCounter += 1;
    const wrapper = document.createElement('div');
    wrapper.className = 'mb-3 template-button-row';
    wrapper.innerHTML = `
      <div class="row g-2">
        <div class="col-md-4">
          <select class="form-control" name="buttons[${buttonCounter}][type]">
            <option value="QUICK_REPLY">Quick Reply</option>
            <option value="URL">Website URL</option>
            <option value="PHONE_NUMBER">Phone Number</option>
          </select>
        </div>
        <div class="col-md-4">
          <input type="text" name="buttons[${buttonCounter}][name]" class="form-control" placeholder="Button name">
        </div>
        <div class="col-md-3">
          <input type="url" name="buttons[${buttonCounter}][link]" class="form-control" placeholder="URL or phone">
        </div>
        <div class="col-md-1">
          <button type="button" class="btn btn-danger btn-sm w-100">Remove</button>
        </div>
      </div>
    `;
    wrapper.querySelector('button').addEventListener('click', () => {
      wrapper.remove();
      syncPreview();
    });
    wrapper.querySelectorAll('input, select').forEach((field) => field.addEventListener('input', syncPreview));
    buttonContainer.appendChild(wrapper);
    syncPreview();
  });

  syncPreview();
</script>
@endpush

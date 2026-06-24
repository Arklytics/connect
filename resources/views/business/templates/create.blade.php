@extends('layouts.business')

@section('title', 'New Template')

@section('content')
  <div class="row">
    <div class="col-md-7 mt-3">
      <h4><i class="bi bi-list-stars"></i> Add Template</h4>
      <form action="{{ route('business.templates.store') }}" method="post">
        @csrf
        <input type="text" name="template_name" class="form-control p-2 shadow mb-4" required placeholder="Template name">
        <input type="text" name="message_title" class="form-control p-2 shadow mb-4" required placeholder="Message Title" id="messageTitle">
        <textarea class="form-control mb-4" name="message" rows="8" placeholder="Message Body" id="messageBody"></textarea>
        <input type="text" name="placeholder" class="form-control p-2 shadow mb-4" required placeholder="Placeholders">
        <input type="text" name="subtitle" class="form-control p-2 shadow mb-4" required placeholder="Sub title" id="subtitle">
        <input type="url" name="media_url" class="form-control p-2 shadow mb-4" placeholder="Media Url" id="mediaUrl">
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
  const updatePreview = () => {
    previewTitle.textContent = messageTitle.value || '[Message Title]';
    previewBody.textContent = messageBody.value || '[Message Body]';
    previewSubtitle.textContent = subtitle.value || '[Sub Title]';
    previewMediaUrl.innerHTML = '';
    if (mediaUrl.value) {
      const img = document.createElement('img');
      img.src = mediaUrl.value;
      img.alt = 'Media Preview';
      img.style.maxWidth = '100%';
      img.style.maxHeight = '200px';
      previewMediaUrl.appendChild(img);
    }
  };
  [messageTitle, messageBody, subtitle, mediaUrl].forEach((field) => field.addEventListener('input', updatePreview));
  addButton.addEventListener('click', () => {
    buttonCounter += 1;
    const wrapper = document.createElement('div');
    wrapper.className = 'mb-3';
    wrapper.innerHTML = `
      <input type="text" name="buttons[${buttonCounter}][name]" class="form-control mb-2" required placeholder="Button ${buttonCounter} Name">
      <input type="url" name="buttons[${buttonCounter}][link]" class="form-control mb-2" required placeholder="Button ${buttonCounter} Link">
      <button type="button" class="btn btn-danger btn-sm">Remove</button>
    `;
    wrapper.querySelector('button').addEventListener('click', () => wrapper.remove());
    buttonContainer.appendChild(wrapper);
  });
</script>
@endpush

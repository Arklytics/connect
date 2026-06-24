@extends('layouts.business')

@section('title', 'Send Messages')

@section('content')
  <div class="row">
    <div class="col-md-6">
      <h4 class="mt-4">Send Messages</h4>
      <form action="{{ route('business.messages.send') }}" method="post">
        @csrf
        <div class="mb-3">
          <select id="templateDropdown" name="template_id" class="form-control" required>
            <option value="">--Select Template--</option>
            @foreach ($templates ?? [] as $template)
              <option value="{{ $template->id }}">{{ $template->template_name }}</option>
            @endforeach
          </select>
        </div>
        <div class="mb-3">
          <select name="group_id" class="form-control" required>
            <option value="">--Select Group--</option>
            @foreach ($groups ?? [] as $group)
              <option value="{{ $group->id }}">{{ $group->group_name }}</option>
            @endforeach
          </select>
        </div>
        <button class="btn btn-success" type="submit">Send Message</button>
      </form>
    </div>
    <div class="col-md-3">
      <h5 class="mt-4">Preview</h5>
      <div class="border p-3 shadow-sm bg-light rounded">
        <div class="shadow border-bottom p-3 rounded"><b>Arklytics</b> <i class="bi bi-patch-check-fill text-primary"></i></div>
        <div class="p-3">
          <div id="previewMediaUrl" class="mb-2 text-center"></div>
          <h6 id="previewTitle" class="text-primary mb-2">[Message Title]</h6>
          <p id="previewBody" class="mb-2">[Message Body]</p>
          <h6 id="previewSubtitle" class="text-secondary mb-2">[Sub Title]</h6>
          <div id="previewButtons" class="mt-3"></div>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
<script>
  templateDropdown.addEventListener('change', async () => {
    if (!templateDropdown.value) return;
    const response = await fetch(`{{ url('/business/templates/fetch') }}/${templateDropdown.value}`);
    const data = await response.json();
    previewTitle.textContent = data.message_title || '[Message Title]';
    previewBody.textContent = data.message_body || '[Message Body]';
    previewSubtitle.textContent = data.subtitle || '[Sub Title]';
  });
</script>
@endpush

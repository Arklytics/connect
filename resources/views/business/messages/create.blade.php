@extends('layouts.business')

@section('title', 'Send Messages')

@section('content')
  <div class="card shadow-sm border-0 mt-3 mb-4" style="background: linear-gradient(135deg, #0f172a 0%, #0f766e 100%); color: #fff;">
    <div class="card-body p-4 p-md-5">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
          <div class="text-uppercase small opacity-75 mb-2">Broadcast Center</div>
          <h4 class="mb-2">Send Messages</h4>
          <div class="opacity-75">This page sends templates to a chosen group. Sequence planning lives on the planner page.</div>
        </div>
        <a href="{{ route('business.sequences.index') }}" class="btn btn-light text-success fw-semibold">
          <i class="bi bi-diagram-3"></i> Open Sequence Planner
        </a>
      </div>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-lg-7">
      <div class="card shadow-sm border-0">
        <div class="card-header bg-white">
          <strong>Broadcast Template</strong>
        </div>
        <div class="card-body">
          <div class="alert alert-info py-2">
            This page is for sending templates only. Build reusable WhatsApp sequences on the sequence planner screen.
          </div>
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
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card shadow-sm border-0">
        <div class="card-header bg-white">
          <strong>Preview</strong>
        </div>
        <div class="card-body">
          <div class="border p-3 bg-light rounded-4">
            <div class="shadow-sm border-bottom p-3 rounded bg-white"><b>Arklytics</b> <i class="bi bi-patch-check-fill text-primary"></i></div>
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

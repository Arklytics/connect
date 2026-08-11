@extends('layouts.business')

@section('title', 'Send Messages')

@section('content')
  <div class="card shadow-sm border-0 mt-3 mb-4" style="background: linear-gradient(135deg, #0f172a 0%, #0f766e 100%); color: #fff;">
    <div class="card-body p-4 p-md-5">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
          <div class="text-uppercase small opacity-75 mb-2">Broadcast Center</div>
          <h4 class="mb-2">Send Messages</h4>
          <div class="opacity-75">Send templates to a main group or one subgroup. Main groups include their subgroups.</div>
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
          <select name="parent_group_id" class="form-control parent-select" data-child="#messageSubgroup" required>
            <option value="">--Select Parent Group--</option>
            @foreach ($parentGroups ?? [] as $group)
              <option value="{{ $group->id }}">{{ $group->group_name }}</option>
            @endforeach
          </select>
        </div>
        <div class="mb-3">
          <select name="subgroup_id" id="messageSubgroup" class="form-control subgroup-select" required>
            <option value="">--Select Subgroup--</option>
            @foreach ($subgroups ?? [] as $group)
              <option value="{{ $group->id }}" data-parent="{{ $group->parent_id }}">{{ $group->group_name }}</option>
            @endforeach
          </select>
          <div class="form-text">Messages can be sent only to a subgroup. Select the parent group first.</div>
        </div>
        <div class="mb-3 d-none" id="templateVariableFields">
          <label class="form-label fw-semibold">Template Variable Values</label>
          <div class="form-text mb-2">Use @{{name}}, @{{phone}}, or @{{email}} to personalize each contact.</div>
          <div class="row g-2" id="templateVariableInputs"></div>
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

    wrapper.appendChild(inputLabel);
    wrapper.appendChild(input);
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

  templateDropdown.addEventListener('change', async () => {
    if (!templateDropdown.value) {
      renderTemplateVariableFields({});
      return;
    }
    const response = await fetch(`{{ url('/business/templates/fetch') }}/${templateDropdown.value}`);
    const data = await response.json();
    previewTitle.textContent = data.message_title || '[Message Title]';
    previewBody.textContent = data.message_body || '[Message Body]';
    previewSubtitle.textContent = data.subtitle || '[Sub Title]';
    renderTemplateVariableFields(data.variable_requirements || {});
  });

  document.querySelectorAll('.parent-select').forEach((parentSelect) => {
    const childSelect = document.querySelector(parentSelect.dataset.child);
    if (!childSelect) return;

    const options = Array.from(childSelect.querySelectorAll('option[data-parent]'));
    const syncSubgroups = () => {
      const parentId = parentSelect.value;
      options.forEach((option) => {
        option.hidden = option.dataset.parent !== parentId;
        if (option.hidden && option.selected) {
          childSelect.value = '';
        }
      });
    };

    parentSelect.addEventListener('change', syncSubgroups);
    syncSubgroups();
  });
</script>
@endpush

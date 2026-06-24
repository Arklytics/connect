@extends('layouts.business')

@section('title', 'Connect Contacts')

@section('content')
  <div class="d-flex flex-wrap align-items-center justify-content-between mt-3">
    <div>
      <h4 class="mb-1"><i class="bi bi-kanban"></i> Connect Contacts</h4>
      <div class="text-muted">Manage leads, follow-ups, and WhatsApp-ready contacts from one screen.</div>
    </div>
    <div class="d-flex gap-2 mt-2 mt-md-0">
      <a href="{{ route('business.contacts.import') }}" class="btn btn-outline-success"><i class="bi bi-file-earmark-spreadsheet"></i> Import</a>
      <a href="{{ route('business.groups.index') }}" class="btn btn-success"><i class="bi bi-people-fill"></i> Groups</a>
    </div>
  </div>

  <div class="row mt-4 g-3">
    <div class="col-md-2">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <div class="text-muted small">Total</div>
          <div class="fs-3 fw-bold">{{ $stats['total'] ?? 0 }}</div>
        </div>
      </div>
    </div>
    <div class="col-md-2">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <div class="text-muted small">Leads</div>
          <div class="fs-3 fw-bold">{{ $stats['leads'] ?? 0 }}</div>
        </div>
      </div>
    </div>
    <div class="col-md-2">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <div class="text-muted small">Due</div>
          <div class="fs-3 fw-bold">{{ $stats['due_followups'] ?? 0 }}</div>
        </div>
      </div>
    </div>
    <div class="col-md-2">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <div class="text-muted small">Won</div>
          <div class="fs-3 fw-bold">{{ $stats['won'] ?? 0 }}</div>
        </div>
      </div>
    </div>
    <div class="col-md-2">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <div class="text-muted small">Lost</div>
          <div class="fs-3 fw-bold">{{ $stats['lost'] ?? 0 }}</div>
        </div>
      </div>
    </div>
    <div class="col-md-2">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <div class="text-muted small">Opt-in</div>
          <div class="fs-3 fw-bold">{{ $stats['opted_in'] ?? 0 }}</div>
        </div>
      </div>
    </div>
  </div>

  <form method="GET" class="row g-3 mt-3 align-items-end">
    <div class="col-md-3">
      <label class="form-label">Lead Stage</label>
      <select name="stage" class="form-select">
        <option value="">All stages</option>
        @foreach (['lead' => 'Lead', 'opportunity' => 'Opportunity', 'customer' => 'Customer'] as $value => $label)
          <option value="{{ $value }}" @selected(request('stage') === $value)>{{ $label }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label">Lead Status</label>
      <select name="status" class="form-select">
        <option value="">All statuses</option>
        @foreach (['new' => 'New', 'contacted' => 'Contacted', 'qualified' => 'Qualified', 'won' => 'Won', 'lost' => 'Lost'] as $value => $label)
          <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-3">
      <div class="form-check mt-4">
        <input class="form-check-input" type="checkbox" value="1" id="follow_up_only" name="follow_up_only" @checked(request()->boolean('follow_up_only'))>
        <label class="form-check-label" for="follow_up_only">Only due follow-ups</label>
      </div>
    </div>
    <div class="col-md-3">
      <button class="btn btn-outline-primary w-100" type="submit">Apply Filters</button>
    </div>
  </form>

  <div class="row mt-4">
    <div class="col-lg-7">
      <div class="card shadow-sm">
        <div class="card-header bg-white">
          <strong>Add Manual Contact</strong>
        </div>
        <div class="card-body">
          <form action="{{ route('business.contacts.store') }}" method="POST" class="row g-3">
            @csrf
            <div class="col-md-4">
              <label class="form-label">Group</label>
              <select class="form-select" name="group_id">
                <option value="">No group</option>
                @foreach ($groups ?? [] as $group)
                  <option value="{{ $group->id }}" @selected(old('group_id') == $group->id)>{{ $group->group_name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Full Name</label>
              <input type="text" class="form-control" name="full_name" value="{{ old('full_name') }}" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Mobile Number</label>
              <input type="text" class="form-control" name="mobile_number" value="{{ old('mobile_number') }}" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Email</label>
              <input type="email" class="form-control" name="email" value="{{ old('email') }}">
            </div>
            <div class="col-md-4">
              <label class="form-label">Source</label>
              <input type="text" class="form-control" name="source" value="{{ old('source', 'Manual') }}" placeholder="Manual, Ads, Website...">
            </div>
            <div class="col-md-4">
              <label class="form-label">WhatsApp Opt-In</label>
              <select class="form-select" name="whatsapp_opt_in">
                <option value="0" @selected(old('whatsapp_opt_in', '1') == '0')>No</option>
                <option value="1" @selected(old('whatsapp_opt_in', '1') == '1')>Yes</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Lead Stage</label>
              <select class="form-select" name="lead_stage">
                @foreach (['lead' => 'Lead', 'opportunity' => 'Opportunity', 'customer' => 'Customer'] as $value => $label)
                  <option value="{{ $value }}" @selected(old('lead_stage', 'lead') === $value)>{{ $label }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Lead Status</label>
              <select class="form-select" name="lead_status">
                @foreach (['new' => 'New', 'contacted' => 'Contacted', 'qualified' => 'Qualified', 'won' => 'Won', 'lost' => 'Lost'] as $value => $label)
                  <option value="{{ $value }}" @selected(old('lead_status', 'new') === $value)>{{ $label }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Next Follow-Up</label>
              <input type="datetime-local" class="form-control" name="next_follow_up_at" value="{{ old('next_follow_up_at') }}">
            </div>
            <div class="col-12">
              <label class="form-label">Notes</label>
              <textarea name="notes" rows="3" class="form-control">{{ old('notes') }}</textarea>
            </div>
            <div class="col-12">
              <label class="form-label">Lost Reason</label>
              <input type="text" class="form-control" name="lost_reason" value="{{ old('lost_reason') }}" placeholder="Only needed when marking lost">
            </div>
            <div class="col-12">
              <button type="submit" class="btn btn-success">Save Contact</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card shadow-sm">
        <div class="card-header bg-white">
          <strong>Queue WhatsApp Follow-Up Sequence</strong>
        </div>
        <div class="card-body">
          <form method="POST" action="{{ url('/business/contacts/0/followups') }}" id="followupForm" class="row g-3">
            @csrf
            <div class="col-12">
              <label class="form-label">Contact</label>
              <select class="form-select" name="contact_id" id="followupContactSelect" required>
                <option value="">Choose contact</option>
                @foreach ($contacts ?? [] as $contact)
                  <option value="{{ $contact->id }}">{{ $contact->full_name }} - {{ $contact->phone_number }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Sequence Name</label>
              <input type="text" class="form-control" name="sequence_name" value="{{ old('sequence_name', 'WhatsApp nurture sequence') }}" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">First Follow-Up</label>
              <input type="datetime-local" class="form-control" name="first_follow_up_at" value="{{ old('first_follow_up_at') }}" required>
            </div>
            <div class="col-md-3">
              <label class="form-label">Steps</label>
              <input type="number" min="1" max="10" class="form-control" name="steps" value="{{ old('steps', 3) }}" required>
            </div>
            <div class="col-md-3">
              <label class="form-label">Gap Days</label>
              <input type="number" min="1" max="30" class="form-control" name="step_gap_days" value="{{ old('step_gap_days', 2) }}" required>
            </div>
            <div class="col-12">
              <label class="form-label">Notes</label>
              <textarea name="notes" rows="3" class="form-control">{{ old('notes') }}</textarea>
            </div>
            <div class="col-12">
              <button type="submit" class="btn btn-outline-success w-100">Queue Sequence</button>
            </div>
          </form>
          <div class="alert alert-info mt-3 mb-0">
            Sequence records are stored now, so a WhatsApp connector can send them automatically when your delivery service is ready.
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="card shadow-sm mt-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
      <strong>Contacts</strong>
      <span class="text-muted small">{{ $contacts->count() }} shown</span>
    </div>
    <div class="table-responsive">
      <table class="table table-striped mb-0 align-middle">
        <thead class="table-dark">
          <tr>
            <th>#</th>
            <th>Name</th>
            <th>Phone</th>
            <th>Group</th>
            <th>Stage</th>
            <th>Status</th>
            <th>WhatsApp</th>
            <th>Next Follow-Up</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($contacts ?? [] as $contact)
            <tr>
              <td>{{ $loop->iteration }}</td>
              <td>
                <div class="fw-semibold">{{ $contact->full_name }}</div>
                <div class="text-muted small">{{ $contact->email ?: 'No email' }}</div>
              </td>
              <td>{{ $contact->phone_number }}</td>
              <td>{{ $contact->group_name ?: 'No group' }}</td>
              <td><span class="badge bg-secondary text-uppercase">{{ $contact->lead_stage ?? 'lead' }}</span></td>
              <td>
                @php
                  $badge = match ($contact->lead_status ?? 'new') {
                      'won' => 'success',
                      'lost' => 'danger',
                      'qualified' => 'primary',
                      'contacted' => 'warning',
                      default => 'secondary',
                  };
                @endphp
                <span class="badge bg-{{ $badge }} text-uppercase">{{ $contact->lead_status ?? 'new' }}</span>
              </td>
              <td>{!! !empty($contact->whatsapp_opt_in) ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-light text-dark">No</span>' !!}</td>
              <td>{{ $contact->next_follow_up_at ?: '-' }}</td>
              <td>
                <div class="d-flex flex-wrap gap-1">
                  <form method="POST" action="{{ route('business.contacts.status', $contact->id) }}">
                    @csrf
                    <input type="hidden" name="lead_stage" value="customer">
                    <input type="hidden" name="lead_status" value="won">
                    <button class="btn btn-sm btn-success" type="submit">Won</button>
                  </form>
                  <form method="POST" action="{{ route('business.contacts.status', $contact->id) }}">
                    @csrf
                    <input type="hidden" name="lead_stage" value="lead">
                    <input type="hidden" name="lead_status" value="lost">
                    <button class="btn btn-sm btn-danger" type="submit">Lost</button>
                  </form>
                  <form method="POST" action="{{ route('business.contacts.status', $contact->id) }}" class="d-flex gap-1">
                    @csrf
                    <select name="lead_stage" class="form-select form-select-sm">
                      <option value="lead" @selected(($contact->lead_stage ?? 'lead') === 'lead')>Lead</option>
                      <option value="opportunity" @selected(($contact->lead_stage ?? '') === 'opportunity')>Opportunity</option>
                      <option value="customer" @selected(($contact->lead_stage ?? '') === 'customer')>Customer</option>
                    </select>
                    <select name="lead_status" class="form-select form-select-sm">
                      <option value="new" @selected(($contact->lead_status ?? 'new') === 'new')>New</option>
                      <option value="contacted" @selected(($contact->lead_status ?? '') === 'contacted')>Contacted</option>
                      <option value="qualified" @selected(($contact->lead_status ?? '') === 'qualified')>Qualified</option>
                    </select>
                    <button class="btn btn-sm btn-outline-primary" type="submit">Update</button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="9" class="text-center py-4">No contacts found</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="card shadow-sm mt-4">
    <div class="card-header bg-white">
      <strong>Queued Follow-Ups</strong>
    </div>
    <div class="table-responsive">
      <table class="table table-striped mb-0">
        <thead class="table-dark">
          <tr>
            <th>#</th>
            <th>Contact</th>
            <th>Channel</th>
            <th>Sequence</th>
            <th>Step</th>
            <th>Scheduled</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($followUps ?? [] as $followUp)
            <tr>
              <td>{{ $loop->iteration }}</td>
              <td>{{ $followUp->full_name }}<div class="text-muted small">{{ $followUp->phone_number }}</div></td>
              <td>{{ strtoupper($followUp->channel) }}</td>
              <td>{{ $followUp->sequence_name ?? '-' }}</td>
              <td>{{ $followUp->step_no }}</td>
              <td>{{ $followUp->scheduled_at }}</td>
              <td><span class="badge bg-secondary">{{ $followUp->status }}</span></td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center py-4">No follow-up sequences queued yet</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection

@push('scripts')
<script>
  const followupForm = document.getElementById('followupForm');
  const followupContactSelect = document.getElementById('followupContactSelect');

  if (followupForm && followupContactSelect) {
    followupContactSelect.addEventListener('change', function () {
      if (!this.value) {
        followupForm.action = '{{ url('/business/contacts/0/followups') }}';
        return;
      }

      followupForm.action = `{{ url('/business/contacts') }}/${this.value}/followups`;
    });
  }
</script>
@endpush

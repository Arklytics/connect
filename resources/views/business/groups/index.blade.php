@extends('layouts.business')

@section('title', 'Groups')

@section('content')
  <style>
    .wg-groups-stat {
      min-height: 96px;
    }
    .wg-groups-table th,
    .wg-groups-table td {
      vertical-align: middle;
    }
    .wg-group-icon {
      width: 40px;
      height: 40px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 8px;
      background: #ecfdf5;
      color: #0f766e;
      flex: 0 0 auto;
    }
    .wg-bulk-help {
      font-size: 12px;
      color: #64748b;
    }
    .wg-bulk-textarea {
      min-height: 160px;
      resize: vertical;
    }
  </style>

  <div class="d-flex flex-wrap align-items-center justify-content-between mt-3 gap-2">
    <div>
      <h4 class="mb-1"><i class="bi bi-diagram-3-fill"></i> Groups & Subgroups</h4>
      <div class="text-muted">Organize contacts into parent groups and broadcast-ready subgroups.</div>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a href="{{ route('business.contacts.import') }}" class="btn btn-outline-success">
        <i class="bi bi-upload me-1"></i> Import Contacts
      </a>
      <a href="{{ route('business.messages.create') }}" class="btn btn-success">
        <i class="bi bi-send me-1"></i> Send Broadcast
      </a>
    </div>
  </div>

  <div class="row g-3 mt-3">
    <div class="col-md-4">
      <div class="card shadow-sm border-0 wg-groups-stat">
        <div class="card-body d-flex align-items-center gap-3">
          <span class="wg-group-icon"><i class="bi bi-folder2-open"></i></span>
          <div>
            <div class="text-muted small">Total Groups</div>
            <div class="h3 mb-0">{{ number_format((int) ($totalGroups ?? 0)) }}</div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card shadow-sm border-0 wg-groups-stat">
        <div class="card-body d-flex align-items-center gap-3">
          <span class="wg-group-icon"><i class="bi bi-diagram-2"></i></span>
          <div>
            <div class="text-muted small">Subgroups</div>
            <div class="h3 mb-0">{{ number_format(($subgroups ?? collect())->count()) }}</div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card shadow-sm border-0 wg-groups-stat">
        <div class="card-body d-flex align-items-center gap-3">
          <span class="wg-group-icon"><i class="bi bi-person-lines-fill"></i></span>
          <div>
            <div class="text-muted small">Contacts</div>
            <div class="h3 mb-0">{{ number_format((int) ($totalContacts ?? 0)) }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mt-1">
    <div class="col-xl-4">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-header bg-white">
          <strong>Create Group</strong>
          <div class="small text-muted">Choose whether this is a main group or a subgroup.</div>
        </div>
        <div class="card-body">
          <form action="{{ route('business.groups.store') }}" method="POST" class="row g-3" id="groupCreateForm">
            @csrf
            <div class="col-12">
              <label class="form-label">Group Type</label>
              <select class="form-select" id="groupType" name="group_type">
                <option value="main" @selected(old('group_type', 'main') === 'main')>Main group</option>
                <option value="subgroup" @selected(old('group_type') === 'subgroup')>Subgroup under existing group</option>
              </select>
            </div>
            <div class="col-12" id="parentGroupWrap">
              <label class="form-label">Parent Group</label>
              <select class="form-select" name="parent_id" id="parentGroupSelect">
                <option value="">Select parent group</option>
                @foreach ($parentGroups ?? [] as $parent)
                  <option value="{{ $parent->id }}" @selected(old('parent_id') == $parent->id)>{{ $parent->group_name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Name</label>
              <input type="text" class="form-control" name="group_name" value="{{ old('group_name') }}" required placeholder="Retail Leads, Dealers, Follow-up List...">
            </div>
            <div class="col-12">
              <button class="btn btn-success w-100" type="submit">
                <i class="bi bi-check2-circle me-1"></i> Save Group
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-xl-8">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-header bg-white">
          <strong>Add Multiple Contacts</strong>
          <div class="small text-muted">Paste phone numbers or contact rows into one subgroup.</div>
        </div>
        <div class="card-body">
          <form action="{{ route('business.groups.contacts.store') }}" method="POST" class="row g-3">
            @csrf
            <div class="col-md-4">
              <label class="form-label">Parent Group</label>
              <select class="form-select parent-select" name="parent_group_id" data-child="#bulkSubgroup" required>
                <option value="">Select parent</option>
                @foreach ($parentGroups ?? [] as $group)
                  <option value="{{ $group->id }}" @selected(old('parent_group_id') == $group->id)>{{ $group->group_name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Subgroup</label>
              <select class="form-select subgroup-select" id="bulkSubgroup" name="subgroup_id" required>
                <option value="">Select subgroup</option>
                @foreach ($subgroups ?? [] as $group)
                  <option value="{{ $group->id }}" data-parent="{{ $group->parent_id }}" @selected(old('subgroup_id') == $group->id)>{{ $group->group_name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Source</label>
              <input type="text" class="form-control" name="source" value="{{ old('source', 'Bulk Group Add') }}">
            </div>
            <div class="col-12">
              <label class="form-label">Contacts / Numbers</label>
              <textarea class="form-control wg-bulk-textarea" name="bulk_contacts" required placeholder="Asha Rao, +919876543210, asha@example.com&#10;+91990001004&#10;Nisha Patel | 9876543210 | nisha@example.com">{{ old('bulk_contacts') }}</textarea>
              <div class="wg-bulk-help mt-2">
                One contact per line. Accepted formats: phone only, name + phone, or name + phone + email using comma, tab, or | separators.
              </div>
            </div>
            <div class="col-md-4">
              <label class="form-label">WhatsApp Opt-In</label>
              <select class="form-select" name="whatsapp_opt_in">
                <option value="1" @selected(old('whatsapp_opt_in', '1') == '1')>Yes</option>
                <option value="0" @selected(old('whatsapp_opt_in') == '0')>No</option>
              </select>
            </div>
            <div class="col-md-8 d-flex align-items-end">
              <button class="btn btn-success w-100" type="submit">
                <i class="bi bi-person-plus me-1"></i> Add Contacts to Subgroup
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="card shadow-sm border-0 mt-4">
    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div>
        <strong>Group Structure</strong>
        <div class="small text-muted">Main groups include contacts from all child subgroups.</div>
      </div>
      @if ($groups instanceof \Illuminate\Contracts\Pagination\Paginator)
        <span class="text-muted small">Showing {{ $groups->firstItem() ?? 0 }}-{{ $groups->lastItem() ?? 0 }} of {{ $groups->total() }}</span>
      @endif
    </div>
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle wg-groups-table">
        <thead class="table-dark">
          <tr>
            <th style="width:70px;">#</th>
            <th>Group</th>
            <th>Type</th>
            <th>Direct Contacts</th>
            <th>Total With Subgroups</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($groups ?? [] as $group)
            <tr>
              <td>{{ method_exists($groups, 'firstItem') ? (($groups->firstItem() ?? 1) + $loop->index) : $loop->iteration }}</td>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <span class="wg-group-icon">
                    <i class="bi {{ !empty($group->parent_id) ? 'bi-diagram-2' : 'bi-folder2' }}"></i>
                  </span>
                  <div>
                    <div class="fw-semibold">{{ $group->group_name }}</div>
                    <div class="small text-muted">
                      {{ !empty($group->parent_id) ? 'Under ' . ($group->parent_name ?? 'Parent group') : 'Top-level group' }}
                    </div>
                  </div>
                </div>
              </td>
              <td>
                @if (!empty($group->parent_id))
                  <span class="badge bg-info text-dark">Subgroup</span>
                @else
                  <span class="badge bg-success">Main group</span>
                @endif
              </td>
              <td>{{ number_format((int) ($group->contacts_count ?? 0)) }}</td>
              <td>{{ number_format((int) ($rollupCounts[$group->id] ?? ($group->contacts_count ?? 0))) }}</td>
              <td>
                <div class="d-flex flex-wrap justify-content-end gap-2">
                  @if (!empty($group->parent_id))
                    <a href="{{ route('business.contacts.import', ['parent_group_id' => $group->parent_id, 'subgroup_id' => $group->id]) }}" class="btn btn-sm btn-outline-primary">
                      <i class="bi bi-upload"></i>
                    </a>
                  @else
                    <a href="{{ route('business.contacts.import', ['parent_group_id' => $group->id]) }}" class="btn btn-sm btn-outline-primary">
                      <i class="bi bi-upload"></i>
                    </a>
                  @endif
                  <a href="{{ route('business.contacts.group', $group->id) }}" class="btn btn-sm btn-outline-success">
                    <i class="bi bi-eye"></i>
                  </a>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center py-4">No groups found</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($groups instanceof \Illuminate\Contracts\Pagination\Paginator && $groups->hasPages())
      <div class="card-footer bg-white">
        {{ $groups->links('pagination::bootstrap-5') }}
      </div>
    @endif
  </div>
@endsection

@push('scripts')
<script>
  const groupType = document.getElementById('groupType');
  const parentGroupWrap = document.getElementById('parentGroupWrap');
  const parentGroupSelect = document.getElementById('parentGroupSelect');

  const syncGroupType = () => {
    if (!groupType || !parentGroupWrap || !parentGroupSelect) return;

    const isSubgroup = groupType.value === 'subgroup';
    parentGroupWrap.style.display = isSubgroup ? '' : 'none';
    parentGroupSelect.required = isSubgroup;
    if (!isSubgroup) {
      parentGroupSelect.value = '';
    }
  };

  if (groupType) {
    groupType.addEventListener('change', syncGroupType);
    syncGroupType();
  }

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

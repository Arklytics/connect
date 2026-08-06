@extends('layouts.business')

@section('title', 'Groups')

@section('content')
  <div class="d-flex flex-wrap align-items-center justify-content-between mt-3 gap-2">
    <div>
      <h4 class="mb-1"><i class="bi bi-ui-checks"></i> Groups & Subgroups</h4>
      <div class="text-muted">Create one main group, then split it into smaller subgroups for cleaner broadcasts.</div>
    </div>
    <a href="{{ route('business.contacts.import') }}" class="btn btn-outline-success"><i class="bi bi-upload"></i> Import Contacts</a>
  </div>

  <div class="row g-3 mt-3">
    <div class="col-lg-5">
      <div class="card shadow-sm">
        <div class="card-header bg-white"><strong>Create Group</strong></div>
        <div class="card-body">
          <form action="{{ route('business.groups.store') }}" method="POST" class="row g-3">
            @csrf
            <div class="col-12">
              <label class="form-label">Name</label>
              <input type="text" class="form-control" name="group_name" required placeholder="Customers, Leads, Dealers...">
            </div>
            <div class="col-12">
              <label class="form-label">Parent Group</label>
              <select class="form-select" name="parent_id">
                <option value="">No parent - create main group</option>
                @foreach ($parentGroups ?? [] as $parent)
                  <option value="{{ $parent->id }}">Subgroup under {{ $parent->group_name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-12">
              <button class="btn btn-success w-100" type="submit">Save Group</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-7">
      <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
          <strong>Group Structure</strong>
          <span class="text-muted small">{{ ($groups ?? collect())->count() }} groups</span>
        </div>
        <div class="table-responsive">
          <table class="table table-striped mb-0 align-middle">
            <thead class="table-dark">
              <tr>
                <th>#</th>
                <th>Group</th>
                <th>Direct Contacts</th>
                <th>Total With Subgroups</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($groups ?? [] as $group)
                <tr>
                  <td>{{ $loop->iteration }}</td>
                  <td>
                    <div class="fw-semibold">
                      @if (!empty($group->parent_id))
                        <span class="text-muted me-1">--</span>
                      @endif
                      {{ $group->group_name }}
                    </div>
                    <div class="small text-muted">
                      {{ !empty($group->parent_id) ? 'Subgroup of ' . ($group->parent_name ?? 'Parent group') : 'Main group' }}
                    </div>
                  </td>
                  <td>{{ $group->contacts_count ?? 0 }}</td>
                  <td>{{ $rollupCounts[$group->id] ?? ($group->contacts_count ?? 0) }}</td>
                  <td>
                    <div class="d-flex flex-wrap gap-1">
                      <a href="{{ route('business.contacts.import') }}?group_id={{ $group->id }}" class="btn btn-sm btn-primary">Add</a>
                      <a href="{{ route('business.contacts.group', $group->id) }}" class="btn btn-sm btn-success">View</a>
                    </div>
                  </td>
                </tr>
              @empty
                <tr><td colspan="5" class="text-center py-4">No groups found</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
@endsection

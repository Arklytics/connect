@extends('layouts.admin')

@section('title', 'Packages')

@section('content')
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mt-3">
    <div>
      <h4 class="mb-1"><i class="bi bi-box-seam"></i> Packages</h4>
      <p class="text-muted mb-0">Assign a package to any business and review limit increase requests.</p>
    </div>
    <a href="{{ route('admin.settings.tokens') }}" class="btn btn-outline-success">
      <i class="bi bi-gear-fill me-1"></i> API Settings
    </a>
  </div>

  <div class="card shadow-sm border-0 mt-3">
    <div class="card-body">
      <h5 class="mb-3">Add Package</h5>
      <form action="{{ route('admin.packages.store') }}" method="post">
        @csrf
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Package Name</label>
            <input type="text" name="package_name" class="form-control" value="{{ old('package_name') }}" placeholder="Example: Premium" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Duration Days</label>
            <input type="number" name="duration_days" class="form-control" min="1" max="3650" value="{{ old('duration_days', 30) }}">
          </div>
          <div class="col-md-4">
            <label class="form-label">Total Price</label>
            <input type="text" class="form-control" value="Marketing + Utility" disabled>
          </div>
          <div class="col-md-6">
            <label class="form-label">Marketing Messages</label>
            <input type="number" name="marketing_message_limit" class="form-control" min="0" value="{{ old('marketing_message_limit') }}" placeholder="Marketing limit">
          </div>
          <div class="col-md-6">
            <label class="form-label">Utility Messages</label>
            <input type="number" name="utility_message_limit" class="form-control" min="0" value="{{ old('utility_message_limit') }}" placeholder="Utility limit">
          </div>
          <div class="col-md-6">
            <label class="form-label">Marketing Price</label>
            <input type="number" name="marketing_price" class="form-control" min="0" step="0.01" value="{{ old('marketing_price') }}" placeholder="Marketing price">
          </div>
          <div class="col-md-6">
            <label class="form-label">Utility Price</label>
            <input type="number" name="utility_price" class="form-control" min="0" step="0.01" value="{{ old('utility_price') }}" placeholder="Utility price">
          </div>
        </div>
        <div class="mt-3">
          <button class="btn btn-primary" type="submit">
            <i class="bi bi-plus-circle me-1"></i> Add Package
          </button>
        </div>
      </form>
    </div>
  </div>

  <div class="card shadow-sm border-0 mt-4">
    <div class="card-body">
      <h5 class="mb-3">Assign Package</h5>
      <form action="{{ route('admin.settings.package.store') }}" method="post">
        @csrf
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Business</label>
            <select class="form-control" name="business_id" required>
              <option value="">--Select Business--</option>
              @foreach ($businesses ?? [] as $business)
                <option value="{{ $business->id }}">{{ $business->business_name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Package</label>
            <select class="form-control" name="package_key" required>
              @foreach ($packages ?? [] as $key => $package)
                @php $totalLimit = (int) ($package['marketing_limit'] ?? 0) + (int) ($package['utility_limit'] ?? 0); @endphp
                <option value="{{ $key }}">{{ $package['label'] }} ({{ number_format($totalLimit) }} all messages)</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Duration Days</label>
            <input type="number" name="package_days" class="form-control" min="1" value="30">
          </div>
          <div class="col-md-6">
            <label class="form-label">Marketing Messages</label>
            <input type="number" name="marketing_message_limit" class="form-control" min="0" placeholder="Marketing limit">
          </div>
          <div class="col-md-6">
            <label class="form-label">Utility Messages</label>
            <input type="number" name="utility_message_limit" class="form-control" min="0" placeholder="Utility limit">
          </div>
          <div class="col-md-6">
            <label class="form-label">Marketing Price</label>
            <input type="number" name="marketing_package_price" class="form-control" min="0" step="0.01" placeholder="Marketing price">
          </div>
          <div class="col-md-6">
            <label class="form-label">Utility Price</label>
            <input type="number" name="utility_package_price" class="form-control" min="0" step="0.01" placeholder="Utility price">
          </div>
        </div>
        <div class="mt-3">
          <button class="btn btn-success" type="submit">
            <i class="bi bi-check2-circle me-1"></i> Assign Package
          </button>
        </div>
      </form>
    </div>
  </div>

  <div class="card shadow-sm border-0 mt-4">
    <div class="card-body">
      <h5 class="mb-3">Available Packages</h5>
      <div class="table-responsive">
        <table class="table table-striped align-middle">
          <thead class="table-dark">
            <tr>
              <th>#</th>
              <th>Package</th>
              <th>All Messages</th>
              <th>Marketing</th>
              <th>Utility</th>
              <th>Price</th>
              <th>Days</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            @forelse (($packages ?? []) as $key => $package)
              @php
                $totalLimit = (int) ($package['marketing_limit'] ?? 0) + (int) ($package['utility_limit'] ?? 0);
                $modalId = 'editPackageModal' . md5((string) $key);
              @endphp
              <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $package['label'] }}</td>
                <td>{{ number_format($totalLimit) }}</td>
                <td>{{ number_format((int) ($package['marketing_limit'] ?? 0)) }}</td>
                <td>{{ number_format((int) ($package['utility_limit'] ?? 0)) }}</td>
                <td>{{ number_format((float) ($package['price'] ?? 0), 2) }}</td>
                <td>{{ $package['days'] ?? 30 }}</td>
                <td class="text-end">
                  <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#{{ $modalId }}">
                    <i class="bi bi-pencil-square me-1"></i> Edit
                  </button>
                  <form action="{{ route('admin.packages.destroy', $key) }}" method="post" class="d-inline" onsubmit="return confirm('Delete this package? Businesses already assigned to it will keep their current package details.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger">
                      <i class="bi bi-trash me-1"></i> Delete
                    </button>
                  </form>
                </td>
              </tr>
            @empty
              <tr><td colspan="8" class="text-center">No packages found</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  @foreach (($packages ?? []) as $key => $package)
    @php $modalId = 'editPackageModal' . md5((string) $key); @endphp
    <div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}Label" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
          <form action="{{ route('admin.packages.update', $key) }}" method="post">
            @csrf
            @method('PUT')
            <div class="modal-header">
              <h5 class="modal-title" id="{{ $modalId }}Label">Edit Package</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Package Name</label>
                  <input type="text" name="package_name" class="form-control" value="{{ $package['label'] }}" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Duration Days</label>
                  <input type="number" name="duration_days" class="form-control" min="1" max="3650" value="{{ $package['days'] ?? 30 }}">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Marketing Messages</label>
                  <input type="number" name="marketing_message_limit" class="form-control" min="0" max="1000000" value="{{ (int) ($package['marketing_limit'] ?? 0) }}">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Utility Messages</label>
                  <input type="number" name="utility_message_limit" class="form-control" min="0" max="1000000" value="{{ (int) ($package['utility_limit'] ?? 0) }}">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Marketing Price</label>
                  <input type="number" name="marketing_price" class="form-control" min="0" step="0.01" value="{{ (float) ($package['marketing_price'] ?? 0) }}">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Utility Price</label>
                  <input type="number" name="utility_price" class="form-control" min="0" step="0.01" value="{{ (float) ($package['utility_price'] ?? 0) }}">
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary">
                <i class="bi bi-check2 me-1"></i> Save Changes
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  @endforeach

  <div class="card shadow-sm border-0 mt-4">
    <div class="card-body">
      <h5 class="mb-3">Businesses</h5>
      <div class="table-responsive">
        <table class="table table-striped align-middle">
          <thead class="table-dark">
            <tr>
              <th>#</th>
              <th>Business</th>
              <th>Package</th>
              <th>All Messages</th>
              <th>Marketing</th>
              <th>Utility</th>
              <th>Expires</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($businesses ?? [] as $business)
              <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $business->business_name }}</td>
                <td>{{ $business->package_name ?? 'Not set' }}</td>
                <td>{{ number_format((int) ($business->messages_used ?? 0)) }} / {{ number_format((int) ($business->message_limit ?? 0)) }}</td>
                <td>{{ number_format((int) ($business->marketing_messages_used ?? 0)) }} / {{ number_format((int) ($business->marketing_message_limit ?? 0)) }}</td>
                <td>{{ number_format((int) ($business->utility_messages_used ?? 0)) }} / {{ number_format((int) ($business->utility_message_limit ?? 0)) }}</td>
                <td>{{ $business->package_ends_at ?? '-' }}</td>
              </tr>
            @empty
              <tr><td colspan="7" class="text-center">No businesses found</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="card shadow-sm border-0 mt-4 mb-4">
    <div class="card-body">
      <h5 class="mb-3">Limit Increase Requests</h5>
      <div class="table-responsive">
        <table class="table table-striped align-middle">
          <thead class="table-dark">
            <tr>
              <th>#</th>
              <th>Business ID</th>
              <th>Requested Limit</th>
              <th>Status</th>
              <th>Reason</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($packageRequests ?? [] as $requestRow)
              <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $requestRow->biz_id }}</td>
                <td>{{ number_format((int) $requestRow->requested_limit) }}</td>
                <td>{{ ucfirst($requestRow->status ?? 'pending') }}</td>
                <td>{{ $requestRow->reason ?: '-' }}</td>
              </tr>
            @empty
              <tr><td colspan="5" class="text-center">No requests found</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
@endsection

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
                <option value="{{ $key }}">{{ $package['label'] }} ({{ number_format($package['limit']) }} messages)</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Duration Days</label>
            <input type="number" name="package_days" class="form-control" min="1" value="30">
          </div>
          <div class="col-md-4">
            <label class="form-label">Custom Limit</label>
            <input type="number" name="custom_message_limit" class="form-control" min="1" placeholder="Optional custom limit">
          </div>
          <div class="col-md-4">
            <label class="form-label">Package Price</label>
            <input type="number" name="package_price" class="form-control" min="0" step="0.01" placeholder="Optional price">
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
      <h5 class="mb-3">Businesses</h5>
      <div class="table-responsive">
        <table class="table table-striped align-middle">
          <thead class="table-dark">
            <tr>
              <th>#</th>
              <th>Business</th>
              <th>Package</th>
              <th>Usage</th>
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
                <td>{{ $business->package_ends_at ?? '-' }}</td>
              </tr>
            @empty
              <tr><td colspan="5" class="text-center">No businesses found</td></tr>
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

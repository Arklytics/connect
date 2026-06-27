@extends('layouts.business')

@section('title', 'Reports')

@section('content')
  @php
    $period = request('period', 'this_month');
    $messageStatus = request('message_status', 'all');
    $deliveryStatus = request('delivery_status', 'all');
    $leadStatus = request('lead_status', 'all');
    $leadTemperature = request('lead_temperature', 'all');
  @endphp

  <div class="d-flex flex-wrap gap-3 align-items-center justify-content-between mt-3">
    <div>
      <h4 class="mb-1"><i class="bi bi-clipboard-data-fill"></i> Reports</h4>
      <div class="text-muted">Track message delivery and lead performance with a single filter set.</div>
    </div>
    <a class="btn btn-outline-success" href="{{ route('business.messages.create') }}">
      <i class="bi bi-send me-1"></i> Send Messages
    </a>
  </div>

  <form method="GET" class="card shadow-sm border-0 mt-3">
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-2">
          <label class="form-label">Period</label>
          <select name="period" class="form-select">
            @foreach ([
              'today' => 'Today',
              'this_week' => 'This Week',
              'this_month' => 'This Month',
              'last_30_days' => 'Last 30 Days',
              'custom' => 'Custom',
            ] as $value => $label)
              <option value="{{ $value }}" @selected($period === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">From</label>
          <input type="date" name="from_date" class="form-control" value="{{ request('from_date', $from) }}">
        </div>
        <div class="col-md-2">
          <label class="form-label">To</label>
          <input type="date" name="to_date" class="form-control" value="{{ request('to_date', $to) }}">
        </div>
        <div class="col-md-2">
          <label class="form-label">Message Status</label>
          <select name="message_status" class="form-select">
            @foreach (['all' => 'All', 'sent' => 'Sent', 'failed' => 'Failed'] as $value => $label)
              <option value="{{ $value }}" @selected($messageStatus === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Delivery Status</label>
          <select name="delivery_status" class="form-select">
            @foreach (['all' => 'All', 'pending' => 'Pending', 'sent' => 'Sent', 'delivered' => 'Delivered', 'read' => 'Read', 'failed' => 'Failed'] as $value => $label)
              <option value="{{ $value }}" @selected($deliveryStatus === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Lead Status</label>
          <select name="lead_status" class="form-select">
            @foreach (['all' => 'All', 'new' => 'New', 'contacted' => 'Contacted', 'qualified' => 'Qualified', 'won' => 'Won', 'lost' => 'Lost'] as $value => $label)
              <option value="{{ $value }}" @selected($leadStatus === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Lead Temperature</label>
          <select name="lead_temperature" class="form-select">
            @foreach (['all' => 'All', 'hot' => 'Hot', 'warm' => 'Warm', 'cold' => 'Cold'] as $value => $label)
              <option value="{{ $value }}" @selected($leadTemperature === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
      </div>
      <div class="mt-3">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-funnel me-1"></i> Apply Filters
        </button>
      </div>
    </div>
  </form>

  <div class="row g-3 mt-2">
    <div class="col-md-2">
      <div class="card shadow-sm border-0 h-100"><div class="card-body"><div class="text-muted small">Messages</div><div class="fs-3 fw-bold">{{ $messageTotals->total_messages ?? 0 }}</div></div></div>
    </div>
    <div class="col-md-2">
      <div class="card shadow-sm border-0 h-100"><div class="card-body"><div class="text-muted small">Sent</div><div class="fs-3 fw-bold text-primary">{{ $messageTotals->sent_messages ?? 0 }}</div></div></div>
    </div>
    <div class="col-md-2">
      <div class="card shadow-sm border-0 h-100"><div class="card-body"><div class="text-muted small">Delivered</div><div class="fs-3 fw-bold text-success">{{ $messageTotals->delivered_messages ?? 0 }}</div></div></div>
    </div>
    <div class="col-md-2">
      <div class="card shadow-sm border-0 h-100"><div class="card-body"><div class="text-muted small">Read</div><div class="fs-3 fw-bold text-info">{{ $messageTotals->read_messages ?? 0 }}</div></div></div>
    </div>
    <div class="col-md-2">
      <div class="card shadow-sm border-0 h-100"><div class="card-body"><div class="text-muted small">Pending</div><div class="fs-3 fw-bold text-warning">{{ $messageTotals->queued_messages ?? 0 }}</div></div></div>
    </div>
    <div class="col-md-2">
      <div class="card shadow-sm border-0 h-100"><div class="card-body"><div class="text-muted small">Failed</div><div class="fs-3 fw-bold text-danger">{{ $messageTotals->failed_messages ?? 0 }}</div></div></div>
    </div>
  </div>

  <div class="card shadow-sm border-0 mt-4">
    <div class="card-body">
      <h5 class="mb-3">Message Delivery Report</h5>
      <div class="table-responsive">
        <table class="table table-striped align-middle">
          <thead class="table-dark">
            <tr>
              <th>#</th>
              <th>Phone</th>
              <th>Template</th>
              <th>Title</th>
              <th>Send Status</th>
              <th>Delivery Status</th>
              <th>Sent At</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($messages ?? [] as $message)
              @php
                $sendStatus = strtolower((string) ($message->status ?? ''));
                $delivery = strtolower((string) ($message->delivery_status ?? 'pending'));
                $sendBadge = $sendStatus === 'failed' ? 'danger' : ($sendStatus === 'success' || $sendStatus === 'sent' ? 'success' : 'secondary');
                $deliveryBadge = match ($delivery) {
                  'delivered' => 'success',
                  'read' => 'info',
                  'sent' => 'primary',
                  'failed' => 'danger',
                  default => 'warning',
                };
              @endphp
              <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $message->phone_number }}</td>
                <td>{{ $message->template_name ?: 'Direct Send' }}</td>
                <td>{{ $message->message_title }}</td>
                <td><span class="badge bg-{{ $sendBadge }} text-uppercase">{{ $sendStatus ?: 'pending' }}</span></td>
                <td><span class="badge bg-{{ $deliveryBadge }} text-uppercase">{{ $delivery }}</span></td>
                <td>{{ $message->sent_at ?? $message->created_at }}</td>
              </tr>
            @empty
              <tr><td colspan="7" class="text-center">No messages found for the selected filters.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="row g-3 mt-2">
    <div class="col-md-2">
      <div class="card shadow-sm border-0 h-100"><div class="card-body"><div class="text-muted small">Leads</div><div class="fs-3 fw-bold">{{ $leadTotals->total_leads ?? 0 }}</div></div></div>
    </div>
    <div class="col-md-2">
      <div class="card shadow-sm border-0 h-100"><div class="card-body"><div class="text-muted small">Won</div><div class="fs-3 fw-bold text-success">{{ $leadTotals->won_leads ?? 0 }}</div></div></div>
    </div>
    <div class="col-md-2">
      <div class="card shadow-sm border-0 h-100"><div class="card-body"><div class="text-muted small">Lost</div><div class="fs-3 fw-bold text-danger">{{ $leadTotals->lost_leads ?? 0 }}</div></div></div>
    </div>
    <div class="col-md-2">
      <div class="card shadow-sm border-0 h-100"><div class="card-body"><div class="text-muted small">Hot</div><div class="fs-3 fw-bold text-danger">{{ $leadTotals->hot_leads ?? 0 }}</div></div></div>
    </div>
    <div class="col-md-2">
      <div class="card shadow-sm border-0 h-100"><div class="card-body"><div class="text-muted small">Warm</div><div class="fs-3 fw-bold text-warning">{{ $leadTotals->warm_leads ?? 0 }}</div></div></div>
    </div>
    <div class="col-md-2">
      <div class="card shadow-sm border-0 h-100"><div class="card-body"><div class="text-muted small">Cold</div><div class="fs-3 fw-bold text-primary">{{ $leadTotals->cold_leads ?? 0 }}</div></div></div>
    </div>
  </div>

  <div class="card shadow-sm border-0 mt-4">
    <div class="card-body">
      <h5 class="mb-3">Lead Performance Report</h5>
      <div class="table-responsive">
        <table class="table table-striped align-middle">
          <thead class="table-dark">
            <tr>
              <th>#</th>
              <th>Name</th>
              <th>Phone</th>
              <th>Lead Stage</th>
              <th>Lead Status</th>
              <th>Temperature</th>
              <th>Source</th>
              <th>Created</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($leads ?? [] as $lead)
              @php
                $temp = strtolower((string) ($lead->lead_temperature ?? 'cold'));
                $tempBadge = match ($temp) {
                  'hot' => 'danger',
                  'warm' => 'warning',
                  default => 'primary',
                };
                $status = strtolower((string) ($lead->lead_status ?? $lead->status ?? 'new'));
                $statusBadge = match ($status) {
                  'won' => 'success',
                  'lost' => 'danger',
                  'contacted' => 'info',
                  'qualified' => 'warning',
                  default => 'secondary',
                };
              @endphp
              <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $lead->full_name }}</td>
                <td>{{ $lead->phone_number }}</td>
                <td><span class="badge bg-secondary text-uppercase">{{ $lead->lead_stage ?? 'lead' }}</span></td>
                <td><span class="badge bg-{{ $statusBadge }} text-uppercase">{{ $status }}</span></td>
                <td><span class="badge bg-{{ $tempBadge }} text-uppercase">{{ $temp }}</span></td>
                <td>{{ $lead->source ?? 'Manual' }}</td>
                <td>{{ $lead->created_at ?? $lead->updated_at }}</td>
              </tr>
            @empty
              <tr><td colspan="8" class="text-center">No leads found for the selected filters.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
@endsection

@extends('layouts.business')

@section('title', 'Business Dashboard')

@section('content')
  <div class="row mt-4 g-3">
    <div class="col-md-12">
      <div class="card shadow-sm border-0">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
          <div>
            <div class="text-muted small">Current Package</div>
            <h5 class="mb-1">{{ $packageName ?? 'No Package' }}</h5>
            <div class="text-muted">
              {{ number_format((int) ($messagesUsed ?? 0)) }} used from {{ number_format((int) ($messageLimit ?? 0)) }} messages
              @if (!empty($packageEndsAt))
                <span class="ms-2">Ends: {{ $packageEndsAt }}</span>
              @endif
            </div>
          </div>
          <div class="text-end">
            <div class="h4 mb-1">{{ number_format((int) ($messagesRemaining ?? 0)) }}</div>
            <div class="text-muted small">Messages left</div>
          </div>
        </div>
        <div class="card-footer bg-transparent border-0 pt-0">
          <div class="progress" style="height: 10px;">
            @php
              $limit = max(1, (int) ($messageLimit ?? 0));
              $used = min($limit, (int) ($messagesUsed ?? 0));
              $percent = (int) round(($used / $limit) * 100);
            @endphp
            <div class="progress-bar bg-success" role="progressbar" style="width: {{ $percent }}%;" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"></div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card text-dark bg-light shadow border border-success mb-3">
        <div class="card-header">Total Contacts</div>
        <div class="card-body"><h5>{{ $totalContacts ?? 0 }}</h5></div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card text-dark bg-light shadow border border-success mb-3">
        <div class="card-header">Open Leads</div>
        <div class="card-body"><h5>{{ $openLeads ?? 0 }}</h5></div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card text-dark bg-light shadow border border-success mb-3">
        <div class="card-header">Due Follow-Ups</div>
        <div class="card-body"><h5>{{ $dueFollowUps ?? 0 }}</h5></div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card text-dark bg-light shadow border border-success mb-3">
        <div class="card-header">Won Leads</div>
        <div class="card-body"><h5>{{ $wonLeads ?? 0 }}</h5></div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card text-dark bg-light shadow border border-success mb-3">
        <div class="card-header">Lost Leads</div>
        <div class="card-body"><h5>{{ $lostLeads ?? 0 }}</h5></div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card text-dark bg-light shadow border border-success mb-3">
        <div class="card-header">WhatsApp Opt-In</div>
        <div class="card-body"><h5>{{ $whatsappOptedIn ?? 0 }}</h5></div>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-md-6">
      <div class="card shadow mb-3">
        <div class="card-body">
          <h5 class="card-title">Connect workflow</h5>
          <p class="card-text mb-2">Import leads, tag them by stage, mark won or lost, and queue WhatsApp follow-up steps for later dispatch.</p>
          <a href="{{ route('business.contacts.index') }}" class="btn btn-success">Open Connect</a>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card shadow mb-3">
        <div class="card-body">
          <h5 class="card-title">Request more messages</h5>
          <p class="card-text mb-3">If your package is running low, send a request to the master admin for a limit increase.</p>
          <form action="{{ route('business.packages.request') }}" method="post" class="row g-2">
            @csrf
            <div class="col-md-6">
              <input type="number" name="requested_limit" class="form-control" min="1" placeholder="Requested limit" required>
            </div>
            <div class="col-md-6">
              <input type="text" name="reason" class="form-control" placeholder="Reason">
            </div>
            <div class="col-12">
              <button class="btn btn-outline-success" type="submit">Request Increase</button>
            </div>
          </form>
          <div class="mt-3 small text-muted">
            Status: {{ ucfirst($limitRequestStatus ?? 'none') }}
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection

@extends('layouts.admin')

@section('title', 'API Settings')

@section('content')
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mt-3">
    <div>
      <h4 class="mb-1"><i class="bi bi-gear-fill"></i> API Settings</h4>
      <p class="text-muted mb-0">Manage your Connect API, WhatsApp API, and business-level WhatsApp credentials from one place.</p>
    </div>
  </div>

  <div class="row g-3 mt-2">
    <div class="col-lg-5">
      <div class="card shadow-sm h-100 border-0">
        <div class="card-body">
          <h5 class="card-title mb-3">Documentation</h5>
          <div class="mb-3">
            <h6 class="mb-1">Connect API</h6>
            <p class="small text-muted mb-2">Used for embedded signup, app setup, and webhook verification.</p>
            <ul class="small mb-0 ps-3">
              <li><strong>App ID</strong> identifies your Meta app.</li>
              <li><strong>App Secret</strong> is used for secure token exchange.</li>
              <li><strong>Config ID</strong> powers embedded signup.</li>
              <li><strong>Verify Token</strong> must match the webhook callback check.</li>
            </ul>
          </div>
          <div class="mb-3">
            <h6 class="mb-1">WhatsApp API</h6>
            <p class="small text-muted mb-2">Used for message sending, templates, and your internal API integrations.</p>
            <ul class="small mb-0 ps-3">
              <li><strong>Access Token</strong> sends WhatsApp messages through Meta.</li>
              <li><strong>API Token</strong> protects your internal contact and send APIs.</li>
              <li><strong>Webhook URL</strong> should point to <code>incoming.php</code>.</li>
            </ul>
          </div>
          <div class="alert alert-info mb-0 small">
            Keep these values private. If you rotate a token, update it here first so the app uses the latest value.
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-7">
      <form action="{{ route('admin.settings.package.store') }}" method="post" class="card shadow-sm border-0 mb-3">
        @csrf
        <div class="card-body">
          <h5 class="card-title mb-3">Assign Package to Business</h5>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Business</label>
              <select class="form-control" name="business_id" required>
                <option value="">--Select Business--</option>
                @foreach (($pendingOrders ?? collect())->merge($activeOrders ?? collect()) as $order)
                  <option value="{{ $order->id }}">{{ $order->business_name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Package</label>
              <select class="form-control" name="package_key" required>
                @foreach ($packages ?? [] as $key => $package)
                  <option value="{{ $key }}">{{ $package['label'] }} ({{ number_format($package['limit']) }} messages)</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Custom Limit</label>
              <input type="number" name="custom_message_limit" class="form-control" min="1" placeholder="Optional custom limit">
            </div>
            <div class="col-md-4">
              <label class="form-label">Package Price</label>
              <input type="number" name="package_price" class="form-control" min="0" step="0.01" placeholder="Optional price">
            </div>
            <div class="col-md-4">
              <label class="form-label">Duration Days</label>
              <input type="number" name="package_days" class="form-control" min="1" max="3650" value="30">
            </div>
          </div>
        </div>
        <div class="card-footer bg-transparent border-0 pt-0 pb-3 px-3">
          <button class="btn btn-success w-100" type="submit">
            <i class="bi bi-box-seam me-1"></i> Save Package
          </button>
        </div>
      </form>

      <form action="{{ route('admin.settings.app.store') }}" method="post" class="card shadow-sm border-0 mb-3">
        @csrf
        <div class="card-body">
          <h5 class="card-title mb-3">Connect API and WhatsApp API</h5>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Connect App ID</label>
              <input type="text" name="connect_app_id" class="form-control" value="{{ old('connect_app_id', $appSettings['connect_app_id'] ?? '') }}" placeholder="Meta App ID">
            </div>
            <div class="col-md-6">
              <label class="form-label">Connect Config ID</label>
              <input type="text" name="connect_config_id" class="form-control" value="{{ old('connect_config_id', $appSettings['connect_config_id'] ?? '') }}" placeholder="Embedded signup config id">
            </div>
            <div class="col-md-6">
              <label class="form-label">Connect App Secret</label>
              <input type="password" name="connect_app_secret" class="form-control" value="{{ old('connect_app_secret', $appSettings['connect_app_secret'] ?? '') }}" placeholder="Meta App Secret">
            </div>
            <div class="col-md-6">
              <label class="form-label">Connect Verify Token</label>
              <input type="text" name="connect_verify_token" class="form-control" value="{{ old('connect_verify_token', $appSettings['connect_verify_token'] ?? '') }}" placeholder="Webhook verify token">
            </div>
            <div class="col-md-6">
              <label class="form-label">WhatsApp Access Token</label>
              <input type="password" name="whatsapp_access_token" class="form-control" value="{{ old('whatsapp_access_token', $appSettings['whatsapp_access_token'] ?? '') }}" placeholder="Long-lived access token">
            </div>
            <div class="col-md-6">
              <label class="form-label">Internal API Token</label>
              <input type="password" name="api_token" class="form-control" value="{{ old('api_token', $appSettings['api_token'] ?? '') }}" placeholder="Internal API token">
            </div>
          </div>
        </div>
        <div class="card-footer bg-transparent border-0 pt-0 pb-3 px-3">
          <button class="btn btn-success w-100" type="submit">
            <i class="bi bi-shield-lock me-1"></i> Save API Settings
          </button>
        </div>
      </form>

      <form action="{{ route('admin.settings.tokens.store') }}" method="post" class="card shadow-sm border-0">
        @csrf
        <div class="card-body">
          <h5 class="card-title mb-3">Business WhatsApp Integration</h5>
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Business</label>
              <select class="form-control" name="business_id" required>
                <option value="">--Select Business--</option>
                @foreach ($pendingOrders ?? [] as $order)
                  <option value="{{ $order->id }}">{{ $order->business_name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Auth Token</label>
              <input type="text" name="auth_token" class="form-control" required placeholder="WhatsApp token">
            </div>
            <div class="col-md-6">
              <label class="form-label">WhatsApp Business ID</label>
              <input type="text" name="whatsapp_id" class="form-control" required placeholder="WABA ID">
            </div>
            <div class="col-md-6">
              <label class="form-label">Phone Number ID</label>
              <input type="text" name="phonenumber_id" class="form-control" required placeholder="Phone Number ID">
            </div>
            <div class="col-md-6">
              <label class="form-label">Webhook URL</label>
              <input type="url" name="webhook_url" class="form-control" placeholder="Webhook URL" value="{{ old('webhook_url', $defaultWebhookUrl ?? url('/incoming.php')) }}">
              <div class="form-text">Usually your public <code>incoming.php</code> endpoint.</div>
            </div>
          </div>
        </div>
        <div class="card-footer bg-transparent border-0 pt-0 pb-3 px-3">
          <button class="btn btn-primary w-100" type="submit">
            <i class="bi bi-cloud-upload me-1"></i> Save Business Integration
          </button>
        </div>
      </form>
    </div>
  </div>

  <h4 class="mt-4">Activated Businesses</h4>
  <div class="table-responsive">
    <table class="table table-striped align-middle">
      <thead class="table-dark">
        <tr>
          <th>Sno</th>
          <th>Business Name</th>
          <th>Package</th>
          <th>Usage</th>
          <th>Auth Token</th>
          <th>WhatsApp ID</th>
          <th>Phone Number ID</th>
          <th>Webhook URL</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($activeOrders ?? [] as $order)
          <tr>
            <td>{{ $loop->iteration }}</td>
            <td>{{ $order->business_name }}</td>
            <td>{{ $order->package_name ?? 'Not set' }}</td>
            <td>{{ number_format((int) ($order->messages_used ?? 0)) }} / {{ number_format((int) ($order->message_limit ?? 0)) }}</td>
            <td>{{ $order->auth_token ? 'Saved' : 'Missing' }}</td>
            <td>{{ $order->whatsapp_id ?: 'Not connected' }}</td>
            <td>{{ $order->phone_number_id ?: 'Not connected' }}</td>
            <td>{{ $order->webhook_url ?: ($defaultWebhookUrl ?? url('/incoming.php')) }}</td>
          </tr>
        @empty
          <tr><td colspan="8" class="text-center">No activated businesses found</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <h4 class="mt-4">Limit Increase Requests</h4>
  <div class="table-responsive">
    <table class="table table-striped align-middle">
      <thead class="table-dark">
        <tr>
          <th>Sno</th>
          <th>Business ID</th>
          <th>Current Package</th>
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
            <td>{{ $requestRow->current_package ?: 'Not set' }}</td>
            <td>{{ number_format((int) $requestRow->requested_limit) }}</td>
            <td>{{ ucfirst($requestRow->status ?? 'pending') }}</td>
            <td>{{ $requestRow->reason ?: '-' }}</td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center">No limit requests found</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
@endsection

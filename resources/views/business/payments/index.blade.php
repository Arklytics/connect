@extends('layouts.business')

@section('title', 'Payments')

@section('content')
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mt-3">
    <div>
      <h4 class="mb-1"><i class="bi bi-credit-card"></i> Payments</h4>
      <p class="text-muted mb-0">Choose a package to activate WhatsApp messages for your business.</p>
    </div>
  </div>

  @if (!($packageStatus['active'] ?? false))
    <div class="alert alert-warning mt-3">{{ $packageStatus['reason'] ?? 'Payment is required to continue.' }}</div>
  @else
    <div class="alert alert-success mt-3">Your current package is active. You can renew or upgrade anytime.</div>
  @endif

  @if (empty($razorpayKeyId))
    <div class="alert alert-danger">Razorpay key is missing. Add <code>RAZORPAY_KEY_ID</code> and <code>RAZORPAY_KEY_SECRET</code> in <code>.env</code>.</div>
  @endif

  <div class="row g-3 mt-1">
    @foreach ($packages ?? [] as $key => $package)
      @php
        $marketingLimit = (int) ($package['marketing_limit'] ?? 0);
        $utilityLimit = (int) ($package['utility_limit'] ?? 0);
        $totalLimit = $marketingLimit + $utilityLimit;
      @endphp
      <div class="col-xl-4 col-md-6">
        <div class="card shadow-sm border-0 h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start gap-3">
              <div>
                <div class="text-muted small">Package</div>
                <h4 class="mb-1">{{ $package['label'] }}</h4>
              </div>
              <span class="badge bg-success">INR {{ number_format((float) $package['price'], 2) }}</span>
            </div>
            <div class="mt-3">
              <div class="fw-bold fs-5">{{ number_format($totalLimit) }} all messages</div>
              <div class="text-muted small mt-1">{{ number_format($marketingLimit) }} marketing messages</div>
              <div class="text-muted small">{{ number_format($utilityLimit) }} utility messages</div>
              <div class="text-muted small mt-2">Valid for {{ $package['days'] }} days</div>
            </div>
          </div>
          <div class="card-footer bg-transparent border-0 pt-0">
            <button class="btn btn-success w-100 js-pay-package" type="button" data-package-key="{{ $key }}" {{ empty($razorpayKeyId) ? 'disabled' : '' }}>
              <i class="bi bi-credit-card me-1"></i> Pay and Activate
            </button>
          </div>
        </div>
      </div>
    @endforeach
  </div>
@endsection

@push('scripts')
  <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
  <script>
    document.querySelectorAll('.js-pay-package').forEach(function (button) {
      button.addEventListener('click', async function () {
        const packageKey = button.dataset.packageKey || 'starter';
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Starting payment';

        try {
          const orderResponse = await fetch('{{ route('business.payments.order') }}', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-CSRF-TOKEN': '{{ csrf_token() }}',
            },
            body: JSON.stringify({ package_key: packageKey }),
          });
          const order = await orderResponse.json();
          if (!order.ok) {
            throw new Error(order.error || 'Unable to start payment.');
          }

          const checkout = new Razorpay({
            key: order.key_id,
            amount: order.amount,
            currency: order.currency || 'INR',
            name: 'Arklytics Connect',
            description: (order.package?.label || 'Package') + ' package',
            order_id: order.order_id,
            handler: async function (response) {
              const verifyResponse = await fetch('{{ route('business.payments.verify') }}', {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                  'Accept': 'application/json',
                  'X-CSRF-TOKEN': '{{ csrf_token() }}',
                },
                body: JSON.stringify(response),
              });
              const verified = await verifyResponse.json();
              if (!verified.ok) {
                throw new Error(verified.error || 'Payment verification failed.');
              }

              window.location.href = verified.redirect_url || '{{ route('business.dashboard') }}';
            },
            theme: { color: '#0f8f63' },
          });
          checkout.open();
        } catch (error) {
          alert(error.message || 'Payment could not be started.');
        } finally {
          button.disabled = false;
          button.innerHTML = '<i class="bi bi-credit-card me-1"></i> Pay and Activate';
        }
      });
    });
  </script>
@endpush

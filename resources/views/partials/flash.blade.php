@if (session('success') || session('warning') || session('error'))
  @php
    $type = session('success') ? 'success' : (session('warning') ? 'warning' : 'danger');
    $message = session('success') ?? session('warning') ?? session('error');
  @endphp
  <div class="position-fixed bottom-0 end-0 p-3 wg-footer-toast-container">
    <div class="toast align-items-center text-bg-{{ $type }} border-0 show wg-flash-toast" role="alert" aria-live="assertive" aria-atomic="true">
      <div class="d-flex">
        <div class="toast-body">{{ $message }}</div>
        <button type="button" class="btn-close {{ $type === 'warning' ? '' : 'btn-close-white' }} me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
    </div>
  </div>
@endif

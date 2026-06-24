@if (session('success') || session('warning') || session('error'))
  @php
    $type = session('success') ? 'success' : (session('warning') ? 'warning' : 'danger');
    $message = session('success') ?? session('warning') ?? session('error');
  @endphp
  <div class="alert alert-{{ $type }} mt-3">{{ $message }}</div>
@endif

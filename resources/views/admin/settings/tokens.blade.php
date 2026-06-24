@extends('layouts.admin')

@section('title', 'WhatsApp Credentials')

@section('content')
  <h4 class="mt-3"><i class="bi bi-gear-fill"></i> Add WhatsApp Credentials</h4>
  <form action="{{ route('admin.settings.tokens.store') }}" method="post" class="mt-3">
    @csrf
    <div class="row d-flex justify-content-center mb-4">
      <div class="col-md-6">
        <select class="form-control" name="business_id" required>
          <option value="">--Select Business--</option>
          @foreach ($pendingOrders ?? [] as $order)
            <option value="{{ $order->id }}">{{ $order->business_name }}</option>
          @endforeach
        </select>
      </div>
    </div>
    <div class="row d-flex justify-content-center mb-4"><div class="col-md-6"><input type="text" name="auth_token" class="form-control p-2 shadow" required placeholder="Auth Token"></div></div>
    <div class="row d-flex justify-content-center mb-4"><div class="col-md-6"><input type="text" name="whatsapp_id" class="form-control p-2 shadow" required placeholder="WhatsApp ID"></div></div>
    <div class="row d-flex justify-content-center mb-4"><div class="col-md-6"><input type="text" name="phonenumber_id" class="form-control p-2 shadow" required placeholder="Phone Number ID"></div></div>
    <div class="row d-flex justify-content-center mb-4"><div class="col-md-6"><input type="url" name="webhook_url" class="form-control p-2 shadow" required placeholder="Webhook URL"></div></div>
    <div class="row d-flex justify-content-center"><div class="col-md-6 text-center"><button class="btn btn-success w-100 shadow" type="submit">Submit</button></div></div>
  </form>

  <h4 class="mt-4">Activated Businesses</h4>
  <table class="table table-striped">
    <thead class="table-dark"><tr><th>Sno</th><th>Business Name</th><th>Auth Token</th><th>WhatsApp ID</th><th>Phone Number ID</th><th>Webhook URL</th></tr></thead>
    <tbody>
      @forelse ($activeOrders ?? [] as $order)
        <tr>
          <td>{{ $loop->iteration }}</td>
          <td>{{ $order->business_name }}</td>
          <td>{{ $order->auth_token }}</td>
          <td>{{ $order->whatsapp_id }}</td>
          <td>{{ $order->phone_number_id }}</td>
          <td>{{ $order->webhook_url }}</td>
        </tr>
      @empty
        <tr><td colspan="6" class="text-center">No activated businesses found</td></tr>
      @endforelse
    </tbody>
  </table>
@endsection

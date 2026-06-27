@extends('layouts.admin')

@section('title', 'View Orders')

@section('content')
  <h4 class="mt-3"><i class="bi bi-list-columns-reverse"></i> View Orders</h4>
  <table class="table table-striped mt-4">
    <thead class="table-dark">
      <tr>
        <th>Sno</th>
        <th>Business Name</th>
        <th>Package</th>
        <th>Usage</th>
        <th>Contact</th>
        <th>Location</th>
        <th>Status</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($orders ?? [] as $order)
        <tr>
          <td>{{ $loop->iteration }}</td>
          <td>{{ $order->business_name }}</td>
          <td>{{ $order->package_name ?? 'Not set' }}</td>
          <td>{{ number_format((int) ($order->messages_used ?? 0)) }} / {{ number_format((int) ($order->message_limit ?? 0)) }}</td>
          <td>{{ $order->business_number }}</td>
          <td>{{ $order->business_location }}</td>
          <td>{{ (int) $order->status === 1 ? 'Activated' : 'In-active' }}</td>
          <td>
            <form action="{{ route('admin.orders.destroy', $order->id) }}" method="post" onsubmit="return confirm('Delete this business and all related data?');">
              @csrf
              @method('DELETE')
              <button type="submit" class="btn btn-sm btn-danger">
                <i class="bi bi-trash me-1"></i> Delete
              </button>
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="8" class="text-center">No orders found</td></tr>
      @endforelse
    </tbody>
  </table>
@endsection

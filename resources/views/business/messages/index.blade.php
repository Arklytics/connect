@extends('layouts.business')

@section('title', 'View Messages')

@section('content')
  <h4 class="mt-3"><i class="bi bi-list-columns-reverse"></i> View Messages</h4>
  <form method="GET" class="row g-3">
    <div class="col-md-4"><label class="form-label">From Date</label><input type="date" class="form-control" name="from_date" value="{{ request('from_date', now()->subDays(7)->toDateString()) }}" required></div>
    <div class="col-md-4"><label class="form-label">To Date</label><input type="date" class="form-control" name="to_date" value="{{ request('to_date', now()->toDateString()) }}" required></div>
    <div class="col-md-4 mt-4"><button type="submit" class="btn btn-primary">Filter</button></div>
  </form>
  <table class="table table-striped mt-4">
    <thead class="table-dark"><tr><th>#</th><th>Phone Number</th><th>Template Name</th><th>Message Title</th><th>Status</th><th>Sent Date</th></tr></thead>
    <tbody>
      @forelse ($messages ?? [] as $message)
        <tr>
          <td>{{ $loop->iteration }}</td>
          <td>{{ $message->phone_number }}</td>
          <td>{{ $message->template_name }}</td>
          <td>{{ $message->message_title }}</td>
          <td><span class="badge bg-{{ $message->status === 'success' ? 'success' : 'danger' }}">{{ $message->status }}</span></td>
          <td>{{ $message->sent_at }}</td>
        </tr>
      @empty
        <tr><td colspan="6" class="text-center">No messages found</td></tr>
      @endforelse
    </tbody>
  </table>
@endsection

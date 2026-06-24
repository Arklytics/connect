@extends('layouts.business')

@section('title', 'Group Contacts')

@section('content')
  <h4 class="mt-3"><i class="bi bi-list-columns-reverse"></i> Group Contacts</h4>
  <div class="table-responsive mt-4">
    <table class="table table-striped">
      <thead class="table-dark">
        <tr>
          <th>Sno</th>
          <th>Full Name</th>
          <th>Phone Number</th>
          <th>Email</th>
          <th>Lead Stage</th>
          <th>Lead Status</th>
          <th>Next Follow-Up</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($contacts ?? [] as $contact)
          <tr>
            <td>{{ $loop->iteration }}</td>
            <td>{{ $contact->full_name }}</td>
            <td>{{ $contact->phone_number }}</td>
            <td>{{ $contact->email ?: '-' }}</td>
            <td>{{ $contact->lead_stage ?? '-' }}</td>
            <td>{{ $contact->lead_status ?? $contact->status ?? '-' }}</td>
            <td>{{ $contact->next_follow_up_at ?: '-' }}</td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center">No contacts found</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
@endsection

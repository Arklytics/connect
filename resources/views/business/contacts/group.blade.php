@extends('layouts.business')

@section('title', 'Group Contacts')

@section('content')
  <h4 class="mt-3"><i class="bi bi-list-columns-reverse"></i> Group Contacts</h4>
  <p class="text-muted mb-0">Main groups include contacts from their subgroups. Subgroup views show only that subgroup.</p>
  <div class="card shadow-sm border-0 mt-4">
    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
      <strong>Contacts</strong>
      @if ($contacts instanceof \Illuminate\Contracts\Pagination\Paginator)
        <span class="text-muted small">Showing {{ $contacts->firstItem() ?? 0 }}-{{ $contacts->lastItem() ?? 0 }} of {{ $contacts->total() }}</span>
      @endif
    </div>
    <div class="table-responsive">
    <table class="table table-striped mb-0">
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
            <td>{{ method_exists($contacts, 'firstItem') ? (($contacts->firstItem() ?? 1) + $loop->index) : $loop->iteration }}</td>
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
    @if ($contacts instanceof \Illuminate\Contracts\Pagination\Paginator && $contacts->hasPages())
      <div class="card-footer bg-white">
        {{ $contacts->links('pagination::bootstrap-5') }}
      </div>
    @endif
  </div>
@endsection

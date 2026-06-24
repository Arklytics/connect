@extends('layouts.business')

@section('title', 'Groups')

@section('content')
  <h4 class="mt-3"><i class="bi bi-ui-checks"></i> Add New Group</h4>
  <form action="{{ route('business.groups.store') }}" method="POST" class="mt-3">
    @csrf
    <div class="row bg-light mt-2">
      <div class="col-md-4"><input type="text" class="form-control p-2 shadow" name="group_name" required placeholder="Enter New Group name"></div>
      <div class="col-md-3"><button class="btn btn-success" type="submit">Submit</button></div>
    </div>
  </form>

  <table class="table table-striped mt-3">
    <thead class="table-dark"><tr><th>Sno</th><th>Group Name</th><th>Total Contacts</th><th>Actions</th></tr></thead>
    <tbody>
      @forelse ($groups ?? [] as $group)
        <tr>
          <td>{{ $loop->iteration }}</td>
          <td>{{ $group->group_name }}</td>
          <td>{{ $group->contacts_count ?? 0 }}</td>
          <td>
            <a href="{{ route('business.contacts.import') }}" class="btn btn-primary">Add</a>
            <a href="{{ route('business.contacts.group', $group->id) }}" class="btn btn-success">View</a>
          </td>
        </tr>
      @empty
        <tr><td colspan="4" class="text-center">No groups found</td></tr>
      @endforelse
    </tbody>
  </table>
@endsection

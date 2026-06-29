@extends('layouts.business')

@section('title', 'Import Contacts')

@section('content')
  <div class="mt-3">
    <h4><i class="bi bi-file-earmark-spreadsheet"></i> Import Contacts</h4>
    <p class="text-muted mb-0">Upload a CSV or Excel file and map rows into your selected group. Supported headers include `full_name`, `name`, `phone_number`, `mobile_number`, `email`, `lead_stage`, `lead_status`, `source`, `next_follow_up_at`, `whatsapp_opt_in`, and `notes`.</p>
  </div>

  <div class="card shadow-sm mt-4">
    <div class="card-body">
      <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
        <div class="text-muted small">
          Download the sample CSV to see the exact column names and a valid example row.
        </div>
        <a href="{{ route('business.contacts.import.sample') }}" class="btn btn-outline-success">
          <i class="bi bi-download me-1"></i> Download Sample CSV
        </a>
      </div>

      <form action="{{ route('business.contacts.import.store') }}" method="post" enctype="multipart/form-data" class="row g-3">
        @csrf
        <div class="col-md-4">
          <label class="form-label">Target Group</label>
          <select class="form-select" name="group_id" required>
            <option value="">--Select Group--</option>
            @foreach ($groups ?? [] as $group)
              <option value="{{ $group->id }}">{{ $group->group_name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-5">
          <label class="form-label">File</label>
          <input type="file" name="file" class="form-control" accept=".csv,.xls,.xlsx" required>
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <button class="btn btn-success w-100" type="submit">Import Contacts</button>
        </div>
      </form>
    </div>
  </div>

  <div class="alert alert-warning mt-4">
    The importer will update an existing contact when the same phone number already exists for this business.
  </div>
@endsection

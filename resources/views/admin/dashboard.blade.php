@extends('layouts.admin')

@section('title', 'Master Dashboard')

@section('content')
  <h4 class="mt-3">Master Dashboard</h4>
  <div class="row mt-4">
    <div class="col-md-4">
      <div class="card border-success shadow">
        <div class="card-header">Businesses</div>
        <div class="card-body"><h5>{{ $businessCount ?? 0 }}</h5></div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card border-success shadow">
        <div class="card-header">Active Businesses</div>
        <div class="card-body"><h5>{{ $activeBusinessCount ?? 0 }}</h5></div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card border-success shadow">
        <div class="card-header">Packages</div>
        <div class="card-body">
          <p class="mb-2">Assign Starter, Growth, or Pro plans to businesses.</p>
          <a href="{{ route('admin.settings.tokens') }}" class="btn btn-success btn-sm">Open Package Manager</a>
        </div>
      </div>
    </div>
  </div>
@endsection

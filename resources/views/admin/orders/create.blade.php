@extends('layouts.admin')

@section('title', 'New Order')

@section('content')
  <h4 class="mt-3"><i class="bi bi-ui-checks"></i> Add New Order</h4>
  <form action="{{ route('admin.orders.store') }}" method="POST" enctype="multipart/form-data" class="mt-3">
    @csrf
    <div class="row bg-light mt-2">
      <div class="col-md-3"><input type="text" class="form-control p-2 shadow" name="full_name" value="{{ old('full_name') }}" required placeholder="Enter Full Name"></div>
      <div class="col-md-3"><input type="number" class="form-control p-2 shadow" name="mobile_number" value="{{ old('mobile_number') }}" required placeholder="Enter Mobile Number"></div>
      <div class="col-md-3"><input type="email" class="form-control p-2 shadow" name="email" value="{{ old('email') }}" placeholder="Enter Email"></div>
      <div class="col-md-3"><input type="password" class="form-control p-2 shadow" name="password" required placeholder="Password"></div>
    </div>
    <div class="row bg-light mt-2">
      <div class="col-md-3"><input type="text" class="form-control p-2 shadow" name="business_name" value="{{ old('business_name') }}" required placeholder="Enter Business Name"></div>
      <div class="col-md-3"><input type="number" class="form-control p-2 shadow" name="business_number" value="{{ old('business_number') }}" required placeholder="Enter Business Number"></div>
      <div class="col-md-3"><input type="email" class="form-control p-2 shadow" name="business_email" value="{{ old('business_email') }}" required placeholder="Enter Business Email"></div>
      <div class="col-md-3"><input type="text" class="form-control p-2 shadow" name="business_location" value="{{ old('business_location') }}" required placeholder="Location"></div>
    </div>
    <div class="row bg-light mt-2">
      <div class="col-md-12"><textarea class="form-control shadow rounded" name="business_description" rows="5" placeholder="About Business">{{ old('business_description') }}</textarea></div>
    </div>
    <div class="row bg-light mt-2">
      <div class="col-md-3">
        <input type="file" class="form-control" name="business_logo" accept="image/*">
        <p style="font-size: 12px;">Upload Business Logo</p>
      </div>
    </div>
    <button class="btn btn-success mt-2" type="submit"><i class="bi bi-cloud-download-fill"></i> Submit</button>
  </form>
@endsection

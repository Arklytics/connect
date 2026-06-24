@extends('layouts.admin')

@section('title', 'Business Templates')

@section('content')
  <h4 class="mt-3"><i class="bi bi-list-columns-reverse"></i> View Templates</h4>
  <table class="table table-striped mt-4">
    <thead class="table-dark"><tr><th>Sno</th><th>Business Name</th><th>Template Name</th><th>Title</th><th>Type</th><th>Status</th></tr></thead>
    <tbody>
      @forelse ($templates ?? [] as $template)
        <tr>
          <td>{{ $loop->iteration }}</td>
          <td>{{ $template->business_name }}</td>
          <td>{{ $template->template_name }}</td>
          <td>{{ $template->message_title }}</td>
          <td>Marketing</td>
          <td>{{ $template->status }}</td>
        </tr>
      @empty
        <tr><td colspan="6" class="text-center">No templates found</td></tr>
      @endforelse
    </tbody>
  </table>
@endsection

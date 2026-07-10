@extends('layouts.business')

@section('title', 'View Templates')

@section('content')
  <h4 class="mt-3"><i class="bi bi-list-columns-reverse"></i> View Templates</h4>
  <table class="table table-striped mt-4">
    <thead class="table-dark"><tr><th>Sno</th><th>Preview</th><th>Template Name</th><th>Title</th><th>Type</th><th>Status</th></tr></thead>
    <tbody>
      @forelse ($templates ?? [] as $template)
        @php
          $mediaUrl = trim((string) ($template->media_url ?? ''));
          $meta = json_decode((string) ($template->placeholders ?? ''), true);
          $headerType = is_array($meta) ? strtoupper(trim((string) ($meta['header_type'] ?? ''))) : '';
          $mediaKind = \ApiSupport::mediaKind('', $mediaUrl);
        @endphp
        <tr>
          <td>{{ $loop->iteration }}</td>
          <td style="width: 320px;">
            <div class="border rounded bg-white p-2" style="max-width: 300px;">
              @if ($mediaUrl !== '')
                <div class="mb-2 text-center">
                  @if ($headerType === 'VIDEO' || $mediaKind === 'video')
                    <video src="{{ $mediaUrl }}" controls style="width:100%; max-height:150px; border-radius:6px;"></video>
                  @elseif ($headerType === 'DOCUMENT' || $mediaKind === 'document')
                    <a class="btn btn-light btn-sm" href="{{ $mediaUrl }}" target="_blank" rel="noopener"><i class="bi bi-file-earmark-text me-1"></i> Open document</a>
                  @else
                    <img src="{{ $mediaUrl }}" alt="Template media" style="max-width:100%; max-height:150px; border-radius:6px;">
                  @endif
                </div>
              @endif
              <div class="small fw-semibold">{{ $template->message_title }}</div>
              <div class="small text-muted" style="white-space: pre-wrap;">{{ $template->message_body }}</div>
              @if (!empty($template->subtitle))
                <div class="small text-secondary mt-1">{{ $template->subtitle }}</div>
              @endif
            </div>
          </td>
          <td>{{ $template->template_name }}</td>
          <td>{{ $template->message_title }}</td>
          <td>{{ $template->category ?: 'Marketing' }}</td>
          <td>{{ $template->status }}</td>
        </tr>
      @empty
        <tr><td colspan="6" class="text-center">No templates found</td></tr>
      @endforelse
    </tbody>
  </table>
@endsection

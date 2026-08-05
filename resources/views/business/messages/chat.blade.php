@extends('layouts.business')

@section('title', 'Client Chats')

@section('content')
  <style>
    .wg-chat-shell {
      min-height: 72vh;
    }
    .wg-chat-list {
      max-height: 72vh;
      overflow-y: auto;
    }
    .wg-chat-thread {
      height: 58vh;
      overflow-y: auto;
      background: #eef6f2;
    }
    .wg-chat-item {
      border-left: 4px solid transparent;
      color: #111827;
    }
    .wg-chat-item.active {
      border-left-color: #0f766e;
      background: #ecfdf5;
    }
    .wg-chat-bubble {
      max-width: min(78%, 620px);
      border-radius: 8px;
      white-space: pre-wrap;
      overflow-wrap: anywhere;
    }
    .wg-chat-bubble.inbound {
      background: #ffffff;
      border: 1px solid #d9e8e1;
    }
    .wg-chat-bubble.outbound {
      background: #dcf8c6;
      border: 1px solid #bde8a6;
    }
    .wg-chat-meta {
      font-size: 12px;
      color: #64748b;
    }
    .wg-chat-compose textarea {
      resize: none;
      min-height: 52px;
      max-height: 120px;
    }
  </style>

  <div class="d-flex flex-wrap gap-3 align-items-center justify-content-between mt-3">
    <div>
      <h4 class="mb-1"><i class="bi bi-chat-dots-fill"></i> Client Chats</h4>
      <div class="text-muted">Watch client messages, AI auto replies, broadcasts, and manual replies in one chat window.</div>
    </div>
    <a class="btn btn-outline-success" href="{{ route('business.messages.create') }}">
      <i class="bi bi-send me-1"></i> Send Broadcast
    </a>
  </div>

  <div class="row g-3 mt-2 wg-chat-shell">
    <div class="col-lg-4 col-xl-3">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-header bg-white">
          <strong>Clients</strong>
          <div class="input-group input-group-sm mt-3">
            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
            <input type="search" class="form-control" id="chatSearch" placeholder="Search name or number">
          </div>
        </div>
        <div class="list-group list-group-flush wg-chat-list">
          @forelse ($contacts ?? [] as $contact)
            @php
              $isActive = $selectedContact && (int) $selectedContact->id === (int) $contact->id;
              $replyPath = trim((string) ($contact->reply_path ?? ''));
              $lastReply = trim((string) ($contact->last_reply_text ?? ''));
              $searchText = strtolower((string) ($contact->full_name ?? '') . ' ' . (string) ($contact->phone_number ?? ''));
            @endphp
            <a href="{{ route('business.messages.chat', ['contact' => $contact->id]) }}" class="list-group-item list-group-item-action wg-chat-item {{ $isActive ? 'active' : '' }}" data-search="{{ $searchText }}">
              <div class="d-flex align-items-start justify-content-between gap-2">
                <div class="min-w-0">
                  <div class="fw-semibold text-truncate">{{ $contact->full_name ?: 'Client' }}</div>
                  <div class="small text-muted">{{ $contact->phone_number }}</div>
                </div>
                @if ($replyPath !== '')
                  <span class="badge bg-success text-uppercase">{{ str_replace('_', ' ', $replyPath) }}</span>
                @endif
              </div>
              <div class="small text-muted text-truncate mt-2">{{ $lastReply !== '' ? $lastReply : 'No inbound reply yet' }}</div>
            </a>
          @empty
            <div class="p-4 text-center text-muted">No contacts found.</div>
          @endforelse
        </div>
      </div>
    </div>

    <div class="col-lg-8 col-xl-9">
      <div class="card shadow-sm border-0 h-100">
        @if ($selectedContact)
          <div class="card-header bg-white d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
              <strong>{{ $selectedContact->full_name ?: 'Client' }}</strong>
              <div class="small text-muted">{{ $selectedContact->phone_number }}</div>
            </div>
            <div class="d-flex flex-wrap gap-2">
              @if (!empty($selectedContact->lead_status))
                <span class="badge bg-secondary text-uppercase">{{ $selectedContact->lead_status }}</span>
              @endif
              @if (!empty($selectedContact->lead_temperature))
                <span class="badge bg-warning text-dark text-uppercase">{{ $selectedContact->lead_temperature }}</span>
              @endif
            </div>
          </div>

          <div class="card-body p-0">
            <div class="wg-chat-thread p-3" id="chatThread">
              @php($previousDateLabel = '')
              @forelse ($timeline ?? [] as $message)
                @php
                  $isInbound = ($message['direction'] ?? 'inbound') === 'inbound';
                  $source = (string) ($message['source'] ?? '');
                  $label = $isInbound ? 'Client' : (($source === 'ai') ? 'AI Auto Reply' : 'Business');
                  $time = trim((string) ($message['time'] ?? ''));
                  $status = trim((string) ($message['status'] ?? ''));
                  $timestamp = $time !== '' ? strtotime($time) : false;
                  $dateLabel = '';
                  $timeLabel = '';
                  if ($timestamp) {
                    $messageDate = date('Y-m-d', $timestamp);
                    $dateLabel = $messageDate === date('Y-m-d') ? 'Today' : ($messageDate === date('Y-m-d', strtotime('-1 day')) ? 'Yesterday' : date('M j, Y', $timestamp));
                    $timeLabel = date('g:i A', $timestamp);
                  }
                @endphp
                @if ($dateLabel !== '' && $dateLabel !== $previousDateLabel)
                  <div class="text-center my-3">
                    <span class="badge rounded-pill bg-light text-secondary border fw-normal px-3 py-2">{{ $dateLabel }}</span>
                  </div>
                  @php($previousDateLabel = $dateLabel)
                @endif
                <div class="d-flex mb-3 {{ $isInbound ? 'justify-content-start' : 'justify-content-end' }}">
                  <div class="wg-chat-bubble {{ $isInbound ? 'inbound' : 'outbound' }} p-3 shadow-sm">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1 wg-chat-meta">
                      <span class="fw-semibold">{{ $label }}</span>
                      @if ($source === 'ai')
                        <span class="badge bg-primary">AI</span>
                      @endif
                    </div>
                    <div>{{ $message['body'] }}</div>
                    <div class="wg-chat-meta text-end mt-2">
                      {{ $timeLabel }}
                      @if (!$isInbound && $status !== '')
                        <span class="ms-2 text-uppercase">{{ $status }}</span>
                      @endif
                    </div>
                  </div>
                </div>
              @empty
                <div class="h-100 d-flex align-items-center justify-content-center text-muted text-center">
                  <div>
                    <div class="fs-2 mb-2"><i class="bi bi-chat-square-text"></i></div>
                    <div>No chat messages yet for this client.</div>
                  </div>
                </div>
              @endforelse
            </div>
          </div>

          <div class="card-footer bg-white">
            <form action="{{ route('business.messages.chat.reply', ['contact' => $selectedContact->id]) }}" method="post" class="wg-chat-compose">
              @csrf
              <div class="input-group">
                <textarea name="message" class="form-control" rows="2" maxlength="1200" required placeholder="Type a WhatsApp reply"></textarea>
                <button class="btn btn-success px-4" type="submit">
                  <i class="bi bi-send-fill"></i>
                </button>
              </div>
            </form>
          </div>
        @else
          <div class="card-body d-flex align-items-center justify-content-center text-muted text-center" style="min-height: 72vh;">
            <div>
              <div class="fs-2 mb-2"><i class="bi bi-person-lines-fill"></i></div>
              <div>Add contacts first, then client conversations will appear here.</div>
            </div>
          </div>
        @endif
      </div>
    </div>
  </div>
@endsection

@push('scripts')
<script>
  const chatThread = document.getElementById('chatThread');
  if (chatThread) {
    chatThread.scrollTop = 0;
  }

  const chatSearch = document.getElementById('chatSearch');
  if (chatSearch) {
    chatSearch.addEventListener('input', () => {
      const query = chatSearch.value.trim().toLowerCase();
      document.querySelectorAll('.wg-chat-item[data-search]').forEach((item) => {
        item.style.display = item.dataset.search.includes(query) ? '' : 'none';
      });
    });
  }
</script>
@endpush

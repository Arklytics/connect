<div class="text-light">
  <ul class="list-group list-group-flush">
    <a href="{{ route('business.dashboard') }}" class="text-decoration-none">
      <li class="list-group-item text-light shadow rounded mt-2 p-3"><i class="bi bi-house-heart-fill"></i> Dashboard</li>
    </a>
    <a href="{{ route('business.contacts.index') }}" class="text-decoration-none">
      <li class="list-group-item text-light shadow rounded mt-2 p-3"><i class="bi bi-person-lines-fill"></i> Contacts</li>
    </a>
    <a href="{{ route('business.contacts.import') }}" class="text-decoration-none">
      <li class="list-group-item text-light shadow rounded mt-2 p-3"><i class="bi bi-file-earmark-spreadsheet"></i> Import Contacts</li>
    </a>
    <a href="{{ route('business.messages.create') }}" class="text-decoration-none">
      <li class="list-group-item text-light shadow rounded mt-2 p-3"><i class="bi bi-whatsapp"></i> WhatsApp Sequences</li>
    </a>
    <a href="{{ route('business.groups.index') }}" class="text-decoration-none">
      <li class="list-group-item text-light shadow rounded mt-2 p-3"><i class="bi bi-people-fill"></i> Groups</li>
    </a>
    <a href="{{ route('business.templates.create') }}" class="text-decoration-none">
      <li class="list-group-item text-light shadow rounded mt-2 p-3"><i class="bi bi-list-stars"></i> New Template</li>
    </a>
    <a href="{{ route('business.templates.index') }}" class="text-decoration-none">
      <li class="list-group-item text-light shadow rounded mt-2 p-3"><i class="bi bi-card-list"></i> View Templates</li>
    </a>
    <a href="{{ route('business.messages.create') }}" class="text-decoration-none">
      <li class="list-group-item text-light shadow rounded mt-2 p-3"><i class="bi bi-send-check-fill"></i> Send Messages</li>
    </a>
    <a href="{{ route('business.messages.index') }}" class="text-decoration-none">
      <li class="list-group-item text-light shadow rounded mt-2 p-3"><i class="bi bi-clipboard-data-fill"></i> Reports</li>
    </a>
    <form method="POST" action="{{ route('business.logout') }}">
      @csrf
      <button class="list-group-item text-light shadow rounded mt-2 p-3 w-100 text-start border-0" type="submit">
        <i class="bi bi-x-circle-fill"></i> Logout
      </button>
    </form>
  </ul>
</div>

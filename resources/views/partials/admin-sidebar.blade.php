<div class="text-light">
  <ul class="list-group list-group-flush">
    <a href="{{ route('admin.dashboard') }}" class="text-decoration-none">
      <li class="list-group-item text-light shadow rounded mt-2 p-3"><i class="bi bi-house-heart-fill"></i> Dashboard</li>
    </a>
    <a href="{{ route('admin.orders.create') }}" class="text-decoration-none">
      <li class="list-group-item text-light shadow rounded mt-2 p-3"><i class="bi bi-bag-heart-fill"></i> New Order</li>
    </a>
    <a href="{{ route('admin.orders.index') }}" class="text-decoration-none">
      <li class="list-group-item text-light shadow rounded mt-2 p-3"><i class="bi bi-list-columns-reverse"></i> View Orders</li>
    </a>
    <a href="{{ route('admin.settings.tokens') }}" class="text-decoration-none">
      <li class="list-group-item text-light shadow rounded mt-2 p-3"><i class="bi bi-gear-fill"></i> Packages & API</li>
    </a>
    <a href="{{ route('admin.templates.index') }}" class="text-decoration-none">
      <li class="list-group-item text-light shadow rounded mt-2 p-3"><i class="bi bi-card-list"></i> Templates</li>
    </a>
    <form method="POST" action="{{ route('admin.logout') }}">
      @csrf
      <button class="list-group-item text-light shadow rounded mt-2 p-3 w-100 text-start border-0" type="submit">
        <i class="bi bi-x-circle-fill"></i> Logout
      </button>
    </form>
  </ul>
</div>

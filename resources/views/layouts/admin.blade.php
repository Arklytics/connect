<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="{{ asset('master/css/style.css') }}" rel="stylesheet">
    <title>@yield('title', 'Arklytics Connect Master')</title>
  </head>
  <body>
    <nav class="navbar bg-success bg-gradient navbar-dark p-3 shadow">
      <div class="container-fluid">
        <a class="navbar-brand" href="{{ route('admin.dashboard') }}">Arklytics Connect Master</a>
      </div>
    </nav>

    <div class="container-fluid">
      <div class="row">
        <aside class="col-md-2" style="left: 0; min-height: 100vh; overflow-y: auto; background-color: #00002E;">
          @include('partials.admin-sidebar')
        </aside>
        <main class="col-md-10">
          @include('partials.flash')
          @yield('content')
        </main>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
  </body>
</html>

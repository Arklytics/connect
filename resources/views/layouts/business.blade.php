<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="{{ asset('master/css/style.css') }}" rel="stylesheet">
    <title>@yield('title', 'Arklytics Connect Business')</title>
    <style>
      body {
        background: #f4f7fb;
      }
      .wg-business-shell {
        min-height: calc(100vh - 64px);
      }
      .wg-business-main {
        padding: 24px;
      }
      .wg-business-aside {
        background: linear-gradient(180deg, #06121f 0%, #0f172a 100%);
        min-height: calc(100vh - 64px);
        overflow-y: auto;
      }
      .navbar.wg-topbar {
        background: linear-gradient(135deg, #0f766e 0%, #14532d 100%);
      }
    </style>
  </head>
  <body>
    <nav class="navbar wg-topbar navbar-dark p-3 shadow">
      <div class="container-fluid">
        <a class="navbar-brand" href="{{ route('business.dashboard') }}">Arklytics Connect</a>
      </div>
    </nav>

    <div class="container-fluid wg-business-shell p-0">
      <div class="row">
        <aside class="col-md-2 wg-business-aside p-0">
          @include('partials.business-sidebar')
        </aside>
        <main class="col-md-10 wg-business-main">
          @include('partials.flash')
          @yield('content')
        </main>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
  </body>
</html>

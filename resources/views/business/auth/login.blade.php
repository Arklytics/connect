<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Arklytics Connect Business Login</title>
  </head>
  <body style="background-color: #EEEEEE;">
    <nav class="navbar bg-success bg-gradient navbar-dark">
      <div class="container-fluid"><a class="navbar-brand" href="#">Arklytics Connect</a></div>
    </nav>
    <div class="container vh-100 d-flex justify-content-center align-items-center rounded-2">
      <div class="row w-100">
        <div class="col-md-6 mx-auto">
          <div class="card shadow p-4">
            <h4 class="text-center mb-4">Login to Continue!</h4>
            @include('partials.flash')
            <form action="{{ route('business.login.store') }}" method="post">
              @csrf
              <div class="mb-3">
                <label for="mobile" class="form-label">Mobile Number</label>
                <input type="number" class="form-control" id="mobile" name="mobile" value="{{ old('mobile') }}" required>
              </div>
              <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
              </div>
              <button type="submit" class="btn btn-success w-100">Login</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </body>
</html>

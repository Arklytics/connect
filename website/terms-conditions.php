<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Terms and Conditions | Arklytics Connect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="<?php echo h(app_url('master/css/style.css')); ?>" rel="stylesheet">
  </head>
  <body class="wg-login-body">
    <main class="wg-login-shell">
      <div class="wg-login-form-wrap">
        <div class="wg-login-card" style="max-width: 900px;">
          <div class="wg-brand text-dark mb-3">
            <img class="wg-brand-logo" src="<?php echo h(app_url('website/uploads/connect-logo.png')); ?>" alt="Connect logo">
            <span>Terms and Conditions</span>
          </div>
          <h2>Terms and Conditions</h2>
          <p class="subtitle">Rules for using the Arklytics Connect platform.</p>

          <div class="text-start">
            <p>By using this platform, you agree to provide accurate account information and to use the service only for lawful business purposes.</p>
            <p>You are responsible for the contacts, messages, and content you upload or send from your workspace.</p>
            <p>Arklytics Connect may suspend access if the platform is misused, if login credentials are shared improperly, or if activity violates applicable laws or third-party platform rules.</p>
            <p>Message delivery depends on external providers such as WhatsApp, Meta, hosting, and database services. Availability may vary.</p>
            <p>Features and pricing may change as the product evolves.</p>
          </div>

          <div class="mt-4 d-flex justify-content-between flex-wrap gap-2">
            <a href="<?php echo h(app_url('privacy-policy')); ?>" class="btn btn-light">
              <i class="bi bi-shield-check me-1"></i> Privacy Policy
            </a>
            <a href="<?php echo h(app_url('crm-privacy')); ?>" class="btn btn-outline-success">
              <i class="bi bi-people me-1"></i> CRM Privacy
            </a>
          </div>
        </div>
      </div>
    </main>
  </body>
</html>

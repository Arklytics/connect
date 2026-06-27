<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CRM Privacy | Arklytics Connect</title>
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
            <span>CRM Privacy</span>
          </div>
          <h2>CRM Privacy</h2>
          <p class="subtitle">How contact and follow-up data is used inside your workspace.</p>

          <div class="text-start">
            <p>CRM data includes leads, contact groups, follow-up schedules, notes, and delivery status saved in your business workspace.</p>
            <p>This data is used to help you manage customer relationships, automate reminders, and keep campaign history organized.</p>
            <p>Workspace admins should only add information they are allowed to store and should not upload sensitive data unless it is necessary for business operations.</p>
            <p>Contact records remain tied to your business account and are not shared across unrelated customers.</p>
          </div>

          <div class="mt-4 d-flex justify-content-between flex-wrap gap-2">
            <a href="<?php echo h(app_url('privacy-policy')); ?>" class="btn btn-light">
              <i class="bi bi-shield-check me-1"></i> Privacy Policy
            </a>
            <a href="<?php echo h(app_url('terms-conditions')); ?>" class="btn btn-outline-success">
              <i class="bi bi-file-earmark-text me-1"></i> Terms & Conditions
            </a>
          </div>
        </div>
      </div>
    </main>
  </body>
</html>

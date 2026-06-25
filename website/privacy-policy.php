<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Privacy Policy | WhatsGrow</title>
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
            <span>Privacy Policy</span>
          </div>
          <h2>Privacy Policy</h2>
          <p class="subtitle">How WhatsGrow handles account, CRM, and WhatsApp-related data.</p>

          <div class="text-start">
            <p>We collect and store the information needed to run your workspace, including business profile details, contacts you add, templates you create, and delivery logs for messages sent through the platform.</p>
            <p>CRM data is used only to manage leads, follow-ups, and reporting for your workspace. We do not sell your customer lists or use them for unrelated advertising purposes.</p>
            <p>If you connect WhatsApp or Meta services, we may store access tokens, IDs, and webhook details needed to keep the integration working.</p>
            <p>Only authorized users for your business account should access the workspace. Please keep your login credentials secure.</p>
            <p>If you need data removal or a privacy request, contact your administrator or the site owner for assistance.</p>
          </div>

          <div class="mt-4 d-flex justify-content-between flex-wrap gap-2">
            <a href="<?php echo h(app_url('terms-conditions')); ?>" class="btn btn-light">
              <i class="bi bi-file-earmark-text me-1"></i> Terms & Conditions
            </a>
            <a href="<?php echo h(app_url('crm-privacy')); ?>" class="btn btn-outline-success">
              <i class="bi bi-shield-lock me-1"></i> CRM Privacy
            </a>
          </div>
        </div>
      </div>
    </main>
  </body>
</html>

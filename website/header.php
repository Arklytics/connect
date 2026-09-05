<!doctype html>
<html lang="en">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
 <link href="<?php echo h(app_url('master/css/style.css')); ?>" rel="stylesheet">
    <title>Arklytics Connect</title>
  </head>
  
  <body>
    

<nav class="navbar wg-topbar">
  <div class="container-fluid align-items-center">
    <a class="navbar-brand wg-brand" href="<?php echo h(app_url('business')); ?>" aria-label="Arklytics Connect business home">
      <img class="wg-brand-logo" src="<?php echo h(app_url('website/uploads/connect-logo.png')); ?>" alt="Arklytics Connect logo">
      <span class="wg-brand-copy d-none d-sm-flex">
        <span class="wg-brand-title">Connect CRM</span>
        <span class="wg-brand-subtitle">WhatsApp business workspace</span>
      </span>
    </a>

    <div class="wg-topnav d-none d-lg-flex">
      <a href="<?php echo h(app_url('business/create-contact')); ?>"><i class="bi bi-people"></i> Contacts</a>
      <a href="<?php echo h(app_url('business/send-messages')); ?>"><i class="bi bi-send"></i> Campaigns</a>
      <a href="<?php echo h(app_url('business/reports')); ?>"><i class="bi bi-bar-chart"></i> Reports</a>
    </div>

    <div class="wg-topbar-actions">
      <a class="btn btn-light btn-sm" href="<?php echo h(app_url('business/payments')); ?>">
        <i class="bi bi-layers"></i>
        <span class="d-none d-sm-inline">Plans</span>
      </a>
      <a class="btn btn-light btn-sm" href="<?php echo h(app_url('business/profile')); ?>" aria-label="Profile">
        <i class="bi bi-person-circle"></i>
      </a>
      <a class="btn btn-success btn-sm" href="<?php echo h(app_url('business/logout')); ?>">
        <i class="bi bi-box-arrow-right"></i>
        <span class="d-none d-sm-inline">Logout</span>
      </a>
    </div>
  </div>
</nav>

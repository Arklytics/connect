<aside class="text-light">
  <ul class="list-group list-group-flush">
 <a href="<?php echo h(app_url('business')); ?>" class="text-decoration-none">
      <li class="list-group-item active"><i class="bi bi-grid-1x2-fill"></i> Dashboard</li>
    </a>

    <li class="list-group-item dropdown-toggle" id="crmDropdown" style="cursor: pointer;">
      <i class="bi bi-kanban"></i> Connect
    </li>
    <ul class="list-group collapse" id="crmMenu">
 <a href="<?php echo h(app_url('business/create-contact')); ?>" class="text-decoration-none"><li class="list-group-item"><i class="bi bi-person-lines-fill"></i> Contacts</li></a>
 <a href="<?php echo h(app_url('business/add-contacts-group')); ?>" class="text-decoration-none"><li class="list-group-item"><i class="bi bi-file-earmark-spreadsheet"></i> Import Contacts</li></a>
 <a href="<?php echo h(app_url('business/create-group')); ?>" class="text-decoration-none"><li class="list-group-item"><i class="bi bi-people"></i> Contact Groups</li></a>
 <a href="<?php echo h(app_url('business/whatsapp-sequences')); ?>" class="text-decoration-none"><li class="list-group-item"><i class="bi bi-diagram-3"></i> WhatsApp Sequences</li></a>
    </ul>

    <li class="list-group-item dropdown-toggle" id="templatesDropdown" style="cursor: pointer;">
      <i class="bi bi-layout-text-window-reverse"></i> Templates
    </li>
    <ul class="list-group collapse" id="templatesMenu">
 <a href="<?php echo h(app_url('business/new-template')); ?>" class="text-decoration-none"><li class="list-group-item"><i class="bi bi-file-earmark-plus"></i> New Template</li></a>
 <a href="<?php echo h(app_url('business/upload-media')); ?>" class="text-decoration-none"><li class="list-group-item"><i class="bi bi-cloud-upload"></i> Upload Media</li></a>
 <a href="<?php echo h(app_url('business/view-templates')); ?>" class="text-decoration-none"><li class="list-group-item"><i class="bi bi-files"></i> View Templates</li></a>
    </ul>

    <li class="list-group-item dropdown-toggle" id="settingsDropdown" style="cursor: pointer;">
      <i class="bi bi-gear"></i> Settings
    </li>
    <ul class="list-group collapse" id="settingsMenu">
 <a href="<?php echo h(app_url('business/profile')); ?>" class="text-decoration-none"><li class="list-group-item"><i class="bi bi-person-badge"></i> Profile Settings</li></a>
 <a href="<?php echo h(app_url('business/connect-whatsapp')); ?>" class="text-decoration-none"><li class="list-group-item"><i class="bi bi-whatsapp"></i> WhatsApp Connection</li></a>
      <li class="list-group-item text-muted"><i class="bi bi-credit-card"></i> Payment Settings <span class="badge bg-secondary ms-2">Soon</span></li>
    </ul>

    <li class="list-group-item dropdown-toggle" id="messageDropdown" style="cursor: pointer;">
      <i class="bi bi-whatsapp"></i> Messages
    </li>
    <ul class="list-group collapse" id="messageMenu">
 <a href="<?php echo h(app_url('business/send-messages')); ?>" class="text-decoration-none"><li class="list-group-item"><i class="bi bi-send"></i> Send Messages</li></a>
      <li class="list-group-item"><i class="bi bi-inbox"></i> Check Message</li>
    </ul>

    <li class="list-group-item dropdown-toggle" id="reportsDropdown" style="cursor: pointer;">
      <i class="bi bi-bar-chart-fill"></i> Reports
    </li>
    <ul class="list-group collapse" id="reportsMenu">
 <a href="<?php echo h(app_url('business/reports')); ?>" class="text-decoration-none"><li class="list-group-item"><i class="bi bi-clipboard-data-fill"></i> Reports Home</li></a>
 <a href="<?php echo h(app_url('business/view-messages')); ?>" class="text-decoration-none"><li class="list-group-item"><i class="bi bi-chat-dots-fill"></i> Message Reports</li></a>
 <a href="<?php echo h(app_url('business/lead-reports')); ?>" class="text-decoration-none"><li class="list-group-item"><i class="bi bi-people-fill"></i> Lead Reports</li></a>
    </ul>

 <a href="<?php echo h(app_url('business/logout')); ?>" class="text-decoration-none">
      <li class="list-group-item"><i class="bi bi-box-arrow-right"></i> Logout</li>
    </a>
  </ul>
</aside>

<script>
  ["crm", "settings", "templates", "message", "reports"].forEach(function (name) {
    var trigger = document.getElementById(name + "Dropdown");
    var menu = document.getElementById(name + "Menu");
    if (!trigger || !menu) {
      return;
    }
    trigger.addEventListener("click", function () {
      menu.classList.toggle("collapse");
    });
  });
</script>

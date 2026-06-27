<aside class="text-light">
  <nav class="list-group list-group-flush">
    <a href="<?php echo h(app_url('master')); ?>" class="list-group-item list-group-item-action active">
      <i class="bi bi-grid-1x2-fill"></i> Dashboard
    </a>

    <button type="button" class="list-group-item list-group-item-action dropdown-toggle" id="ordersDropdown" style="cursor: pointer;">
      <i class="bi bi-bag-check-fill"></i> Orders
    </button>
    <div class="collapse" id="ordersMenu">
      <div class="list-group mt-2 ms-3">
        <a href="<?php echo h(app_url('master/new-order')); ?>" class="list-group-item list-group-item-action">
          <i class="bi bi-plus-circle"></i> New Order
        </a>
        <a href="<?php echo h(app_url('master/view-orders')); ?>" class="list-group-item list-group-item-action">
          <i class="bi bi-table"></i> View Orders
        </a>
      </div>
    </div>

    <button type="button" class="list-group-item list-group-item-action dropdown-toggle" id="templatesDropdown" style="cursor: pointer;">
      <i class="bi bi-layout-text-window-reverse"></i> Templates
    </button>
    <div class="collapse" id="templatesMenu">
      <div class="list-group mt-2 ms-3">
        <a href="<?php echo h(app_url('master/new-templates')); ?>" class="list-group-item list-group-item-action">
          <i class="bi bi-files"></i> View Templates
        </a>
        <span class="list-group-item">
          <i class="bi bi-check2-circle"></i> Check Templates
        </span>
      </div>
    </div>

    <span class="list-group-item">
      <i class="bi bi-whatsapp"></i> Messages
    </span>

    <button type="button" class="list-group-item list-group-item-action dropdown-toggle" id="settingsDropdown" style="cursor: pointer;">
      <i class="bi bi-gear-fill"></i> Settings
    </button>
    <div class="collapse" id="settingsMenu">
      <div class="list-group mt-2 ms-3">
        <a href="<?php echo h(app_url('master/setting-token')); ?>" class="list-group-item list-group-item-action">
          <i class="bi bi-key"></i> API Settings
        </a>
      </div>
    </div>

    <button type="button" class="list-group-item list-group-item-action dropdown-toggle" id="reportsDropdown" style="cursor: pointer;">
      <i class="bi bi-bar-chart-fill"></i> Reports
    </button>
    <div class="collapse" id="reportsMenu">
      <div class="list-group mt-2 ms-3">
        <span class="list-group-item"><i class="bi bi-credit-card"></i> Payments</span>
        <span class="list-group-item"><i class="bi bi-chat-left-text"></i> Messages</span>
        <span class="list-group-item"><i class="bi bi-file-earmark-text"></i> Templates</span>
        <span class="list-group-item"><i class="bi bi-bag"></i> Orders</span>
      </div>
    </div>

    <a href="<?php echo h(app_url('master/signout')); ?>" class="list-group-item list-group-item-action">
      <i class="bi bi-box-arrow-right"></i> Logout
    </a>
  </nav>
</aside>

<script>
  ["orders", "templates", "settings", "reports"].forEach(function (name) {
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

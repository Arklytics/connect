<aside class="text-light">
  <ul class="list-group list-group-flush">
    <a href="/wpi2/master" class="text-decoration-none">
      <li class="list-group-item active"><i class="bi bi-grid-1x2-fill"></i> Dashboard</li>
    </a>

    <li class="list-group-item dropdown-toggle" id="ordersDropdown" style="cursor: pointer;">
      <i class="bi bi-bag-check-fill"></i> Orders
    </li>
    <ul class="list-group collapse" id="ordersMenu">
      <a href="/wpi2/master/new-order" class="text-decoration-none"><li class="list-group-item"><i class="bi bi-plus-circle"></i> New Order</li></a>
      <a href="/wpi2/master/view-orders" class="text-decoration-none"><li class="list-group-item"><i class="bi bi-table"></i> View Orders</li></a>
    </ul>

    <li class="list-group-item dropdown-toggle" id="templatesDropdown" style="cursor: pointer;">
      <i class="bi bi-layout-text-window-reverse"></i> Templates
    </li>
    <ul class="list-group collapse" id="templatesMenu">
      <a href="/wpi2/master/new-templates" class="text-decoration-none"><li class="list-group-item"><i class="bi bi-files"></i> View Templates</li></a>
      <li class="list-group-item"><i class="bi bi-check2-circle"></i> Check Templates</li>
    </ul>

    <li class="list-group-item"><i class="bi bi-whatsapp"></i> Messages</li>

    <li class="list-group-item dropdown-toggle" id="settingsDropdown" style="cursor: pointer;">
      <i class="bi bi-gear-fill"></i> Settings
    </li>
    <ul class="list-group collapse" id="settingsMenu">
      <a href="/wpi2/master/setting-token" class="text-decoration-none"><li class="list-group-item"><i class="bi bi-key"></i> Add Token</li></a>
    </ul>

    <li class="list-group-item dropdown-toggle" id="reportsDropdown" style="cursor: pointer;">
      <i class="bi bi-bar-chart-fill"></i> Reports
    </li>
    <ul class="list-group collapse" id="reportsMenu">
      <li class="list-group-item"><i class="bi bi-credit-card"></i> Payments</li>
      <li class="list-group-item"><i class="bi bi-chat-left-text"></i> Messages</li>
      <li class="list-group-item"><i class="bi bi-file-earmark-text"></i> Templates</li>
      <li class="list-group-item"><i class="bi bi-bag"></i> Orders</li>
    </ul>

    <a href="/wpi2/master/signout" class="text-decoration-none">
      <li class="list-group-item"><i class="bi bi-box-arrow-right"></i> Logout</li>
    </a>
  </ul>
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



<!-- Sidebar Styles and Dependencies -->
<link rel="stylesheet" href="fonts/fonts.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="css/sidebar.css">

<!-- Sidebar HTML -->
<aside class="sidebar" id="sidebar" aria-label="منوی اصلی">
  <!-- Date Display -->
  <div class="sidebar-header">
    <div class="date-display" id="persian-date" aria-live="polite"></div>
  </div>

  <!-- Navigation Menu -->
  <nav class="sidebar-nav">
    <ul class="nav-list">
      <li class="nav-item">
        <a href="dashboard.php" class="nav-link">
          <i class="fas fa-tachometer-alt nav-icon" aria-hidden="true"></i>
          <span class="nav-text">داشبورد</span>
        </a>
      </li>
      <li class="nav-item">
        <a href="products.php" class="nav-link">
          <i class="fas fa-box nav-icon" aria-hidden="true"></i>
          <span class="nav-text">محصولات</span>
        </a>
      </li>
      <li class="nav-item">
        <a href="bulk_products.php" class="nav-link">
          <i class="fas fa-layer-group nav-icon" aria-hidden="true"></i>
          <span class="nav-text">ثبت عمده</span>
        </a>
      </li>
      <li class="nav-item">
        <a href="payments.php" class="nav-link">
          <i class="fas fa-credit-card nav-icon" aria-hidden="true"></i>
          <span class="nav-text">پرداختی‌ها</span>
        </a>
      </li>
      <li class="nav-item">
        <a href="invoices.php" class="nav-link">
          <i class="fas fa-file-invoice nav-icon" aria-hidden="true"></i>
          <span class="nav-text">فاکتورها</span>
        </a>
      </li>
    </ul>
  </nav>

  <!-- Support Section -->
  <div class="sidebar-footer">
    <a href="support.php" class="support-link">
      <i class="fas fa-headset support-icon" aria-hidden="true"></i>
      <span class="support-text">پشتیبانی</span>
    </a>
  </div>
</aside>

<!-- Sidebar Scripts -->
<script src="https://cdn.jsdelivr.net/npm/jalaali-js/dist/jalaali.min.js"></script>
<script src="js/sidebar.js"></script>

<?php
include 'db.php';
include 'jalali_calendar.php';

// دریافت بازه تاریخ از فرم
$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';

// شرط تاریخ برای کوئری
$where = "";
$params = [];
if ($from && $to) {
    // تبدیل تاریخ شمسی به میلادی
    if (strpos($from, '/') !== false) {
        list($jy,$jm,$jd) = explode('/', $from);
        list($gy,$gm,$gd) = jalali_to_gregorian($jy, $jm, $jd);
        $from = sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
    }
    if (strpos($to, '/') !== false) {
        list($jy,$jm,$jd) = explode('/', $to);
        list($gy,$gm,$gd) = jalali_to_gregorian($jy, $jm, $jd);
        $to = sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
    }

    $where = "WHERE purchase_date BETWEEN ? AND ?";
    $params = [$from, $to];
}

// ------------------
// گزارش روزانه
// ------------------
$stmt = $conn->prepare("
    SELECT purchase_date, SUM(quantity) as total_qty
    FROM purchases
    $where
    GROUP BY purchase_date
    ORDER BY purchase_date DESC
    LIMIT 30
");
$stmt->execute($params);
$daily = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ------------------
// گزارش ماهانه (شمسی)
// ------------------
$stmt = $conn->prepare("SELECT purchase_date, quantity FROM purchases $where");
$stmt->execute($params);
$purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

$monthly = [];
foreach ($purchases as $p) {
    list($gy,$gm,$gd) = explode('-', $p['purchase_date']);
    list($jy,$jm,$jd) = gregorian_to_jalali($gy,$gm,$gd);
    $key = sprintf("%04d/%02d", $jy, $jm);
    if (!isset($monthly[$key])) $monthly[$key] = 0;
    $monthly[$key] += $p['quantity'];
}

// محاسبه آمار کلی
$totalPurchases = array_sum(array_column($purchases, 'quantity'));
$uniqueDays = count($daily);
$averageDaily = $uniqueDays > 0 ? round($totalPurchases / $uniqueDays, 1) : 0;
?>
<!DOCTYPE html>
<html lang="fa">
<head>
<meta charset="UTF-8">
<title>📊 گزارش فروش</title>

<!-- Fonts -->
<link rel="stylesheet" href="fonts/fonts.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<!-- Styles -->
<link rel="stylesheet" href="css/design-system.css">
<link rel="stylesheet" href="css/sidebar.css">
<link rel="stylesheet" href="css/reports.css">

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jalaali-js/dist/jalaali.min.js"></script>
<script src="js/sidebar.js"></script>
</head>

<body class="dashboard-container">
  <!-- Sidebar -->
  <aside class="sidebar">
    <?php include 'sidebar.php'; ?>
  </aside>

  <!-- Main Content -->
  <div class="main-content">
    <!-- Top Bar -->
    <header class="top-bar">
      <h1 class="h1 reports-title">
        <i class="fas fa-chart-bar reports-icon"></i>
        گزارش فروش
      </h1>
      <div class="header-actions">
        <!-- Space for future actions -->
      </div>
    </header>

    <!-- Content Area -->
    <div class="content-area">
      <!-- Statistics Cards -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-value"><?= number_format($totalPurchases) ?></div>
          <div class="stat-label">کل فروش</div>
        </div>
        <div class="stat-card">
          <div class="stat-value"><?= $uniqueDays ?></div>
          <div class="stat-label">روز فعال</div>
        </div>
        <div class="stat-card">
          <div class="stat-value"><?= number_format($averageDaily) ?></div>
          <div class="stat-label">میانگین روزانه</div>
        </div>
      </div>

      <!-- Filter Form -->
      <div class="reports-container">
        <form method="get" class="filter-form">
          <div class="filter-row">
            <div class="filter-field">
              <label class="filter-label">از تاریخ:</label>
              <input type="text" name="from" class="filter-input" placeholder="مثلا 1404/01/01" value="<?= htmlspecialchars($_GET['from'] ?? '') ?>">
            </div>
            <div class="filter-field">
              <label class="filter-label">تا تاریخ:</label>
              <input type="text" name="to" class="filter-input" placeholder="مثلا 1404/12/29" value="<?= htmlspecialchars($_GET['to'] ?? '') ?>">
            </div>
            <div class="filter-field">
              <button type="submit" class="filter-button">
                <i class="fas fa-filter"></i>
                اعمال فیلتر
              </button>
            </div>
          </div>
        </form>
      </div>

      <!-- Daily Reports -->
      <div class="reports-container">
        <h3 class="section-header">
          <i class="fas fa-calendar-day section-icon"></i>
          گزارش روزانه (۳۰ روز اخیر)
        </h3>
        <div class="reports-table">
          <table>
            <thead>
              <tr>
                <th>تاریخ</th>
                <th>تعداد محصول</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($daily as $d): ?>
              <tr>
                <td>
                  <?php
                    list($gy,$gm,$gd) = explode('-', $d['purchase_date']);
                    list($jy,$jm,$jd) = gregorian_to_jalali($gy,$gm,$gd);
                    echo "$jy/$jm/$jd";
                  ?>
                </td>
                <td><?= number_format($d['total_qty']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Monthly Reports -->
      <div class="reports-container">
        <h3 class="section-header">
          <i class="fas fa-calendar-alt section-icon"></i>
          گزارش ماهانه
        </h3>
        <div class="reports-table">
          <table>
            <thead>
              <tr>
                <th>ماه</th>
                <th>تعداد محصول</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($monthly as $m => $q): ?>
              <tr>
                <td><?= $m ?></td>
                <td><?= number_format($q) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Chart -->
      <div class="chart-container">
        <h3 class="chart-title">نمودار فروش ماهانه</h3>
        <canvas id="monthlyChart" width="400" height="200"></canvas>
      </div>
    </div>
  </div>

  <script>
  // -----------------
  // نمودار ماهانه (ستونی)
  // -----------------
  const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
  new Chart(monthlyCtx, {
      type: 'bar',
      data: {
          labels: <?= json_encode(array_keys($monthly), JSON_UNESCAPED_UNICODE) ?>,
          datasets: [{
              label: 'تعداد محصولات فروخته‌شده (ماهانه)',
              data: <?= json_encode(array_values($monthly)) ?>,
              backgroundColor: 'rgba(91, 124, 255, 0.6)',
              borderColor: 'rgba(91, 124, 255, 1)',
              borderWidth: 1
          }]
      },
      options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
              legend: {
                  display: false
              },
              title: {
                  display: true,
                  text: 'فروش ماهانه',
                  font: {
                      size: 16,
                      family: 'Peyda, sans-serif'
                  }
              }
          },
          scales: {
              y: {
                  beginAtZero: true,
                  ticks: {
                      callback: function(value) {
                          return value.toLocaleString('fa-IR');
                      }
                  }
              },
              x: {
                  ticks: {
                      font: {
                          family: 'Peyda, sans-serif'
                      }
                  }
              }
          }
      }
  });
  </script>

</body>
</html>

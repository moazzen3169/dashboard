<?php
include 'db.php'; // اتصال به دیتابیس
include 'jalali_calendar.php'; // توابع تبدیل تاریخ شمسی

// Function to get KPI data
function getKpiData($conn, $table, $column, $dateCondition) {
    try {
        $stmt = $conn->prepare("SELECT SUM($column) as total FROM $table WHERE $dateCondition");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'] ?? 0;
    } catch (PDOException $e) {
        error_log("KPI query failed: " . $e->getMessage());
        return 0;
    }
}

function getMonthlySalesData($conn) {
    try {
        // Get current Jalali year
        $today = date('Y-m-d');
        list($gy, $gm, $gd) = explode('-', $today);
        $jalali = gregorian_to_jalali($gy, $gm, $gd);
        $currentJalaliYear = $jalali[0];

        // Fetch all sales
        $stmt = $conn->prepare("SELECT sale_date, price FROM products");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data = array_fill(0, 12, 0);
        foreach ($rows as $row) {
            list($gy, $gm, $gd) = explode('-', $row['sale_date']);
            $jalali = gregorian_to_jalali($gy, $gm, $gd);
            if ($jalali[0] == $currentJalaliYear) {
                $monthIndex = (int)$jalali[1] - 1; // Jalali month 1-12, index 0-11
                $data[$monthIndex] += $row['price'];
            }
        }
        return $data;
    } catch (PDOException $e) {
        error_log("Monthly sales query failed: " . $e->getMessage());
        return array_fill(0, 12, 0);
    }
}

// Function to get previous KPI data
function getPreviousKpiData($conn, $table, $column, $dateCondition) {
    try {
        $stmt = $conn->prepare("SELECT SUM($column) as total FROM $table WHERE $dateCondition");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'] ?? 0;
    } catch (PDOException $e) {
        error_log("Previous KPI query failed: " . $e->getMessage());
        return 0;
    }
}

// Function to calculate percentage change
function calculatePercentageChange($current, $previous) {
    if ($previous == 0) {
        return $current > 0 ? 100 : 0;
    }
    return (($current - $previous) / $previous) * 100;
}

// Get KPI values
$dailySales = getKpiData($conn, 'products', 'price', 'DATE(sale_date) = CURDATE()');
$monthlySales = getKpiData($conn, 'products', 'price', 'MONTH(sale_date) = MONTH(CURDATE()) AND YEAR(sale_date) = YEAR(CURDATE())');
$yearlySales = getKpiData($conn, 'products', 'price', 'YEAR(sale_date) = YEAR(CURDATE())');

$dailyPayments = getKpiData($conn, 'payments', 'amount', 'DATE(payment_date) = CURDATE()');
$monthlyPayments = getKpiData($conn, 'payments', 'amount', 'MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE())');
$yearlyPayments = getKpiData($conn, 'payments', 'amount', 'YEAR(payment_date) = YEAR(CURDATE())');

// Get previous KPI values
$prevDailySales = getPreviousKpiData($conn, 'products', 'price', 'DATE(sale_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)');
$prevMonthlySales = getPreviousKpiData($conn, 'products', 'price', 'MONTH(sale_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(sale_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))');
$prevYearlySales = getPreviousKpiData($conn, 'products', 'price', 'YEAR(sale_date) = YEAR(CURDATE()) - 1');

$prevDailyPayments = getPreviousKpiData($conn, 'payments', 'amount', 'DATE(payment_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)');
$prevMonthlyPayments = getPreviousKpiData($conn, 'payments', 'amount', 'MONTH(payment_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(payment_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))');
$prevYearlyPayments = getPreviousKpiData($conn, 'payments', 'amount', 'YEAR(payment_date) = YEAR(CURDATE()) - 1');

// Calculate percentage changes
$dailySalesChange = calculatePercentageChange($dailySales, $prevDailySales);
$monthlySalesChange = calculatePercentageChange($monthlySales, $prevMonthlySales);
$yearlySalesChange = calculatePercentageChange($yearlySales, $prevYearlySales);

$dailyPaymentsChange = calculatePercentageChange($dailyPayments, $prevDailyPayments);
$monthlyPaymentsChange = calculatePercentageChange($monthlyPayments, $prevMonthlyPayments);
$yearlyPaymentsChange = calculatePercentageChange($yearlyPayments, $prevYearlyPayments);

// Get chart data
$monthlySalesData = getMonthlySalesData($conn);
?>

<!DOCTYPE html>
<html lang="fa">
<head>
  <meta charset="UTF-8">
  <title>داشبورد مدیریت فروشگاه</title>
  <link rel="stylesheet" href="css/design-system.css">
  <link rel="stylesheet" href="css/sidebar.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css"/>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/js/persian-datepicker.min.js"></script>
  <script src="js/persian-datepicker-init.js"></script>
</head>
<body class="dashboard-container">
  <aside class="sidebar">
    <?php include 'sidebar.php'; ?>
  </aside>

  <div class="main-content">
    <header class="top-bar">
      <h1 class="h1"><i class="fas fa-chart-line"></i> داشبورد</h1>
      <div>
        <!-- Placeholder for search, notifications, etc. -->
        <button class="btn-secondary">جستجو</button>
      </div>
    </header>

    <div class="content-area">
      <section class="kpi-grid">
        <!-- پرداختی های امروز -->
        <div class="kpi-card">
          <div class="icon-container pastel-purple">
            <i class="fas fa-calendar-day kpi-icon"></i>
          </div>
          <div class="kpi-text">
            <h3 class="body">پرداختی‌های امروز</h3>
            <p class="kpi-value"><?php echo number_format($dailyPayments) ?: 0; ?></p>
            <p class="kpi-change <?php echo $dailyPaymentsChange > 0 ? 'positive' : ($dailyPaymentsChange < 0 ? 'negative' : 'zero'); ?>">
              <?php echo number_format(abs($dailyPaymentsChange), 1) . '%'; ?> نسبت به دیروز
              <span class="arrow"><?php echo $dailyPaymentsChange > 0 ? '&#9650;' : '&#9660;'; ?></span>
            </p>
          </div>
        </div>

        <div class="kpi-card">
          <div class="icon-container pastel-green">
            <i class="fas fa-calendar-alt kpi-icon"></i>
          </div>
          <div class="kpi-text">
            <h3 class="body">پرداختی‌های ماهانه</h3>
            <p class="kpi-value"><?php echo number_format($monthlyPayments) ?: 0; ?></p>
            <p class="kpi-change <?php echo $monthlyPaymentsChange > 0 ? 'positive' : ($monthlyPaymentsChange < 0 ? 'negative' : 'zero'); ?>">
              <?php echo number_format(abs($monthlyPaymentsChange), 1) . '%'; ?> نسبت به ماه قبل
              <span class="arrow"><?php echo $monthlyPaymentsChange > 0 ? '&#9650;' : '&#9660;'; ?></span>
            </p>
          </div>
        </div>

        <div class="kpi-card">
          <div class="icon-container pastel-yellow">
            <i class="fas fa-calendar-week kpi-icon"></i>
          </div>
          <div class="kpi-text">
            <h3 class="body">پرداختی‌های سالانه</h3>
            <p class="kpi-value"><?php echo number_format($yearlyPayments) ?: 0; ?></p>
            <p class="kpi-change <?php echo $yearlyPaymentsChange > 0 ? 'positive' : ($yearlyPaymentsChange < 0 ? 'negative' : 'zero'); ?>">
              <?php echo number_format(abs($yearlyPaymentsChange), 1) . '%'; ?> نسبت به سال قبل
              <span class="arrow"><?php echo $yearlyPaymentsChange > 0 ? '&#9650;' : '&#9660;'; ?></span>
            </p>
          </div>
        </div>

        <div class="kpi-card">
          <div class="icon-container pastel-red">
            <i class="fas fa-sun kpi-icon"></i>
          </div>
          <div class="kpi-text">
            <h3 class="body">فروش روزانه</h3>
            <p class="kpi-value"><?php echo number_format($dailySales) ?: 0; ?></p>
            <p class="kpi-change <?php echo $dailySalesChange > 0 ? 'positive' : ($dailySalesChange < 0 ? 'negative' : 'zero'); ?>">
              <?php echo number_format(abs($dailySalesChange), 1) . '%'; ?> نسبت به دیروز
              <span class="arrow"><?php echo $dailySalesChange > 0 ? '&#9650;' : '&#9660;'; ?></span>
            </p>
          </div>
        </div>

        <div class="kpi-card">
          <div class="icon-container pastel-blue">
            <i class="fas fa-sun kpi-icon"></i>
          </div>
          <div class="kpi-text">
            <h3 class="body">فروش ماهانه</h3>
            <p class="kpi-value"><?php echo number_format($monthlySales) ?: 0; ?></p>
            <p class="kpi-change <?php echo $monthlySalesChange > 0 ? 'positive' : ($monthlySalesChange < 0 ? 'negative' : 'zero'); ?>">
              <?php echo number_format(abs($monthlySalesChange), 1) . '%'; ?> نسبت به ماه قبل
              <span class="arrow"><?php echo $monthlySalesChange > 0 ? '&#9650;' : '&#9660;'; ?></span>
            </p>
          </div>
        </div>

        <div class="kpi-card">
          <div class="icon-container pastel-orange">
            <i class="fas fa-sun kpi-icon"></i>
          </div>
          <div class="kpi-text">
            <h3 class="body">فروش سالانه (1404)</h3>
            <p class="kpi-value"><?php echo number_format($yearlySales) ?: 0; ?></p>
            <p class="kpi-change <?php echo $yearlySalesChange > 0 ? 'positive' : ($yearlySalesChange < 0 ? 'negative' : 'zero'); ?>">
              <?php echo number_format(abs($yearlySalesChange), 1) . '%'; ?> نسبت به سال قبل
              <span class="arrow"><?php echo $yearlySalesChange > 0 ? '&#9650;' : '&#9660;'; ?></span>
            </p>
          </div>
        </div>
      </section>

      <!-- بخش نمودار -->
      <section class="chart-container">
        <canvas id="salesChart"></canvas>
      </section>
    </div>
  </div>

  <script>
    // نمایش نمودار فروش ماهانه با داده‌های واقعی
    const ctx = document.getElementById('salesChart').getContext('2d');
    const salesChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'],
        datasets: [{
          label: 'فروش ماهانه (تومان)',
          data: <?php echo json_encode($monthlySalesData); ?>,
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        scales: {
          y: { beginAtZero: true }
        }
      }
    });
  </script>
  <script src="js/persian-datepicker-init.js"></script>

</body>
</html>

<?php
include 'db.php'; // اتصال به دیتابیس

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

// Function to get monthly sales data for chart
function getMonthlySalesData($conn) {
    try {
        $data = [];
        for ($month = 1; $month <= 12; $month++) {
            $stmt = $conn->prepare("SELECT SUM(price) as total FROM products WHERE MONTH(sale_date) = ? AND YEAR(sale_date) = YEAR(CURDATE())");
            $stmt->execute([$month]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $data[] = $row['total'] ?? 0;
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
        <!-- فروش -->
        <div class="kpi-card">
          <i class="fas fa-shopping-cart kpi-icon"></i>
          <h3 class="body">فروش روزانه</h3>
          <p class="kpi-value"><?php echo number_format($dailySales) . " تومان"; ?></p>
          <p class="kpi-change <?php echo $dailySalesChange > 0 ? 'positive' : ($dailySalesChange < 0 ? 'negative' : 'zero'); ?>"><?php echo ($dailySalesChange > 0 ? '+' : '') . number_format($dailySalesChange, 1) . '%'; ?></p>
        </div>

        <div class="kpi-card">
          <i class="fas fa-shopping-cart kpi-icon"></i>
          <h3 class="body">فروش ماهانه</h3>
          <p class="kpi-value"><?php echo number_format($monthlySales) . " تومان"; ?></p>
          <p class="kpi-change <?php echo $monthlySalesChange > 0 ? 'positive' : ($monthlySalesChange < 0 ? 'negative' : 'zero'); ?>"><?php echo ($monthlySalesChange > 0 ? '+' : '') . number_format($monthlySalesChange, 1) . '%'; ?></p>
        </div>

        <div class="kpi-card">
          <i class="fas fa-shopping-cart kpi-icon"></i>
          <h3 class="body">فروش سالانه</h3>
          <p class="kpi-value"><?php echo number_format($yearlySales) . " تومان"; ?></p>
          <p class="kpi-change <?php echo $yearlySalesChange > 0 ? 'positive' : ($yearlySalesChange < 0 ? 'negative' : 'zero'); ?>"><?php echo ($yearlySalesChange > 0 ? '+' : '') . number_format($yearlySalesChange, 1) . '%'; ?></p>
        </div>

        <!-- پرداختی‌ها -->
        <div class="kpi-card">
          <i class="fas fa-credit-card kpi-icon"></i>
          <h3 class="body">پرداختی روزانه</h3>
          <p class="kpi-value"><?php echo number_format($dailyPayments) . " تومان"; ?></p>
          <p class="kpi-change <?php echo $dailyPaymentsChange > 0 ? 'positive' : ($dailyPaymentsChange < 0 ? 'negative' : 'zero'); ?>"><?php echo ($dailyPaymentsChange > 0 ? '+' : '') . number_format($dailyPaymentsChange, 1) . '%'; ?></p>
        </div>

        <div class="kpi-card">
          <i class="fas fa-credit-card kpi-icon"></i>
          <h3 class="body">پرداختی ماهانه</h3>
          <p class="kpi-value"><?php echo number_format($monthlyPayments) . " تومان"; ?></p>
          <p class="kpi-change <?php echo $monthlyPaymentsChange > 0 ? 'positive' : ($monthlyPaymentsChange < 0 ? 'negative' : 'zero'); ?>"><?php echo ($monthlyPaymentsChange > 0 ? '+' : '') . number_format($monthlyPaymentsChange, 1) . '%'; ?></p>
        </div>

        <div class="kpi-card">
          <i class="fas fa-credit-card kpi-icon"></i>
          <h3 class="body">پرداختی سالانه</h3>
          <p class="kpi-value"><?php echo number_format($yearlyPayments) . " تومان"; ?></p>
          <p class="kpi-change <?php echo $yearlyPaymentsChange > 0 ? 'positive' : ($yearlyPaymentsChange < 0 ? 'negative' : 'zero'); ?>"><?php echo ($yearlyPaymentsChange > 0 ? '+' : '') . number_format($yearlyPaymentsChange, 1) . '%'; ?></p>
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

</body>
</html>

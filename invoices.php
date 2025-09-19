<?php
include 'db.php'; // اتصال به دیتابیس
include 'jalali_calendar.php'; // توابع تبدیل تاریخ شمسی

function getMonthlyProducts($conn) {
    try {
        $stmt = $conn->prepare("
            SELECT 
                YEAR(sale_date) AS year, 
                MONTH(sale_date) AS month, 
                id, 
                name, 
                price, 
                sale_date 
            FROM products 
            ORDER BY sale_date DESC, id DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Monthly products query failed: ' . $e->getMessage());
        return [];
    }
}

$products = getMonthlyProducts($conn);

function groupByMonth($products) {
    $grouped = [];
    foreach ($products as $product) {
        $key = $product['year'] . '-' . str_pad($product['month'], 2, '0', STR_PAD_LEFT);
        if (!isset($grouped[$key])) {
            $grouped[$key] = [];
        }
        $grouped[$key][] = $product;
    }
    return $grouped;
}

$monthlyProducts = groupByMonth($products);

function gregorianToJalali($gy, $gm, $gd) {
    $g_d_m = array(0,31,59,90,120,151,181,212,243,273,304,334);
    if($gy > 1600){
        $jy=979;
        $gy-=1600;
    }else{
        $jy=0;
        $gy-=621;
    }
    $gy2=($gm>2)?($gy+1):$gy;
    $days=(365*$gy) + ((int)(($gy2+3)/4)) - ((int)(($gy2+99)/100)) + ((int)(($gy2+399)/400)) - 80 + $gd + $g_d_m[$gm-1];
    $jy+=33*((int)($days/12053));
    $days%=12053;
    $jy+=4*((int)($days/1461));
    $days%=1461;
    if($days > 365){
        $jy+=(int)(($days-1)/365);
        $days=($days-1)%365;
    }
    $jm=($days < 186)?1+(int)($days/31):7+(int)(($days-186)/30);
    $jd=1+(($days < 186)?($days%31):(($days-186)%30));
    return array($jy, $jm, $jd);
}

function getJalaliMonthName($month) {
    $months = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
    return $months[$month - 1];
}

function formatJalaliMonthYear($yearMonth) {
    list($year, $month) = explode('-', $yearMonth);
    $year = (int)$year;
    $month = (int)$month;
    list($jy, $jm, $jd) = gregorianToJalali($year, $month, 1);
    $jalaliMonthName = getJalaliMonthName($jm);
    return $jalaliMonthName . ' ' . $jy;
}
?>

<!DOCTYPE html>
<html lang="fa">
<head>
  <meta charset="UTF-8">
  <title><i class="fas fa-file-invoice"></i> فاکتورها</title>
  <link rel="stylesheet" href="css/design-system.css">
  <link rel="stylesheet" href="css/sidebar.css">
  <link rel="stylesheet" href="css/invoices.css">
</head>

<body class="dashboard-container">
  <aside class="sidebar">
    <?php include 'sidebar.php'; ?>
  </aside>

  <div class="main-content">
    <header class="top-bar">
      <h1 class="h1"><i class="fas fa-file-invoice"></i> فاکتورها</h1>
      <div>
        <button onclick="printTable()" class="btn-primary"><i class="fas fa-print"></i> پرینت همه فاکتورها</button>
      </div>
    </header>

    <div class="content-area">
      <?php foreach ($monthlyProducts as $month => $products): ?>
        <section class="invoice-month-block">
          <h2>فاکتور ماه: <?php echo htmlspecialchars(formatJalaliMonthYear($month)); ?></h2>
          <p>تعداد محصولات: <?php echo count($products); ?></p>
          <a href="invoice_month.php?month=<?php echo urlencode($month); ?>" class="btn-small">نمایش فاکتور</a>
        </section>
      <?php endforeach; ?>
    </div>
  </div>

  <script src="js/print.js"></script>

</body>
</html>

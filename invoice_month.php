<?php
include 'db.php';
include 'jalali_calendar.php'; // توابع تبدیل تاریخ شمسی

// Convert Gregorian date to Jalali string
function formatJalaliDate($gregorianDate) {
    list($gy, $gm, $gd) = explode('-', $gregorianDate);
    $jalali = gregorian_to_jalali($gy, $gm, $gd);
    return sprintf('%04d/%02d/%02d', $jalali[0], $jalali[1], $jalali[2]);
}

function getProductsByMonth($conn, $year, $month) {
    try {
        $stmt = $conn->prepare("
            SELECT id, name, price, sale_date 
            FROM products 
            WHERE YEAR(sale_date) = :year AND MONTH(sale_date) = :month
            ORDER BY sale_date DESC, id DESC
        ");
        $stmt->bindParam(':year', $year, PDO::PARAM_INT);
        $stmt->bindParam(':month', $month, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Products by month query failed: ' . $e->getMessage());
        return [];
    }
}

// Jalali conversion function
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

if (!isset($_GET['month'])) {
    die("پارامتر ماه مشخص نشده است.");
}

$monthParam = $_GET['month']; // format: YYYY-MM
if (!preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
    die("پارامتر ماه نامعتبر است.");
}

list($year, $month) = explode('-', $monthParam);
$year = (int)$year;
$month = (int)$month;

$products = getProductsByMonth($conn, $year, $month);

list($jy, $jm, $jd) = gregorianToJalali($year, $month, 1);
$jalaliMonthName = getJalaliMonthName($jm);
$jalaliMonthYear = $jalaliMonthName . ' ' . $jy;

$total = 0;
foreach ($products as $product) {
    $total += $product['price'];
}
?>

<!DOCTYPE html>
<html lang="fa">
<head>
  <meta charset="UTF-8">
  <title>فاکتور ماه <?php echo htmlspecialchars($jalaliMonthYear); ?></title>
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
      <h1 class="h1">فاکتور ماه <?php echo htmlspecialchars($jalaliMonthYear); ?></h1>
      <div>
        <button onclick="printInvoice()" class="btn-primary"><i class="fas fa-print"></i> پرینت فاکتور</button>
        <button id="downloadImageBtn" class="btn-primary" style="margin-right: 10px;"><i class="fas fa-camera"></i> دانلود عکس</button>
      </div>
    </header>
    <div class="content-area" id="invoiceContent">
      <table class="table invoice-table">
        <thead>
          <tr>
            <th>شماره فاکتور</th>
            <th>نام محصول</th>
            <th>قیمت (تومان)</th>
            <th>تاریخ</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($products as $product): ?>
          <tr>
            <td><?php echo htmlspecialchars($product['id']); ?></td>
            <td><?php echo htmlspecialchars($product['name']); ?></td>
            <td><?php echo number_format($product['price']); ?></td>
            <td><?php echo htmlspecialchars(formatJalaliDate($product['sale_date'])); ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="2"><strong>جمع کل ماه</strong></td>
            <td colspan="2"><strong><?php echo number_format($total); ?> تومان</strong></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
  <script>
    function printInvoice() {
      var printContents = document.getElementById('invoiceContent').innerHTML;
      var originalContents = document.body.innerHTML;
      document.body.innerHTML = printContents;
      window.print();
      document.body.innerHTML = originalContents;
      location.reload();
    }

    document.getElementById('downloadImageBtn').addEventListener('click', function() {
      var invoiceContent = document.getElementById('invoiceContent');
      html2canvas(invoiceContent).then(function(canvas) {
        var link = document.createElement('a');
        link.download = 'invoice-<?php echo htmlspecialchars($monthParam); ?>.png';
        link.href = canvas.toDataURL();
        link.click();
      });
    });
  </script>
</body>
</html>

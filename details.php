<?php
include 'db.php';
include 'jalali_calendar.php';

/** number_format امن (NULL -> 0) */
function nf($v) {
    return number_format((float)($v ?? 0));
}

/** تعداد روزهای یک ماه جلالی */
function jalali_days_in_month($jy, $jm) {
    if ($jm <= 6) return 31;
    if ($jm <= 11) return 30;
    // تشخیص 29/30 بودن اسفند
    $g = jalali_to_gregorian($jy, $jm, 30);
    list($gy,$gm,$gd) = $g;
    list($jjy,$jjm,$jjd) = gregorian_to_jalali($gy, $gm, $gd);
    return ($jjy == $jy && $jjm == $jm && $jjd == 30) ? 30 : 29;
}

$buyer_id = intval($_GET['buyer_id'] ?? 0);
if ($buyer_id <= 0) {
    die("خریدار نامعتبر است");
}

// اطلاعات خریدار
$stmt = $conn->prepare("SELECT * FROM buyers WHERE id=?");
$stmt->execute([$buyer_id]);
$buyer = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$buyer) {
    die("خریدار پیدا نشد");
}

// همه خریدهای این خریدار
$stmt = $conn->prepare("SELECT * FROM purchases WHERE buyer_id=? ORDER BY purchase_date ASC");
$stmt->execute([$buyer_id]);
$purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

// گروه‌بندی بر اساس ماه جلالی
$grouped = [];
foreach ($purchases as $p) {
    $isReturn = isset($p['is_return']) && intval($p['is_return']) === 1;

    list($gy,$gm,$gd) = explode('-', $p['purchase_date']);
    list($jy,$jm,$jd) = gregorian_to_jalali($gy, $gm, $gd);
    $monthKey = sprintf("%04d/%02d", $jy, $jm);

    if (!isset($grouped[$monthKey])) {
        $grouped[$monthKey] = [
            'products'    => [],
            'total_price' => 0.0, // خالص ماه (خرید - مرجوعی)
            'total_qty'   => 0
        ];
    }

    $grouped[$monthKey]['products'][] = $p;

    if ($isReturn) {
        $grouped[$monthKey]['total_price'] -= (float)$p['total_price'];
        $grouped[$monthKey]['total_qty']   -= (int)$p['quantity'];
    } else {
        $grouped[$monthKey]['total_price'] += (float)$p['total_price'];
        $grouped[$monthKey]['total_qty']   += (int)$p['quantity'];
    }
}

// آماده‌سازی داده‌های ماهانه
$monthlyData = [];
foreach ($grouped as $month => $data) {
    list($jy, $jm) = explode('/', $month);
    $daysInMonth = jalali_days_in_month($jy, $jm);
    $startOfMonthGregorian = jalali_to_gregorian($jy, $jm, 1);
    $endOfMonthGregorian   = jalali_to_gregorian($jy, $jm, $daysInMonth);

    $startDate = sprintf("%04d-%02d-%02d", $startOfMonthGregorian[0], $startOfMonthGregorian[1], $startOfMonthGregorian[2]);
    $endDate   = sprintf("%04d-%02d-%02d", $endOfMonthGregorian[0], $endOfMonthGregorian[1], $endOfMonthGregorian[2]);

    $stmt = $conn->prepare("SELECT SUM(amount) FROM payments WHERE buyer_id=? AND payment_date BETWEEN ? AND ?");
    $stmt->execute([$buyer_id, $startDate, $endDate]);
    $monthPayments = (float)($stmt->fetchColumn() ?: 0);

    $stmt = $conn->prepare("SELECT SUM(IF(is_return=1, -total_price, total_price)) FROM purchases WHERE buyer_id=? AND purchase_date <= ?");
    $stmt->execute([$buyer_id, $endDate]);
    $totalPurchases = (float)($stmt->fetchColumn() ?: 0);

    $stmt = $conn->prepare("SELECT SUM(amount) FROM payments WHERE buyer_id=? AND payment_date <= ?");
    $stmt->execute([$buyer_id, $endDate]);
    $totalPaymentsTillEnd = (float)($stmt->fetchColumn() ?: 0);

    $balance = $totalPurchases - $totalPaymentsTillEnd;

    $monthlyData[$month] = [
        'total_qty'     => (int)$data['total_qty'],
        'total_price'   => (float)$data['total_price'],
        'monthPayments' => $monthPayments,
        'balance'       => $balance,
        'products'      => $data['products']
    ];
}

$selectedMonth = $_GET['month'] ?? null;

$previousBalance    = 0.0;
$totalPayments      = 0.0;
$balance            = 0.0;
$paymentsThisMonth  = 0.0;

if ($selectedMonth) {
    list($jy, $jm) = explode('/', $selectedMonth);
    $daysInMonth = jalali_days_in_month($jy, $jm);

    $startOfMonthGregorian = jalali_to_gregorian($jy, $jm, 1);
    $endOfMonthGregorian   = jalali_to_gregorian($jy, $jm, $daysInMonth);
    $startOfMonthDate = sprintf("%04d-%02d-%02d", $startOfMonthGregorian[0], $startOfMonthGregorian[1], $startOfMonthGregorian[2]);
    $endOfMonthDate   = sprintf("%04d-%02d-%02d", $endOfMonthGregorian[0], $endOfMonthGregorian[1], $endOfMonthGregorian[2]);

    $stmt = $conn->prepare("SELECT SUM(amount) FROM payments WHERE buyer_id=? AND payment_date BETWEEN ? AND ?");
    $stmt->execute([$buyer_id, $startOfMonthDate, $endOfMonthDate]);
    $paymentsThisMonth = (float)($stmt->fetchColumn() ?: 0);

    $prevMonth = (int)$jm - 1;
    $prevYear  = (int)$jy;
    if ($prevMonth <= 0) {
        $prevMonth = 12;
        $prevYear--;
    }
    $prevDays = jalali_days_in_month($prevYear, $prevMonth);
    $endOfPrevMonthGregorian = jalali_to_gregorian($prevYear, $prevMonth, $prevDays);
    $endOfPrevMonthDate = sprintf("%04d-%02d-%02d", $endOfPrevMonthGregorian[0], $endOfPrevMonthGregorian[1], $endOfPrevMonthGregorian[2]);

    $stmt = $conn->prepare("SELECT SUM(IF(is_return=1, -total_price, total_price)) FROM purchases WHERE buyer_id=? AND purchase_date <= ?");
    $stmt->execute([$buyer_id, $endOfPrevMonthDate]);
    $purchasesPrev = (float)($stmt->fetchColumn() ?: 0);

    $stmt = $conn->prepare("SELECT SUM(amount) FROM payments WHERE buyer_id=? AND payment_date <= ?");
    $stmt->execute([$buyer_id, $endOfPrevMonthDate]);
    $paymentsPrev = (float)($stmt->fetchColumn() ?: 0);

    $previousBalance = $purchasesPrev - $paymentsPrev;

    $stmt = $conn->prepare("SELECT SUM(IF(is_return=1, -total_price, total_price)) FROM purchases WHERE buyer_id=? AND purchase_date <= ?");
    $stmt->execute([$buyer_id, $endOfMonthDate]);
    $totalPurchases = (float)($stmt->fetchColumn() ?: 0);

    $stmt = $conn->prepare("SELECT SUM(amount) FROM payments WHERE buyer_id=? AND payment_date <= ?");
    $stmt->execute([$buyer_id, $endOfMonthDate]);
    $totalPayments = (float)($stmt->fetchColumn() ?: 0);

    $balance = $totalPurchases - $totalPayments;

    if (!isset($monthlyData[$selectedMonth])) {
        $monthlyData[$selectedMonth] = [
            'total_qty'     => 0,
            'total_price'   => 0.0,
            'monthPayments' => $paymentsThisMonth,
            'balance'       => $balance,
            'products'      => []
        ];
    } else {
        $monthlyData[$selectedMonth]['monthPayments'] = $paymentsThisMonth;
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<title>جزئیات خرید - <?= htmlspecialchars($buyer['name']) ?></title>
<link rel="stylesheet" href="fonts/fonts.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="css/sidebar.css">
<link rel="stylesheet" href="css/design-system.css">
<link rel="stylesheet" href="css/details.css">
<style>
@media print {
  body * { visibility: hidden !important; }
  #invoice-area, #invoice-area * { visibility: visible !important; }
  #invoice-area { position:absolute; inset:0; width:100%; }
}
.calc-steps { background:#f9f9f9; padding:10px; border-radius:8px; margin-top:20px; }
.calc-steps li { margin:5px 0; }
</style>
</head>
<body class="dashboard-container">
<div class="details-main-content">

<?php if (!$selectedMonth): ?>
  <!-- لیست ماه‌ها -->
  <div class="details-monthly-section details-fade-in">
    <h2 class="details-section-title"><i class="fas fa-calendar-month"></i> خریدها بر اساس ماه</h2>
    <div class="table-responsive">
      <table class="details-monthly-table">
        <thead>
          <tr>
            <th>ماه</th>
            <th>تعداد محصولات</th>
            <th>مجموع مبلغ (خالص ماه)</th>
            <th>پرداختی همان ماه</th>
            <th>مانده پایان ماه (تجمیعی)</th>
            <th>عملیات</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($monthlyData as $month=>$info): ?>
          <tr>
            <td><strong><?= htmlspecialchars($month) ?></strong></td>
            <td><?= nf($info['total_qty']) ?></td>
            <td><?= nf($info['total_price']) ?> تومان</td>
            <td><?= nf($info['monthPayments']) ?> تومان</td>
            <td style="font-weight:bold; color:red;"><?= nf($info['balance']) ?> تومان</td>
            <td>
              <a href="details.php?buyer_id=<?= $buyer_id ?>&month=<?= urlencode($month) ?>" class="details-view-btn">
                <i class="fas fa-eye"></i> نمایش جزئیات
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php else: ?>
  <?php
  list($gy,$gm,$gd) = explode('-', date("Y-m-d"));
  list($jy,$jm,$jd) = gregorian_to_jalali($gy, $gm, $gd);
  $jalaliDate = sprintf("%04d/%02d/%02d", $jy, $jm, $jd);
  $invoiceNumber = $buyer_id . str_replace("/", "", $selectedMonth);

  $totalQty=0; $totalPrice=0.0; $totalReturnsQty=0; $totalReturnsPrice=0.0;
  foreach($monthlyData[$selectedMonth]['products'] as $p){
      $isReturn = isset($p['is_return']) && intval($p['is_return'])===1;
      if ($isReturn) {
          $totalReturnsQty   += (int)$p['quantity'];
          $totalReturnsPrice += (float)$p['total_price'];
      } else {
          $totalQty   += (int)$p['quantity'];
          $totalPrice += (float)$p['total_price'];
      }
  }
  $netMonth = $totalPrice - $totalReturnsPrice;
  $finalInvoiceAmount = $netMonth - $paymentsThisMonth;
  ?>
  
  <div id="invoice-area" class="details-fade-in">
    <h2 class="details-section-title" style="text-align:center;">🧾 فاکتور ماه <?= htmlspecialchars($selectedMonth) ?></h2>

    <!-- سربرگ -->
    <table class="details-products-table" style="margin-bottom:var(--space-md);">
      <tr style="background:#f7f7f7;">
        <td><strong>ماه:</strong> <?= htmlspecialchars($selectedMonth) ?></td>
        <td><strong>شماره فاکتور:</strong> <?= htmlspecialchars($invoiceNumber) ?></td>
      </tr>
      <tr>
        <td><strong>فروشنده:</strong> تولیدی الماس</td>
        <td><strong>تاریخ صدور:</strong> <?= $jalaliDate ?></td>
      </tr>
      <tr>
        <td colspan="2"><strong>خریدار:</strong> <?= htmlspecialchars($buyer['name']) ?></td>
      </tr>
    </table>

    <!-- محصولات -->
    <div class="table-responsive">
      <table class="details-products-table">
        <thead><tr><th>ردیف</th><th>نام محصول</th><th>قیمت فی</th><th>تعداد</th><th>جمع</th><th>نوع</th></tr></thead>
        <tbody>
        <?php $i=1; foreach($monthlyData[$selectedMonth]['products'] as $p): 
            $isReturn = isset($p['is_return']) && intval($p['is_return'])===1; ?>
          <tr style="<?= $isReturn ? 'color:red; font-weight:bold;' : '' ?>">
            <td><?= $i++ ?></td>
            <td><?= htmlspecialchars($p['product_name']) ?></td>
            <td><?= nf($p['unit_price']) ?> تومان</td>
            <td><?= nf($p['quantity']) ?></td>
            <td><?= nf($p['total_price']) ?> تومان</td>
            <td><?= $isReturn ? 'مرجوعی' : 'خرید' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- خلاصه حساب -->
    <h3 class="details-section-title" style="margin-top:var(--space-lg);">خلاصه حساب</h3>
    <table class="details-products-table">
      <tr><td>مبلغ کل خرید</td><td><?= nf($totalPrice) ?> تومان</td></tr>
      <tr><td>مبلغ کل مرجوعی</td><td><?= nf($totalReturnsPrice) ?> تومان</td></tr>
      <tr><td>خالص خرید ماه</td><td><?= nf($netMonth) ?> تومان</td></tr>
      <tr><td>پرداختی همان ماه</td><td><?= nf($paymentsThisMonth) ?> تومان</td></tr>
      <tr style="background:#ffe0e0;"><td>مبلغ نهایی فاکتور ماه</td><td><?= nf($finalInvoiceAmount) ?> تومان</td></tr>
      <tr><td>حساب قبلی</td><td><?= nf($previousBalance) ?> تومان</td></tr>
      <tr><td>مانده پایان ماه (تجمیعی)</td><td><?= nf($balance) ?> تومان</td></tr>
    </table>

    <!-- روند محاسبه -->
    <h3 class="details-section-title">🧮 روند محاسبه</h3>
    <div class="calc-steps">
      <ol>
        <li>مبلغ کل خرید ماه: <?= nf($totalPrice) ?> تومان</li>
        <li>منهای مرجوعی‌ها: <?= nf($totalReturnsPrice) ?> تومان</li>
        <li>= خالص خرید ماه: <?= nf($netMonth) ?> تومان</li>
        <li>منهای پرداختی همان ماه: <?= nf($paymentsThisMonth) ?> تومان</li>
        <li>= مبلغ نهایی فاکتور ماه: <?= nf($finalInvoiceAmount) ?> تومان</li>
        <li>اضافه می‌شود حساب قبلی: <?= nf($previousBalance) ?> تومان</li>
        <li>= مانده پایان ماه (تجمیعی): <?= nf($balance) ?> تومان</li>
      </ol>
    </div>
  </div>

  <div class="details-action-buttons" style="text-align:center; margin-top:var(--space-lg);">
    <button class="details-print-btn" onclick="window.print()"><i class="fas fa-print"></i> پرینت</button>
    <a href="details.php?buyer_id=<?= $buyer_id ?>" class="details-back-btn"><i class="fas fa-arrow-right"></i> بازگشت</a>
  </div>
<?php endif; ?>

</div>
</body>
</html>

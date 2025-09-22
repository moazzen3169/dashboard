<?php
include 'db.php';
include 'jalali_calendar.php';

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
    list($gy,$gm,$gd) = explode('-', $p['purchase_date']);
    list($jy,$jm,$jd) = gregorian_to_jalali($gy, $gm, $gd);
    $monthKey = sprintf("%04d/%02d", $jy, $jm); // مثل 1404/06
    if (!isset($grouped[$monthKey])) {
        $grouped[$monthKey] = [
            'products' => [],
            'total_price' => 0,
            'total_qty' => 0
        ];
    }
    $grouped[$monthKey]['products'][] = $p;
    $grouped[$monthKey]['total_price'] += $p['total_price'];
    $grouped[$monthKey]['total_qty']   += $p['quantity'];
}

// آماده‌سازی داده‌های ماهانه
$monthlyData = [];
foreach ($grouped as $month => $data) {
    list($jy, $jm) = explode('/', $month);

    $startOfMonthGregorian = jalali_to_gregorian($jy, $jm, 1);
    $endOfMonthGregorian   = jalali_to_gregorian($jy, $jm, 31);

    $startDate = sprintf("%04d-%02d-%02d", $startOfMonthGregorian[0], $startOfMonthGregorian[1], $startOfMonthGregorian[2]);
    $endDate   = sprintf("%04d-%02d-%02d", $endOfMonthGregorian[0], $endOfMonthGregorian[1], $endOfMonthGregorian[2]);

    // پرداختی‌های همان ماه
    $stmt = $conn->prepare("SELECT SUM(amount) FROM payments WHERE buyer_id=? AND payment_date BETWEEN ? AND ?");
    $stmt->execute([$buyer_id, $startDate, $endDate]);
    $monthPayments = $stmt->fetchColumn() ?? 0;

    // مانده حساب تا پایان این ماه
    $stmt = $conn->prepare("SELECT SUM(total_price) FROM purchases WHERE buyer_id=? AND purchase_date <= ?");
    $stmt->execute([$buyer_id, $endDate]);
    $totalPurchases = $stmt->fetchColumn() ?? 0;

    $stmt = $conn->prepare("SELECT SUM(amount) FROM payments WHERE buyer_id=? AND payment_date <= ?");
    $stmt->execute([$buyer_id, $endDate]);
    $totalPayments = $stmt->fetchColumn() ?? 0;

    $balance = $totalPurchases - $totalPayments;

    $monthlyData[$month] = [
        'total_qty'     => $data['total_qty'],
        'total_price'   => $data['total_price'],
        'monthPayments' => $monthPayments,
        'balance'       => $balance,
        'products'      => $data['products']
    ];
}

$selectedMonth = $_GET['month'] ?? null;

// متغیرهای خلاصه ماه
$previousBalance = 0;
$totalPayments = 0;
$balance = 0;

if ($selectedMonth) {
    list($jy, $jm) = explode('/', $selectedMonth);

    $endOfMonthGregorian = jalali_to_gregorian($jy, $jm, 31);
    $endOfMonthDate = sprintf("%04d-%02d-%02d", $endOfMonthGregorian[0], $endOfMonthGregorian[1], $endOfMonthGregorian[2]);

    $prevMonth = $jm - 1;
    $prevYear  = $jy;
    if ($prevMonth <= 0) {
        $prevMonth = 12;
        $prevYear--;
    }
    $endOfPrevMonthGregorian = jalali_to_gregorian($prevYear, $prevMonth, 31);
    $endOfPrevMonthDate = sprintf("%04d-%02d-%02d", $endOfPrevMonthGregorian[0], $endOfPrevMonthGregorian[1], $endOfPrevMonthGregorian[2]);

    // حساب قبلی (بدون این ماه)
    $stmt = $conn->prepare("SELECT SUM(total_price) FROM purchases WHERE buyer_id=? AND purchase_date <= ?");
    $stmt->execute([$buyer_id, $endOfPrevMonthDate]);
    $purchasesPrev = $stmt->fetchColumn() ?? 0;

    $stmt = $conn->prepare("SELECT SUM(amount) FROM payments WHERE buyer_id=? AND payment_date <= ?");
    $stmt->execute([$buyer_id, $endOfPrevMonthDate]);
    $paymentsPrev = $stmt->fetchColumn() ?? 0;

    $previousBalance = $purchasesPrev - $paymentsPrev;

    // خرید و پرداخت تا همین ماه
    $stmt = $conn->prepare("SELECT SUM(total_price) FROM purchases WHERE buyer_id=? AND purchase_date <= ?");
    $stmt->execute([$buyer_id, $endOfMonthDate]);
    $totalPurchases = $stmt->fetchColumn() ?? 0;

    $stmt = $conn->prepare("SELECT SUM(amount) FROM payments WHERE buyer_id=? AND payment_date <= ?");
    $stmt->execute([$buyer_id, $endOfMonthDate]);
    $totalPayments = $stmt->fetchColumn() ?? 0;

    $balance = $totalPurchases - $totalPayments;
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<style>
/* استایل مخصوص پرینت */
@media print {
  body * {
    visibility: hidden; /* همه المان‌ها مخفی میشن */
  }
  #invoice-area, #invoice-area * {
    visibility: visible; /* فقط فاکتور و محتوایش نمایش داده میشه */
    
  }
  #invoice-area {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
  }
}


#invoice-area {
    max-width:800px;
    margin:0 auto;
    padding:20px;
    background-color:#fff;
    box-shadow:var(--factor-shadow-lg);
    border-radius:10px;
}



</style>



    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>جزئیات خرید - <?= htmlspecialchars($buyer['name']) ?></title>
    <link rel="stylesheet" href="fonts/fonts.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/design-system.css">
    <link rel="stylesheet" href="css/details.css">
</head>

<body class="dashboard-container">
    <div class="details-main-content">
        <div class="details-header details-fade-in">
            <h1 class="details-title"><i class="fas fa-user-circle"></i> جزئیات خریدهای خریدار</h1>
            <div class="details-buyer-info">
                <h2 class="details-buyer-name"><?= htmlspecialchars($buyer['name']) ?></h2>
                <p class="body" style="color: var(--details-text-muted); margin-top: var(--space-xs);">مشاهده جزئیات و تاریخچه خریدها</p>
            </div>
        </div>

        <?php if (!$selectedMonth): ?>
            <!-- جدول خریدها بر اساس ماه -->
            <div class="details-monthly-section details-fade-in">
                <h2 class="details-section-title"><i class="fas fa-calendar-month"></i> خریدها بر اساس ماه</h2>
                <div class="table-responsive">
                    <table class="details-monthly-table">
                        <thead>
                            <tr>
                                <th>ماه</th>
                                <th>تعداد محصولات</th>
                                <th>مجموع مبلغ خرید</th>
                                <th>پرداختی همان ماه</th>
                                <th>مانده حساب نهایی</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($monthlyData as $month => $info): ?>
                            <tr>
                                <td><strong><?= $month ?></strong></td>
                                <td><?= number_format($info['total_qty']) ?></td>
                                <td><?= number_format($info['total_price']) ?> تومان</td>
                                <td><?= number_format($info['monthPayments']) ?> تومان</td>
                                <td style="font-weight:bold; color:red;"><?= number_format($info['balance']) ?> تومان</td>
                                <td>
                                    <a href="details.php?buyer_id=<?= $buyer_id ?>&month=<?= $month ?>" class="details-view-btn">
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
    // تاریخ صدور فاکتور (شمسی)
    $now = date("Y-m-d H:i:s");
    list($gy,$gm,$gd) = explode('-', date("Y-m-d"));
    list($jy,$jm,$jd) = gregorian_to_jalali($gy, $gm, $gd);
    $jalaliDate = sprintf("%04d/%02d/%02d", $jy, $jm, $jd);

    // شماره فاکتور (ساده: id خریدار + ماه انتخابی)
    $invoiceNumber = $buyer_id . str_replace("/", "", $selectedMonth);
    ?>

    <div id="invoice-area">
        <h2 style="text-align:center; margin:20px 0;">فاکتور خرید</h2>

        <!-- سربرگ فاکتور -->
        <table border="1" cellspacing="0" cellpadding="10" width="100%" style="border-collapse:collapse; font-size:14px;">
            <tr style="background:#f0f0f0;">
                <td><strong>فاتور ماه :</strong> <?= $selectedMonth ?></td>
                <td><strong>شماره فاکتور:</strong> <?= $invoiceNumber ?></td>
            </tr>
            <tr>
                <td><strong>فروشنده:</strong> تولیدی الماس</td>
                <td><strong>تاریخ صدور:</strong> <?= $jalaliDate ?></td>
            </tr>
            <tr>
                <td colspan="2"><strong>خریدار:</strong> <?= htmlspecialchars($buyer['name']) ?></td>
            </tr>
        </table>

        <br>

        <!-- جدول محصولات -->
        <table border="1" cellspacing="0" cellpadding="8" width="100%" style="border-collapse:collapse; text-align:center; font-size:14px;">
            <thead style="background:#e8e8e8;">
                <tr>
                    <th>ردیف</th>
                    <th>نام محصول</th>
                    <th>قیمت فی</th>
                    <th>تعداد</th>
                    <th>جمع</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $i = 1;
                $totalQty = 0;
                $totalPrice = 0;
                foreach($monthlyData[$selectedMonth]['products'] as $p):
                    $totalQty += $p['quantity'];
                    $totalPrice += $p['total_price'];
                ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><?= htmlspecialchars($p['product_name']) ?></td>
                    <td><?= number_format($p['unit_price']) ?> تومان</td>
                    <td><?= number_format($p['quantity']) ?></td>
                    <td><?= number_format($p['total_price']) ?> تومان</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <br>

        <!-- جمع‌بندی -->
        <h3 style="margin:15px 0;">خلاصه حساب</h3>
        <table border="1" cellspacing="0" cellpadding="8" width="100%" style="border-collapse:collapse; font-size:14px;">
            <tr>
                <td><strong>تعداد کل محصولات</strong></td>
                <td><?= number_format($totalQty) ?></td>
            </tr>
            <tr>
                <td><strong>مجموع مبلغ ماه</strong></td>
                <td><?= number_format($totalPrice) ?> تومان</td>
            </tr>
            <tr>
                <td><strong>حساب قبلی (بدون این ماه)</strong></td>
                <td><?= number_format($previousBalance) ?> تومان</td>
            </tr>
            <tr>
                <td><strong>پرداختی‌ها تا این ماه</strong></td>
                <td><?= number_format($totalPayments) ?> تومان</td>
            </tr>
            <tr style="background:#ffe0e0;">
                <td><strong>مانده حساب نهایی</strong></td>
                <td style="font-weight:bold; color:red;"><?= number_format($balance) ?> تومان</td>
            </tr>
        </table>
    </div>

    <br>

    <!-- دکمه‌ها -->
    <div style="text-align:center; margin-top:20px;">
        <button onclick="printInvoice()" style="padding:10px 20px; margin:5px;">🖨 پرینت فاکتور</button>
        <button onclick="saveInvoiceAsImage()" style="padding:10px 20px; margin:5px;">💾 ذخیره به عنوان عکس</button>
    </div>

    <!-- اسکریپت‌ها -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script>
        function printInvoice() {
            var printContents = document.getElementById("invoice-area").innerHTML;
            var newWin = window.open("");
            newWin.document.write("<html><head><title>پرینت فاکتور</title></head><body>" + printContents + "</body></html>");
            newWin.document.close();
            newWin.print();
        }

        function saveInvoiceAsImage() {
            html2canvas(document.getElementById("invoice-area")).then(canvas => {
                var link = document.createElement("a");
                link.download = "invoice.png";
                link.href = canvas.toDataURL();
                link.click();
            });
        }

    function printInvoice() {
    window.print();
}

    </script>
<?php endif; ?>

    </div>
</body>
</html>

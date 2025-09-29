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
        $grouped[$monthKey]['total_price'] -= (float)$p['unit_price'] * (int)$p['quantity'];
        $grouped[$monthKey]['total_qty']   -= (int)$p['quantity'];
    } else {
        $grouped[$monthKey]['total_price'] += (float)$p['unit_price'] * (int)$p['quantity'];
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

    $stmt = $conn->prepare("SELECT SUM(IF(is_return=1, -(unit_price * quantity), (unit_price * quantity))) FROM purchases WHERE buyer_id=? AND purchase_date <= ?");
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

    $stmt = $conn->prepare("SELECT SUM(IF(is_return=1, -(unit_price * quantity), (unit_price * quantity))) FROM purchases WHERE buyer_id=? AND purchase_date <= ?");
    $stmt->execute([$buyer_id, $endOfPrevMonthDate]);
    $purchasesPrev = (float)($stmt->fetchColumn() ?: 0);

    $stmt = $conn->prepare("SELECT SUM(amount) FROM payments WHERE buyer_id=? AND payment_date <= ?");
    $stmt->execute([$buyer_id, $endOfPrevMonthDate]);
    $paymentsPrev = (float)($stmt->fetchColumn() ?: 0);

    $previousBalance = $purchasesPrev - $paymentsPrev;

    $stmt = $conn->prepare("SELECT SUM(IF(is_return=1, -(unit_price * quantity), (unit_price * quantity))) FROM purchases WHERE buyer_id=? AND purchase_date <= ?");
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>جزئیات خرید - <?= htmlspecialchars($buyer['name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@100..900&display=swap');
        * { font-family: 'Vazirmatn', sans-serif; }
        
        @media print {
            body * { visibility: hidden !important; }
            #invoice-area, #invoice-area * { visibility: visible !important; }
            #invoice-area { 
                position: absolute !important; 
                inset: 0 !important; 
                width: 100% !important;
                background: white !important;
                margin: 0 !important;
                padding: 20px !important;
            }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-6 max-w-6xl">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6 no-print">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">
                        <i class="fas fa-user ml-2"></i>
                        جزئیات خرید - <?= htmlspecialchars($buyer['name']) ?>
                    </h1>
                    <p class="text-gray-600 mt-1">مدیریت و مشاهده جزئیات خریدهای مشتری</p>
                </div>
                <a href="factor-products.php" 
                   class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition flex items-center">
                    <i class="fas fa-arrow-left ml-2"></i>
                    بازگشت
                </a>
            </div>
        </div>

        <?php if (!$selectedMonth): ?>
            <!-- لیست ماه‌ها -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4 border-b pb-2">
                    <i class="fas fa-calendar-month ml-2"></i>
                    خریدها بر اساس ماه
                </h2>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-right">
                        <thead class="bg-gray-100 text-gray-700">
                            <tr>
                                <th class="p-3 font-medium">ماه</th>
                                <th class="p-3 font-medium">تعداد محصولات</th>
                                <th class="p-3 font-medium">مجموع مبلغ (خالص ماه)</th>
                                <th class="p-3 font-medium">پرداختی همان ماه</th>
                                <th class="p-3 font-medium">مانده پایان ماه</th>
                                <th class="p-3 font-medium">عملیات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach($monthlyData as $month => $info): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="p-3 font-medium"><?= htmlspecialchars($month) ?></td>
                                <td class="p-3"><?= nf($info['total_qty']) ?></td>
                                <td class="p-3"><?= nf($info['total_price']) ?> تومان</td>
                                <td class="p-3"><?= nf($info['monthPayments']) ?> تومان</td>
                                <td class="p-3 font-medium <?= $info['balance'] < 0 ? 'text-red-600' : 'text-green-600' ?>">
                                    <?= nf($info['balance']) ?> تومان
                                </td>
                                <td class="p-3">
                                    <a href="details.php?buyer_id=<?= $buyer_id ?>&month=<?= urlencode($month) ?>" 
                                       class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 transition text-sm flex items-center w-fit">
                                        <i class="fas fa-eye ml-1"></i>
                                        نمایش جزئیات
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

            $totalQty = 0; 
            $totalPrice = 0.0; 
            $totalReturnsQty = 0; 
            $totalReturnsPrice = 0.0;
            
            foreach($monthlyData[$selectedMonth]['products'] as $p){
                $isReturn = isset($p['is_return']) && intval($p['is_return']) === 1;
                if ($isReturn) {
                    $totalReturnsQty   += (int)$p['quantity'];
                    $totalReturnsPrice += (float)$p['unit_price'] * (int)$p['quantity'];
                } else {
                    $totalQty   += (int)$p['quantity'];
                    $totalPrice += (float)$p['unit_price'] * (int)$p['quantity'];
                }
            }
            $netMonth = $totalPrice - $totalReturnsPrice;
            $finalInvoiceAmount = $netMonth - $paymentsThisMonth;
            ?>
            
            <div id="invoice-area" class="bg-white rounded-lg shadow-md p-6 print:shadow-none print:border">
                <!-- Header Info -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6 border-b pb-4">
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="font-medium text-gray-600">ماه:</span>
                            <span class="font-bold"><?= htmlspecialchars($selectedMonth) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="font-medium text-gray-600">فروشنده:</span>
                            <span>تولیدی الماس</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="font-medium text-gray-600">خریدار:</span>
                            <span class="font-bold"><?= htmlspecialchars($buyer['name']) ?></span>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="font-medium text-gray-600">شماره فاکتور:</span>
                            <span class="font-mono"><?= htmlspecialchars($invoiceNumber) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="font-medium text-gray-600">تاریخ صدور:</span>
                            <span><?= $jalaliDate ?></span>
                        </div>
                    </div>
                </div>

                <!-- Products Table -->
                <div class="mb-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-3">لیست محصولات</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-right border-collapse">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="p-3 border font-medium">ردیف</th>
                                    <th class="p-3 border font-medium">نام محصول</th>
                                    <th class="p-3 border font-medium">قیمت فی</th>
                                    <th class="p-3 border font-medium">تعداد</th>
                                    <th class="p-3 border font-medium">جمع</th>
                                    <th class="p-3 border font-medium">نوع</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i=1; foreach($monthlyData[$selectedMonth]['products'] as $p): 
                                    $isReturn = isset($p['is_return']) && intval($p['is_return']) === 1; 
                                    $itemTotal = (float)$p['unit_price'] * (int)$p['quantity'];
                                ?>
                                <tr class="<?= $isReturn ? 'bg-red-50 text-red-700 font-medium' : 'hover:bg-gray-50' ?>">
                                    <td class="p-3 border"><?= $i++ ?></td>
                                    <td class="p-3 border"><?= htmlspecialchars($p['product_name']) ?></td>
                                    <td class="p-3 border"><?= nf($p['unit_price']) ?> تومان</td>
                                    <td class="p-3 border"><?= nf($p['quantity']) ?></td>
                                    <td class="p-3 border"><?= nf($itemTotal) ?> تومان</td>
                                    <td class="p-3 border">
                                        <span class="px-2 py-1 rounded-full text-xs <?= $isReturn ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' ?>">
                                            <?= $isReturn ? 'مرجوعی' : 'خرید' ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Summary -->
                <div class="bg-gray-50 rounded-lg p-4 border">
                    <h3 class="text-lg font-bold text-gray-800 mb-3">خلاصه حساب</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-gray-600">مبلغ کل خرید:</span>
                                <span class="font-medium"><?= nf($totalPrice) ?> تومان</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">مبلغ کل مرجوعی:</span>
                                <span class="font-medium text-red-600"><?= nf($totalReturnsPrice) ?> تومان</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">خالص خرید ماه:</span>
                                <span class="font-medium text-blue-600"><?= nf($netMonth) ?> تومان</span>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-gray-600">پرداختی همان ماه:</span>
                                <span class="font-medium text-green-600"><?= nf($paymentsThisMonth) ?> تومان</span>
                            </div>
                            <div class="flex justify-between border-t pt-2">
                                <span class="text-gray-600">حساب قبلی:</span>
                                <span class="font-medium"><?= nf($previousBalance) ?> تومان</span>
                            </div>
                            <div class="flex justify-between border-t pt-2">
                                <span class="text-gray-600">مانده پایان ماه:</span>
                                <span class="font-medium <?= $balance < 0 ? 'text-red-600' : 'text-green-600' ?>">
                                    <?= nf($balance) ?> تومان
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Final Invoice Amount -->
                    <div class="mt-4 pt-4 border-t">
                        <div class="flex justify-between items-center bg-red-50 p-3 rounded-lg">
                            <span class="text-lg font-bold text-red-800">مبلغ نهایی فاکتور ماه:</span>
                            <span class="text-xl font-bold text-red-600"><?= nf($finalInvoiceAmount) ?> تومان</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex gap-3 justify-center mt-6 no-print">
                <button onclick="window.print()" 
                        class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition flex items-center">
                    <i class="fas fa-print ml-2"></i>
                    پرینت فاکتور
                </button>
                <a href="details.php?buyer_id=<?= $buyer_id ?>" 
                   class="bg-gray-600 text-white px-6 py-2 rounded-lg hover:bg-gray-700 transition flex items-center">
                    <i class="fas fa-arrow-right ml-2"></i>
                    بازگشت به لیست
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
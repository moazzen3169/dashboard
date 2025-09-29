<?php
// گزارش فروش بهینه‌شده
include 'db.php';
include 'jalali_calendar.php';

class SalesReport {
    private $conn;
    private $filters;
    private $params;

    public function __construct($conn) {
        $this->conn = $conn;
        $this->filters = [];
        $this->params = [];
    }

    public function validateAndConvertDate($date) {
        if (empty($date)) return null;
        
        if (preg_match('/^(\d{4})\/(\d{2})\/(\d{2})$/', $date, $matches)) {
            list($jy, $jm, $jd) = array_slice($matches, 1);
            if (!checkdate($jm, $jd, $jy)) {
                throw new InvalidArgumentException("تاریخ شمسی نامعتبر: $date");
            }
            list($gy, $gm, $gd) = jalali_to_gregorian($jy, $jm, $jd);
            return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
        }
        
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date; // قبلاً میلادی است
        }
        
        throw new InvalidArgumentException("فرمت تاریخ نامعتبر: $date");
    }

    public function applyFilters() {
        $from = $_GET['from'] ?? '';
        $to = $_GET['to'] ?? '';
        $buyer_id = $_GET['buyer_id'] ?? '';
        $product = $_GET['product'] ?? '';

        try {
            // فیلتر تاریخ
            if ($from && $to) {
                $fromGreg = $this->validateAndConvertDate($from);
                $toGreg = $this->validateAndConvertDate($to);
                
                if ($fromGreg && $toGreg) {
                    $this->filters[] = "purchase_date BETWEEN ? AND ?";
                    $this->params[] = $fromGreg;
                    $this->params[] = $toGreg;
                }
            }

            // فیلتر مشتری
            if ($buyer_id && is_numeric($buyer_id)) {
                $this->filters[] = "buyer_id = ?";
                $this->params[] = intval($buyer_id);
            }

            // فیلتر محصول (امن)
            if ($product) {
                $allowed_products = $this->getAllowedProducts();
                if (in_array($product, $allowed_products)) {
                    $this->filters[] = "product_name = ?";
                    $this->params[] = $product;
                }
            }
        } catch (Exception $e) {
            error_log("خطا در اعمال فیلترها: " . $e->getMessage());
        }
    }

    private function getAllowedProducts() {
        $stmt = $this->conn->prepare("SELECT DISTINCT product_name FROM purchases");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getWhereClause() {
        return $this->filters ? "WHERE " . implode(" AND ", $this->filters) : "";
    }

    public function getParams() {
        return $this->params;
    }

    public function getFilterData() {
        return [
            'where' => $this->getWhereClause(),
            'params' => $this->params
        ];
    }

    public function getDailyReport() {
        $whereSQL = $this->getWhereClause();

        $stmt = $this->conn->prepare("
            SELECT
                purchase_date,
                SUM(IF(is_return=1, -quantity, quantity)) as total_qty,
                SUM(IF(is_return=1, -total_price, total_price)) as total_price
            FROM purchases
            $whereSQL
            GROUP BY purchase_date
            ORDER BY purchase_date DESC
            LIMIT 30
        ");

        $stmt->execute($this->params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMonthlyReport() {
        $whereSQL = $this->getWhereClause();

        $stmt = $this->conn->prepare("
            SELECT
                YEAR(purchase_date) as year,
                MONTH(purchase_date) as month,
                SUM(IF(is_return=1, -quantity, quantity)) as total_qty,
                SUM(IF(is_return=1, -total_price, total_price)) as total_price
            FROM purchases
            $whereSQL
            GROUP BY YEAR(purchase_date), MONTH(purchase_date)
            ORDER BY year DESC, month DESC
        ");

        $stmt->execute($this->params);
        $monthlyData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($monthlyData as $row) {
            list($jy, $jm, $jd) = gregorian_to_jalali($row['year'], $row['month'], 1);
            $monthKey = sprintf("%04d/%02d", $jy, $jm);

            $result[$monthKey] = [
                'qty' => $row['total_qty'],
                'price' => $row['total_price'],
                'payments' => $this->getMonthlyPayments($jy, $jm),
                'balance' => $this->getMonthlyBalance($row['year'], $row['month']),
                'percentPaid' => 0
            ];

            if ($row['total_price'] > 0) {
                $result[$monthKey]['percentPaid'] = round(
                    ($result[$monthKey]['payments'] / $row['total_price']) * 100, 1
                );
            }

            $topProduct = $this->getTopProductForMonth($jy, $jm);
            $result[$monthKey]['topProduct'] = $topProduct;
            $result[$monthKey]['topCustomer'] = $this->getTopCustomerForProductInMonth($jy, $jm, $topProduct);
        }

        return $result;
    }

    private function getMonthlyPayments($jYear, $jMonth) {
        $start = jalali_to_gregorian($jYear, $jMonth, 1);
        $end = jalali_to_gregorian($jYear, $jMonth, 31);
        $startDate = sprintf("%04d-%02d-%02d", $start[0], $start[1], $start[2]);
        $endDate = sprintf("%04d-%02d-%02d", $end[0], $end[1], $end[2]);

        $stmt = $this->conn->prepare("
            SELECT COALESCE(SUM(amount), 0) 
            FROM payments 
            WHERE payment_date BETWEEN ? AND ?
        ");
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchColumn();
    }

    private function getMonthlyBalance($year, $month) {
        $endDate = sprintf("%04d-%02d-%02d", $year, $month, 31);

        $stmt = $this->conn->prepare("
            SELECT
                (SELECT COALESCE(SUM(IF(is_return=1, -total_price, total_price)), 0) FROM purchases WHERE purchase_date <= ?) -
                (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_date <= ?) as balance
        ");
        $stmt->execute([$endDate, $endDate]);
        return $stmt->fetchColumn();
    }

    private function getTopProductForMonth($jYear, $jMonth) {
        $start = jalali_to_gregorian($jYear, $jMonth, 1);
        $end = jalali_to_gregorian($jYear, $jMonth, 31);
        $startDate = sprintf("%04d-%02d-%02d", $start[0], $start[1], $start[2]);
        $endDate = sprintf("%04d-%02d-%02d", $end[0], $end[1], $end[2]);

        $stmt = $this->conn->prepare("
            SELECT product_name
            FROM purchases
            WHERE purchase_date BETWEEN ? AND ?
            GROUP BY product_name
            ORDER BY SUM(IF(is_return=1, -quantity, quantity)) DESC
            LIMIT 1
        ");
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchColumn() ?: '-';
    }

    private function getTopCustomerForProductInMonth($jYear, $jMonth, $product) {
        if ($product == '-') return '-';

        $start = jalali_to_gregorian($jYear, $jMonth, 1);
        $end = jalali_to_gregorian($jYear, $jMonth, 31);
        $startDate = sprintf("%04d-%02d-%02d", $start[0], $start[1], $start[2]);
        $endDate = sprintf("%04d-%02d-%02d", $end[0], $end[1], $end[2]);

        $stmt = $this->conn->prepare("
            SELECT b.name
            FROM purchases p
            JOIN buyers b ON p.buyer_id = b.id
            WHERE p.purchase_date BETWEEN ? AND ? AND p.product_name = ?
            GROUP BY b.id, b.name
            ORDER BY SUM(IF(p.is_return=1, -p.quantity, p.quantity)) DESC
            LIMIT 1
        ");
        $stmt->execute([$startDate, $endDate, $product]);
        return $stmt->fetchColumn() ?: '-';
    }

    public function getOverallStats($purchases) {
        $totalPurchases = array_sum(array_column($purchases, 'quantity'));
        $totalRevenue = array_sum(array_column($purchases, 'net_total_price'));
        $dailyReport = $this->getDailyReport();
        $uniqueDays = count($dailyReport);
        $averageDaily = $uniqueDays > 0 ? round($totalPurchases / $uniqueDays, 1) : 0;

        return [
            'totalPurchases' => $totalPurchases,
            'totalRevenue' => $totalRevenue,
            'uniqueDays' => $uniqueDays,
            'averageDaily' => $averageDaily
        ];
    }

    public function getBests() {
        $whereSQL = $this->getWhereClause();
        $whereSQL .= $whereSQL ? " AND is_return = 0" : " WHERE is_return = 0";

        $stmt = $this->conn->prepare("
            SELECT product_name, SUM(quantity) as q
            FROM purchases
            $whereSQL
            GROUP BY product_name
            ORDER BY q DESC
            LIMIT 1
        ");
        $stmt->execute($this->params);
        $bestProduct = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $this->conn->prepare("
            SELECT b.name, SUM(p.total_price) as s
            FROM purchases p
            JOIN buyers b ON p.buyer_id = b.id
            $whereSQL
            GROUP BY b.id, b.name
            ORDER BY s DESC
            LIMIT 1
        ");
        $stmt->execute($this->params);
        $bestBuyer = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'product' => $bestProduct,
            'buyer' => $bestBuyer
        ];
    }

    public function getAllPurchases() {
        $whereSQL = $this->getWhereClause();
        $stmt = $this->conn->prepare("SELECT *, IF(is_return=1, -total_price, total_price) as net_total_price FROM purchases $whereSQL");
        $stmt->execute($this->params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUserPayments() {
        $stmt = $this->conn->prepare("
            SELECT
                b.name as buyer_name,
                SUM(p.amount) as total_payments,
                COUNT(p.id) as payment_count
            FROM payments p
            JOIN buyers b ON p.buyer_id = b.id
            GROUP BY b.id, b.name
            ORDER BY total_payments DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFilteredTotalPayments() {
        $paymentFilters = [];
        $paymentParams = [];

        $from = $_GET['from'] ?? '';
        $to = $_GET['to'] ?? '';
        $buyer_id = $_GET['buyer_id'] ?? '';

        try {
            if ($from && $to) {
                $fromGreg = $this->validateAndConvertDate($from);
                $toGreg = $this->validateAndConvertDate($to);

                if ($fromGreg && $toGreg) {
                    $paymentFilters[] = "payment_date BETWEEN ? AND ?";
                    $paymentParams[] = $fromGreg;
                    $paymentParams[] = $toGreg;
                }
            }

            if ($buyer_id && is_numeric($buyer_id)) {
                $paymentFilters[] = "buyer_id = ?";
                $paymentParams[] = intval($buyer_id);
            }
        } catch (Exception $e) {
            error_log("خطا در اعمال فیلترهای پرداختی: " . $e->getMessage());
        }

        $whereSQL = $paymentFilters ? "WHERE " . implode(" AND ", $paymentFilters) : "";
        $stmt = $this->conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments $whereSQL");
        $stmt->execute($paymentParams);
        return $stmt->fetchColumn();
    }
}

// اجرای اصلی
try {
    $report = new SalesReport($conn);
    $report->applyFilters();
    
    $dailyReport = $report->getDailyReport();
    $monthlyReport = $report->getMonthlyReport();
    $allPurchases = $report->getAllPurchases();
    
    $overallStats = $report->getOverallStats($allPurchases);
    $bests = $report->getBests();
    $userPayments = $report->getUserPayments();
    $totalPayments = $report->getFilteredTotalPayments();

} catch (Exception $e) {
    error_log("خطا در تولید گزارش: " . $e->getMessage());
    die("خطا در تولید گزارش. لطفاً با پشتیبانی تماس بگیرید.");
}

// ذخیره پارامترهای فیلتر برای فرم
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$buyer_id = $_GET['buyer_id'] ?? '';
$product = $_GET['product'] ?? '';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>گزارش‌های فروش - تولیدی الماس</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@100..900&display=swap');
        * { font-family: 'Vazirmatn', sans-serif; }
        
        /* Print styles */
        @media print {
            .no-print { display: none !important; }
            body { 
                background: white !important;
                font-size: 12pt;
            }
            .print-break { page-break-inside: avoid; }
            .print-full { width: 100% !important; }
        }
        
        /* Custom scrollbar */
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 10px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
        
        /* Progress bar animation */
        .progress-bar {
            transition: width 0.6s ease;
        }
        
        /* Chart container responsive */
        .chart-container {
            position: relative;
            height: 400px;
            width: 100%;
        }
        
        @media (max-width: 768px) {
            .chart-container {
                height: 300px;
            }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="flex w-full">
        <!-- Sidebar -->
        <aside class="w-64 bg-blue-900 text-white min-h-screen flex-shrink-0">
            <?php include 'sidebar.php'; ?>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 w-full min-w-0 p-6">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-800 mb-2">
                    <i class="fas fa-chart-bar ml-2 text-blue-600"></i>
                    گزارش‌های جامع فروش
                </h1>
                <p class="text-gray-600">تحلیل و بررسی عملکرد فروش و مالی</p>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8 print-break">
                <div class="bg-white rounded-xl shadow-lg p-6 border-r-4 border-blue-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm mb-1">کل فروش</p>
                            <h3 class="text-2xl font-bold text-gray-800"><?= number_format($overallStats['totalPurchases']) ?></h3>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-shopping-cart text-blue-600 text-xl"></i>
                        </div>
                    </div>
                    <p class="text-green-600 text-sm mt-2">
                        <i class="fas fa-arrow-up ml-1"></i>
                        میانگین روزانه: <?= number_format($overallStats['averageDaily']) ?>
                    </p>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6 border-r-4 border-green-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm mb-1">درآمد کل</p>
                            <h3 class="text-2xl font-bold text-gray-800"><?= number_format($overallStats['totalRevenue']) ?> ریال</h3>
                        </div>
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-money-bill-wave text-green-600 text-xl"></i>
                        </div>
                    </div>
                    <p class="text-blue-600 text-sm mt-2">
                        <i class="fas fa-calendar ml-1"></i>
                        <?= $overallStats['uniqueDays'] ?> روز فعال
                    </p>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6 border-r-4 border-purple-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm mb-1">پرداختی‌ها</p>
                            <h3 class="text-2xl font-bold text-gray-800"><?= number_format($totalPayments) ?> ریال</h3>
                        </div>
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-credit-card text-purple-600 text-xl"></i>
                        </div>
                    </div>
                    <p class="text-gray-600 text-sm mt-2">
                        <i class="fas fa-users ml-1"></i>
                        <?= count($userPayments) ?> مشتری
                    </p>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6 border-r-4 border-orange-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm mb-1">موجودی</p>
                            <h3 class="text-2xl font-bold text-gray-800">
                                <?= number_format($overallStats['totalRevenue'] - $totalPayments) ?> ریال
                            </h3>
                        </div>
                        <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-wallet text-orange-600 text-xl"></i>
                        </div>
                    </div>
                    <p class="text-gray-600 text-sm mt-2">
                        <i class="fas fa-chart-line ml-1"></i>
                        خالص فروش
                    </p>
                </div>
            </div>

            <!-- Best Performers -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8 print-break">
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-crown text-yellow-500 ml-2"></i>
                        پرفروش‌ترین محصول
                    </h3>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-2xl font-bold text-gray-800">
                                <?= htmlspecialchars($bests['product']['product_name'] ?? '---') ?>
                            </p>
                            <p class="text-gray-600 text-sm mt-1">
                                <?= number_format($bests['product']['q'] ?? 0) ?> عدد فروش
                            </p>
                        </div>
                        <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-star text-yellow-600 text-2xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-trophy text-blue-500 ml-2"></i>
                        بهترین مشتری
                    </h3>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xl font-bold text-gray-800">
                                <?= htmlspecialchars($bests['buyer']['name'] ?? '---') ?>
                            </p>
                            <p class="text-gray-600 text-sm mt-1">
                                <?= number_format($bests['buyer']['s'] ?? 0) ?> ریال خرید
                            </p>
                        </div>
                        <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-user text-blue-600 text-2xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Form -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8 no-print">
                <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-filter text-gray-600 ml-2"></i>
                    فیلترهای پیشرفته
                </h3>
                <form method="get" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">از تاریخ</label>
                        <input type="text" name="from" value="<?= htmlspecialchars($from) ?>" 
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="1403/01/01">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">تا تاریخ</label>
                        <input type="text" name="to" value="<?= htmlspecialchars($to) ?>" 
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="1403/12/29">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">مشتری</label>
                        <select name="buyer_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">همه مشتری‌ها</option>
                            <?php 
                            $buyers = $conn->query("SELECT id, name FROM buyers ORDER BY name");
                            foreach($buyers as $b): 
                            ?>
                                <option value="<?= $b['id'] ?>" <?= $buyer_id == $b['id'] ? "selected" : "" ?>>
                                    <?= htmlspecialchars($b['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">محصول</label>
                        <select name="product" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">همه محصولات</option>
                            <?php 
                            $products = $conn->query("SELECT DISTINCT product_name FROM purchases ORDER BY product_name");
                            foreach($products as $pr): 
                            ?>
                                <option value="<?= htmlspecialchars($pr['product_name']) ?>" 
                                    <?= $product == $pr['product_name'] ? "selected" : "" ?>>
                                    <?= htmlspecialchars($pr['product_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="md:col-span-2 lg:col-span-4 flex gap-3 justify-end">
                        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition flex items-center">
                            <i class="fas fa-filter ml-2"></i>
                            اعمال فیلتر
                        </button>
                        <button type="button" onclick="clearFilters()" class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600 transition flex items-center">
                            <i class="fas fa-times ml-2"></i>
                            پاک کردن
                        </button>
                    </div>
                </form>
            </div>

            <!-- Monthly Report -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8 print-break print-full">
                <h3 class="text-lg font-bold text-gray-800 mb-6 flex items-center">
                    <i class="fas fa-calendar-alt text-green-600 ml-2"></i>
                    گزارش ماهانه فروش
                </h3>
                <div class="overflow-x-auto custom-scrollbar">
                    <table class="w-full text-sm text-right">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="p-4 font-medium text-gray-700 border-b">ماه</th>
                                <th class="p-4 font-medium text-gray-700 border-b">تعداد فروش</th>
                                <th class="p-4 font-medium text-gray-700 border-b">مبلغ کل</th>
                                <th class="p-4 font-medium text-gray-700 border-b">پرداختی‌ها</th>
                                <th class="p-4 font-medium text-gray-700 border-b">مانده حساب</th>
                                <th class="p-4 font-medium text-gray-700 border-b">درصد تسویه</th>
                                <th class="p-4 font-medium text-gray-700 border-b">محصول برتر</th>
                                <th class="p-4 font-medium text-gray-700 border-b">مشتری برتر</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if (empty($monthlyReport)): ?>
                                <tr>
                                    <td colspan="8" class="p-8 text-center text-gray-500">
                                        <i class="fas fa-inbox text-4xl mb-4 block"></i>
                                        هیچ داده‌ای برای نمایش وجود ندارد
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($monthlyReport as $month => $info): ?>
                                    <tr class="hover:bg-gray-50 transition <?= $info['qty'] < 20 ? 'bg-yellow-50' : '' ?>">
                                        <td class="p-4 font-medium"><?= $month ?></td>
                                        <td class="p-4"><?= number_format($info['qty']) ?></td>
                                        <td class="p-4 font-medium"><?= number_format($info['price']) ?> ریال</td>
                                        <td class="p-4 text-green-600"><?= number_format($info['payments']) ?> ریال</td>
                                        <td class="p-4 font-medium <?= $info['balance'] > 0 ? 'text-red-600' : 'text-green-600' ?>">
                                            <?= number_format($info['balance']) ?> ریال
                                        </td>
                                        <td class="p-4">
                                            <div class="flex items-center gap-3">
                                                <div class="flex-1 bg-gray-200 rounded-full h-2">
                                                    <div class="progress-bar h-2 rounded-full <?= $info['percentPaid'] >= 80 ? 'bg-green-500' : ($info['percentPaid'] >= 50 ? 'bg-yellow-500' : 'bg-red-500') ?>" 
                                                         style="width: <?= min($info['percentPaid'], 100) ?>%"></div>
                                                </div>
                                                <span class="text-sm font-medium w-12"><?= $info['percentPaid'] ?>%</span>
                                            </div>
                                        </td>
                                        <td class="p-4">
                                            <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-xs font-medium">
                                                <?= htmlspecialchars($info['topProduct']) ?>
                                            </span>
                                        </td>
                                        <td class="p-4">
                                            <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs font-medium">
                                                <?= htmlspecialchars($info['topCustomer']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- User Payments Report -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8 print-break print-full">
                <h3 class="text-lg font-bold text-gray-800 mb-6 flex items-center">
                    <i class="fas fa-money-bill-wave text-purple-600 ml-2"></i>
                    گزارش پرداختی‌های مشتریان
                </h3>
                <div class="overflow-x-auto custom-scrollbar">
                    <table class="w-full text-sm text-right">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="p-4 font-medium text-gray-700 border-b">نام مشتری</th>
                                <th class="p-4 font-medium text-gray-700 border-b">مجموع پرداختی‌ها</th>
                                <th class="p-4 font-medium text-gray-700 border-b">تعداد پرداختی‌ها</th>
                                <th class="p-4 font-medium text-gray-700 border-b">میانگین هر پرداخت</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if (empty($userPayments)): ?>
                                <tr>
                                    <td colspan="4" class="p-8 text-center text-gray-500">
                                        <i class="fas fa-inbox text-4xl mb-4 block"></i>
                                        هیچ داده‌ای برای نمایش وجود ندارد
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($userPayments as $payment): ?>
                                    <tr class="hover:bg-gray-50 transition">
                                        <td class="p-4 font-medium"><?= htmlspecialchars($payment['buyer_name']) ?></td>
                                        <td class="p-4 text-green-600 font-medium"><?= number_format($payment['total_payments']) ?> ریال</td>
                                        <td class="p-4"><?= number_format($payment['payment_count']) ?></td>
                                        <td class="p-4 text-blue-600">
                                            <?= number_format($payment['total_payments'] / max($payment['payment_count'], 1)) ?> ریال
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Charts -->
            <div class="no-print">
                <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                    <h3 class="text-lg font-bold text-gray-800 mb-6 flex items-center">
                        <i class="fas fa-chart-line text-red-600 ml-2"></i>
                        نمودار تحلیلی فروش
                    </h3>
                    <div class="chart-container">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex gap-4 justify-center no-print">
                <button onclick="downloadCSV()" class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition flex items-center">
                    <i class="fas fa-download ml-2"></i>
                    دانلود گزارش CSV
                </button>
                <button onclick="window.print()" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition flex items-center">
                    <i class="fas fa-print ml-2"></i>
                    چاپ گزارش
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // نمودار فروش
        const monthlyData = <?= json_encode($monthlyReport, JSON_UNESCAPED_UNICODE) ?>;
        const months = Object.keys(monthlyData);
        const quantities = months.map(m => monthlyData[m].qty);
        const revenues = months.map(m => monthlyData[m].price);

        const ctx = document.getElementById('salesChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: months,
                datasets: [
                    {
                        label: 'تعداد فروش',
                        data: quantities,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'درآمد (ریال)',
                        data: revenues,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'ماه',
                            color: '#6b7280',
                            font: {
                                family: 'Vazirmatn',
                                size: 12
                            }
                        },
                        ticks: {
                            color: '#6b7280',
                            font: {
                                family: 'Vazirmatn'
                            }
                        }
                    },
                    y: {
                        title: {
                            display: true,
                            text: 'تعداد فروش',
                            color: '#6b7280',
                            font: {
                                family: 'Vazirmatn',
                                size: 12
                            }
                        },
                        ticks: {
                            color: '#6b7280',
                            font: {
                                family: 'Vazirmatn'
                            }
                        }
                    },
                    y1: {
                        position: 'right',
                        title: {
                            display: true,
                            text: 'درآمد (ریال)',
                            color: '#6b7280',
                            font: {
                                family: 'Vazirmatn',
                                size: 12
                            }
                        },
                        ticks: {
                            color: '#6b7280',
                            font: {
                                family: 'Vazirmatn'
                            },
                            callback: function(value) {
                                return new Intl.NumberFormat('fa-IR').format(value);
                            }
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                        rtl: true,
                        labels: {
                            font: {
                                family: 'Vazirmatn'
                            },
                            color: '#374151'
                        }
                    },
                    tooltip: {
                        rtl: true,
                        bodyFont: {
                            family: 'Vazirmatn'
                        },
                        titleFont: {
                            family: 'Vazirmatn'
                        },
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.dataset.yAxisID === 'y1') {
                                    label += new Intl.NumberFormat('fa-IR').format(context.parsed.y) + ' ریال';
                                } else {
                                    label += new Intl.NumberFormat('fa-IR').format(context.parsed.y);
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });

        // دانلود CSV
        function downloadCSV() {
            const rows = [
                ['ماه', 'تعداد فروش', 'مبلغ کل (ریال)', 'پرداختی‌ها', 'مانده حساب', 'درصد تسویه', 'محصول برتر', 'مشتری برتر']
            ];

            <?php foreach($monthlyReport as $month => $info): ?>
                rows.push([
                    "<?= $month ?>",
                    "<?= $info['qty'] ?>",
                    "<?= $info['price'] ?>",
                    "<?= $info['payments'] ?>",
                    "<?= $info['balance'] ?>",
                    "<?= $info['percentPaid'] ?>",
                    "<?= htmlspecialchars($info['topProduct']) ?>",
                    "<?= htmlspecialchars($info['topCustomer']) ?>"
                ]);
            <?php endforeach; ?>

            const csv = rows.map(row => row.join(",")).join("\n");
            const link = document.createElement("a");
            link.href = "data:text/csv;charset=utf-8,\uFEFF" + encodeURIComponent(csv);
            link.download = "sales_report_<?= date('Y-m-d') ?>.csv";
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // پاک کردن فیلترها
        function clearFilters() {
            document.querySelector('input[name="from"]').value = '';
            document.querySelector('input[name="to"]').value = '';
            document.querySelector('select[name="buyer_id"]').value = '';
            document.querySelector('select[name="product"]').value = '';
            document.querySelector('form').submit();
        }

        // اعتبارسنجی فرم
        document.querySelector('form').addEventListener('submit', function(e) {
            const from = document.querySelector('input[name="from"]').value;
            const to = document.querySelector('input[name="to"]').value;
            
            if ((from && !to) || (!from && to)) {
                e.preventDefault();
                alert('لطفاً هر دو فیلد تاریخ را پر کنید یا هر دو را خالی بگذارید.');
                return false;
            }
        });
    </script>
</body>
</html>     
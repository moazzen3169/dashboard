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

    // متد جدید برای دسترسی به پارامترها
    public function getParams() {
        return $this->params;
    }

    // متد جدید برای گرفتن تمام داده‌های فیلتر
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

        // کوئری بهینه‌شده برای گزارش ماهانه
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

            // محاسبه درصد پرداختی
            if ($row['total_price'] > 0) {
                $result[$monthKey]['percentPaid'] = round(
                    ($result[$monthKey]['payments'] / $row['total_price']) * 100, 1
                );
            }

            // اضافه کردن محصول و مشتری برتر
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

    // متد جدید برای گرفتن تمام خریدها با فیلترهای اعمال شده
    public function getAllPurchases() {
        $whereSQL = $this->getWhereClause();
        $stmt = $this->conn->prepare("SELECT *, IF(is_return=1, -total_price, total_price) as net_total_price FROM purchases $whereSQL");
        $stmt->execute($this->params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // متد جدید برای گرفتن پرداختی‌های هر کاربر با گروه‌بندی
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

    // متد جدید برای گرفتن کل پرداختی‌ها
    public function getTotalPayments() {
        $stmt = $this->conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments");
        $stmt->execute();
        return $stmt->fetchColumn();
    }
}

// اجرای اصلی
try {
    $report = new SalesReport($conn);
    $report->applyFilters();
    
    $dailyReport = $report->getDailyReport();
    $monthlyReport = $report->getMonthlyReport();
    $allPurchases = $report->getAllPurchases(); // استفاده از متد جدید
    
    $overallStats = $report->getOverallStats($allPurchases);
    $bests = $report->getBests();
    $userPayments = $report->getUserPayments();
    $totalPayments = $report->getTotalPayments();

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
    <title>📊 گزارش فروش بهینه‌شده</title>
    <link rel="stylesheet" href="fonts/fonts.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/design-system.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/reports-update.css">
    <style>
        .error-message {
            background: #ffe0e0;
            color: #d00;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
            border: 1px solid #ffb3b3;
        }
        
        .loading-spinner {
            display: none;
            text-align: center;
            padding: 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            border-right: 4px solid #007bff;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        
        .stat-label {
            color: #666;
            margin-top: 5px;
        }
        
        .filter-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
        
        .filter-form input,
        .filter-form select {
            margin: 0 10px 10px 0;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .filter-form button {
            background: #007bff;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 5px;
            cursor: pointer;
        }
        
        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .report-table th,
        .report-table td {
            padding: 12px;
            text-align: center;
            border-bottom: 1px solid #eee;
        }
        
        .report-table th {
            background: #007bff;
            color: white;
        }
        
        .report-table tr:hover {
            background: #f5f5f5;
        }
        
        .low-sales {
            background: #fff3cd !important;
        }
    </style>
</head>
<body class="dashboard-container">
    <aside class="sidebar"><?php include 'sidebar.php'; ?></aside>
    
    <div class="main-content">
        <header class="top-bar">
            <h1 class="h1">📊 گزارش فروش بهینه‌شده</h1>
        </header>
        
        <div class="content-area">
            <!-- نمایش خطاها -->
            <?php if (isset($e)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    خطا در تولید گزارش: <?= htmlspecialchars($e->getMessage()) ?>
                </div>
            <?php endif; ?>

            <!-- آمار کلی -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?= number_format($overallStats['totalPurchases']) ?></div>
                    <div class="stat-label">کل تعداد فروش</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= number_format($overallStats['totalRevenue']) ?></div>
                    <div class="stat-label">کل درآمد (ریال)</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= number_format($totalPayments) ?></div>
                    <div class="stat-label">کل پرداختی‌ها (ریال)</div>
                </div>
            </div>

            <!-- بهترین‌ها -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?= htmlspecialchars(($bests['product']['product_name'] ?? '-') . ' (' . number_format($bests['product']['q'] ?? 0) . ' عدد)') ?></div>
                    <div class="stat-label">پرفروش‌ترین محصول</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= htmlspecialchars($bests['buyer']['name'] ?? '-') ?></div>
                    <div class="stat-label">بهترین مشتری</div>
                </div>
            </div>

            <!-- فرم فیلتر -->
            <form method="get" class="filter-form" id="filterForm">
                <div>
                    <input type="text" name="from" placeholder="از تاریخ (مثال: 1404/01/01)" 
                           value="<?= htmlspecialchars($from) ?>" pattern="\d{4}/\d{2}/\d{2}">
                    <input type="text" name="to" placeholder="تا تاریخ (مثال: 1404/12/29)" 
                           value="<?= htmlspecialchars($to) ?>" pattern="\d{4}/\d{2}/\d{2}">
                    
                    <select name="buyer_id">
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

                    <select name="product">
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

                    <button type="submit">
                        <i class="fas fa-filter"></i> اعمال فیلتر
                    </button>
                    <button type="button" onclick="clearFilters()">
                        <i class="fas fa-times"></i> پاک کردن
                    </button>
                </div>
            </form>

            <!-- گزارش روزانه -->
            
            <!-- گزارش ماهانه -->
            <section>
                <h3><i class="fas fa-calendar-alt"></i> گزارش ماهانه</h3>
                <div class="table-responsive">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>ماه</th>
                                <th>تعداد فروش</th>
                                <th>مبلغ کل (ریال)</th>
                                <th>پرداختی‌ها</th>
                                <th>مانده حساب</th>
                                <th>درصد تسویه</th>
                                <th>محصول برتر(ماه)</th>
                                <th>نام خریدار</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($monthlyReport)): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; color: #666;">
                                        هیچ داده‌ای برای نمایش وجود ندارد
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($monthlyReport as $month => $info): ?>
                                    <tr class="<?= $info['qty'] < 20 ? 'low-sales' : '' ?>">
                                        <td><strong><?= $month ?></strong></td>
                                        <td><?= number_format($info['qty']) ?></td>
                                        <td><?= number_format($info['price']) ?></td>
                                        <td><?= number_format($info['payments']) ?></td>
                                        <td>
                                            <span style="color: <?= $info['balance'] > 0 ? '#d00' : '#0a0' ?>">
                                                <?= number_format($info['balance']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="background: #e0e0e0; border-radius: 10px; height: 20px;">
                                                <div style="background: <?= $info['percentPaid'] >= 80 ? '#28a745' : ($info['percentPaid'] >= 50 ? '#ffc107' : '#dc3545') ?>;
                                                     width: <?= min($info['percentPaid'], 100) ?>%; height: 100%; border-radius: 10px; text-align: center; color: white; font-size: 12px; line-height: 20px;">
                                                    <?= $info['percentPaid'] ?>%
                                                </div>
                                            </div>
                                        </td>
                                        <td><strong><?= htmlspecialchars($info['topProduct']) ?></strong></td>
                                        <td><strong><?= htmlspecialchars($info['topCustomer']) ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- گزارش پرداختی‌های کاربران -->
            <section>
                <h3><i class="fas fa-money-bill-wave"></i> گزارش پرداختی‌های کاربران</h3>
                <div class="table-responsive">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>نام مشتری</th>
                                <th>مجموع پرداختی‌ها (ریال)</th>
                                <th>تعداد پرداختی‌ها</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($userPayments)): ?>
                                <tr>
                                    <td colspan="3" style="text-align: center; color: #666;">
                                        هیچ داده‌ای برای نمایش وجود ندارد
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($userPayments as $payment): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($payment['buyer_name']) ?></strong></td>
                                        <td><?= number_format($payment['total_payments']) ?></td>
                                        <td><?= number_format($payment['payment_count']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- نمودارها -->
            <section>
                <h3><i class="fas fa-chart-line"></i> نمودار تحلیلی</h3>
                <div style="background: white; padding: 20px; border-radius: 10px; margin: 20px 0; height: 96vh">
                    <canvas id="salesChart" ></canvas>
                </div>
            </section>

            <!-- دکمه‌های خروجی -->
            <div style="text-align: center; margin: 30px 0;">
                <button onclick="downloadCSV()" class="btn-primary">
                    <i class="fas fa-download"></i> دانلود گزارش CSV
                </button>
                <button onclick="window.print()" class="btn-secondary">
                    <i class="fas fa-print"></i> چاپ گزارش
                </button>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
                        borderColor: '#007bff',
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        yAxisID: 'y',
                        tension: 0.3
                    },
                    {
                        label: 'درآمد (ریال)',
                        data: revenues,
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        yAxisID: 'y1',
                        tension: 0.3
                    }
                ]
            },
            options: {
                responsive: true,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'ماه'
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'تعداد فروش'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'درآمد (ریال)'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                        rtl: true
                    },
                    tooltip: {
                        rtl: true,
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
            document.getElementById('filterForm').submit();
        }

        // اعتبارسنجی فرم
        document.getElementById('filterForm').addEventListener('submit', function(e) {
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
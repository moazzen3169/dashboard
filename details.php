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
$stmt = $conn->prepare("
    SELECT * FROM purchases
    WHERE buyer_id=?
    ORDER BY purchase_date ASC
");
$stmt->execute([$buyer_id]);
$purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

// گروه‌بندی بر اساس ماه جلالی
$grouped = [];
foreach ($purchases as $p) {
    list($gy,$gm,$gd) = explode('-', $p['purchase_date']);
    list($jy,$jm,$jd) = gregorian_to_jalali($gy, $gm, $gd);
    $monthKey = sprintf("%04d/%02d", $jy, $jm); // مثلا 1404/06
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

// اگر ماه انتخاب شده بود
$selectedMonth = $_GET['month'] ?? null;
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>جزئیات خرید - <?= htmlspecialchars($buyer['name']) ?></title>

    <!-- Sidebar Dependencies -->
    <link rel="stylesheet" href="fonts/fonts.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/sidebar.css">

    <!-- Modern Styling -->
    <link rel="stylesheet" href="css/design-system.css">
    <link rel="stylesheet" href="css/details.css">

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/jalaali-js/dist/jalaali.min.js"></script>
    <script src="js/sidebar.js"></script>
</head>

<body class="dashboard-container">
    <!-- Sidebar -->

    <!-- Main Content -->
    <div class="details-main-content">
        <!-- Header -->
        <div class="details-header details-fade-in">
            <h1 class="details-title">
                <i class="fas fa-user-circle"></i>
                جزئیات خریدهای خریدار
            </h1>
            <div class="details-buyer-info">
                <h2 class="details-buyer-name">
                    <?= htmlspecialchars($buyer['name']) ?>
                </h2>
                <p class="body" style="color: var(--details-text-muted); margin: var(--space-xs) 0 0 0;">
                    مشاهده جزئیات و تاریخچه خریدها
                </p>
            </div>
        </div>

        <?php if (!$selectedMonth): ?>
            <!-- Summary Cards -->
            <div class="details-summary-section details-fade-in">
                <div class="details-summary-card">
                    <h3 class="details-summary-title">
                        <i class="fas fa-shopping-bag"></i>
                        مجموع خریدها
                    </h3>
                    <p class="details-summary-value">
                        <?= number_format(array_sum(array_column($grouped, 'total_price'))) ?> تومان
                    </p>
                </div>

                <div class="details-summary-card">
                    <h3 class="details-summary-title">
                        <i class="fas fa-boxes"></i>
                        تعداد محصولات
                    </h3>
                    <p class="details-summary-value">
                        <?= number_format(array_sum(array_column($grouped, 'total_qty'))) ?>
                    </p>
                </div>

                <div class="details-summary-card">
                    <h3 class="details-summary-title">
                        <i class="fas fa-calendar-alt"></i>
                        تعداد ماه‌ها
                    </h3>
                    <p class="details-summary-value">
                        <?= count($grouped) ?>
                    </p>
                </div>
            </div>

            <!-- Monthly Overview -->
            <div class="details-monthly-section details-fade-in">
                <h2 class="details-section-title">
                    <i class="fas fa-calendar-month"></i>
                    خریدها بر اساس ماه
                </h2>

                <div class="table-responsive">
                    <table class="details-monthly-table">
                        <thead>
                            <tr>
                                <th><i class="fas fa-calendar"></i> ماه</th>
                                <th><i class="fas fa-box"></i> تعداد محصولات</th>
                                <th><i class="fas fa-money-bill-wave"></i> مجموع مبلغ</th>
                                <th><i class="fas fa-eye"></i> عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($grouped as $month => $data): ?>
                            <tr>
                                <td><strong><?= $month ?></strong></td>
                                <td><?= number_format($data['total_qty']) ?></td>
                                <td><strong><?= number_format($data['total_price']) ?> تومان</strong></td>
                                <td>
                                    <a href="details.php?buyer_id=<?= $buyer_id ?>&month=<?= $month ?>" class="details-view-btn">
                                        <i class="fas fa-eye"></i>
                                        نمایش محصولات
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php else: ?>
            <!-- Products Detail for Selected Month -->
            <div class="details-products-section details-fade-in">
                <h2 class="details-products-title">
                    <i class="fas fa-box-open"></i>
                    محصولات در ماه <?= htmlspecialchars($selectedMonth) ?>
                </h2>

                <div class="table-responsive">
                    <table class="details-products-table">
                        <thead>
                            <tr>
                                <th><i class="fas fa-tag"></i> محصول</th>
                                <th><i class="fas fa-money-bill"></i> قیمت فی</th>
                                <th><i class="fas fa-sort-numeric-up"></i> تعداد</th>
                                <th><i class="fas fa-calendar-day"></i> تاریخ</th>
                                <th><i class="fas fa-calculator"></i> جمع</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $totalQty = 0;
                            $totalPrice = 0;
                            foreach($grouped[$selectedMonth]['products'] as $p):
                                list($gy,$gm,$gd) = explode('-', $p['purchase_date']);
                                list($jy,$jm,$jd) = gregorian_to_jalali($gy, $gm, $gd);
                                $shamsi = "$jy/$jm/$jd";
                                $totalQty += $p['quantity'];
                                $totalPrice += $p['total_price'];
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($p['product_name']) ?></strong></td>
                                <td><?= number_format($p['unit_price']) ?> تومان</td>
                                <td><?= number_format($p['quantity']) ?></td>
                                <td><?= $shamsi ?></td>
                                <td><strong><?= number_format($p['total_price']) ?> تومان</strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Summary Footer -->
            <div class="details-summary-footer details-fade-in">
                <h3 class="details-section-title" style="margin-bottom: var(--space-lg);">
                    <i class="fas fa-chart-pie"></i>
                    خلاصه ماه <?= htmlspecialchars($selectedMonth) ?>
                </h3>

                <div class="details-total-row">
                    <div class="details-total-item">
                        <div class="details-total-label">تعداد کل محصولات</div>
                        <div class="details-total-value">
                            <?= number_format($totalQty) ?>
                        </div>
                    </div>

                    <div class="details-total-item">
                        <div class="details-total-label">مجموع مبلغ</div>
                        <div class="details-total-value">
                            <?= number_format($totalPrice) ?> تومان
                        </div>
                    </div>
                </div>

                <div class="details-action-buttons">
                    <button class="details-print-btn" onclick="window.print()">
                        <i class="fas fa-print"></i>
                        پرینت
                    </button>
                    <a href="details.php?buyer_id=<?= $buyer_id ?>" class="details-back-btn">
                        <i class="fas fa-arrow-right"></i>
                        بازگشت
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Back to Factor Products -->
        <?php if (!$selectedMonth): ?>
            <div style="text-align: center; margin-top: var(--space-xl);">
                <a href="factor-products.php" class="details-back-btn" style="display: inline-flex;">
                    <i class="fas fa-arrow-right"></i>
                    بازگشت به مدیریت خریدها
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Print functionality
        function printPage() {
            window.print();
        }

        // Add fade-in animation to elements on load
        document.addEventListener('DOMContentLoaded', function() {
            const animatedElements = document.querySelectorAll('.details-fade-in');
            animatedElements.forEach((element, index) => {
                setTimeout(() => {
                    element.style.opacity = '1';
                    element.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });

        // Add loading states for buttons
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('details-print-btn') || e.target.classList.contains('details-back-btn')) {
                e.target.style.opacity = '0.7';
                setTimeout(() => {
                    e.target.style.opacity = '1';
                }, 200);
            }
        });
    </script>
</body>
</html>

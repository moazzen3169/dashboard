<?php
include 'db.php';
include 'jalali_calendar.php'; // فایل تاریخ خودت

// گرفتن تاریخ جلالی امروز
list($jy, $jm, $jd) = gregorian_to_jalali(date('Y'), date('m'), date('d'));
$current_jalali_date = sprintf('%04d/%02d/%02d', $jy, $jm, $jd);

// =======================
// ذخیره خریدها
// =======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_purchase'])) {
    $buyer_id = $_POST['buyer_id'] ?? '';

    // اگر خریدار جدید وارد شده بود
    if (empty($buyer_id)) {
        $buyer_name = trim($_POST['buyer_name'] ?? '');
        if ($buyer_name !== '') {
            $stmt = $conn->prepare("INSERT INTO buyers (name) VALUES (?)");
            $stmt->execute([$buyer_name]);
            $buyer_id = $conn->lastInsertId();
        }
    }

    // درج محصولات
    if (!empty($_POST['products']) && is_array($_POST['products'])) {
        $insert = $conn->prepare("
            INSERT INTO purchases (buyer_id, product_name, unit_price, quantity, purchase_date)
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($_POST['products'] as $p) {
            $name  = trim($p['product_name'] ?? '');
            $price = $p['unit_price'] ?? null;
            $qty   = $p['quantity'] ?? null;
            $date  = trim($p['purchase_date'] ?? '');

            if ($name === '' || $price === null || $qty === null || $date === '') {
                continue;
            }

            // اگر تاریخ جلالی بود (مثلا 1404/06/10) به میلادی تبدیل می‌کنیم
            if (strpos($date, '/') !== false) {
                list($jy, $jm, $jd) = explode('/', $date);
                list($gy, $gm, $gd) = jalali_to_gregorian($jy, $jm, $jd);
                $date = sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
            }

            $insert->execute([$buyer_id, $name, $price, $qty, $date]);
        }
    }

    header("Location: factor-products.php");
    exit;
}

// =======================
// حذف خرید
// =======================
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM purchases WHERE id=$id");
    header("Location: factor-products.php");
    exit;
}

// =======================
// گرفتن داده‌ها
// =======================

// همه خریدارها
$buyers = $conn->query("SELECT * FROM buyers ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

// گزارش گروهی مجموع خرید هر خریدار
$summary = $conn->query("
    SELECT b.id, b.name, SUM(p.total_price) as total
    FROM purchases p
    JOIN buyers b ON p.buyer_id=b.id
    GROUP BY b.id
")->fetchAll(PDO::FETCH_ASSOC);

// همه خریدها
$purchases = $conn->query("
    SELECT p.*, b.name as buyer_name
    FROM purchases p
    JOIN buyers b ON p.buyer_id=b.id
    ORDER BY p.id DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت خریدها</title>

    <!-- Sidebar Dependencies -->
    <link rel="stylesheet" href="fonts/fonts.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/sidebar.css">

    <!-- Modern Styling -->
    <link rel="stylesheet" href="css/design-system.css">
    <link rel="stylesheet" href="css/factor-products.css">

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/jalaali-js/dist/jalaali.min.js"></script>
    <script src="js/sidebar.js"></script>
</head>

<body class="dashboard-container">
    <!-- Sidebar -->
    <aside class="sidebar">
        <?php include 'sidebar.php'; ?>
    </aside>

    <!-- Main Content -->
    <div class="factor-main-content">
        <!-- Header -->
        <div class="factor-header factor-fade-in">
            <h1 class="factor-title">
                <i class="fas fa-shopping-cart"></i>
                مدیریت خریدها
            </h1>
            <p class="body" style="color: var(--factor-text-muted); margin: 0;">
                ثبت و مدیریت خریدهای مشتریان
            </p>
        </div>

        <!-- Purchase Form -->
        <div class="factor-form-section factor-fade-in">
            <h2 class="factor-form-title">
                <i class="fas fa-plus-circle"></i>
                فرم ثبت خرید جدید
            </h2>

            <form method="POST" id="purchaseForm">
                <div class="factor-form-grid">
                    <div class="factor-form-group">
                        <label class="factor-form-label" for="buyer_select">انتخاب خریدار:</label>
                        <select name="buyer_id" id="buyer_select" class="factor-form-select">
                            <option value="">-- خریدار جدید --</option>
                            <?php foreach($buyers as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="factor-form-group">
                        <label class="factor-form-label" for="buyer_name">نام خریدار جدید:</label>
                        <input type="text" name="buyer_name" id="buyer_name" class="factor-form-input" placeholder="نام خریدار را وارد کنید">
                    </div>
                </div>

                <div id="products" class="factor-products-container">
                    <!-- Products will be added here dynamically -->
                </div>

                <button type="button" class="factor-add-product-btn" onclick="addProductRow()">
                    <i class="fas fa-plus"></i>
                    افزودن محصول جدید
                </button>

                <div style="margin-top: var(--space-lg);">
                    <button type="submit" name="save_purchase" class="factor-submit-btn">
                        <i class="fas fa-save"></i>
                        ذخیره خرید
                    </button>
                </div>
            </form>
        </div>

        <!-- Summary Table -->
        <div class="factor-table-section factor-fade-in">
            <h2 class="factor-section-title">
                <i class="fas fa-chart-bar"></i>
                گزارش خریدها بر اساس خریدار
            </h2>

            <div class="table-responsive">
                <table class="factor-table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-user"></i> نام خریدار</th>
                            <th><i class="fas fa-money-bill-wave"></i> مجموع خرید</th>
                            <th><i class="fas fa-eye"></i> جزئیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($summary as $s): ?>
                        <tr>
                            <td><?= htmlspecialchars($s['name']) ?></td>
                            <td><strong><?= number_format($s['total']) ?> تومان</strong></td>
                            <td>
                                <a href="details.php?buyer_id=<?= $s['id'] ?>" class="btn factor-btn-primary">
                                    <i class="fas fa-external-link-alt"></i>
                                    نمایش جزئیات
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- All Purchases Table -->
        <div class="factor-table-section factor-fade-in">
            <h2 class="factor-section-title">
                <i class="fas fa-list"></i>
                لیست همه خریدها
            </h2>

            <div class="table-responsive">
                <table class="factor-table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-user"></i> خریدار</th>
                            <th><i class="fas fa-box"></i> محصول</th>
                            <th><i class="fas fa-tag"></i> قیمت فی</th>
                            <th><i class="fas fa-sort-numeric-up"></i> تعداد</th>
                            <th><i class="fas fa-calendar"></i> تاریخ</th>
                            <th><i class="fas fa-calculator"></i> جمع</th>
                            <th><i class="fas fa-cogs"></i> عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($purchases as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['buyer_name']) ?></td>
                            <td><?= htmlspecialchars($p['product_name']) ?></td>
                            <td><?= number_format($p['unit_price']) ?> تومان</td>
                            <td><?= number_format($p['quantity']) ?></td>
                            <td>
                                <?php
                                    list($gy,$gm,$gd) = explode('-', $p['purchase_date']);
                                    list($jy,$jm,$jd) = gregorian_to_jalali($gy, $gm, $gd);
                                    echo "$jy/$jm/$jd";
                                ?>
                            </td>
                            <td><strong><?= number_format($p['total_price']) ?> تومان</strong></td>
                            <td>
                                <a href="?delete=<?= $p['id'] ?>" class="btn factor-btn-danger" onclick="return confirm('آیا مطمئن هستید که می‌خواهید این خرید را حذف کنید؟')">
                                    <i class="fas fa-trash"></i>
                                    حذف
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        let rowIndex = 0;

        function addProductRow() {
            const container = document.getElementById('products');
            const idx = rowIndex++;

            const productDiv = document.createElement('div');
            productDiv.className = 'factor-products-container';
            productDiv.style.marginBottom = 'var(--space-md)';
            productDiv.innerHTML = '<div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: var(--space-md); align-items: end;">' +
                '<div>' +
                    '<label class="factor-form-label">نام محصول:</label>' +
                    '<input type="text" name="products[' + idx + '][product_name]" class="factor-form-input" required placeholder="نام محصول را وارد کنید">' +
                '</div>' +
                '<div>' +
                    '<label class="factor-form-label">قیمت فی:</label>' +
                    '<input type="text" name="products[' + idx + '][unit_price]" class="factor-form-input price-input" required placeholder="0.00">' +
                '</div>' +
                '<div>' +
                    '<label class="factor-form-label">تعداد:</label>' +
                    '<input type="number" min="1" name="products[' + idx + '][quantity]" class="factor-form-input" required placeholder="1">' +
                '</div>' +
                '<div>' +
                    '<label class="factor-form-label">تاریخ:</label>' +
                    '<input type="text" name="products[' + idx + '][purchase_date]" class="factor-form-input" required placeholder="1404/06/10" value="' + current_jalali_date + '">' +
                '</div>' +
            '</div>' +
            '<button type="button" class="btn factor-btn-danger" onclick="removeProductRow(this)" style="margin-top: var(--space-sm);">' +
                '<i class="fas fa-minus"></i>' +
                'حذف محصول' +
            '</button>';

            container.appendChild(productDiv);

            // Focus on the first input of the new product
            const firstInput = productDiv.querySelector('input[type="text"]');
            if (firstInput) {
                firstInput.focus();
            }

            // Add event listener for price input formatting
            const priceInput = productDiv.querySelector('.price-input');
            priceInput.addEventListener('input', function(e) {
                let value = e.target.value;
                // Remove all non-digit characters except dot
                value = value.replace(/[^0-9.]/g, '');
                // Split integer and decimal parts
                const parts = value.split('.');
                // Format integer part with commas
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                // Join parts back
                e.target.value = parts.join('.');
            });
        }

        function removeProductRow(button) {
            const productDiv = button.parentElement;
            productDiv.remove();
        }

        // Form validation and comma removal on submit
        document.getElementById('purchaseForm').addEventListener('submit', function(e) {
            // Remove commas from all price inputs before submit
            const priceInputs = document.querySelectorAll('.price-input');
            priceInputs.forEach(input => {
                input.value = input.value.replace(/,/g, '');
            });

            const buyerSelect = document.getElementById('buyer_select');
            const buyerName = document.getElementById('buyer_name');

            if (!buyerSelect.value && !buyerName.value.trim()) {
                e.preventDefault();
                alert('لطفاً یک خریدار انتخاب کنید یا نام خریدار جدید را وارد کنید.');
                return false;
            }

            const products = document.querySelectorAll('#products input[required]');
            let hasEmptyProduct = false;

            products.forEach(input => {
                if (!input.value.trim()) {
                    hasEmptyProduct = true;
                }
            });

            if (hasEmptyProduct) {
                e.preventDefault();
                alert('لطفاً تمام فیلدهای محصولات را تکمیل کنید.');
                return false;
            }
        });

        // Auto-focus first input when adding new product
        // تاریخ جلالی امروز از PHP به JS
        const current_jalali_date = "<?php echo $current_jalali_date; ?>";
    </script>
</body>
</html>

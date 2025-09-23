<?php
include 'db.php';
include 'jalali_calendar.php';
session_start();

// تاریخ جلالی امروز
list($jy, $jm, $jd) = gregorian_to_jalali(date('Y'), date('m'), date('d'));
$current_jalali_date = sprintf('%04d/%02d/%02d', $jy, $jm, $jd);

$error_message = "";
$warning_message = "";

// =======================
// ذخیره خرید جدید
// =======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_purchase'])) {
    $buyer_id = $_POST['buyer_id'] ?? '';

    // اگر خریدار جدید وارد شد
    if ($buyer_id === 'new' || empty($buyer_id)) {
        $buyer_name = trim($_POST['buyer_name'] ?? '');

        if ($buyer_name !== '') {
            // بررسی وجود خریدار دقیق
            $stmt = $conn->prepare("SELECT * FROM buyers WHERE name = ?");
            $stmt->execute([$buyer_name]);
            $existingBuyer = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existingBuyer) {
                $error_message = "❌ خریدار با نام «" . htmlspecialchars($buyer_name) . "» از قبل وجود دارد.";
            } else {
                // بررسی نام‌های مشابه
                $stmt = $conn->prepare("SELECT name FROM buyers WHERE name LIKE ?");
                $stmt->execute(['%' . $buyer_name . '%']);
                $similarBuyers = $stmt->fetchAll(PDO::FETCH_COLUMN);

                if (!empty($similarBuyers)) {
                    $warning_message = "⚠️ توجه: نام‌های مشابهی در سیستم وجود دارد: " . implode("، ", $similarBuyers);
                }

                // ذخیره خریدار جدید
                if (empty($error_message)) {
                    $stmt = $conn->prepare("INSERT INTO buyers (name) VALUES (?)");
                    $stmt->execute([$buyer_name]);
                    $buyer_id = $conn->lastInsertId();
                }
            }
        }
    }

    // ادامه ذخیره محصولات
    if (empty($error_message) && !empty($_POST['products']) && is_array($_POST['products'])) {
        $insert = $conn->prepare("
            INSERT INTO purchases (buyer_id, product_name, unit_price, quantity, purchase_date, is_return)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        foreach ($_POST['products'] as $p) {
            $name  = trim($p['product_name'] ?? '');
            $price = floatval(str_replace([',','٬'], '', $p['unit_price'] ?? 0));
            $qty   = intval($p['quantity'] ?? 0);
            $date  = trim($p['purchase_date'] ?? '');
            $is_return = isset($p['is_return']) && $p['is_return'] == 1 ? 1 : 0;

            if ($name === '' || $price <= 0 || $qty <= 0 || $date === '') continue;

            // تاریخ جلالی به میلادی
            if (strpos($date, '/') !== false) {
                list($jy,$jm,$jd) = explode('/', $date);
                list($gy,$gm,$gd) = jalali_to_gregorian($jy, $jm, $jd);
                $date = sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
            }

            $insert->execute([$buyer_id, $name, $price, $qty, $date, $is_return]);
        }
    }

    if (empty($error_message)) {
        header("Location: factor-products.php");
        exit;
    }
}

// =======================
// ویرایش خرید
// =======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_purchase'])) {
    $id     = intval($_POST['edit_id']);
    $name   = trim($_POST['edit_product_name']);
    $price  = floatval(str_replace([',','٬'], '', $_POST['edit_unit_price']));
    $qty    = intval($_POST['edit_quantity']);
    $date   = trim($_POST['edit_purchase_date']);
    $is_ret = intval($_POST['edit_is_return']);

    if (strpos($date, '/') !== false) {
        list($jy,$jm,$jd) = explode('/', $date);
        list($gy,$gm,$gd) = jalali_to_gregorian($jy, $jm, $jd);
        $date = sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
    }

    $stmt = $conn->prepare("UPDATE purchases SET product_name=?, unit_price=?, quantity=?, purchase_date=?, is_return=? WHERE id=?");
    $stmt->execute([$name, $price, $qty, $date, $is_ret, $id]);

    header("Location: factor-products.php");
    exit;
}

// =======================
// حذف خرید
// =======================
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM purchases WHERE id=?");
    $stmt->execute([$id]);
    header("Location: factor-products.php");
    exit;
}

// =======================
// گرفتن داده‌ها
// =======================
$buyers = $conn->query("SELECT * FROM buyers ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

$summary = $conn->query("
    SELECT b.id, b.name, SUM(IF(is_return=1, -(p.unit_price * p.quantity), (p.unit_price * p.quantity))) as total
    FROM purchases p
    JOIN buyers b ON p.buyer_id=b.id
    GROUP BY b.id
")->fetchAll(PDO::FETCH_ASSOC);

$purchases = $conn->query("
    SELECT p.*, b.name as buyer_name, (p.unit_price * p.quantity) as total_price
    FROM purchases p
    JOIN buyers b ON p.buyer_id=b.id
    ORDER BY p.id DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<title>مدیریت خریدها</title>
<link rel="stylesheet" href="fonts/fonts.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="css/sidebar.css">
<link rel="stylesheet" href="css/design-system.css">
<link rel="stylesheet" href="css/factor-products.css">
</head>
<body class="dashboard-container">
<aside class="sidebar"><?php include 'sidebar.php'; ?></aside>

<div class="main-content">
    <h1><i class="fas fa-shopping-cart"></i> مدیریت خریدها</h1>

    <!-- فرم ثبت خرید -->
    <form method="POST" id="purchaseForm">
        <h2>ثبت خرید جدید</h2>
        <label>انتخاب خریدار:</label>
        <select name="buyer_id" id="buyerSelect">
            <option value="">-- انتخاب خریدار --</option>
            <?php foreach($buyers as $b): ?>
                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
            <?php endforeach; ?>
        </select>

        <button type="button" id="showNewBuyerBtn">➕ افزودن خریدار جدید</button>
        <div id="newBuyerInput" style="display:none; margin-top:5px;">
            <input type="text" name="buyer_name" placeholder="نام خریدار جدید">
        </div>

        <div id="products"></div>
        <button class="form_add_btn" type="button" onclick="addProductRow()">+ افزودن محصول</button>
        <button class="form_save_btn" type="submit" name="save_purchase">ذخیره خرید</button>
    </form>

    <?php if (!empty($error_message)): ?>
        <p style="color:red;"><?= $error_message ?></p>
    <?php elseif (!empty($warning_message)): ?>
        <p style="color:orange;"><?= $warning_message ?></p>
    <?php endif; ?>

    <!-- جدول خلاصه -->
    <h2>گزارش خریدها بر اساس خریدار</h2>
    <table border="1" width="100%">
        <tr><th>نام خریدار</th><th>مجموع خالص</th><th>جزئیات</th></tr>
        <?php foreach($summary as $s): ?>
        <tr>
            <td><?= htmlspecialchars($s['name']) ?></td>
            <td><?= number_format($s['total']) ?> تومان</td>
            <td><a href="details.php?buyer_id=<?= $s['id'] ?>">مشاهده</a></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <!-- جدول همه خریدها -->
    <h2>لیست همه خریدها</h2>
    <table border="1" width="100%">
        <tr>
            <th>خریدار</th><th>محصول</th><th>قیمت</th><th>تعداد</th><th>جمع</th>
            <th>تاریخ</th><th>وضعیت</th><th>عملیات</th>
        </tr>
        <?php foreach($purchases as $p): ?>
        <tr style="<?= $p['is_return'] ? 'color:red;' : '' ?>">
            <td><?= htmlspecialchars($p['buyer_name']) ?></td>
            <td><?= htmlspecialchars($p['product_name']) ?></td>
            <td><?= number_format($p['unit_price']) ?></td>
            <td><?= number_format($p['quantity']) ?></td>
            <td><?= number_format($p['total_price']) ?></td>
            <td><?php list($gy,$gm,$gd)=explode('-',$p['purchase_date']); list($jy,$jm,$jd)=gregorian_to_jalali($gy,$gm,$gd); echo "$jy/$jm/$jd"; ?></td>
            <td><?= $p['is_return'] ? 'مرجوعی' : 'خرید' ?></td>
            <td>
                <a href="?delete=<?= $p['id'] ?>" onclick="return confirm('حذف شود؟')">حذف</a>
                <button type="button" onclick='openEditModal(<?= json_encode($p, JSON_UNESCAPED_UNICODE) ?>)'>ویرایش</button>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>

<!-- Modal -->
<div class="modal-overlay" onclick="closeModal()"></div>
<div class="modal" id="editModal">
    <h3>ویرایش خرید</h3>
    <form method="POST" id="editForm">
        <input type="hidden" name="edit_id" id="edit_id">
        <div><label>نام محصول:</label><input type="text" name="edit_product_name" id="edit_product_name"></div>
        <div><label>قیمت:</label><input type="text" name="edit_unit_price" id="edit_unit_price"></div>
        <div><label>تعداد:</label><input type="number" name="edit_quantity" id="edit_quantity"></div>
        <div><label>تاریخ:</label><input type="text" name="edit_purchase_date" id="edit_purchase_date"></div>
        <div>
            <label>نوع:</label>
            <select name="edit_is_return" id="edit_is_return">
                <option value="0">خرید</option>
                <option value="1">مرجوعی</option>
            </select>
        </div>
        <button type="submit" name="update_purchase">ذخیره</button>
        <button type="button" onclick="closeModal()">بستن</button>
    </form>
</div>

<script>
let rowIndex = 0;
function addProductRow() {
    const div = document.createElement('div');
    div.innerHTML = `
        <input type="text" name="products[${rowIndex}][product_name]" placeholder="نام محصول" required>
        <input type="text" class="price-input" name="products[${rowIndex}][unit_price]" placeholder="قیمت" required>
        <input type="number" name="products[${rowIndex}][quantity]" placeholder="تعداد" required>
        <input type="text" name="products[${rowIndex}][purchase_date]" value="<?= $current_jalali_date ?>" required>
        <label><input type="radio" name="products[${rowIndex}][is_return]" value="0" checked> خرید</label>
        <label><input type="radio" name="products[${rowIndex}][is_return]" value="1"> مرجوعی</label>
        <button type="button" onclick="this.parentElement.remove()">❌</button>
    `;
    document.getElementById('products').appendChild(div);
    rowIndex++;
}

// فرمت سه‌رقمی قیمت
function formatPriceInput(input) {
    let value = input.value.replace(/[^\d]/g, "");
    if (value === "") {
        input.value = "";
        return;
    }
    input.value = value.replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}
document.addEventListener("input", function(e) {
    if (e.target.classList.contains("price-input")) {
        formatPriceInput(e.target);
    }
});

// حذف جداکننده قبل از ارسال
document.getElementById("purchaseForm").addEventListener("submit", function() {
    document.querySelectorAll(".price-input").forEach(inp => {
        inp.value = inp.value.replace(/[^\d]/g, "");
    });
});
document.getElementById("editForm").addEventListener("submit", function() {
    document.getElementById("edit_unit_price").value = document.getElementById("edit_unit_price").value.replace(/[^\d]/g, "");
});

function openEditModal(p) {
    document.getElementById('edit_id').value = p.id;
    document.getElementById('edit_product_name').value = p.product_name || '';
    document.getElementById('edit_unit_price').value = (p.unit_price+"").replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    document.getElementById('edit_quantity').value = p.quantity || '';
    document.getElementById('edit_is_return').value = p.is_return || 0;

    // تاریخ جلالی
    if (p.purchase_date && p.purchase_date.includes('-')) {
        const parts = p.purchase_date.split('-');
        const gy = parseInt(parts[0]), gm = parseInt(parts[1]), gd = parseInt(parts[2]);
        const jalaliDate = convertGregorianToJalali(gy, gm, gd);
        document.getElementById('edit_purchase_date').value = jalaliDate;
    } else {
        document.getElementById('edit_purchase_date').value = p.purchase_date || '';
    }

    document.querySelector('.modal-overlay').style.display = 'block';
    document.getElementById('editModal').style.display = 'block';
}

function convertGregorianToJalali(gy, gm, gd) {
    const g_d_m = [0,31,59,90,120,151,181,212,243,273,304,334];
    let jy = (gy <= 1600) ? 0 : 979;
    gy -= (gy <= 1600) ? 621 : 1600;
    let gy2 = (gm > 2) ? (gy + 1) : gy;
    let days = (365 * gy) + parseInt((gy2 + 3) / 4) - parseInt((gy2 + 99) / 100) + parseInt((gy2 + 399) / 400) - 80 + gd + g_d_m[gm - 1];
    jy += 33 * parseInt(days / 12053);
    days %= 12053;
    jy += 4 * parseInt(days / 1461);
    days %= 1461;
    if (days > 365) {
        jy += parseInt((days - 1) / 365);
        days = (days - 1) % 365;
    }
    const jm = (days < 186) ? 1 + parseInt(days / 31) : 7 + parseInt((days - 186) / 30);
    const jd = 1 + ((days < 186) ? (days % 31) : ((days - 186) % 30));
    return jy + "/" + jm.toString().padStart(2, '0') + "/" + jd.toString().padStart(2, '0');
}

function closeModal() {
    document.querySelector('.modal-overlay').style.display = 'none';
    document.getElementById('editModal').style.display = 'none';
}

// نمایش input خریدار جدید
document.getElementById('showNewBuyerBtn').addEventListener('click', function() {
    document.getElementById('newBuyerInput').style.display = 'block';
    document.getElementById('buyerSelect').value = '';
    this.style.display = 'none';
});
</script>
</body>
</html>

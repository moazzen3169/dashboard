<?php
include 'db.php';
include 'jalali_calendar.php';
session_start();

// گرفتن تاریخ جلالی امروز
list($jy, $jm, $jd) = gregorian_to_jalali(date('Y'), date('m'), date('d'));
$current_jalali_date = sprintf('%04d/%02d/%02d', $jy, $jm, $jd);

// =======================
// ذخیره خرید جدید
// =======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_purchase'])) {
    $buyer_id = $_POST['buyer_id'] ?? '';

    // اگر خریدار جدید وارد شد
    if ($buyer_id === 'new' || empty($buyer_id)) {
        $buyer_name = trim($_POST['buyer_name'] ?? '');
        if ($buyer_name !== '') {
            $stmt = $conn->prepare("INSERT INTO buyers (name) VALUES (?)");
            $stmt->execute([$buyer_name]);
            $buyer_id = $conn->lastInsertId();
        }
    }

    if (!empty($_POST['products']) && is_array($_POST['products'])) {
        $insert = $conn->prepare("
            INSERT INTO purchases (buyer_id, product_name, unit_price, quantity, purchase_date, is_return)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        foreach ($_POST['products'] as $p) {
            $name  = trim($p['product_name'] ?? '');
            $price = floatval($p['unit_price'] ?? 0);
            $qty   = intval($p['quantity'] ?? 0);
            $date  = trim($p['purchase_date'] ?? '');
            $is_return = isset($p['is_return']) && $p['is_return'] == 1 ? 1 : 0;

            if ($name === '' || $price <= 0 || $qty <= 0 || $date === '') continue;

            // تاریخ جلالی → میلادی
            if (strpos($date, '/') !== false) {
                list($jy,$jm,$jd) = explode('/', $date);
                list($gy,$gm,$gd) = jalali_to_gregorian($jy, $jm, $jd);
                $date = sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
            }

            $insert->execute([$buyer_id, $name, $price, $qty, $date, $is_return]);
        }
    }

    header("Location: factor-products.php");
    exit;
}

// =======================
// ویرایش خرید
// =======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_purchase'])) {
    $id     = intval($_POST['edit_id']);
    $name   = trim($_POST['edit_product_name']);
    $price  = floatval($_POST['edit_unit_price']);
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
    SELECT b.id, b.name, SUM(IF(is_return=1, -p.total_price, p.total_price)) as total
    FROM purchases p
    JOIN buyers b ON p.buyer_id=b.id
    GROUP BY b.id
")->fetchAll(PDO::FETCH_ASSOC);

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
        <select name="buyer_id">
            <option value="">-- خریدار جدید --</option>
            <?php foreach($buyers as $b): ?>
                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="buyer_name" placeholder="نام خریدار جدید">

        <div id="products"></div>
        <button type="button" onclick="addProductRow()">+ افزودن محصول</button>
        <button type="submit" name="save_purchase">ذخیره خرید</button>
    </form>

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

<!-- Modal Overlay -->
<div class="modal-overlay" onclick="closeModal()"></div>

<!-- Modal ویرایش -->
<div class="modal" id="editModal">
    <h3>ویرایش خرید</h3>
    <form method="POST">
        <input type="hidden" name="edit_id" id="edit_id">
        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: bold;">نام محصول:</label>
            <input type="text" name="edit_product_name" id="edit_product_name" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
        </div>
        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: bold;">قیمت:</label>
            <input type="number" name="edit_unit_price" id="edit_unit_price" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
        </div>
        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: bold;">تعداد:</label>
            <input type="number" name="edit_quantity" id="edit_quantity" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
        </div>
        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: bold;">تاریخ:</label>
            <input type="text" name="edit_purchase_date" id="edit_purchase_date" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
        </div>
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: bold;">نوع:</label>
            <select name="edit_is_return" id="edit_is_return" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                <option value="0">خرید</option>
                <option value="1">مرجوعی</option>
            </select>
        </div>
        <div style="display: flex; gap: 10px;">
            <button type="submit" name="update_purchase" style="flex: 1; padding: 10px; background: #1976d2; color: white; border: none; border-radius: 4px; cursor: pointer;">ذخیره</button>
            <button type="button" onclick="closeModal()" style="flex: 1; padding: 10px; background: #f3f4f6; color: #333; border: none; border-radius: 4px; cursor: pointer;">بستن</button>
        </div>
    </form>
</div>

<script>
let rowIndex = 0;
function addProductRow() {
    const div = document.createElement('div');
    div.innerHTML = `
        <input type="text" name="products[${rowIndex}][product_name]" placeholder="نام محصول" required>
        <input type="number" name="products[${rowIndex}][unit_price]" placeholder="قیمت" required>
        <input type="number" name="products[${rowIndex}][quantity]" placeholder="تعداد" required>
        <input type="text" name="products[${rowIndex}][purchase_date]" value="<?= $current_jalali_date ?>" required>
        <label><input type="radio" name="products[${rowIndex}][is_return]" value="0" checked> خرید</label>
        <label><input type="radio" name="products[${rowIndex}][is_return]" value="1"> مرجوعی</label>
        <button type="button" onclick="this.parentElement.remove()">❌</button>
    `;
    document.getElementById('products').appendChild(div);
    rowIndex++;
}
// تابع تبدیل تاریخ میلادی به شمسی
function gregorianToJalali(gregorianDate) {
    if (!gregorianDate || gregorianDate === '0000-00-00') return '';

    const parts = gregorianDate.split('-');
    if (parts.length !== 3) return gregorianDate;

    const gy = parseInt(parts[0]);
    const gm = parseInt(parts[1]);
    const gd = parseInt(parts[2]);

    if (gy === 0 || gm === 0 || gd === 0) return '';

    // استفاده از تابع PHP از طریق AJAX یا محاسبه ساده
    // برای سادگی از محاسبه ساده استفاده می‌کنیم
    const g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    const jy = (gy <= 1600) ? 0 : 979;
    const gy2 = gy - ((gy <= 1600) ? 621 : 1600);
    let days = (365 * gy2) + parseInt((gy2 + 3) / 4) - parseInt((gy2 + 99) / 100) + parseInt((gy2 + 399) / 400) - 80 + gd + g_d_m[gm - 1] + ((gm > 2 && gy2 % 4 === 0 && (gy2 % 100 !== 0 || gy2 % 400 === 0)) ? 1 : 0) - 1;

    let jy2 = 33 * parseInt(days / 12053);
    days %= 12053;
    jy2 += 4 * parseInt(days / 1461);
    days %= 1461;
    jy2 += parseInt((days - 1) / 365);
    if (days > 365) days = (days - 1) % 365;
    const jm = (days < 186) ? 1 + parseInt(days / 31) : 7 + parseInt((days - 186) / 30);
    const jd = 1 + ((days < 186) ? (days % 31) : ((days - 186) % 30));

    return jy + jy2 + '/' + (jm < 10 ? '0' : '') + jm + '/' + (jd < 10 ? '0' : '') + jd;
}

function openEditModal(p) {
    // تنظیم مقادیر فرم
    document.getElementById('edit_id').value = p.id;
    document.getElementById('edit_product_name').value = p.product_name || '';
    document.getElementById('edit_unit_price').value = p.unit_price || '';
    document.getElementById('edit_quantity').value = p.quantity || '';
    document.getElementById('edit_is_return').value = p.is_return || 0;

    // تبدیل تاریخ میلادی به شمسی
    const jalaliDate = gregorianToJalali(p.purchase_date);
    document.getElementById('edit_purchase_date').value = jalaliDate;

    // نمایش مودال
    const modalOverlay = document.querySelector('.modal-overlay');
    const editModal = document.getElementById('editModal');

    modalOverlay.style.display = 'block';
    editModal.style.display = 'block';

    // اضافه کردن کلاس show برای انیمیشن
    setTimeout(() => {
        modalOverlay.classList.add('show');
        editModal.classList.add('show');
    }, 10);
}

function closeModal() {
    // مخفی کردن مودال
    const modalOverlay = document.querySelector('.modal-overlay');
    const editModal = document.getElementById('editModal');

    modalOverlay.classList.remove('show');
    editModal.classList.remove('show');

    // مخفی کردن کامل بعد از انیمیشن
    setTimeout(() => {
        modalOverlay.style.display = 'none';
        editModal.style.display = 'none';
    }, 300);
}

// جلوگیری از بسته شدن مودال با کلیک داخل آن
document.getElementById('editModal').addEventListener('click', function(e) {
    e.stopPropagation();
});
</script>
</body>
</html>

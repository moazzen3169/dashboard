<?php
include 'db.php';
include 'jalali_calendar.php';
session_start();

// CSRF Protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// تاریخ جلالی امروز
list($jy, $jm, $jd) = gregorian_to_jalali(date('Y'), date('m'), date('d'));
$current_jalali_date = sprintf('%04d/%02d/%02d', $jy, $jm, $jd);

$error_message = "";
$warning_message = "";
$success_message = "";

// =======================
// ذخیره خرید جدید
// =======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_purchase'])) {
    // CSRF Validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "خطای امنیتی! لطفا مجدد تلاش کنید.";
    } else {
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
                        if ($stmt->execute([$buyer_name])) {
                            $buyer_id = $conn->lastInsertId();
                            $success_message = "خریدار جدید با موفقیت اضافه شد.";
                        } else {
                            $error_message = "خطا در ثبت خریدار جدید.";
                        }
                    }
                }
            } else {
                $error_message = "نام خریدار نمی‌تواند خالی باشد.";
            }
        }

        // ادامه ذخیره محصولات
        if (empty($error_message) && !empty($_POST['products']) && is_array($_POST['products'])) {
            $conn->beginTransaction();
            
            try {
                $insert = $conn->prepare("
                    INSERT INTO purchases (buyer_id, product_name, unit_price, quantity, purchase_date, is_return)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");

                $valid_products = 0;
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
                    $valid_products++;
                }

                if ($valid_products > 0) {
                    $conn->commit();
                    $_SESSION['success'] = "خرید با موفقیت ثبت شد. تعداد محصولات: " . $valid_products;
                    header("Location: factor-products.php");
                    exit;
                } else {
                    $conn->rollBack();
                    $error_message = "هیچ محصول معتبری برای ثبت وجود ندارد.";
                }
            } catch (Exception $e) {
                $conn->rollBack();
                $error_message = "خطا در ثبت خرید: " . $e->getMessage();
            }
        } elseif (empty($error_message)) {
            $error_message = "لطفا حداقل یک محصول اضافه کنید.";
        }
    }
}

// =======================
// ویرایش خرید
// =======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_purchase'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "خطای امنیتی! لطفا مجدد تلاش کنید.";
    } else {
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
        if ($stmt->execute([$name, $price, $qty, $date, $is_ret, $id])) {
            $_SESSION['success'] = "خرید با موفقیت ویرایش شد.";
        } else {
            $error_message = "خطا در ویرایش خرید.";
        }
        
        header("Location: factor-products.php");
        exit;
    }
}

// =======================
// حذف خرید
// =======================
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    // Confirm deletion
    if (!isset($_GET['confirm'])) {
        $_SESSION['delete_id'] = $id;
        header("Location: factor-products.php?confirm_delete=" . $id);
        exit;
    }
    
    if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes' && $_SESSION['delete_id'] === $id) {
        $stmt = $conn->prepare("DELETE FROM purchases WHERE id=?");
        if ($stmt->execute([$id])) {
            $_SESSION['success'] = "خرید با موفقیت حذف شد.";
        } else {
            $error_message = "خطا در حذف خرید.";
        }
        unset($_SESSION['delete_id']);
    }
    
    header("Location: factor-products.php");
    exit;
}

// نمایش پیام موفقیت از session
if (isset($_SESSION['success'])) {
    $success_message = $_SESSION['success'];
    unset($_SESSION['success']);
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
    ORDER BY total DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Pagination for purchases
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$total_purchases = $conn->query("SELECT COUNT(*) FROM purchases")->fetchColumn();
$total_pages = ceil($total_purchases / $limit);

$purchases = $conn->prepare("
    SELECT p.*, b.name as buyer_name, (p.unit_price * p.quantity) as total_price
    FROM purchases p
    JOIN buyers b ON p.buyer_id=b.id
    ORDER BY p.id DESC
    LIMIT ? OFFSET ?
");
$purchases->bindValue(1, $limit, PDO::PARAM_INT);
$purchases->bindValue(2, $offset, PDO::PARAM_INT);
$purchases->execute();
$purchases = $purchases->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت خریدها</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@100..900&display=swap');
        * { font-family: 'Vazirmatn', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="flex">
        <!-- Sidebar -->
        <aside class="w-64  ">
            <?php include 'sidebar.php'; ?>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 p-6">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-800 mb-2">
                    <i class="fas fa-shopping-cart ml-2"></i>
                    مدیریت خریدها
                </h1>
                <p class="text-gray-600">مدیریت و ثبت خریدهای مشتریان</p>
            </div>

            <!-- Messages -->
            <?php if (!empty($success_message)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
                    <?= $success_message ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
                    <?= $error_message ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($warning_message)): ?>
                <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded-lg mb-6">
                    <?= $warning_message ?>
                </div>
            <?php endif; ?>

            <!-- Confirmation Modal for Delete -->
            <?php if (isset($_GET['confirm_delete'])): ?>
                <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div class="bg-white p-6 rounded-lg shadow-xl max-w-md w-full mx-4">
                        <h3 class="text-lg font-bold text-gray-800 mb-4">تأیید حذف</h3>
                        <p class="text-gray-600 mb-6">آیا از حذف این خرید اطمینان دارید؟</p>
                        <div class="flex gap-2 justify-end">
                            <a href="?delete=<?= $_GET['confirm_delete'] ?>&confirm=yes" 
                               class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition">
                                بله، حذف شود
                            </a>
                            <a href="factor-products.php" 
                               class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition">
                                انصراف
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- فرم ثبت خرید -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <h2 class="text-xl font-bold text-gray-800 mb-4 border-b pb-2">
                    <i class="fas fa-plus-circle ml-2"></i>
                    ثبت خرید جدید
                </h2>
                
                <form method="POST" id="purchaseForm">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    
                    <!-- Buyer Selection -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">انتخاب خریدار</label>
                            <select name="buyer_id" id="buyerSelect" 
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">-- انتخاب خریدار --</option>
                                <?php foreach($buyers as $b): ?>
                                    <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="flex items-end">
                            <button type="button" id="showNewBuyerBtn" 
                                    class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition flex items-center">
                                <i class="fas fa-user-plus ml-2"></i>
                                افزودن خریدار جدید
                            </button>
                        </div>
                    </div>

                    <!-- New Buyer Input -->
                    <div id="newBuyerInput" class="hidden mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">نام خریدار جدید</label>
                        <input type="text" name="buyer_name" placeholder="نام کامل خریدار"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <!-- Products List -->
                    <div id="products" class="space-y-4 mb-6"></div>

                    <!-- Buttons -->
                    <div class="flex gap-3">
                        <button type="button" onclick="addProductRow()" 
                                class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition flex items-center">
                            <i class="fas fa-plus ml-2"></i>
                            افزودن محصول
                        </button>
                        
                        <button type="submit" name="save_purchase" 
                                class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition flex items-center">
                            <i class="fas fa-save ml-2"></i>
                            ذخیره خرید
                        </button>
                    </div>
                </form>
            </div>

            <!-- خلاصه خریدها -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <h2 class="text-xl font-bold text-gray-800 mb-4 border-b pb-2">
                    <i class="fas fa-chart-bar ml-2"></i>
                    گزارش خریدها بر اساس خریدار
                </h2>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-right">
                        <thead class="bg-gray-100 text-gray-700">
                            <tr>
                                <th class="p-3 font-medium">نام خریدار</th>
                                <th class="p-3 font-medium">مجموع خالص</th>
                                <th class="p-3 font-medium">عملیات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach($summary as $s): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="p-3"><?= htmlspecialchars($s['name']) ?></td>
                                <td class="p-3 font-medium <?= $s['total'] < 0 ? 'text-red-600' : 'text-green-600' ?>">
                                    <?= number_format($s['total']) ?> تومان
                                </td>
                                <td class="p-3">
                                    <a href="details.php?buyer_id=<?= $s['id'] ?>" 
                                       class="text-blue-600 hover:text-blue-800 transition">
                                        مشاهده جزئیات
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- لیست همه خریدها -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4 border-b pb-2">
                    <i class="fas fa-list ml-2"></i>
                    لیست همه خریدها
                </h2>

                <!-- Pagination Info -->
                <div class="flex justify-between items-center mb-4">
                    <p class="text-sm text-gray-600">
                        نمایش <?= count($purchases) ?> از <?= $total_purchases ?> خرید
                    </p>
                    
                    <?php if ($total_pages > 1): ?>
                    <div class="flex gap-1">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>" class="px-3 py-1 bg-gray-200 rounded hover:bg-gray-300">
                                قبلی
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?= $i ?>" 
                               class="px-3 py-1 <?= $i == $page ? 'bg-blue-600 text-white' : 'bg-gray-200 hover:bg-gray-300' ?> rounded">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?= $page + 1 ?>" class="px-3 py-1 bg-gray-200 rounded hover:bg-gray-300">
                                بعدی
                            </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-right">
                        <thead class="bg-gray-100 text-gray-700">
                            <tr>
                                <th class="p-3 font-medium">خریدار</th>
                                <th class="p-3 font-medium">محصول</th>
                                <th class="p-3 font-medium">قیمت واحد</th>
                                <th class="p-3 font-medium">تعداد</th>
                                <th class="p-3 font-medium">جمع</th>
                                <th class="p-3 font-medium">تاریخ</th>
                                <th class="p-3 font-medium">وضعیت</th>
                                <th class="p-3 font-medium">عملیات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach($purchases as $p): ?>
                            <tr class="<?= $p['is_return'] ? 'bg-red-50 text-red-700' : 'hover:bg-gray-50' ?>">
                                <td class="p-3"><?= htmlspecialchars($p['buyer_name']) ?></td>
                                <td class="p-3"><?= htmlspecialchars($p['product_name']) ?></td>
                                <td class="p-3"><?= number_format($p['unit_price']) ?></td>
                                <td class="p-3"><?= number_format($p['quantity']) ?></td>
                                <td class="p-3 font-medium"><?= number_format($p['total_price']) ?></td>
                                <td class="p-3">
                                    <?php 
                                    list($gy,$gm,$gd)=explode('-',$p['purchase_date']); 
                                    list($jy,$jm,$jd)=gregorian_to_jalali($gy,$gm,$gd); 
                                    echo "$jy/$jm/$jd"; 
                                    ?>
                                </td>
                                <td class="p-3">
                                    <span class="px-2 py-1 rounded-full text-xs <?= $p['is_return'] ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' ?>">
                                        <?= $p['is_return'] ? 'مرجوعی' : 'خرید' ?>
                                    </span>
                                </td>
                                <td class="p-3">
                                    <div class="flex gap-2">
                                        <a href="?delete=<?= $p['id'] ?>" 
                                           class="text-red-600 hover:text-red-800 transition"
                                           onclick="return confirm('آیا از حذف این خرید اطمینان دارید؟')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                        <button type="button" 
                                                onclick='openEditModal(<?= json_encode($p, JSON_UNESCAPED_UNICODE) ?>)'
                                                class="text-blue-600 hover:text-blue-800 transition">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal ویرایش -->
    <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-edit ml-2"></i>
                    ویرایش خرید
                </h3>
                
                <form method="POST" id="editForm">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="edit_id" id="edit_id">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">نام محصول</label>
                            <input type="text" name="edit_product_name" id="edit_product_name" 
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">قیمت واحد</label>
                            <input type="text" name="edit_unit_price" id="edit_unit_price" 
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 price-input" required>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">تعداد</label>
                            <input type="number" name="edit_quantity" id="edit_quantity" 
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">تاریخ</label>
                            <input type="text" name="edit_purchase_date" id="edit_purchase_date" 
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">نوع</label>
                            <select name="edit_is_return" id="edit_is_return" 
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="0">خرید</option>
                                <option value="1">مرجوعی</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="flex gap-3 mt-6">
                        <button type="submit" name="update_purchase" 
                                class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                            ذخیره تغییرات
                        </button>
                        <button type="button" onclick="closeModal()" 
                                class="flex-1 bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition">
                            انصراف
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    let rowIndex = 0;
    
    function addProductRow() {
        const div = document.createElement('div');
        div.className = 'bg-gray-50 p-4 rounded-lg border border-gray-200';
        div.innerHTML = `
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-3 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">نام محصول</label>
                    <input type="text" name="products[${rowIndex}][product_name]" 
                           class="w-full border border-gray-300 rounded px-3 py-2 text-sm" 
                           placeholder="نام محصول" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">قیمت واحد</label>
                    <input type="text" class="price-input w-full border border-gray-300 rounded px-3 py-2 text-sm" 
                           name="products[${rowIndex}][unit_price]" placeholder="قیمت" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">تعداد</label>
                    <input type="number" name="products[${rowIndex}][quantity]" 
                           class="w-full border border-gray-300 rounded px-3 py-2 text-sm" 
                           placeholder="تعداد" required min="1">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">تاریخ</label>
                    <input type="text" name="products[${rowIndex}][purchase_date]" 
                           value="<?= $current_jalali_date ?>" 
                           class="w-full border border-gray-300 rounded px-3 py-2 text-sm" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">نوع</label>
                    <div class="flex gap-3">
                        <label class="flex items-center">
                            <input type="radio" name="products[${rowIndex}][is_return]" value="0" checked 
                                   class="ml-1 text-blue-600">
                            <span class="text-sm">خرید</span>
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="products[${rowIndex}][is_return]" value="1" 
                                   class="ml-1 text-red-600">
                            <span class="text-sm">مرجوعی</span>
                        </label>
                    </div>
                </div>
                <div>
                    <button type="button" onclick="this.closest('div').remove()" 
                            class="w-full bg-red-600 text-white py-2 rounded hover:bg-red-700 transition text-sm">
                        <i class="fas fa-trash ml-1"></i>
                        حذف
                    </button>
                </div>
            </div>
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
        document.getElementById("edit_unit_price").value = 
            document.getElementById("edit_unit_price").value.replace(/[^\d]/g, "");
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

        document.getElementById('editModal').classList.remove('hidden');
        document.getElementById('editModal').classList.add('flex');
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
        document.getElementById('editModal').classList.add('hidden');
        document.getElementById('editModal').classList.remove('flex');
    }

    // نمایش input خریدار جدید
    document.getElementById('showNewBuyerBtn').addEventListener('click', function() {
        document.getElementById('newBuyerInput').classList.remove('hidden');
        document.getElementById('buyerSelect').value = '';
        this.style.display = 'none';
    });

    // اضافه کردن یک ردیف محصول به صورت پیش‌فرض
    document.addEventListener('DOMContentLoaded', function() {
        addProductRow();
    });
    </script>
</body>
</html>
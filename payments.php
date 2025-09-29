<?php
include 'db.php';
include 'jalali_calendar.php';

// CSRF Protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// گرفتن تاریخ امروز شمسی
function getTodayJalali() {
    $today = date('Y-m-d');
    list($gy, $gm, $gd) = explode('-', $today);
    $jalali = gregorian_to_jalali($gy, $gm, $gd);
    return sprintf('%04d/%02d/%02d', $jalali[0], $jalali[1], $jalali[2]);
}

function formatJalaliDate($gregorianDate) {
    list($gy, $gm, $gd) = explode('-', $gregorianDate);
    $jalali = gregorian_to_jalali($gy, $gm, $gd);
    return sprintf('%04d/%02d/%02d', $jalali[0], $jalali[1], $jalali[2]);
}

// اعتبارسنجی ورودی
function validatePaymentInput($data) {
    $errors = [];
    if (empty($data['buyer_id']) || !is_numeric($data['buyer_id'])) $errors[] = "مشتری الزامی است.";
    if (empty($data['amount']) || !is_numeric(str_replace([',', '٬'], '', $data['amount'])) || floatval(str_replace([',', '٬'], '', $data['amount'])) <= 0) $errors[] = "مبلغ باید عدد مثبت باشد.";
    if (empty($data['payment_date']) || !preg_match('/^\d{4}\/\d{2}\/\d{2}$/', $data['payment_date'])) $errors[] = "تاریخ پرداختی معتبر نیست.";
    return $errors;
}

// افزودن پرداختی
function addPayment($conn, $data) {
    $errors = validatePaymentInput($data);
    if (!empty($errors)) return $errors;

    try {
        $stmt = $conn->prepare("INSERT INTO payments (buyer_id, amount, payment_date) VALUES (?, ?, ?)");
        $amount = floatval(str_replace([',', '٬'], '', $data['amount']));
        $stmt->execute([$data['buyer_id'], $amount, $data['payment_date']]);
        $_SESSION['success'] = "پرداختی با موفقیت ثبت شد.";
        header("Location: payments.php");
        exit;
    } catch (PDOException $e) {
        error_log("Add payment failed: " . $e->getMessage());
        return ["خطا در افزودن پرداختی."];
    }
}

// ویرایش پرداختی
function updatePayment($conn, $data) {
    $errors = validatePaymentInput($data);
    if (empty($data['id']) || !is_numeric($data['id'])) $errors[] = "شناسه پرداختی نامعتبر است.";
    if (!empty($errors)) return $errors;

    try {
        $stmt = $conn->prepare("UPDATE payments SET buyer_id=?, amount=?, payment_date=? WHERE id=?");
        $amount = floatval(str_replace([',', '٬'], '', $data['amount']));
        $stmt->execute([$data['buyer_id'], $amount, $data['payment_date'], $data['id']]);
        $_SESSION['success'] = "پرداختی با موفقیت ویرایش شد.";
        header("Location: payments.php");
        exit;
    } catch (PDOException $e) {
        error_log("Update payment failed: " . $e->getMessage());
        return ["خطا در ویرایش پرداختی."];
    }
}

// حذف پرداختی
function deletePayment($conn, $id) {
    if (empty($id) || !is_numeric($id)) return ["شناسه پرداختی نامعتبر است."];

    try {
        $stmt = $conn->prepare("DELETE FROM payments WHERE id=?");
        $stmt->execute([$id]);
        $_SESSION['success'] = "پرداختی با موفقیت حذف شد.";
        header("Location: payments.php");
        exit;
    } catch (PDOException $e) {
        error_log("Delete payment failed: " . $e->getMessage());
        return ["خطا در حذف پرداختی."];
    }
}

// گرفتن لیست پرداختی‌ها همراه با نام مشتری
function getPayments($conn) {
    try {
        $stmt = $conn->prepare("
            SELECT p.*, b.name AS buyer_name 
            FROM payments p 
            JOIN buyers b ON p.buyer_id = b.id
            ORDER BY p.id DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Get payments failed: " . $e->getMessage());
        return [];
    }
}

// گرفتن لیست مشتری‌ها برای انتخاب در فرم
function getBuyers($conn) {
    $stmt = $conn->prepare("SELECT * FROM buyers ORDER BY name ASC");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// تبدیل تاریخ شمسی به میلادی
function jalaliToGregorian($jalaliDate) {
    if (preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $jalaliDate, $matches)) {
        list(, $jy, $jm, $jd) = $matches;
        $gregorian = jalali_to_gregorian($jy, $jm, $jd);
        return sprintf('%04d-%02d-%02d', $gregorian[0], $gregorian[1], $gregorian[2]);
    }
    return $jalaliDate;
}

// پردازش فرم‌ها
$errors = [];
$success_message = "";

if (isset($_SESSION['success'])) {
    $success_message = $_SESSION['success'];
    unset($_SESSION['success']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "خطای امنیتی! لطفا مجدد تلاش کنید.";
    } else {
        if (isset($_POST['add_payment'])) {
            $_POST['payment_date'] = jalaliToGregorian($_POST['payment_date']);
            $errors = addPayment($conn, $_POST);
        } elseif (isset($_POST['update_payment'])) {
            $_POST['payment_date'] = jalaliToGregorian($_POST['payment_date']);
            $errors = updatePayment($conn, $_POST);
        } elseif (isset($_POST['delete_payment'])) {
            $errors = deletePayment($conn, $_POST['id']);
        }
    }
}

$payments = getPayments($conn);
$buyers = getBuyers($conn);
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت پرداختی‌ها</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@100..900&display=swap');
        * { font-family: 'Vazirmatn', sans-serif; }
        
        /* Persian Date Picker Styles */
        .pdp-farsi { font-family: 'Vazirmatn', sans-serif !important; }
    </style>
</head>
<body class="bg-gray-50  min-h-screen">
    <div class="flex w-full">
        <!-- Sidebar -->
        <aside class="w-64 ">
            <?php include 'sidebar.php'; ?>
        </aside>

        <!-- Main Content -->
        <div class="flex-1  p-6">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-800 mb-2">
                    <i class="fas fa-money-bill-wave ml-2"></i>
                    مدیریت پرداختی‌ها
                </h1>
                <p class="text-gray-600">مدیریت و ثبت پرداخت‌های مشتریان</p>
            </div>

            <!-- Messages -->
            <?php if (!empty($success_message)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
                    <?= $success_message ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
                    <?php foreach($errors as $error): ?>
                        <p><?= $error ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- فرم ثبت پرداختی جدید -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <h2 class="text-xl font-bold text-gray-800 mb-4 border-b pb-2">
                    <i class="fas fa-plus-circle ml-2"></i>
                    ثبت پرداختی جدید
                </h2>
                
                <form method="POST" id="paymentForm">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">مشتری</label>
                            <select name="buyer_id" required 
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">-- انتخاب مشتری --</option>
                                <?php foreach ($buyers as $b): ?>
                                    <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">مبلغ پرداختی (تومان)</label>
                            <input type="text" name="amount" id="amount" required 
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 price-input"
                                   placeholder="مبلغ را وارد کنید">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">تاریخ پرداختی</label>
                            <input type="text" name="payment_date" class="jalali-date w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                   value="<?= getTodayJalali() ?>" required>
                        </div>
                    </div>
                    
                    <div class="flex justify-end">
                        <button type="submit" name="add_payment" 
                                class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition flex items-center">
                            <i class="fas fa-plus ml-2"></i>
                            ثبت پرداختی
                        </button>
                    </div>
                </form>
            </div>

            <!-- جدول پرداختی‌ها -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4 border-b pb-2">
                    <i class="fas fa-list ml-2"></i>
                    لیست پرداختی‌ها
                </h2>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-right">
                        <thead class="bg-gray-100 text-gray-700">
                            <tr>
                                <th class="p-3 font-medium">کد</th>
                                <th class="p-3 font-medium">مشتری</th>
                                <th class="p-3 font-medium">مبلغ</th>
                                <th class="p-3 font-medium">تاریخ پرداختی</th>
                                <th class="p-3 font-medium">عملیات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($payments as $p): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="p-3 font-mono"><?= $p['id'] ?></td>
                                    <td class="p-3"><?= htmlspecialchars($p['buyer_name']) ?></td>
                                    <td class="p-3 font-medium text-green-600"><?= number_format($p['amount']) ?> تومان</td>
                                    <td class="p-3"><?= formatJalaliDate($p['payment_date']) ?></td>
                                    <td class="p-3">
                                        <div class="flex gap-2">
                                            <button type="button" 
                                                    onclick='openEditModal(<?= json_encode(array_merge($p, ["jalali_date" => formatJalaliDate($p["payment_date"])]), JSON_UNESCAPED_UNICODE) ?>)'
                                                    class="bg-blue-600 text-white p-2 rounded hover:bg-blue-700 transition">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" 
                                                    onclick="openDeleteModal(<?= $p['id'] ?>)"
                                                    class="bg-red-600 text-white p-2 rounded hover:bg-red-700 transition">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($payments)): ?>
                                <tr>
                                    <td colspan="5" class="p-4 text-center text-gray-500">
                                        <i class="fas fa-inbox text-3xl mb-2 block"></i>
                                        هیچ پرداختی ثبت نشده است
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- مودال ویرایش -->
    <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
            <div class="p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-edit ml-2"></i>
                    ویرایش پرداختی
                </h3>
                
                <form method="POST" id="editForm">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">مشتری</label>
                            <select name="buyer_id" id="edit_buyer_id" required 
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <?php foreach ($buyers as $b): ?>
                                    <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">مبلغ (تومان)</label>
                            <input type="text" name="amount" id="edit_amount" required 
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 price-input">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">تاریخ پرداختی</label>
                            <input type="text" name="payment_date" id="edit_date" required 
                                   class="jalali-date w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>
                    
                    <div class="flex gap-3 mt-6">
                        <button type="submit" name="update_payment" 
                                class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                            ذخیره تغییرات
                        </button>
                        <button type="button" onclick="closeModal('editModal')" 
                                class="flex-1 bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition">
                            انصراف
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- مودال حذف -->
    <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
            <div class="p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-exclamation-triangle ml-2 text-yellow-500"></i>
                    حذف پرداختی
                </h3>
                
                <p class="text-gray-600 mb-6">آیا مطمئن هستید که می‌خواهید این پرداختی را حذف کنید؟</p>
                
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="id" id="delete_id">
                    
                    <div class="flex gap-3">
                        <button type="submit" name="delete_payment" 
                                class="flex-1 bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition">
                            بله، حذف کن
                        </button>
                        <button type="button" onclick="closeModal('deleteModal')" 
                                class="flex-1 bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition">
                            لغو
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
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
        document.getElementById("paymentForm").addEventListener("submit", function() {
            document.querySelectorAll(".price-input").forEach(inp => {
                inp.value = inp.value.replace(/[^\d]/g, "");
            });
        });
        
        document.getElementById("editForm").addEventListener("submit", function() {
            document.getElementById("edit_amount").value = document.getElementById("edit_amount").value.replace(/[^\d]/g, "");
        });

        function openEditModal(payment) {
            document.getElementById('edit_id').value = payment.id;
            document.getElementById('edit_buyer_id').value = payment.buyer_id;
            document.getElementById('edit_amount').value = payment.amount.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
            document.getElementById('edit_date').value = payment.jalali_date;
            
            document.getElementById('editModal').classList.remove('hidden');
            document.getElementById('editModal').classList.add('flex');
        }

        function openDeleteModal(id) {
            document.getElementById('delete_id').value = id;
            document.getElementById('deleteModal').classList.remove('hidden');
            document.getElementById('deleteModal').classList.add('flex');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
            document.getElementById(modalId).classList.remove('flex');
        }

        // Initialize date pickers
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Persian Date Picker
            const dateInputs = document.querySelectorAll('.jalali-date');
            dateInputs.forEach(input => {
                input.addEventListener('focus', function() {
                    // You can integrate a Persian date picker library here
                    // For now, we'll just show a native date picker fallback
                    this.type = 'date';
                });
                
                input.addEventListener('blur', function() {
                    if (this.value === '') {
                        this.type = 'text';
                    }
                });
            });
        });
    </script>
</body>
</html>
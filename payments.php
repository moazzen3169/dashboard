<?php
include 'db.php';
include 'jalali_calendar.php'; // اینجا باید gregorian_to_jalali و jalaali_to_gregorian وجود داشته باشه

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
    if (empty($data['amount']) || !is_numeric($data['amount']) || $data['amount'] <= 0) $errors[] = "مبلغ باید عدد مثبت باشد.";
    if (empty($data['payment_date']) || !strtotime($data['payment_date'])) $errors[] = "تاریخ پرداختی معتبر نیست.";
    return $errors;
}

// افزودن پرداختی
function addPayment($conn, $data) {
    $errors = validatePaymentInput($data);
    if (!empty($errors)) return $errors;

    try {
        $stmt = $conn->prepare("INSERT INTO payments (buyer_id, amount, payment_date) VALUES (?, ?, ?)");
        $stmt->execute([$data['buyer_id'], $data['amount'], $data['payment_date']]);
        header("Location: payments.php?success=added");
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
        $stmt->execute([$data['buyer_id'], $data['amount'], $data['payment_date'], $data['id']]);
        header("Location: payments.php?success=updated");
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
        header("Location: payments.php?success=deleted");
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
        $gregorian = jalaali_to_gregorian($jy, $jm, $jd);
        return sprintf('%04d-%02d-%02d', $gregorian[0], $gregorian[1], $gregorian[2]);
    }
    return $jalaliDate;
}

// پردازش فرم‌ها
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

$payments = getPayments($conn);
$buyers = getBuyers($conn);
?>

<!DOCTYPE html>
<html lang="fa">
<head>
  <meta charset="UTF-8">
  <title>مدیریت پرداختی‌ها</title>
  <link rel="stylesheet" href="css/design-system.css">
  <link rel="stylesheet" href="css/sidebar.css">
  <link rel="stylesheet" href="css/payments.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/js/persian-datepicker.min.js"></script>
  <script src="js/persian-datepicker-init.js"></script>
</head>

<body class="dashboard-container">
  <aside class="sidebar">
    <?php include 'sidebar.php'; ?>
  </aside>

  <div class="main-content">
    <header class="top-bar">
      <h1 class="h1"><i class="fas fa-money-bill-wave"></i> مدیریت پرداختی‌ها</h1>
    </header>

    <div class="content-area">
      <h2 class="h2"><i class="fas fa-plus"></i> ثبت پرداختی جدید</h2>

      <!-- فرم ثبت پرداختی -->
      <form method="POST" class="form">
        <div class="form-row">
          <div class="field">
            <label>مشتری:</label>
            <select name="buyer_id" required>
              <option value="">-- انتخاب مشتری --</option>
              <?php foreach ($buyers as $b): ?>
                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label>مبلغ پرداختی (تومان):</label>
            <input type="text" name="amount" id="amount" required>
            </div>
        </div>
        <div class="form-row">
          <div class="field">
            <label>تاریخ پرداختی:</label>
            <input type="text" name="payment_date" class="jalali-date" value="<?= getTodayJalali() ?>" required>
          </div>
        </div>
        <div class="form-row submit-row">
          <button type="submit" name="add_payment" class="btn"><i class="fas fa-plus"></i> ثبت پرداختی</button>
        </div>
      </form>

      <!-- جدول نمایش پرداختی‌ها -->
      <table class="table">
        <thead>
          <tr>
            <th>کد</th>
            <th>مشتری</th>
            <th>مبلغ</th>
            <th>تاریخ پرداختی</th>
            <th>عملیات</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($payments as $p): ?>
            <tr>
              <td><?= $p['id'] ?></td>
              <td><?= htmlspecialchars($p['buyer_name']) ?></td>
              <td><?= number_format($p['amount']) ?> تومان</td>
              <td><?= formatJalaliDate($p['payment_date']) ?></td>
              <td>
                <button class="btn-small" onclick='openEditModal(<?= json_encode(array_merge($p, ["jalali_date" => formatJalaliDate($p["payment_date"])]), JSON_UNESCAPED_UNICODE) ?>)'><i class="fas fa-edit"></i></button>
                <button class="btn-small danger" onclick="openDeleteModal(<?= $p['id'] ?>)"><i class="fas fa-trash"></i></button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- مودال ویرایش -->
  <div class="modal" id="editModal">
    <div class="modal-content">
      <h2><i class="fas fa-edit"></i> ویرایش پرداختی</h2>
      <form method="POST" class="form">
        <input type="hidden" name="id" id="edit_id">
        <div class="form-row">
          <div class="field">
            <label>مشتری:</label>
            <select name="buyer_id" id="edit_buyer_id" required>
              <?php foreach ($buyers as $b): ?>
                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label>مبلغ:</label>
            <input type="text" name="amount" id="amount" required>
            </div>
        </div>
        <div class="form-row">
          <div class="field">
            <label>تاریخ پرداختی:</label>
            <input type="text" name="payment_date" id="edit_date" class="jalali-date" required>
          </div>
        </div>
        <div class="form-row submit-row">
          <button type="submit" name="update_payment" class="btn"><i class="fas fa-check"></i> ذخیره تغییرات</button>
          <button type="button" class="btn-small danger" onclick="closeModal('editModal')"><i class="fas fa-times"></i> لغو</button>
        </div>
      </form>
    </div>
  </div>

  <!-- مودال حذف -->
  <div class="modal" id="deleteModal">
    <div class="modal-content">
      <h2><i class="fas fa-exclamation-triangle"></i> حذف پرداختی</h2>
      <p>آیا مطمئن هستید که می‌خواهید این پرداختی را حذف کنید؟</p>
      <form method="POST">
        <input type="hidden" name="id" id="delete_id">
        <button type="submit" name="delete_payment" class="btn-small danger"><i class="fas fa-trash"></i> بله، حذف کن</button>
        <button type="button" class="btn-small" onclick="closeModal('deleteModal')"><i class="fas fa-times"></i> لغو</button>
      </form>
    </div>
  </div>

  <script>
    function openEditModal(payment) {
      document.getElementById('edit_id').value = payment.id;
      document.getElementById('edit_buyer_id').value = payment.buyer_id;
      document.getElementById('edit_amount').value = payment.amount;
      document.getElementById('edit_date').value = payment.jalali_date;
      document.getElementById('editModal').classList.add('active');
    }
    function openDeleteModal(id) {
      document.getElementById('delete_id').value = id;
      document.getElementById('deleteModal').classList.add('active');
    }
    function closeModal(id) {
      document.getElementById(id).classList.remove('active');
    }
  </script>

<script>
document.addEventListener("DOMContentLoaded", function () {
  const amountInputs = document.querySelectorAll('input[name="amount"], #edit_amount');

  amountInputs.forEach(function (input) {
    input.addEventListener("input", function () {
      // فقط رقم رو نگه می‌داریم (اعداد فارسی هم پشتیبانی بشه)
      let value = this.value.replace(/[^\d]/g, "");

      // اگه مقداری وجود داشت، فرمت هزارگان بزن
      if (value !== "") {
        this.value = new Intl.NumberFormat('en-US').format(value);
      } else {
        this.value = "";
      }
    });
  });

  // قبل از ارسال فرم، کاماها حذف بشن
  document.querySelectorAll("form").forEach(function (form) {
    form.addEventListener("submit", function () {
      amountInputs.forEach(function (input) {
        input.value = input.value.replace(/,/g, "");
      });
    });
  });
});
</script>

  
</body>
</html>

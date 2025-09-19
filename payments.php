<?php
include 'db.php'; // اتصال به دیتابیس
include 'jalali_calendar.php'; // توابع تبدیل تاریخ شمسی

// Get today's Jalali date
function getTodayJalali() {
    $today = date('Y-m-d');
    list($gy, $gm, $gd) = explode('-', $today);
    $jalali = gregorian_to_jalali($gy, $gm, $gd);
    return sprintf('%04d/%02d/%02d', $jalali[0], $jalali[1], $jalali[2]);
}

// Convert Gregorian date to Jalali string
function formatJalaliDate($gregorianDate) {
    list($gy, $gm, $gd) = explode('-', $gregorianDate);
    $jalali = gregorian_to_jalali($gy, $gm, $gd);
    return sprintf('%04d/%02d/%02d', $jalali[0], $jalali[1], $jalali[2]);
}

// Function to validate payment input
function validatePaymentInput($data) {
    $errors = [];
    if (empty($data['target'])) $errors[] = "مقصد پرداختی الزامی است.";
    if (empty($data['amount']) || !is_numeric($data['amount']) || $data['amount'] < 0) $errors[] = "مبلغ باید عدد مثبت باشد.";
    if (empty($data['payment_date']) || !strtotime($data['payment_date'])) $errors[] = "تاریخ پرداختی معتبر نیست.";
    return $errors;
}

// Function to add payment
function addPayment($conn, $data) {
    $errors = validatePaymentInput($data);
    if (!empty($errors)) return $errors;

    try {
        $stmt = $conn->prepare("INSERT INTO payments (target, amount, payment_date) VALUES (?, ?, ?)");
        $stmt->execute([$data['target'], $data['amount'], $data['payment_date']]);
        header("Location: payments.php?success=added");
        exit;
    } catch (PDOException $e) {
        error_log("Add payment failed: " . $e->getMessage());
        return ["خطا در افزودن پرداختی."];
    }
}

// Function to update payment
function updatePayment($conn, $data) {
    $errors = validatePaymentInput($data);
    if (empty($data['id']) || !is_numeric($data['id'])) $errors[] = "شناسه پرداختی نامعتبر.";
    if (!empty($errors)) return $errors;

    try {
        $stmt = $conn->prepare("UPDATE payments SET target=?, amount=?, payment_date=? WHERE id=?");
        $stmt->execute([$data['target'], $data['amount'], $data['payment_date'], $data['id']]);
        header("Location: payments.php?success=updated");
        exit;
    } catch (PDOException $e) {
        error_log("Update payment failed: " . $e->getMessage());
        return ["خطا در ویرایش پرداختی."];
    }
}

// Function to delete payment
function deletePayment($conn, $id) {
    if (empty($id) || !is_numeric($id)) return ["شناسه پرداختی نامعتبر."];

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

// Function to get payments
function getPayments($conn) {
    try {
        $stmt = $conn->prepare("SELECT * FROM payments ORDER BY id DESC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Get payments failed: " . $e->getMessage());
        return [];
    }
}

function jalaliToGregorian($jalaliDate) {
    if (preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $jalaliDate, $matches)) {
        list(, $jy, $jm, $jd) = $matches;
        $gregorian = jalaali_to_gregorian($jy, $jm, $jd);
        return sprintf('%04d-%02d-%02d', $gregorian[0], $gregorian[1], $gregorian[2]);
    }
    return $jalaliDate;
}

function jalaali_to_gregorian($jy, $jm, $jd) {
    $jy = (int)$jy - 979;
    $jm = (int)$jm - 1;
    $jd = (int)$jd - 1;

    $j_day_no = 365 * $jy + (int)($jy / 33) * 8 + (int)((($jy % 33) + 3) / 4);
    for ($i = 0; $i < $jm; ++$i) {
        $j_day_no += ($i < 6) ? 31 : 30;
    }
    $j_day_no += $jd;

    $g_day_no = $j_day_no + 79;

    $gy = 1600 + 400 * (int)($g_day_no / 146097);
    $g_day_no %= 146097;

    $leap = true;
    if ($g_day_no >= 36525) {
        $g_day_no--;
        $gy += 100 * (int)($g_day_no / 36524);
        $g_day_no %= 36524;

        if ($g_day_no >= 365) {
            $g_day_no++;
        } else {
            $leap = false;
        }
    }

    $gy += 4 * (int)($g_day_no / 1461);
    $g_day_no %= 1461;

    if ($g_day_no >= 366) {
        $leap = false;
        $g_day_no--;
        $gy += (int)($g_day_no / 365);
        $g_day_no = $g_day_no % 365;
    }

    $gm = 0;
    $gd = 0;
    $g_month_days = [31, ($leap ? 29 : 28), 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

    for ($i = 0; $i < 12; $i++) {
        if ($g_day_no < $g_month_days[$i]) {
            $gm = $i + 1;
            $gd = $g_day_no + 1;
            break;
        }
        $g_day_no -= $g_month_days[$i];
    }

    return [$gy, $gm, $gd];
}

// Handle POST requests
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
?>

<!DOCTYPE html>
<html lang="fa">
<head>
  <meta charset="UTF-8">
  <title><i class="fas fa-money-bill-wave"></i> مدیریت پرداختی‌ها</title>
  <link rel="stylesheet" href="css/design-system.css">
  <link rel="stylesheet" href="css/sidebar.css">
  <link rel="stylesheet" href="css/payments.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/jalaali-js@1.2.6/dist/jalaali.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/js/persian-datepicker.min.js"></script>
  <script src="js/persian-datepicker-init.js"></script>
  <script src="js/print.js"></script>
</head>

<body class="dashboard-container">
  <aside class="sidebar">
    <?php include 'sidebar.php'; ?>
  </aside>

  <div class="main-content">
    <header class="top-bar">
      <h1 class="h1"><i class="fas fa-money-bill-wave"></i> مدیریت پرداختی‌ها</h1>
      <div>
        <!-- Placeholder for actions -->
      </div>
    </header>

    <div class="content-area">
      <h2 class="h2"><i class="fas fa-plus"></i> ثبت پرداختی جدید</h2>

    <!-- فرم ثبت پرداختی -->
    <form method="POST" class="form">
      <div class="form-row">
        <div class="field">
          <label>مقصد پرداختی:</label>
          <input type="text" name="target" required>
        </div>
        <div class="field">
          <label>مبلغ پرداختی (تومان):</label>
          <input type="number" name="amount" required>
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
          <th>مقصد پرداختی</th>
          <th>مبلغ</th>
          <th>تاریخ پرداختی</th>
          <th>عملیات</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($payments as $p): ?>
          <tr>
            <td><?= $p['id'] ?></td>
            <td><?= htmlspecialchars($p['target']) ?></td>
            <td><?= number_format($p['amount']) ?> تومان</td>
            <td><?= formatJalaliDate($p['payment_date']) ?></td>
            <td>
              <button class="btn-small" onclick='openEditModal(<?= json_encode(array_merge($p, ['jalali_date' => formatJalaliDate($p['payment_date'])]), JSON_UNESCAPED_UNICODE) ?>)'><i class="fas fa-edit"></i></button>
              <button class="btn-small danger" onclick="openDeleteModal(<?= $p['id'] ?>)"><i class="fas fa-trash"></i></button>
              <button onclick='printPaymentReceipt(<?= json_encode(array_merge($p, ['jalali_date' => formatJalaliDate($p['payment_date'])]), JSON_UNESCAPED_UNICODE) ?>)' class="btn-small"><i class="fas fa-print"></i></button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </main>

  <!-- مودال ویرایش -->
  <div class="modal" id="editModal">
    <div class="modal-content">
      <h2><i class="fas fa-edit"></i> ویرایش پرداختی</h2>
      <form method="POST" class="form">
        <input type="hidden" name="id" id="edit_id">
        <div class="form-row">
          <div class="field">
            <label>مقصد پرداختی:</label>
            <input type="text" name="target" id="edit_target" required>
          </div>
          <div class="field">
            <label>مبلغ:</label>
            <input type="number" name="amount" id="edit_amount" required>
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
    // باز کردن مودال ویرایش
    function openEditModal(payment) {
      document.getElementById('edit_id').value = payment.id;
      document.getElementById('edit_target').value = payment.target;
      document.getElementById('edit_amount').value = payment.amount;
      document.getElementById('edit_date').value = payment.jalali_date;
      document.getElementById('editModal').classList.add('active');
    }

    // باز کردن مودال حذف
    function openDeleteModal(id) {
      document.getElementById('delete_id').value = id;
      document.getElementById('deleteModal').classList.add('active');
    }

    // بستن مودال
    function closeModal(id) {
      document.getElementById(id).classList.remove('active');
    }

    // پرینت ردیف
    function printRow(btn) {
      var row = btn.closest("tr");
      var newWin = window.open("");
      newWin.document.write("<html><head><title>فاکتور پرداختی</title></head><body>");
      newWin.document.write("<table border='1' style='border-collapse:collapse'>" + row.outerHTML + "</table>");
      newWin.document.write("</body></html>");
      newWin.print();
      newWin.close();
    }
  </script>

</body>
</html>

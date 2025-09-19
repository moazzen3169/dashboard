<?php
include 'db.php'; // اتصال به دیتابیس

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

// Handle POST requests
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_payment'])) {
        $errors = addPayment($conn, $_POST);
    } elseif (isset($_POST['update_payment'])) {
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
          <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" required>
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
            <td><?= $p['payment_date'] ?></td>
            <td>
              <button class="btn-small" onclick='openEditModal(<?= json_encode($p, JSON_UNESCAPED_UNICODE) ?>)'><i class="fas fa-edit"></i> ویرایش</button>
              <button class="btn-small danger" onclick="openDeleteModal(<?= $p['id'] ?>)"><i class="fas fa-trash"></i> حذف</button>
              <button onclick="printRow(this)" class="btn-small"><i class="fas fa-print"></i> پرینت</button>
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
            <input type="date" name="payment_date" id="edit_date" required>
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
      document.getElementById('edit_date').value = payment.payment_date;
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

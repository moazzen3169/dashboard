<?php
include 'db.php'; // اتصال به دیتابیس

// Function to validate product input
function validateProductInput($data) {
    $errors = [];
    if (empty($data['name'])) $errors[] = "نام محصول الزامی است.";
    if (empty($data['color'])) $errors[] = "رنگ الزامی است.";
    if (empty($data['size'])) $errors[] = "سایز الزامی است.";
    if (empty($data['sale_date']) || !strtotime($data['sale_date'])) $errors[] = "تاریخ فروش معتبر نیست.";
    if (empty($data['price']) || !is_numeric($data['price']) || $data['price'] < 0) $errors[] = "قیمت باید عدد مثبت باشد.";
    return $errors;
}

// Function to add product
function addProduct($conn, $data) {
    $errors = validateProductInput($data);
    if (!empty($errors)) return $errors;

    try {
        $stmt = $conn->prepare("INSERT INTO products (name, color, size, sale_date, price) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$data['name'], $data['color'], $data['size'], $data['sale_date'], $data['price']]);
        header("Location: products.php?success=added");
        exit;
    } catch (PDOException $e) {
        error_log("Add product failed: " . $e->getMessage());
        return ["خطا در افزودن محصول."];
    }
}

// Function to update product
function updateProduct($conn, $data) {
    $errors = validateProductInput($data);
    if (empty($data['id']) || !is_numeric($data['id'])) $errors[] = "شناسه محصول نامعتبر.";
    if (!empty($errors)) return $errors;

    try {
        $stmt = $conn->prepare("UPDATE products SET name=?, color=?, size=?, sale_date=?, price=? WHERE id=?");
        $stmt->execute([$data['name'], $data['color'], $data['size'], $data['sale_date'], $data['price'], $data['id']]);
        header("Location: products.php?success=updated");
        exit;
    } catch (PDOException $e) {
        error_log("Update product failed: " . $e->getMessage());
        return ["خطا در ویرایش محصول."];
    }
}

// Function to delete product
function deleteProduct($conn, $id) {
    if (empty($id) || !is_numeric($id)) return ["شناسه محصول نامعتبر."];

    try {
        $stmt = $conn->prepare("DELETE FROM products WHERE id=?");
        $stmt->execute([$id]);
        header("Location: products.php?success=deleted");
        exit;
    } catch (PDOException $e) {
        error_log("Delete product failed: " . $e->getMessage());
        return ["خطا در حذف محصول."];
    }
}

// Function to get products
function getProducts($conn) {
    try {
        $stmt = $conn->prepare("SELECT * FROM products ORDER BY id DESC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Get products failed: " . $e->getMessage());
        return [];
    }
}

// Handle POST requests
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_product'])) {
        $errors = addProduct($conn, $_POST);
    } elseif (isset($_POST['update_product'])) {
        $errors = updateProduct($conn, $_POST);
    } elseif (isset($_POST['delete_product'])) {
        $errors = deleteProduct($conn, $_POST['id']);
    }
}

$products = getProducts($conn);
?>

<!DOCTYPE html>
<html lang="fa">
<head>
  <meta charset="UTF-8">
  <title><i class="fas fa-box"></i> مدیریت محصولات</title>
  <link rel="stylesheet" href="css/design-system.css">
  <link rel="stylesheet" href="css/sidebar.css">
  <link rel="stylesheet" href="css/products.css">
</head>

<body class="dashboard-container">
  <aside class="sidebar">
    <?php include 'sidebar.php'; ?>
  </aside>

  <div class="main-content">
    <header class="top-bar">
      <h1 class="h1"><i class="fas fa-box"></i> مدیریت محصولات</h1>
      <div>
        <!-- Placeholder for actions -->
      </div>
    </header>

    <div class="content-area">
      <?php if (!empty($errors)): ?>
        <div class="error-messages">
          <ul>
            <?php foreach ($errors as $error): ?>
              <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if (isset($_GET['success'])): ?>
        <div class="success-message">
          <?php if ($_GET['success'] === 'added'): ?> محصول با موفقیت اضافه شد. <?php endif; ?>
          <?php if ($_GET['success'] === 'updated'): ?> محصول با موفقیت ویرایش شد. <?php endif; ?>
          <?php if ($_GET['success'] === 'deleted'): ?> محصول با موفقیت حذف شد. <?php endif; ?>
        </div>
      <?php endif; ?>

      <h2 class="h2"><i class="fas fa-plus"></i> ثبت محصول جدید</h2>

    <!-- فرم ثبت محصول -->
    <form method="POST" class="form">
      <div class="form-row">
        <div class="field">
          <label>نام محصول:</label>
          <select name="name" required>
            <option value="تی‌شرت">تی‌شرت</option>
            <option value="شلوار">شلوار</option>
            <option value="کفش">کفش</option>
            <option value="هودی">هودی</option>
          </select>
        </div>
        <div class="field">
          <label>رنگ:</label>
          <select name="color" required>
            <option value="سفید">سفید</option>
            <option value="مشکی">مشکی</option>
            <option value="آبی">آبی</option>
            <option value="قرمز">قرمز</option>
          </select>
        </div>
        <div class="field">
          <label>سایز:</label>
          <select name="size" required>
            <option value="S">S</option>
            <option value="M">M</option>
            <option value="L">L</option>
            <option value="XL">XL</option>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="field">
          <label>تاریخ فروش:</label>
          <input type="date" name="sale_date" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="field">
          <label>قیمت (تومان):</label>
          <input type="number" name="price" required>
        </div>
      </div>
      <div class="form-row submit-row">
        <button type="submit" name="add_product" class="btn"><i class="fas fa-plus"></i> ثبت محصول</button>
      </div>
    </form>

    <!-- جدول نمایش محصولات -->
    <table class="table">
      <thead>
        <tr>
          <th>کد</th>
          <th>نام محصول</th>
          <th>رنگ</th>
          <th>سایز</th>
          <th>تاریخ فروش</th>
          <th>قیمت</th>
          <th>عملیات</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($products as $p): ?>
          <tr>
            <td><?= $p['id'] ?></td>
            <td><?= htmlspecialchars($p['name']) ?></td>
            <td><?= htmlspecialchars($p['color']) ?></td>
            <td><?= htmlspecialchars($p['size']) ?></td>
            <td><?= $p['sale_date'] ?></td>
            <td><?= number_format($p['price']) ?> تومان</td>
            <td>
              <button class="btn-small" onclick='openEditModal(<?= json_encode($p, JSON_UNESCAPED_UNICODE) ?>)'><i class="fas fa-edit"></i></button>
              <button class="btn-small danger" onclick="openDeleteModal(<?= $p['id'] ?>)"><i class="fas fa-trash"></i></button>
              <button onclick="printRow(this)" class="btn-small"><i class="fas fa-print"></i></button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </main>

  <!-- مودال ویرایش -->
  <div class="modal" id="editModal">
    <div class="modal-content">
      <h2><i class="fas fa-edit"></i> ویرایش محصول</h2>
      <form method="POST" class="form">
        <input type="hidden" name="id" id="edit_id">
        <div class="form-row">
          <div class="field">
            <label>نام محصول:</label>
            <input type="text" name="name" id="edit_name" required>
          </div>
          <div class="field">
            <label>رنگ:</label>
            <input type="text" name="color" id="edit_color" required>
          </div>
          <div class="field">
            <label>سایز:</label>
            <input type="text" name="size" id="edit_size" required>
          </div>
        </div>
        <div class="form-row">
          <div class="field">
            <label>تاریخ فروش:</label>
            <input type="date" name="sale_date" id="edit_date" required>
          </div>
          <div class="field">
            <label>قیمت (تومان):</label>
            <input type="number" name="price" id="edit_price" required>
          </div>
        </div>
        <div class="form-row submit-row">
          <button type="submit" name="update_product" class="btn"><i class="fas fa-check"></i> ذخیره تغییرات</button>
          <button type="button" class="btn-small danger" onclick="closeModal('editModal')"><i class="fas fa-times"></i> لغو</button>
        </div>
      </form>
    </div>
  </div>

  <!-- مودال حذف -->
  <div class="modal" id="deleteModal">
    <div class="modal-content">
      <h2><i class="fas fa-exclamation-triangle"></i> حذف محصول</h2>
      <p>آیا مطمئن هستید که می‌خواهید این محصول را حذف کنید؟</p>
      <form method="POST">
        <input type="hidden" name="id" id="delete_id">
        <button type="submit" name="delete_product" class="btn-small danger"><i class="fas fa-trash"></i> بله، حذف کن</button>
        <button type="button" class="btn-small" onclick="closeModal('deleteModal')"><i class="fas fa-times"></i> لغو</button>
      </form>
    </div>
  </div>

  <script>
    // باز کردن مودال ویرایش
    function openEditModal(product) {
      document.getElementById('edit_id').value = product.id;
      document.getElementById('edit_name').value = product.name;
      document.getElementById('edit_color').value = product.color;
      document.getElementById('edit_size').value = product.size;
      document.getElementById('edit_date').value = product.sale_date;
      document.getElementById('edit_price').value = product.price;
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
      newWin.document.write("<html><head><title>فاکتور محصول</title></head><body>");
      newWin.document.write("<table border='1' style='border-collapse:collapse'>" + row.outerHTML + "</table>");
      newWin.document.write("</body></html>");
      newWin.print();
      newWin.close();
    }
  </script>

</body>
</html>

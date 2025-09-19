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

// Convert Jalali date (YYYY/MM/DD) to Gregorian date (YYYY-MM-DD)
function jalaliToGregorian($jalaliDate) {
    if (preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $jalaliDate, $matches)) {
        list(, $jy, $jm, $jd) = $matches;
        $gregorian = jalali_to_gregorian($jy, $jm, $jd);
        return sprintf('%04d-%02d-%02d', $gregorian[0], $gregorian[1], $gregorian[2]);
    }
    return $jalaliDate; // fallback
}

// Convert Gregorian date to Jalali string
function formatJalaliDate($gregorianDate) {
    list($gy, $gm, $gd) = explode('-', $gregorianDate);
    $jalali = gregorian_to_jalali($gy, $gm, $gd);
    return sprintf('%04d/%02d/%02d', $jalali[0], $jalali[1], $jalali[2]);
}

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
        $data = $_POST;
        $data['sale_date'] = jalaliToGregorian($data['sale_date']);
        $errors = addProduct($conn, $data);
    } elseif (isset($_POST['update_product'])) {
        $data = $_POST;
        $data['sale_date'] = jalaliToGregorian($data['sale_date']);
        $errors = updateProduct($conn, $data);
    } elseif (isset($_POST['delete_product'])) {
        $errors = deleteProduct($conn, $_POST['id']);
    }
}

$products = getProducts($conn);

// Add Jalali date to each product
foreach ($products as &$p) {
    $p['sale_date_jalali'] = formatJalaliDate($p['sale_date']);
}
?>

<!DOCTYPE html>
<html lang="fa">
<head>
  <meta charset="UTF-8">
  <title><i class="fas fa-box"></i> مدیریت محصولات</title>
  <link rel="stylesheet" href="css/design-system.css">
  <link rel="stylesheet" href="css/sidebar.css">
  <link rel="stylesheet" href="css/products.css">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/jalaali-js@1.2.6/dist/jalaali.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/js/persian-datepicker.min.js"></script>
  <script src="js/persian-datepicker-init.js"></script>
  <script src="js/print.js"></script>
  <script src="js/product-name-modal.js"></script>

  <style>
    /* Modal styles */
    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0; top: 0; width: 100%; height: 100%;
      overflow: auto;
      background-color: rgba(0,0,0,0.4);
    }
    .modal.active {
      display: block;
    }
    .modal-content {
      background-color: #fefefe;
      margin: 10% auto;
      padding: 20px;
      border: 1px solid #888;
      width: 400px;
      border-radius: 8px;
    }
  </style>
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
      <select name="name" id="product_name_select" required>
        <?php
          // Fetch product names from products_name table
          $stmt = $conn->prepare("SELECT name FROM `products-name` ORDER BY id DESC");
          $stmt->execute();
          $productNames = $stmt->fetchAll(PDO::FETCH_COLUMN);
          foreach ($productNames as $productName) {
            echo '<option value="' . htmlspecialchars($productName) . '">' . htmlspecialchars($productName) . '</option>';
          }
        ?>
      </select>
      <button type="button" id="showAddProductNameModalBtn" class="btn-small" style="margin-top: 5px;">+ افزودن نام محصول جدید</button>
        </div>
        <div class="field">
          <label>رنگ:</label>
          <select name="color" required>
            <option value="سفید">سفید</option>
            <option value="مشکی">مشکی</option>
            <option value="خاکستری">خاکستری</option>
            <option value="نارنجی">نارنجی</option>
            <option value="زرد">زرد</option>
            <option value="سبز">سبز</option>
            <option value="آبی تیره">آبی تیره</option>
            <option value="آبی روشن">آبی روشن</option>
            <option value="قرمز">قرمز</option>
            <option value="بنفش">بنفش</option>
            <option value="صورتی">صورتی</option>
            <option value="قهوه‌ای">قهوه‌ای</option>
            <option value="کرم">کرم</option>
            <option value="بژ">بژ</option>
            <option value="زیتونی">زیتونی</option>
          </select>
        </div>
        <div class="field">
          <label>سایز:</label>
          <select name="size" required>
            <option value="36">36</option>
            <option value="38">38</option>
            <option value="40">40</option>
            <option value="42">42</option>
            <option value="44">44</option>
            <option value="46">46</option>
            <option value="48">48</option>
            <option value="50">50</option>
            <option value="52">52</option>
            <option value="54">54</option>
            <option value="56">56</option>
            <option value="58">58</option>
            <option value="60">60</option>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="field">
          <label>تاریخ فروش:</label>
          <input type="text" name="sale_date" class="jalali-date" value="<?= getTodayJalali() ?>" required>
        </div>
        <div class="field">
          <label>قیمت (تومان):</label>
          <input type="text" name="price" required>
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
            <td><?= $p['sale_date_jalali'] ?></td>
            <td><?= number_format($p['price']) ?> تومان</td>
            <td>
              <button class="btn-small" onclick='openEditModal(<?= json_encode($p, JSON_UNESCAPED_UNICODE) ?>)'><i class="fas fa-edit"></i></button>
              <button class="btn-small danger" onclick="openDeleteModal(<?= $p['id'] ?>)"><i class="fas fa-trash"></i></button>
              <button onclick='printProductReceipt(<?= json_encode($p, JSON_UNESCAPED_UNICODE) ?>)' class="btn-small"><i class="fas fa-print"></i></button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

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
            <input type="text" name="sale_date" id="edit_date" class="jalali-date" required>
          </div>
          <div class="field">
            <label>قیمت (تومان):</label>
            <input type="text" name="price" id="edit_price" required>
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
      document.getElementById('edit_date').value = product.sale_date_jalali;
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

    // Function to format number with commas
    function formatNumber(num) {
      return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    // Function to remove commas from number
    function unformatNumber(str) {
      return str.replace(/,/g, '');
    }

    // Add event listeners to price inputs
    document.querySelectorAll('input[name="price"]').forEach(function(input) {
      input.addEventListener('input', function(e) {
        let value = unformatNumber(e.target.value);
        e.target.value = formatNumber(value);
      });
    });

    // Add submit listeners to forms to remove commas
    document.querySelectorAll('form').forEach(function(form) {
      form.addEventListener('submit', function(e) {
        let priceInput = form.querySelector('input[name="price"]');
        if (priceInput) {
          priceInput.value = unformatNumber(priceInput.value);
        }
      });
    });

    // Modify openEditModal to format the price
    function openEditModal(product) {
      document.getElementById('edit_id').value = product.id;
      document.getElementById('edit_name').value = product.name;
      document.getElementById('edit_color').value = product.color;
      document.getElementById('edit_size').value = product.size;
      document.getElementById('edit_date').value = product.sale_date_jalali;
      document.getElementById('edit_price').value = formatNumber(product.price);
      document.getElementById('editModal').classList.add('active');
    }

  </script>

  <!-- Modal HTML for adding new product name -->
  <div class="modal" id="addProductNameModal">
    <div class="modal-content">
      <h2>افزودن نام محصول جدید</h2>
      <form id="addProductNameForm">
        <div class="form-row">
          <div class="field">
            <label>نام محصول:</label>
            <input type="text" id="new_product_name" required />
          </div>
          <div class="field">
            <label>قیمت (تومان):</label>
            <input type="number" id="new_product_price" required />
          </div>
        </div>
        <div class="form-row submit-row">
          <button type="submit" class="btn">ذخیره</button>
          <button type="button" class="btn-small danger" id="cancelAddProductName">لغو</button>
        </div>
      </form>
    </div>
  </div>

</body>
</html>

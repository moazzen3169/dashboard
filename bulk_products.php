<?php
include 'db.php';

// دریافت لیست محصولات برای نمایش در سلکت
try {
    $stmt = $conn->query("SELECT id, name FROM `products-name` ORDER BY name");
    $productNames = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Failed to fetch product names: ' . $e->getMessage());
    $productNames = [];
}
?>
<!DOCTYPE html>
<html lang="fa">
<head>
  <meta charset="UTF-8">
  <title><i class="fas fa-layer-group"></i> ثبت محصولات عمده</title>
  <link rel="stylesheet" href="css/design-system.css">
  <link rel="stylesheet" href="css/sidebar.css">
  <link rel="stylesheet" href="css/bulk-products.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="js/bulk-products.js" defer></script>
</head>
<body class="dashboard-container">
  <aside class="sidebar">
    <?php include 'sidebar.php'; ?>
  </aside>

  <div class="main-content">
    <header class="top-bar">
      <h1 class="h1"><i class="fas fa-layer-group"></i> ثبت محصولات عمده</h1>
    </header>

    <div class="content-area">
      <section class="card">
        <h2 class="h2"><i class="fas fa-plus"></i> ایجاد پکیج محصول</h2>
        <p class="body">در این فرم می‌توانید برای یک محصول چندین رنگ و سایز تعریف کنید و در نهایت ثبت نهایی انجام دهید.</p>

        <form class="form" id="bulkProductForm">
          <div class="form-row">
            <div class="field">
              <label for="productSelect">انتخاب محصول</label>
              <select id="productSelect" name="product_id" required>
                <option value="" disabled selected>یک محصول انتخاب کنید...</option>
                <?php foreach ($productNames as $product): ?>
                  <option value="<?= htmlspecialchars($product['id']) ?>"><?= htmlspecialchars($product['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="color-section" id="colorContainer">
            <h3 class="h3"><i class="fas fa-palette"></i> رنگ‌ها</h3>
            <p class="hint">برای هر رنگ می‌توانید چند سایز مختلف اضافه کنید.</p>
            <div class="color-groups"></div>
            <button type="button" class="btn-secondary" id="addColorBtn"><i class="fas fa-plus"></i> افزودن رنگ جدید</button>
          </div>

          <div class="form-row submit-row">
            <button type="submit" class="btn"><i class="fas fa-save"></i> ثبت محصولات</button>
          </div>
        </form>

        <section class="card preview-card" id="previewCard" hidden>
          <h2 class="h3"><i class="fas fa-eye"></i> پیش‌نمایش ثبت</h2>
          <div id="previewContent" class="preview-content"></div>
        </section>
      </section>
    </div>
  </div>
</body>
</html>

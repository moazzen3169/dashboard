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

$availableColors = [
    ['value' => 'red', 'label' => 'قرمز', 'hex' => '#f44336'],
    ['value' => 'blue', 'label' => 'آبی', 'hex' => '#2196f3'],
    ['value' => 'green', 'label' => 'سبز', 'hex' => '#4caf50'],
    ['value' => 'yellow', 'label' => 'زرد', 'hex' => '#ffeb3b'],
    ['value' => 'black', 'label' => 'مشکی', 'hex' => '#000000'],
    ['value' => 'white', 'label' => 'سفید', 'hex' => '#ffffff'],
    ['value' => 'gray', 'label' => 'طوسی', 'hex' => '#9e9e9e'],
    ['value' => 'purple', 'label' => 'بنفش', 'hex' => '#9c27b0'],
    ['value' => 'orange', 'label' => 'نارنجی', 'hex' => '#ff9800'],
    ['value' => 'pink', 'label' => 'صورتی', 'hex' => '#ff8ab6'],
    ['value' => 'navy', 'label' => 'سرمه‌ای', 'hex' => '#1a237e'],
    ['value' => 'brown', 'label' => 'قهوه‌ای', 'hex' => '#795548'],
    ['value' => 'beige', 'label' => 'بژ', 'hex' => '#f5f5dc'],
];

$availableSizes = [
    ['value' => 'XS', 'label' => 'XS'],
    ['value' => 'S', 'label' => 'S'],
    ['value' => 'M', 'label' => 'M'],
    ['value' => 'L', 'label' => 'L'],
    ['value' => 'XL', 'label' => 'XL'],
    ['value' => 'XXL', 'label' => 'XXL'],
    ['value' => '36', 'label' => '36'],
    ['value' => '38', 'label' => '38'],
    ['value' => '40', 'label' => '40'],
    ['value' => '42', 'label' => '42'],
    ['value' => '44', 'label' => '44'],
    ['value' => '46', 'label' => '46'],
    ['value' => 'OneSize', 'label' => 'فری سایز'],
];
?>
<!DOCTYPE html>
<html lang="fa">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ثبت محصولات عمده</title>
  <link rel="stylesheet" href="css/design-system.css">
  <link rel="stylesheet" href="css/sidebar.css">
  <link rel="stylesheet" href="css/bulk-products.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <script>
    window.bulkProductOptions = {
      colors: <?= json_encode($availableColors, JSON_UNESCAPED_UNICODE); ?>,
      sizes: <?= json_encode($availableSizes, JSON_UNESCAPED_UNICODE); ?>
    };
  </script>
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
      <section class="card form-card">
        <header class="card-header">
          <div>
            <h2 class="h2"><i class="fas fa-plus"></i> ساخت پکیج عمده</h2>
            <p class="body muted">یک محصول را انتخاب کنید، سپس رنگ‌ها و سایزهای دلخواه را برای آن تعیین کنید.</p>
          </div>
          <div class="steps">
            <span class="step active" data-step="product">۱. محصول</span>
            <span class="step" data-step="colors">۲. رنگ‌ها</span>
            <span class="step" data-step="sizes">۳. سایزها</span>
            <span class="step" data-step="preview">۴. تایید</span>
          </div>
        </header>

        <form class="form" id="bulkProductForm">
          <div class="form-grid">
            <div class="field product-field">
              <label for="productSelect">انتخاب محصول</label>
              <?php $placeholderText = empty($productNames) ? 'هنوز محصولی ثبت نشده است' : 'یک محصول انتخاب کنید...'; ?>
              <select id="productSelect" name="product_id" required>
                <option value="" disabled <?= empty($productNames) ? '' : 'selected' ?>><?= $placeholderText ?></option>
                <?php foreach ($productNames as $product): ?>
                  <option value="<?= htmlspecialchars($product['id']) ?>"><?= htmlspecialchars($product['name']) ?></option>
                <?php endforeach; ?>
              </select>
              <?php if (empty($productNames)): ?>
                <p class="hint warning"><i class="fas fa-exclamation-triangle"></i> برای ثبت محصولات عمده، ابتدا محصولی را از بخش مدیریت کالا اضافه کنید.</p>
              <?php endif; ?>
            </div>

            <div class="field info-field">
              <h3 class="h4"><i class="fas fa-info-circle"></i> راهنمایی</h3>
              <p class="body small">برای هر رنگ انتخابی می‌توانید چند سایز مشخص کنید. پس از تکمیل اطلاعات، پیش‌نمایش نهایی برای بررسی نمایش داده می‌شود.</p>
            </div>
          </div>

          <div class="color-section" id="colorContainer">
            <div class="color-section-header">
              <div>
                <h3 class="h3"><i class="fas fa-palette"></i> رنگ‌ها و سایزها</h3>
                <p class="hint">هر رنگ را از لیست انتخاب کنید و سپس سایزهای مرتبط را علامت بزنید.</p>
              </div>
              <button type="button" class="btn-secondary" id="addColorBtn"><i class="fas fa-plus"></i> افزودن رنگ جدید</button>
            </div>
            <div class="color-groups"></div>
          </div>

          <div class="form-row submit-row">
            <button type="submit" class="btn-primary"><i class="fas fa-eye"></i> مشاهده پیش‌نمایش</button>
            <button type="reset" class="btn-secondary" id="resetForm"><i class="fas fa-rotate"></i> پاک‌سازی</button>
          </div>
        </form>
      </section>

      <section class="card preview-card" id="previewCard" hidden>
        <div class="preview-header">
          <h2 class="h3"><i class="fas fa-eye"></i> پیش‌نمایش ثبت</h2>
          <button class="btn-link" id="hidePreview" type="button"><i class="fas fa-times"></i> بستن</button>
        </div>
        <div id="previewContent" class="preview-content"></div>
      </section>
    </div>
  </div>
</body>
</html>

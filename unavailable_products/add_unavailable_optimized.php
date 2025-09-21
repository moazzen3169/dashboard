<?php
include "../db.php";

// Fetch existing product names for select
$products_names = [];
try {
    $result = $conn->query("SELECT DISTINCT name FROM `products-name` ORDER BY name");
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $products_names[] = $row['name'];
    }
} catch (Exception $e) {
    $error = "خطا در دریافت لیست محصولات: " . $e->getMessage();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $product_name = trim($_POST['product_name'] ?? '');

    // Validate product name
    if (empty($product_name)) {
        $error = "لطفاً نام محصول را انتخاب کنید.";
    } else {
        // Process colors and sizes into JSON format
        $colors_data = [];
        $has_valid_color = false;

        if (isset($_POST['colors']) && is_array($_POST['colors'])) {
            foreach ($_POST['colors'] as $color_data) {
                if (isset($color_data['color']) && !empty($color_data['color']) &&
                    isset($color_data['sizes']) && is_array($color_data['sizes']) && !empty($color_data['sizes'])) {

                    $color = trim($color_data['color']);
                    $sizes = array_filter($color_data['sizes'], function($size) {
                        return !empty($size) && is_numeric($size);
                    });

                    if (!empty($color) && !empty($sizes)) {
                        $colors_data[] = [
                            'color' => $color,
                            'sizes' => array_values($sizes)
                        ];
                        $has_valid_color = true;
                    }
                }
            }
        }

        if (!$has_valid_color) {
            $error = "لطفاً حداقل یک رنگ با سایز معتبر انتخاب کنید.";
        } else {
            $colors_json = json_encode($colors_data);

            try {
                $stmt = $conn->prepare("INSERT INTO Wholesale (product_name, colors) VALUES (?, ?)");
                $stmt->execute([$product_name, $colors_json]);

                // Redirect with success message
                header("Location: add_unavailable_optimized.php?success=1");
                exit;
            } catch (Exception $e) {
                $error = "خطا در ذخیره اطلاعات: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>افزودن محصول ناموجود - بهینه شده</title>
  <link rel="stylesheet" href="../css/design-system.css">
  <link rel="stylesheet" href="../css/sidebar.css">
  <link rel="stylesheet" href="unavailable_styles.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="dashboard-container">
  <aside class="sidebar">
    <?php include '../sidebar.php'; ?>
  </aside>

  <div class="main-content">
    <header class="top-bar">
      <h1 class="h1"><i class="fas fa-plus-circle"></i> افزودن محصول ناموجود</h1>
    </header>

    <div class="content-area">
      <?php if (isset($error)): ?>
        <div class="error-messages" style="margin-bottom: var(--space-lg);">
          <ul>
            <li><?php echo htmlspecialchars($error); ?></li>
          </ul>
        </div>
      <?php endif; ?>

      <?php if (isset($_GET['success'])): ?>
        <div class="success-message" style="margin-bottom: var(--space-lg);">
          <i class="fas fa-check-circle"></i> محصول با موفقیت اضافه شد.
        </div>
      <?php endif; ?>

      <section class="card">
        <h2 class="h2"><i class="fas fa-plus"></i> ایجاد محصول ناموجود</h2>
        <p class="body">در این فرم می‌توانید محصولات ناموجود را با رنگ‌ها و سایزهای مختلف ثبت کنید.</p>

        <form class="form" method="post" id="unavailableForm">
          <div class="form-row">
            <div class="field">
              <label for="productSelect">نام محصول <span class="required">*</span></label>
              <select id="productSelect" name="product_name" required>
                <option value="" disabled selected>یک محصول انتخاب کنید...</option>
                <?php foreach ($products_names as $pname): ?>
                  <option value="<?= htmlspecialchars($pname) ?>"><?= htmlspecialchars($pname) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="color-section" id="colorContainer">
            <h3 class="h3"><i class="fas fa-palette"></i> رنگ‌ها و سایزها</h3>
            <p class="hint">برای هر رنگ، سایزهای ناموجود را انتخاب کنید.</p>
            <div class="color-groups" id="colorsDiv"></div>
            <button type="button" class="btn-secondary" id="addColorBtn">
              <i class="fas fa-plus"></i> افزودن رنگ جدید
            </button>
          </div>

          <div class="form-row submit-row">
            <button type="submit" class="btn" id="submitBtn">
              <i class="fas fa-save"></i> ثبت محصول
            </button>
            <button type="button" class="btn-secondary" onclick="resetForm()">
              <i class="fas fa-undo"></i> پاک کردن فرم
            </button>
          </div>
        </form>
      </section>

      <div class="form-row">
        <a href="list_unavailable_optimized.php" class="btn-secondary">
          <i class="fas fa-list"></i> مشاهده لیست محصولات
        </a>
      </div>
    </div>
  </div>

  <script>
  class ColorFormManager {
    constructor() {
      this.colorIndex = 0;
      this.availableSizes = [36, 38, 40, 42, 44, 46, 48, 50, 52, 54, 56, 58, 60];
      this.colors = [
        'مشکی', 'سفید', 'قرمز', 'سبز', 'زرد', 'خردلی', 'کرمی',
        'قهوه ای', 'صورتی', 'زرشکی', 'توسی', 'گلبهی', 'بنفش', 'آبی', 'تعویضی'
      ];

      this.init();
    }

    init() {
      this.bindEvents();
      this.addInitialColorField();
    }

    bindEvents() {
      // Add color button
      document.getElementById('addColorBtn').addEventListener('click', () => {
        this.addColorField();
      });

      // Form submission
      document.getElementById('unavailableForm').addEventListener('submit', (e) => {
        this.handleFormSubmit(e);
      });

      // Product selection change
      document.getElementById('productSelect').addEventListener('change', () => {
        this.validateForm();
      });
    }

    addInitialColorField() {
      this.addColorField();
    }

    addColorField(colorData = null) {
      const colorsDiv = document.getElementById("colorsDiv");
      const div = document.createElement("div");
      div.className = "color-group";
      div.setAttribute('data-index', this.colorIndex);

      const colorValue = colorData ? colorData.color : '';
      const sizes = colorData ? colorData.sizes || [] : [];

      div.innerHTML = `
        <div class="color-group-header">
          <div class="field">
            <label>رنگ: <span class="required">*</span></label>
            <select name="colors[${this.colorIndex}][color]" class="form-input color-select" required>
              <option value="">انتخاب رنگ...</option>
              ${this.colors.map(color =>
                `<option value="${color}" ${colorValue === color ? 'selected' : ''}>${color}</option>`
              ).join('')}
            </select>
          </div>
          <button type="button" class="btn-small danger remove-color-btn" onclick="colorManager.removeColorField(this)">
            <i class="fas fa-trash"></i>
          </button>
        </div>
        <div class="size-list">
          ${this.availableSizes.map(size => `
            <label class="size-item" onclick="colorManager.toggleSize(this)">
              <input type="checkbox" name="colors[${this.colorIndex}][sizes][]" value="${size}" ${sizes.includes(size) ? 'checked' : ''}>
              <span>${size}</span>
            </label>
          `).join('')}
        </div>
      `;

      colorsDiv.appendChild(div);
      this.colorIndex++;
      this.validateForm();
    }

    removeColorField(button) {
      const colorsDiv = document.getElementById("colorsDiv");
      const colorGroup = button.closest('.color-group');

      // Don't remove if it's the only color field
      if (colorsDiv.children.length > 1) {
        colorGroup.remove();
        this.validateForm();
      } else {
        this.showNotification('حداقل یک رنگ باید وجود داشته باشد', 'warning');
      }
    }

    toggleSize(labelElement) {
      const checkbox = labelElement.querySelector('input[type="checkbox"]');
      checkbox.checked = !checkbox.checked;
      this.validateForm();
    }

    validateForm() {
      const productSelect = document.getElementById('productSelect');
      const colorGroups = document.querySelectorAll('.color-group');
      const submitBtn = document.getElementById('submitBtn');

      let isValid = true;

      // Check if product is selected
      if (!productSelect.value) {
        isValid = false;
      }

      // Check if at least one color has sizes selected
      let hasValidColor = false;
      colorGroups.forEach(group => {
        const colorSelect = group.querySelector('.color-select');
        const sizeCheckboxes = group.querySelectorAll('input[type="checkbox"]:checked');

        if (colorSelect.value && sizeCheckboxes.length > 0) {
          hasValidColor = true;
        }
      });

      if (!hasValidColor) {
        isValid = false;
      }

      submitBtn.disabled = !isValid;
      return isValid;
    }

    async handleFormSubmit(e) {
      e.preventDefault();

      if (!this.validateForm()) {
        this.showNotification('لطفاً تمام فیلدهای ضروری را تکمیل کنید', 'error');
        return;
      }

      const submitBtn = document.getElementById('submitBtn');
      const originalText = submitBtn.innerHTML;

      try {
        // Show loading state
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> در حال ذخیره...';

        const formData = new FormData(e.target);

        const response = await fetch('add_unavailable_optimized.php', {
          method: 'POST',
          body: formData
        });

        if (response.ok) {
          this.showNotification('محصول با موفقیت اضافه شد', 'success');
          setTimeout(() => {
            window.location.href = 'add_unavailable_optimized.php?success=1';
          }, 1000);
        } else {
          throw new Error('خطا در ارسال اطلاعات');
        }
      } catch (error) {
        this.showNotification('خطا در ذخیره اطلاعات', 'error');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
      }
    }

    showNotification(message, type = 'info') {
      // Remove existing notifications
      const existing = document.querySelector('.notification');
      if (existing) existing.remove();

      const notification = document.createElement('div');
      notification.className = `notification notification-${type}`;
      notification.innerHTML = `
        <div class="notification-content">
          <i class="fas fa-${this.getNotificationIcon(type)}"></i>
          <span>${message}</span>
        </div>
        <button class="notification-close" onclick="this.parentElement.remove()">
          <i class="fas fa-times"></i>
        </button>
      `;

      document.body.appendChild(notification);

      // Auto remove after 5 seconds
      setTimeout(() => {
        if (notification.parentElement) {
          notification.remove();
        }
      }, 5000);
    }

    getNotificationIcon(type) {
      const icons = {
        success: 'check-circle',
        error: 'exclamation-triangle',
        warning: 'exclamation-circle',
        info: 'info-circle'
      };
      return icons[type] || 'info-circle';
    }

    resetForm() {
      const form = document.getElementById('unavailableForm');
      form.reset();
      const colorsDiv = document.getElementById("colorsDiv");
      colorsDiv.innerHTML = '';
      this.colorIndex = 0;
      this.addInitialColorField();
      this.validateForm();
    }
  }

  // Initialize when DOM is loaded
  document.addEventListener('DOMContentLoaded', function() {
    window.colorManager = new ColorFormManager();
  });
  </script>

  <style>
  /* Additional styles for optimized form */
  .required {
    color: var(--color-danger, #dc3545);
  }

  .notification {
    position: fixed;
    top: 20px;
    left: 50%;
    transform: translateX(-50%);
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-lg);
    z-index: 1001;
    min-width: 300px;
    max-width: 400px;
  }

  .notification-content {
    display: flex;
    align-items: center;
    gap: var(--space-sm);
    padding: var(--space-md);
  }

  .notification.success {
    border-left: 4px solid var(--color-success, #28a745);
  }

  .notification.error {
    border-left: 4px solid var(--color-danger, #dc3545);
  }

  .notification.warning {
    border-left: 4px solid var(--color-warning, #ffc107);
  }

  .notification-close {
    position: absolute;
    top: 10px;
    left: 10px;
    background: none;
    border: none;
    cursor: pointer;
    padding: 5px;
    color: var(--color-text-muted);
  }

  .btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }

  .size-item {
    cursor: pointer;
    user-select: none;
  }

  .size-item:hover {
    background: var(--color-hover, rgba(0, 123, 255, 0.1));
  }
  </style>
</body>
</html>

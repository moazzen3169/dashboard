<?php
include "../db.php";

// Security: Validate and sanitize input
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Handle delete via AJAX
if (isset($_POST['delete_id'])) {
    $id = intval($_POST['delete_id']);
    if ($id <= 0) {
        exit(json_encode(['success' => false, 'error' => 'شناسه نامعتبر']));
    }

    try {
        $stmt = $conn->prepare("DELETE FROM Wholesale WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() > 0) {
            exit(json_encode(['success' => true, 'message' => 'محصول با موفقیت حذف شد']));
        } else {
            exit(json_encode(['success' => false, 'error' => 'محصول یافت نشد']));
        }
    } catch (Exception $e) {
        exit(json_encode(['success' => false, 'error' => 'خطا در حذف محصول: ' . $e->getMessage()]));
    }
}

// Handle update via AJAX
if (isset($_POST['update_id'])) {
    $id = intval($_POST['update_id']);
    $product_name = sanitizeInput($_POST['product_name']);
    $colors_json = $_POST['colors'];

    if (empty($product_name) || $id <= 0) {
        exit(json_encode(['success' => false, 'error' => 'داده‌های ورودی نامعتبر']));
    }

    try {
        $stmt = $conn->prepare("UPDATE Wholesale SET product_name = ?, colors = ? WHERE id = ?");
        $stmt->execute([$product_name, $colors_json, $id]);

        if ($stmt->rowCount() > 0) {
            exit(json_encode(['success' => true, 'message' => 'محصول با موفقیت بروزرسانی شد']));
        } else {
            exit(json_encode(['success' => false, 'error' => 'محصول یافت نشد']));
        }
    } catch (Exception $e) {
        exit(json_encode(['success' => false, 'error' => 'خطا در بروزرسانی محصول: ' . $e->getMessage()]));
    }
}

// Fetch all wholesale products with error handling
try {
    $result = $conn->query("SELECT * FROM Wholesale ORDER BY product_name");
    if ($result === false) {
        throw new Exception('خطا در دریافت داده‌ها');
    }

    $products = [];
    while($row = $result->fetch(PDO::FETCH_ASSOC)){
        $row['colors'] = json_decode($row['colors'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $row['colors'] = [];
        }
        $products[] = $row;
    }
} catch (Exception $e) {
    $products = [];
    $error_message = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>لیست محصولات ناموجود - بهینه شده</title>
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
      <h1 class="h1"><i class="fas fa-list"></i> لیست محصولات ناموجود</h1>
    </header>

    <div class="content-area">
      <div class="form-row">
        <a href="add_unavailable_optimized.php" class="btn">
          <i class="fas fa-plus"></i> افزودن محصول جدید
        </a>
        <button class="btn-secondary" onclick="refreshList()">
          <i class="fas fa-sync-alt"></i> بروزرسانی لیست
        </button>
      </div>

      <?php if (isset($error_message)): ?>
        <div class="error-messages" style="margin-bottom: var(--space-lg);">
          <ul>
            <li><?php echo htmlspecialchars($error_message); ?></li>
          </ul>
        </div>
      <?php endif; ?>

      <div class="table-container">
        <table class="table" id="productsTable">
          <thead>
            <tr>
              <th>نام محصول</th>
              <th>رنگ‌ها و سایزها</th>
              <th>عملیات</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($products)): ?>
              <tr>
                <td colspan="3" style="text-align: center; color: var(--color-text-muted);">
                  <i class="fas fa-info-circle"></i> هیچ محصولی ثبت نشده است
                </td>
              </tr>
            <?php else: ?>
              <?php foreach($products as $product): ?>
                <tr data-id="<?= $product['id'] ?>">
                  <td><?= htmlspecialchars($product['product_name']) ?></td>
                  <td>
                    <?php if (is_array($product['colors'])): ?>
                      <?php foreach($product['colors'] as $color_data): ?>
                        <div class="color-info">
                          <strong><?= htmlspecialchars($color_data['color']) ?>:</strong>
                          <?php if (is_array($color_data['sizes'])): ?>
                            <span><?= implode(', ', array_map('htmlspecialchars', $color_data['sizes'])) ?></span>
                          <?php endif; ?>
                        </div>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </td>
                  <td>
                    <button class="btn-small" onclick="unavailableManager.editItem(<?= $product['id'] ?>, '<?= htmlspecialchars($product['product_name']) ?>', '<?= htmlspecialchars(json_encode($product['colors'])) ?>')" title="ویرایش">
                      <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn-small" onclick="unavailableManager.printItem(<?= $product['id'] ?>, '<?= htmlspecialchars($product['product_name']) ?>', '<?= htmlspecialchars(json_encode($product['colors'])) ?>')" title="پرینت">
                      <i class="fas fa-print"></i>
                    </button>
                    <button class="btn-small danger" onclick="unavailableManager.deleteItem(<?= $product['id'] ?>, this)" title="حذف">
                      <i class="fas fa-trash"></i>
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Edit Modal -->
  <div id="editModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3 class="h3"><i class="fas fa-edit"></i> ویرایش محصول ناموجود</h3>
        <button class="modal-close" onclick="unavailableManager.closeEditModal()">&times;</button>
      </div>
      <form id="editForm">
        <input type="hidden" id="editId" name="id">
        <div class="form-row">
          <div class="field">
            <label for="editProductName">نام محصول <span class="required">*</span></label>
            <input type="text" id="editProductName" name="product_name" required>
          </div>
        </div>
        <div id="editColorsContainer">
          <h4 class="h4"><i class="fas fa-palette"></i> رنگ‌ها و سایزها</h4>
          <div id="editColorsDiv"></div>
          <button type="button" class="btn-secondary" onclick="unavailableManager.addEditColorField()">
            <i class="fas fa-plus"></i> افزودن رنگ
          </button>
        </div>
        <div class="form-row submit-row">
          <button type="submit" class="btn">
            <i class="fas fa-save"></i> ذخیره تغییرات
          </button>
          <button type="button" class="btn-secondary" onclick="unavailableManager.closeEditModal()">انصراف</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Print Modal -->
  <div id="printModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3 class="h3"><i class="fas fa-print"></i> پرینت محصول ناموجود</h3>
        <button class="modal-close" onclick="unavailableManager.closePrintModal()">&times;</button>
      </div>
      <div id="printContent">
        <!-- Print content will be inserted here -->
      </div>
      <div class="form-row">
        <button class="btn" onclick="window.print()">
          <i class="fas fa-print"></i> پرینت
        </button>
        <button class="btn-secondary" onclick="unavailableManager.closePrintModal()">بستن</button>
      </div>
    </div>
  </div>

  <!-- Load optimized JavaScript -->
  <script src="unavailable_manager.js"></script>

  <script>
  // Additional functionality for the optimized list page
  function refreshList() {
    location.reload();
  }

  // Show loading state for table operations
  function showTableLoading() {
    const tbody = document.querySelector('#productsTable tbody');
    tbody.innerHTML = '<tr><td colspan="3" style="text-align: center;"><i class="fas fa-spinner fa-spin"></i> در حال بارگذاری...</td></tr>';
  }

  // Enhanced notification system
  function showNotification(message, type = 'info') {
    // Remove existing notifications
    const existing = document.querySelector('.notification');
    if (existing) existing.remove();

    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
      <div class="notification-content">
        <i class="fas fa-${getNotificationIcon(type)}"></i>
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

  function getNotificationIcon(type) {
    const icons = {
      success: 'check-circle',
      error: 'exclamation-triangle',
      warning: 'exclamation-circle',
      info: 'info-circle'
    };
    return icons[type] || 'info-circle';
  }
  </script>

  <style>
  /* Additional styles for optimized list page */
  .table-container {
    overflow-x: auto;
    margin-top: var(--space-lg);
  }

  .table {
    width: 100%;
    border-collapse: collapse;
    background: var(--color-surface);
    border-radius: var(--radius-md);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
  }

  .table th,
  .table td {
    padding: var(--space-md);
    text-align: right;
    border-bottom: 1px solid var(--color-border);
  }

  .table th {
    background: var(--color-surface-subtle);
    font-weight: 600;
    color: var(--color-text);
  }

  .table tr:hover {
    background: var(--color-hover, rgba(0, 123, 255, 0.05));
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

  .btn-small {
    margin: 0 2px;
  }

  .btn-small:first-child {
    margin-left: 0;
  }

  .btn-small:last-child {
    margin-right: 0;
  }

  @media (max-width: 768px) {
    .table-container {
      font-size: var(--font-size-sm);
    }

    .table th,
    .table td {
      padding: var(--space-sm);
    }

    .btn-small {
      padding: var(--space-xs);
      font-size: var(--font-size-xs);
    }
  }
  </style>
</body>
</html>

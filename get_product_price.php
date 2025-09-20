<?php
include 'db.php';

header('Content-Type: application/json');

if (!isset($_GET['name'])) {
    echo json_encode(['success' => false, 'message' => 'نام محصول مشخص نشده است.']);
    exit;
}

$name = $_GET['name'];

try {
    $stmt = $conn->prepare("SELECT price FROM `products-name` WHERE name = ?");
    $stmt->execute([$name]);
    $product = $stmt->fetch();

    if ($product) {
        echo json_encode(['success' => true, 'price' => $product['price']]);
    } else {
        echo json_encode(['success' => false, 'message' => 'محصول یافت نشد.']);
    }
} catch (PDOException $e) {
    error_log("Get product price failed: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطا در دریافت قیمت محصول.']);
}
?>

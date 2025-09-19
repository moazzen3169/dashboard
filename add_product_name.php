<?php
include 'db.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['name']) || !isset($data['price'])) {
    echo json_encode(['success' => false, 'message' => 'نام و قیمت محصول الزامی است.']);
    exit;
}

$name = trim($data['name']);
$price = $data['price'];

if ($name === '' || !is_numeric($price) || $price < 0) {
    echo json_encode(['success' => false, 'message' => 'نام یا قیمت نامعتبر است.']);
    exit;
}

try {
    // Check if name already exists
    $stmt = $conn->prepare("SELECT COUNT(*) FROM `products-name` WHERE name = ?");
    $stmt->execute([$name]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'نام محصول قبلا ثبت شده است.']);
        exit;
    }

    // Insert new product name and price
    $stmt = $conn->prepare("INSERT INTO `products-name` (name, price) VALUES (?, ?)");
    $stmt->execute([$name, $price]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log("Add product name failed: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطا در افزودن نام محصول.']);
}
?>

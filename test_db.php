<?php
require 'db.php';

try {
    $stmt = $conn->query("SELECT CURRENT_DATE AS current_date");
    $row = $stmt->fetch();
    echo "Database connection successful. Current date: " . $row['current_date'];
} catch (PDOException $e) {
    echo "Database query failed: " . $e->getMessage();
}
?>

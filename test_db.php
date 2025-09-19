<?php
require 'db.php';

try {
    $stmt = $conn->query("SELECT NOW() AS current_time");
    $row = $stmt->fetch();
    echo "Database connection successful. Current time: " . $row['current_time'];
} catch (PDOException $e) {
    echo "Database query failed: " . $e->getMessage();
}
?>

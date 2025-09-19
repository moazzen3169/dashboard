<?php
/**
 * Database Connection File
 * Uses PDO for secure and efficient database interactions.
 */

// Database configuration
$host = "localhost";
$user = "root";
$pass = "";
$db   = "store_db";
$charset = "utf8mb4";

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

try {
    // Create PDO instance
    $conn = new PDO($dsn, $user, $pass);
    // Set PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Set default fetch mode to associative array
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    // Disable emulation of prepared statements
    $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    // Log error and die with a user-friendly message
    error_log("Database connection failed: " . $e->getMessage());
    die("<i class='fas fa-times'></i> Database connection failed. Please try again later.");
}
?>

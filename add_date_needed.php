<?php
require_once 'config.php';

// Create a new PDO connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Check if date_needed column already exists
    $stmt = $pdo->query("SHOW COLUMNS FROM access_requests LIKE 'date_needed'");
    $columnExists = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$columnExists) {
        // Add the date_needed column to the access_requests table
        $sql = "ALTER TABLE access_requests ADD COLUMN date_needed date DEFAULT NULL AFTER end_date";
        $pdo->exec($sql);
        echo "Successfully added date_needed column to access_requests table.";
    } else {
        echo "The date_needed column already exists in the access_requests table.";
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?> 
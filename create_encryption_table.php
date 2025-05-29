<?php
// Include config file to get database connection
require_once 'config.php';

try {
    // Create the user_encryption_codes table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS user_encryption_codes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(50) NOT NULL,
        encryption_code VARCHAR(6) NOT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        UNIQUE KEY (user_id)
    )";
    
    $pdo->exec($sql);
    echo "Table user_encryption_codes created successfully or already exists.";
} catch(PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
}
?> 
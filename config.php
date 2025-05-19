<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'uar_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('BASE_URL', 'https://royalblue-chimpanzee-160919.hostingersite.com'); // Replace with your actual domain

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
?> 
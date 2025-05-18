<?php
require_once '../config.php';

$username = 'process_owner';
$password = 'process_owner123';
$role = 'process_owner'; // Set the role of the user
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

try {
    // First, clear existing admin user if any
    $stmt = $pdo->prepare("DELETE FROM admin_users WHERE username = ?");
    $stmt->execute([$username]);
    
    // Insert new admin user along with role
    $stmt = $pdo->prepare("INSERT INTO admin_users (username, password, role) VALUES (?, ?, ?)");
    $stmt->execute([$username, $hashed_password, $role]);
    
    echo "Admin user created successfully!\n";
    echo "Username: " . $username . "\n";
    echo "Password: " . $password . "\n";
    echo "Role: " . $role . "\n"; // Output role

} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

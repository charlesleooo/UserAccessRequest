<?php
require_once __DIR__ . '/../config.php';

echo "Starting admin user synchronization...\n";

try {
    // 1. First, fetch all employees with admin-related roles
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE role IN ('admin', 'superior', 'technical_support', 'process_owner')");
    $stmt->execute();
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($admins) . " employees with admin roles.\n";
    
    // 2. For each admin employee, ensure there's a matching admin_users entry
    foreach ($admins as $admin) {
        $employee_id = $admin['employee_id'];
        $role = $admin['role'];
        $name = $admin['employee_name'];
        
        // Check if an admin_users entry with this username already exists
        $checkStmt = $pdo->prepare("SELECT id FROM admin_users WHERE username = ?");
        $checkStmt->execute([$employee_id]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            echo "Admin user with username '{$employee_id}' already exists, updating role...\n";
            // Update the existing record to ensure role is correct
            $updateStmt = $pdo->prepare("UPDATE admin_users SET role = ? WHERE username = ?");
            $updateStmt->execute([$role, $employee_id]);
        } else {
            echo "Creating new admin user for '{$name}' with username '{$employee_id}'...\n";
            
            // Copy password from employees table if it exists, otherwise generate a default password
            $password = $admin['password'] ?? password_hash('default123', PASSWORD_DEFAULT);
            
            // Insert a new admin user
            $insertStmt = $pdo->prepare("INSERT INTO admin_users (role, username, password) VALUES (?, ?, ?)");
            $insertStmt->execute([$role, $employee_id, $password]);
            
            echo "Successfully created admin user with username '{$employee_id}'.\n";
        }
    }
    
    // 3. Also make sure standard role-based users exist
    $standardRoles = ['admin', 'superior', 'technical_support', 'process_owner'];
    $defaultPassword = password_hash('admin123', PASSWORD_DEFAULT);
    
    foreach ($standardRoles as $role) {
        $checkStmt = $pdo->prepare("SELECT id FROM admin_users WHERE username = ?");
        $checkStmt->execute([$role]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$existing) {
            echo "Creating standard role-based user '{$role}'...\n";
            $insertStmt = $pdo->prepare("INSERT INTO admin_users (role, username, password) VALUES (?, ?, ?)");
            $insertStmt->execute([$role, $role, $defaultPassword]);
            echo "Created standard '{$role}' user with password 'admin123'.\n";
        } else {
            echo "Standard role-based user '{$role}' already exists.\n";
        }
    }
    
    echo "Admin user synchronization completed successfully!\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 
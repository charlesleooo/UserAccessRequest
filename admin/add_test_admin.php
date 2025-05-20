<?php
require_once __DIR__ . '/../config.php';

// Define the test admin details
$employee_id = "TEST001";
$employee_name = "Test Administrator";
$company = "AAC";
$department = "IT";
$email = "testadmin@example.com";
$role = "admin";
$password = password_hash("testadmin123", PASSWORD_DEFAULT);

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // 1. Check if employee exists
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE employee_id = ?");
    $stmt->execute([$employee_id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$employee) {
        echo "Creating employee record...\n";
        // Insert into employees table
        $stmt = $pdo->prepare("INSERT INTO employees (employee_id, employee_name, company, department, employee_email, password, is_temp_password, role) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $employee_id,
            $employee_name,
            $company,
            $department,
            $email,
            $password,
            0, // not a temporary password
            $role
        ]);
    } else {
        echo "Employee already exists, updating...\n";
        // Update employee record
        $stmt = $pdo->prepare("UPDATE employees SET 
                              employee_name = ?, 
                              company = ?, 
                              department = ?, 
                              employee_email = ?, 
                              password = ?, 
                              role = ? 
                              WHERE employee_id = ?");
        $stmt->execute([
            $employee_name,
            $company,
            $department,
            $email,
            $password,
            $role,
            $employee_id
        ]);
    }
    
    // 2. Check if admin_users entry exists
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ?");
    $stmt->execute([$employee_id]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin) {
        echo "Creating admin_users record...\n";
        // Insert into admin_users table
        $stmt = $pdo->prepare("INSERT INTO admin_users (username, password, role) VALUES (?, ?, ?)");
        $stmt->execute([
            $employee_id,
            $password,
            $role
        ]);
    } else {
        echo "Admin user already exists, updating...\n";
        // Update admin_users record
        $stmt = $pdo->prepare("UPDATE admin_users SET password = ?, role = ? WHERE username = ?");
        $stmt->execute([
            $password,
            $role,
            $employee_id
        ]);
    }
    
    // Commit transaction
    $pdo->commit();
    
    echo "Test admin successfully created/updated!\n";
    echo "----------------------------------------\n";
    echo "Employee ID: $employee_id\n";
    echo "Name: $employee_name\n";
    echo "Email: $email\n";
    echo "Password: testadmin123\n";
    echo "----------------------------------------\n";
    echo "You can now log in with the test account at admin/login.php\n";
    
} catch (PDOException $e) {
    // Roll back transaction on error
    $pdo->rollBack();
    echo "Error: " . $e->getMessage() . "\n";
}
?> 
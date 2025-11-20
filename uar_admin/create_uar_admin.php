<?php
require_once __DIR__ . '/../config.php';

// Define the UAR admin details
$employee_id = "UAR_ADMIN_001";
$employee_name = "UAR Administrator";
$company = "AAC";
$department = "IT";
$email = "uaradmin@example.com";
$role = "uar_admin";
$username = $employee_id; // Use employee_id as username in admin_users table
$password = "UarAdmin@2025";
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

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
            $hashed_password,
            0, // not a temporary password
            $role
        ]);
        echo "✓ Employee record created\n";
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
            $hashed_password,
            $role,
            $employee_id
        ]);
        echo "✓ Employee record updated\n";
    }

    // 2. Check if admin_users entry exists
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        echo "Creating admin_users record...\n";
        // Insert into admin_users table
        $stmt = $pdo->prepare("INSERT INTO admin_users (username, password, role) VALUES (?, ?, ?)");
        $stmt->execute([
            $username,
            $hashed_password,
            $role
        ]);
        echo "✓ Admin user record created\n";
    } else {
        echo "Admin user already exists, updating...\n";
        // Update admin_users record
        $stmt = $pdo->prepare("UPDATE admin_users SET password = ?, role = ? WHERE username = ?");
        $stmt->execute([
            $hashed_password,
            $role,
            $username
        ]);
        echo "✓ Admin user record updated\n";
    }

    // Commit transaction
    $pdo->commit();

    echo "\n";
    echo "========================================\n";
    echo "UAR ADMIN SUCCESSFULLY CREATED/UPDATED!\n";
    echo "========================================\n";
    echo "Employee ID: $employee_id\n";
    echo "Name: $employee_name\n";
    echo "Email: $email (Use this to login)\n";
    echo "Password: $password\n";
    echo "Role: $role\n";
    echo "========================================\n";
    echo "\nYou can now log in at:\n";
    echo "- UAR Admin Panel: " . BASE_URL . "/uar_admin/login.php\n";
    echo "\nLogin Credentials:\n";
    echo "- Email: $email\n";
    echo "- Password: $password\n";
    echo "\n";
} catch (PDOException $e) {
    // Roll back transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

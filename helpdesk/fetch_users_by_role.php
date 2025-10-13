<?php
session_start();
require_once '../config.php';

// Check if help desk is logged in
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'help_desk') {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit();
}

// Check if role parameter is provided
if (!isset($_GET['role']) || empty($_GET['role'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Role parameter is required'
    ]);
    exit();
}

$role = $_GET['role'];

// Validate the role parameter - only allow Process Owner and Technical Support
if (!in_array($role, ['process_owner', 'technical_support'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid role parameter - only process_owner and technical_support are allowed'
    ]);
    exit();
}

try {
    // Query to fetch users from employees table and get their corresponding admin_users id
    $stmt = $pdo->prepare("
        SELECT a.id as admin_user_id,
               e.employee_id,
               e.employee_name
        FROM employees e
        LEFT JOIN admin_users a ON (a.username = e.employee_id OR CAST(a.employee_id AS CHAR) = e.employee_id)
        WHERE e.role = :role AND a.id IS NOT NULL
        ORDER BY e.employee_name
    ");
    $stmt->execute(['role' => $role]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug info
    $debug_info = [
        'requested_role' => $role,
        'users_count' => count($users),
        'query' => "SELECT employee_id, employee_name FROM employees WHERE role = '{$role}'"
    ];
    
    echo json_encode([
        'success' => true,
        'users' => $users,
        'debug' => $debug_info
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} 
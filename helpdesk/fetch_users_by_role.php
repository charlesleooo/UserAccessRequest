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

// Validate the role parameter
if (!in_array($role, ['process_owner', 'technical_support'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid role parameter'
    ]);
    exit();
}

try {
    // Query to fetch users from admin_users table instead of employees
    $stmt = $pdo->prepare("
        SELECT a.id as employee_id, a.username as employee_name 
        FROM admin_users a 
        WHERE a.role = :role 
        ORDER BY a.username
    ");
    $stmt->execute(['role' => $role]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug info
    $debug_info = [
        'requested_role' => $role,
        'users_count' => count($users),
        'query' => "SELECT id, username FROM admin_users WHERE role = '{$role}'"
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
<?php
require_once '../config.php';
session_start();

// Security headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("Content-Security-Policy: default-src 'self' https:; script-src 'self' 'unsafe-inline' https:; style-src 'self' 'unsafe-inline' https:;");
header('Content-Type: application/json');

// Debug logging
error_log("Archive reason request received for employee ID: " . ($_POST['employee_id'] ?? 'not provided'));

// Check if user is logged in and is admin
if (!isset($_SESSION['admin_id'])) {
    error_log("Unauthorized access attempt to get_archive_reason.php");
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

// CSRF Protection
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    error_log("Invalid CSRF token in get_archive_reason.php");
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

// Check for employee_id
if (!isset($_POST['employee_id']) || empty($_POST['employee_id'])) {
    error_log("Missing employee_id in get_archive_reason.php request");
    echo json_encode(['success' => false, 'message' => 'Employee ID is required']);
    exit();
}

$employee_id = filter_input(INPUT_POST, 'employee_id', FILTER_SANITIZE_STRING);
error_log("Looking up archive reason for employee ID: " . $employee_id);

try {
    // Get archive reason from employees_archive table
    $stmt = $pdo->prepare("SELECT archive_reason, archived_at FROM employees_archive WHERE employee_id = ?");
    $stmt->execute([$employee_id]);
    $archiveData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($archiveData) {
        error_log("Archive reason found for employee ID: " . $employee_id);
        echo json_encode([
            'success' => true, 
            'reason' => $archiveData['archive_reason'],
            'archived_at' => $archiveData['archived_at']
        ]);
    } else {
        error_log("No archive reason found for employee ID: " . $employee_id);
        echo json_encode(['success' => false, 'message' => 'No archive reason found']);
    }
} catch (PDOException $e) {
    error_log("Database error in get_archive_reason.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} 
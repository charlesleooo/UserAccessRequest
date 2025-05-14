<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['requestor_id'])) {
    header("Location: login.php");
    exit();
}

$requestorId = $_SESSION['requestor_id'];

// Check if request ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: my_requests.php");
    exit();
}

$requestId = intval($_GET['id']);

// Verify the request belongs to the user and is cancelable (pending)
try {
    $checkQuery = "SELECT id, status FROM access_requests 
                  WHERE id = :request_id AND employee_id = :employee_id AND status = 'pending'";
    
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->execute([
        ':request_id' => $requestId,
        ':employee_id' => $requestorId
    ]);
    
    $request = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        // Request not found, doesn't belong to user, or not cancelable
        header("Location: my_requests.php?error=not_found");
        exit();
    }
    
    // Delete the request
    $deleteQuery = "DELETE FROM access_requests WHERE id = :request_id";
    $deleteStmt = $pdo->prepare($deleteQuery);
    $deleteStmt->execute([':request_id' => $requestId]);
    
    // Redirect with success message
    header("Location: my_requests.php?success=canceled");
    exit();
    
} catch (PDOException $e) {
    error_log("Error canceling request: " . $e->getMessage());
    header("Location: my_requests.php?error=db");
    exit();
}
?> 
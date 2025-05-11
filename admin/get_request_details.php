<?php
session_start();
require_once '../config.php';

// Set JSON header first
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Check if request ID is provided
if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Request ID is required']);
    exit();
}

try {
    // Verify database connection
    if (!isset($pdo)) {
        throw new Exception('Database connection not established');
    }

    // Check if this is a history request or current request
    if (isset($_GET['type']) && $_GET['type'] === 'history') {
        $sql = "SELECT 
                h.*,
                a.username as admin_username
            FROM approval_history h 
            LEFT JOIN admin_users a ON h.admin_id = a.id 
            WHERE h.history_id = :id";
    } else {
        // Query for current requests
        $sql = "SELECT 
                r.*,
                a.username as admin_username
            FROM access_requests r
            LEFT JOIN admin_users a ON r.reviewed_by = a.id 
            WHERE r.id = :id";
    }
    
    $stmt = $pdo->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare statement');
    }
    
    $stmt->execute(['id' => $_GET['id']]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        http_response_code(404);
        echo json_encode(['error' => 'Request not found']);
        exit();
    }

    // Format dates if they exist
    if (isset($request['created_at'])) {
        $request['created_at'] = date('Y-m-d H:i:s', strtotime($request['created_at']));
    }
    if (isset($request['submission_date'])) {
        $request['submission_date'] = date('Y-m-d H:i:s', strtotime($request['submission_date']));
    }
    if (isset($request['start_date'])) {
        $request['start_date'] = date('Y-m-d', strtotime($request['start_date']));
    }
    if (isset($request['end_date'])) {
        $request['end_date'] = date('Y-m-d', strtotime($request['end_date']));
    }

    echo json_encode($request);

} catch (PDOException $e) {
    error_log("Database error in get_request_details.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error occurred',
        'message' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Error in get_request_details.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'An error occurred',
        'message' => $e->getMessage()
    ]);
}
?>
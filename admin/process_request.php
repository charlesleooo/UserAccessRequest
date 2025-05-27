<?php
session_start();
require_once '../config.php';

// Set JSON header
header('Content-Type: application/json');

// Track if a transaction is active
$transaction_active = false;

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['role'], ['superior', 'help_desk', 'technical', 'technical_support', 'process_owner', 'admin'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit();
}

// Initialize response array
$response = [
    'success' => false,
    'message' => ''
];

// Check if required parameters are present
if (!isset($_POST['request_id']) || !isset($_POST['action']) || !isset($_POST['review_notes'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters'
    ]);
    exit();
}

try {
    // Get the admin_users id for the current admin
    $adminQuery = $pdo->prepare("SELECT id FROM admin_users WHERE username = :username OR username = :employee_id");
    $adminQuery->execute([
        'username' => $_SESSION['admin_username'] ?? '',
        'employee_id' => $_SESSION['admin_id'] ?? ''
    ]);
    $adminRecord = $adminQuery->fetch(PDO::FETCH_ASSOC);
    $admin_users_id = $adminRecord ? $adminRecord['id'] : null;
    
    if (!$admin_users_id) {
        // Try to find by role as a fallback
        $roleQuery = $pdo->prepare("SELECT id FROM admin_users WHERE role = :role LIMIT 1");
        $roleQuery->execute(['role' => $_SESSION['role']]);
        $roleRecord = $roleQuery->fetch(PDO::FETCH_ASSOC);
        $admin_users_id = $roleRecord ? $roleRecord['id'] : null;
        
        if (!$admin_users_id) {
            throw new Exception('Admin user record not found. Cannot complete approval process.');
        }
    }
    
    $pdo->beginTransaction();
    $transaction_active = true;

    $request_id = $_POST['request_id'];
    $action = $_POST['action'];
    $review_notes = $_POST['review_notes'];
    $admin_id = $_SESSION['admin_id'];
    $role = $_SESSION['role'];
    
    // Get forwarding parameters if they exist
    $forward_to = $_POST['forward_to'] ?? null;
    $forward_user_id = $_POST['user_id'] ?? null;

    // Get current request status
    $stmt = $pdo->prepare("SELECT * FROM access_requests WHERE id = ?");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        throw new Exception('Request not found');
    }

    $current_status = $request['status'];
    $id_field = '';
    $date_field = '';
    $notes_field = '';
    $next_status = '';

    // Determine next status and fields based on role
    switch ($role) {
        case 'superior':
            $can_handle = ($current_status === 'pending_superior');
            $next_status = ($action === 'approve') ? 'pending_help_desk' : 'rejected';
            $id_field = 'superior_id';
            $date_field = 'superior_review_date';
            $notes_field = 'superior_notes';
            break;
            
        case 'help_desk':
            $can_handle = ($current_status === 'pending_help_desk');
            // For help desk, determine next status based on forward_to parameter
            if ($action === 'approve' && $forward_to) {
                $next_status = $forward_to === 'technical' ? 'pending_technical' : 'pending_process_owner';
            } else {
                $next_status = 'rejected';
            }
            $id_field = 'help_desk_id';
            $date_field = 'help_desk_review_date';
            $notes_field = 'help_desk_notes';
            break;

        case 'technical_support':
            $can_handle = ($current_status === 'pending_technical');
            $next_status = ($action === 'approve') ? 'pending_process_owner' : 'rejected';
            $id_field = 'technical_id';
            $date_field = 'technical_review_date';
            $notes_field = 'technical_notes';
            break;

        case 'process_owner':
            $can_handle = ($current_status === 'pending_process_owner');
            $next_status = ($action === 'approve') ? 'pending_admin' : 'rejected';
            $id_field = 'process_owner_id';
            $date_field = 'process_owner_review_date';
            $notes_field = 'process_owner_notes';
            break;

        case 'admin':
            $can_handle = ($current_status === 'pending_admin');
            $next_status = ($action === 'approve') ? 'approved' : 'rejected';
            $id_field = 'admin_id';
            $date_field = 'admin_review_date';
            $notes_field = 'admin_notes';
            break;

        default:
            throw new Exception('Invalid role');
    }

    if (!$can_handle) {
        throw new Exception('You cannot process this request in its current state');
    }

    // For help desk forwarding, validate the user_id exists
    if ($role === 'help_desk' && $action === 'approve') {
        if (!$forward_to || !$forward_user_id) {
            throw new Exception('Forward destination and user must be specified');
        }
        
        // Verify the selected user exists and has the correct role
        $expected_role = $forward_to === 'technical' ? 'technical_support' : 'process_owner';
        $userStmt = $pdo->prepare("SELECT id, username FROM admin_users WHERE id = :user_id AND role = :role");
        $userStmt->execute([
            'user_id' => $forward_user_id,
            'role' => $expected_role
        ]);
        $forwardUser = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$forwardUser) {
            throw new Exception('Selected user is not valid for forwarding');
        }
    }

    // Update request status and add review details
    $sql = "UPDATE access_requests SET 
            status = :next_status,
            $id_field = :admin_users_id,
            $date_field = NOW(),
            $notes_field = :review_notes";
            
    // If help desk is forwarding, set the next reviewer's ID
    if ($role === 'help_desk' && $action === 'approve') {
        $next_id_field = $forward_to === 'technical' ? 'technical_id' : 'process_owner_id';
        $sql .= ", $next_id_field = :forward_user_id";
    }
    
    $sql .= " WHERE id = :request_id";

    $stmt = $pdo->prepare($sql);
    $params = [
        'next_status' => $next_status,
        'admin_users_id' => $admin_users_id,
        'review_notes' => $review_notes,
        'request_id' => $request_id
    ];
    
    // Add forward_user_id to params if forwarding
    if ($role === 'help_desk' && $action === 'approve') {
        $params['forward_user_id'] = $forward_user_id;
    }
    
    $result = $stmt->execute($params);

    if (!$result) {
        throw new Exception('Failed to update request status');
    }

    // If request is approved by admin or rejected by anyone, move to history
    if ($next_status === 'approved' || $next_status === 'rejected') {
        // ... rest of the code for moving to history ...
    }

    $pdo->commit();
    $transaction_active = false;
    
    // Create a more descriptive message
    if ($role === 'help_desk' && $action === 'approve') {
        $destination = $forward_to === 'technical' ? 'Technical Support' : 'Process Owner';
        $message = "Request has been forwarded to $destination successfully";
    } else {
        $message = "Request has been " . ($action === 'approve' ? 'approved' : 'declined') . " successfully";
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message
    ]);

} catch (Exception $e) {
    if ($transaction_active) {
        $pdo->rollBack();
        $transaction_active = false;
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 
<?php
session_start();
require_once '../config.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['role'], ['superior', 'technical', 'technical_support', 'process_owner', 'admin'])) {
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
    $pdo->beginTransaction();

    $request_id = $_POST['request_id'];
    $action = $_POST['action'];
    $review_notes = $_POST['review_notes'];
    $admin_id = $_SESSION['admin_id'];
    $role = $_SESSION['role'];

    // Handle special actions for testing phase first
    if ($role === 'technical_support' && in_array($action, ['finalize_approval', 'reject_after_testing', 'retry_testing'])) {
        // Get request details
        $stmt = $pdo->prepare("SELECT * FROM access_requests WHERE id = ?");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$request) {
            throw new Exception('Request not found');
        }
        
        if ($request['status'] !== 'pending_testing' || !in_array($request['testing_status'], ['success', 'failed'])) {
            throw new Exception('Request is not in the correct state for this action');
        }
        
        // Process based on the action
        switch ($action) {
            case 'finalize_approval':
                if ($request['testing_status'] !== 'success') {
                    throw new Exception('Cannot finalize approval for a request that did not pass testing');
                }
                
                // Move to approval history with "approved" status
                $sql = "INSERT INTO approval_history (
                    access_request_number, action, requestor_name, business_unit, department,
                    access_type, technical_id, comments, system_type, duration_type,
                    start_date, end_date, justification, email, contact_number,
                    testing_status, superior_id, superior_notes, technical_id, technical_notes,
                    process_owner_id, process_owner_notes, testing_instructions
                ) VALUES (
                    :access_request_number, 'approved', :requestor_name, :business_unit, :department,
                    :access_type, :technical_id, :comments, :system_type, :duration_type,
                    :start_date, :end_date, :justification, :email, :contact_number,
                    :testing_status, :superior_id, :superior_notes, :technical_id, :technical_notes,
                    :process_owner_id, :process_owner_notes, :testing_instructions
                )";
                
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([
                    'access_request_number' => $request['access_request_number'],
                    'requestor_name' => $request['requestor_name'],
                    'business_unit' => $request['business_unit'],
                    'department' => $request['department'],
                    'access_type' => $request['access_type'],
                    'technical_id' => $request['technical_id'],
                    'comments' => $review_notes,
                    'system_type' => $request['system_type'],
                    'duration_type' => $request['duration_type'],
                    'start_date' => $request['start_date'],
                    'end_date' => $request['end_date'],
                    'justification' => $request['justification'],
                    'email' => $request['email'],
                    'contact_number' => $request['contact_number'] ?? 'Not provided',
                    'testing_status' => $request['testing_status'],
                    'superior_id' => $request['superior_id'],
                    'superior_notes' => $request['superior_notes'],
                    'technical_id' => $request['technical_id'],
                    'technical_notes' => $request['technical_notes'],
                    'process_owner_id' => $request['process_owner_id'],
                    'process_owner_notes' => $request['process_owner_notes'],
                    'testing_instructions' => $request['testing_instructions'] ?? ''
                ]);
                
                if (!$result) {
                    throw new Exception('Failed to insert into approval history');
                }
                
                // Delete from access_requests
                $stmt = $pdo->prepare("DELETE FROM access_requests WHERE id = ?");
                $stmt->execute([$request_id]);
                
                $message = "Request has been finalized and approved successfully";
                break;
                
            case 'reject_after_testing':
                // Move to approval history with "rejected" status
                $sql = "INSERT INTO approval_history (
                    access_request_number, action, requestor_name, business_unit, department,
                    access_type, technical_id, comments, system_type, duration_type,
                    start_date, end_date, justification, email, contact_number,
                    testing_status, superior_id, superior_notes, technical_id, technical_notes,
                    process_owner_id, process_owner_notes, testing_instructions
                ) VALUES (
                    :access_request_number, 'rejected', :requestor_name, :business_unit, :department,
                    :access_type, :technical_id, :comments, :system_type, :duration_type,
                    :start_date, :end_date, :justification, :email, :contact_number,
                    :testing_status, :superior_id, :superior_notes, :technical_id, :technical_notes,
                    :process_owner_id, :process_owner_notes, :testing_instructions
                )";
                
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([
                    'access_request_number' => $request['access_request_number'],
                    'requestor_name' => $request['requestor_name'],
                    'business_unit' => $request['business_unit'],
                    'department' => $request['department'],
                    'access_type' => $request['access_type'],
                    'technical_id' => $request['technical_id'],
                    'comments' => $review_notes,
                    'system_type' => $request['system_type'],
                    'duration_type' => $request['duration_type'],
                    'start_date' => $request['start_date'],
                    'end_date' => $request['end_date'],
                    'justification' => $request['justification'],
                    'email' => $request['email'],
                    'contact_number' => $request['contact_number'] ?? 'Not provided',
                    'testing_status' => $request['testing_status'],
                    'superior_id' => $request['superior_id'],
                    'superior_notes' => $request['superior_notes'],
                    'technical_id' => $request['technical_id'],
                    'technical_notes' => $request['technical_notes'],
                    'process_owner_id' => $request['process_owner_id'],
                    'process_owner_notes' => $request['process_owner_notes'],
                    'testing_instructions' => $request['testing_instructions'] ?? ''
                ]);
                
                if (!$result) {
                    throw new Exception('Failed to insert into approval history');
                }
                
                // Delete from access_requests
                $stmt = $pdo->prepare("DELETE FROM access_requests WHERE id = ?");
                $stmt->execute([$request_id]);
                
                $message = "Request has been rejected after failed testing";
                break;
                
            case 'retry_testing':
                // Update status to pending_testing_setup again
                $sql = "UPDATE access_requests SET 
                        status = 'pending_testing_setup',
                        testing_status = 'pending',
                        admin_notes = :review_notes
                        WHERE id = :request_id";
                        
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([
                    'review_notes' => $review_notes,
                    'request_id' => $request_id
                ]);
                
                if (!$result) {
                    throw new Exception('Failed to update request for retesting');
                }
                
                $message = "Request has been sent back for retesting";
                break;
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => $message
        ]);
        
        exit();
    }

    // Get the current request status
    $stmt = $pdo->prepare("SELECT * FROM access_requests WHERE id = ?");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        throw new Exception('Request not found');
    }

    $current_status = $request['status'];
    $new_status = '';
    $id_field = '';
    $notes_field = '';
    $date_field = '';

    // Determine the appropriate fields and next status based on role
    switch ($role) {
        case 'superior':
            if ($current_status !== 'pending_superior') {
                throw new Exception('This request is not pending superior review');
            }
            $new_status = ($action === 'approve') ? 'pending_technical' : 'rejected';
            $id_field = 'superior_id';
            $notes_field = 'superior_notes';
            $date_field = 'superior_review_date';
            break;

        case 'technical':
        case 'technical_support':
            if ($current_status === 'pending_technical') {
                $new_status = ($action === 'approve') ? 'pending_process_owner' : 'rejected';
                $id_field = 'technical_id';
                $notes_field = 'technical_notes';
                $date_field = 'technical_review_date';
            } else if ($current_status === 'pending_testing_setup') {
                $new_status = 'pending_testing';
                $id_field = 'technical_id';
                $notes_field = 'testing_instructions';
                $date_field = 'technical_review_date';
                
                // Update testing status to pending when sending instructions
                $sql = "UPDATE access_requests SET 
                        status = :new_status,
                        $id_field = :admin_id,
                        $notes_field = :review_notes,
                        $date_field = NOW(),
                        testing_status = 'pending'
                        WHERE id = :request_id";
            } else if ($current_status === 'pending_testing_review') {
                if ($action === 'approve') {
                    // If test was successful, move to final approval
                    if ($request['testing_status'] === 'success') {
                        $new_status = 'approved';
                    } else {
                        // If test failed, send back for retesting
                        $new_status = 'pending_testing_setup';
                    }
                } else {
                    $new_status = 'rejected';
                }
                $id_field = 'technical_id';
                $notes_field = 'technical_notes';
                $date_field = 'technical_review_date';
            } else {
                throw new Exception('This request is not pending technical review, testing setup, or testing review');
            }
            break;

        case 'process_owner':
            if ($current_status !== 'pending_process_owner') {
                throw new Exception('This request is not pending process owner review');
            }
            $new_status = ($action === 'approve') ? 'pending_admin' : 'rejected';
            $id_field = 'process_owner_id';
            $notes_field = 'process_owner_notes';
            $date_field = 'process_owner_review_date';
            break;

        case 'admin':
            if ($current_status !== 'pending_admin') {
                throw new Exception('This request is not pending admin review');
            }
            
            // Only send to testing_setup if it's a System Application, otherwise approve directly
            if ($action === 'approve') {
                if ($request['access_type'] === 'System Application') {
                    $new_status = 'pending_testing_setup';
                } else {
                    // For non-application access types, approve directly
                    $new_status = 'approved';
                }
            } else {
                $new_status = 'rejected';
            }
            
            // Set both role-specific fields AND the fields the trigger expects
            $id_field = 'admin_id';
            $notes_field = 'admin_notes';
            $date_field = 'admin_review_date';
            break;

        default:
            throw new Exception('Invalid role');
    }

    // Update the request with review details
    $sql = "UPDATE access_requests SET 
            status = :new_status,
            $id_field = :admin_id,
            $notes_field = :review_notes,
            $date_field = NOW()";
    
    // If it's an admin and the new status is approved or rejected, add the fields needed for the trigger
    if ($role === 'admin' && ($new_status === 'approved' || $new_status === 'rejected')) {
        $sql .= ",
            reviewed_by = :admin_id,
            review_notes = :review_notes,
            review_date = NOW()";
    }
    
    $sql .= " WHERE id = :request_id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':new_status' => $new_status,
        ':admin_id' => $admin_id,
        ':review_notes' => $review_notes,
        ':request_id' => $request_id
    ]);

    // If request is approved or rejected by NON-admins, move it to approval_history
    // For admin approvals, the database trigger will handle this
    if (($new_status === 'approved' || $new_status === 'rejected') && $role !== 'admin') {
        $sql = "INSERT INTO approval_history (
                    access_request_number,
                    action,
                    requestor_name,
                    business_unit,
                    department,
                    access_type,
                    admin_id,
                    comments,
                    system_type,
                    duration_type,
                    start_date,
                    end_date,
                    justification,
                    email,
                    contact_number,
                    testing_status
                ) VALUES (
                    :access_request_number,
                    :action,
                    :requestor_name,
                    :business_unit,
                    :department,
                    :access_type,
                    :admin_id,
                    :comments,
                    :system_type,
                    :duration_type,
                    :start_date,
                    :end_date,
                    :justification,
                    :email,
                    :contact_number,
                    :testing_status
                )";

        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            'access_request_number' => $request['access_request_number'],
            'action' => $new_status,
            'requestor_name' => $request['requestor_name'],
            'business_unit' => $request['business_unit'],
            'department' => $request['department'],
            'access_type' => $request['access_type'],
            'admin_id' => $admin_id,
            'comments' => $review_notes,
            'system_type' => $request['system_type'],
            'duration_type' => $request['duration_type'],
            'start_date' => $request['start_date'],
            'end_date' => $request['end_date'],
            'justification' => $request['justification'],
            'email' => $request['email'],
            'contact_number' => $request['contact_number'] ?? 'Not provided',
            'testing_status' => $request['testing_status'] ?? 'not_required'
        ]);

        if (!$result) {
            throw new Exception('Failed to insert into approval history');
        }

        // Delete from access_requests table
        $sql = "DELETE FROM access_requests WHERE id = :request_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['request_id' => $request_id]);
    }

    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Request has been ' . ($action === 'approve' ? 'approved' : 'declined') . ' successfully'
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 
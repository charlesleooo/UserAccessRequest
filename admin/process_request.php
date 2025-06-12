<?php
session_start();
require_once '../config.php';
require_once '../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

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
            // Check if this request came from process owner by checking if process_owner_id is set
            $stmt = $pdo->prepare("SELECT process_owner_id FROM access_requests WHERE id = ?");
            $stmt->execute([$request_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $came_from_process_owner = !empty($result['process_owner_id']);
            
            $next_status = ($action === 'approve') ? ($came_from_process_owner ? 'pending_admin' : 'pending_admin') : 'rejected';
            $id_field = 'technical_id';
            $date_field = 'technical_review_date';
            $notes_field = 'technical_notes';
            break;

        case 'process_owner':
            $can_handle = ($current_status === 'pending_process_owner');
            $next_status = ($action === 'approve') ? 'pending_help_desk' : 'rejected';
            $id_field = 'process_owner_id';
            $date_field = 'process_owner_review_date';
            $notes_field = 'process_owner_notes';
            break;

        case 'admin':
            $can_handle = ($current_status === 'pending_admin' || 
                         ($current_status === 'pending_testing' && in_array($action, ['finalize_approval', 'reject_after_testing', 'retry_testing'])));
            
            if ($action === 'approve') {
                // After admin approval, send to technical support for testing setup
                if ($current_status === 'pending_admin') {
                    $next_status = 'pending_testing_setup';
                    
                    // Update the request to include admin approval and move to technical support
                    $sql = "UPDATE access_requests SET 
                            status = :next_status,
                            admin_id = :admin_users_id,
                            admin_review_date = NOW(),
                            admin_notes = :review_notes,
                            testing_status = 'awaiting_setup'
                            WHERE id = :request_id";
                            
                    $stmt = $pdo->prepare($sql);
                    $result = $stmt->execute([
                        'next_status' => $next_status,
                        'admin_users_id' => $admin_users_id,
                        'review_notes' => $review_notes,
                        'request_id' => $request_id
                    ]);
                    
                    if (!$result) {
                        throw new Exception('Failed to update request status');
                    }
                    
                    // Send email notification to technical support team
                    require_once '../vendor/autoload.php';
                    $mail = new PHPMailer(true);
                    
                    try {
                        // Server settings
                        $mail->isSMTP();
                        $mail->Host = 'smtp.gmail.com';
                        $mail->SMTPAuth = true;
                        $mail->Username = 'charlesondota@gmail.com';
                        $mail->Password = 'crpf bbcb vodv xbjk';
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port = 587;

                        // Recipients
                        $mail->setFrom('charlesondota@gmail.com', 'Access Request System');
                        // Add technical support team email here
                        $mail->addAddress('tech.support@example.com', 'Technical Support Team');

                        // Content
                        $mail->isHTML(true);
                        $mail->Subject = 'Testing Setup Required - ' . $request['access_request_number'];
                        $mail->Body = "
                            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                                <h2 style='color: #1F2937;'>Testing Setup Required</h2>
                                <p>A new access request requires testing setup:</p>
                                
                                <div style='margin-top: 20px;'>
                                    <h3 style='color: #1F2937; border-bottom: 2px solid #E5E7EB; padding-bottom: 10px;'>Request Details</h3>
                                    <table style='width: 100%; border-collapse: collapse; margin-top: 10px;'>
                                        <tr>
                                            <td style='padding: 8px; border: 1px solid #E5E7EB;'><strong>Request Number:</strong></td>
                                            <td style='padding: 8px; border: 1px solid #E5E7EB;'>{$request['access_request_number']}</td>
                                        </tr>
                                        <tr>
                                            <td style='padding: 8px; border: 1px solid #E5E7EB;'><strong>Requestor:</strong></td>
                                            <td style='padding: 8px; border: 1px solid #E5E7EB;'>{$request['requestor_name']}</td>
                                        </tr>
                                        <tr>
                                            <td style='padding: 8px; border: 1px solid #E5E7EB;'><strong>Department:</strong></td>
                                            <td style='padding: 8px; border: 1px solid #E5E7EB;'>{$request['department']}</td>
                                        </tr>
                                        <tr>
                                            <td style='padding: 8px; border: 1px solid #E5E7EB;'><strong>Access Type:</strong></td>
                                            <td style='padding: 8px; border: 1px solid #E5E7EB;'>{$request['access_type']}</td>
                                        </tr>
                                    </table>
                                </div>
                                
                                <div style='margin-top: 30px;'>
                                    <h3 style='color: #1F2937;'>Required Actions:</h3>
                                    <ol style='margin-left: 20px; line-height: 1.6;'>
                                        <li>Review the access request details</li>
                                        <li>Set up the necessary testing environment</li>
                                        <li>Provide testing credentials and instructions</li>
                                        <li>Update the request with testing details</li>
                                    </ol>
                                </div>
                            </div>
                        ";

                        $mail->send();
                        $message = "Request approved and sent to technical support for testing setup.";
                    } catch (Exception $e) {
                        error_log("Email sending failed: {$mail->ErrorInfo}");
                        $message = "Request approved and sent to technical support, but email notification failed.";
                    }
                    
                    // Return early since we've handled everything for testing setup phase
                    $pdo->commit();
                    $transaction_active = false;
                    
                    echo json_encode([
                        'success' => true,
                        'message' => $message
                    ]);
                    return;
                } else {
                    $next_status = 'approved';
                }
            } else if ($action === 'finalize_approval' && $current_status === 'pending_testing' && $routeCheck['testing_status'] === 'success') {
                // Final approval after successful testing
                $next_status = 'approved';
                $review_notes = "Access request approved after successful testing. " . $review_notes;
            } else if ($action === 'reject_after_testing' && $current_status === 'pending_testing' && $routeCheck['testing_status'] === 'failed') {
                // Rejection after failed testing
                $next_status = 'rejected';
                $review_notes = "Access request rejected due to failed testing. " . $review_notes;
            } else if ($action === 'retry_testing' && $current_status === 'pending_testing' && $routeCheck['testing_status'] === 'failed') {
                // Reset testing status for retry
                $next_status = 'pending_testing_setup';
                $sql = "UPDATE access_requests SET 
                        status = :next_status,
                        testing_status = 'awaiting_setup',
                        testing_notes = CONCAT('Previous testing failed. Retrying testing. ', :review_notes)
                        WHERE id = :request_id";
                        
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([
                    'next_status' => $next_status,
                    'review_notes' => $review_notes,
                    'request_id' => $request_id
                ]);
                
                if (!$result) {
                    throw new Exception('Failed to update request status for testing retry');
                }
                
                $message = "Request has been sent back for testing retry.";
                $pdo->commit();
                $transaction_active = false;
                
                echo json_encode([
                    'success' => true,
                    'message' => $message
                ]);
                return;
            } else {
                $next_status = 'rejected';
            }
            
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

    // Special handling for testing phase
    if ($next_status === 'pending_testing_setup') {
        // Send email notification for testing phase
        $mail = new PHPMailer(true);
        
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'charlesondota@gmail.com';
            $mail->Password = 'crpf bbcb vodv xbjk';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            // Recipients
            $mail->setFrom('charlesondota@gmail.com', 'Access Request System');
            $mail->addAddress($request['email'], $request['requestor_name']);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Access Request Testing Phase - ' . $request['access_request_number'];
            $mail->Body = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <h2 style='color: #1F2937;'>Access Request Testing Phase</h2>
                    <p>Dear {$request['requestor_name']},</p>
                    
                    <p>Your access request has been provisionally approved and is now ready for testing. Please proceed with testing the application access and confirm the results.</p>
                    
                    <div style='margin-top: 30px;'>
                        <h3 style='color: #1F2937; border-bottom: 2px solid #E5E7EB; padding-bottom: 10px;'>Request Details</h3>
                        
                        <table style='border-collapse: collapse; width: 100%; margin-bottom: 20px;'>
                            <tr style='background-color: #f8f9fa;'>
                                <td style='padding: 12px; border: 1px solid #ddd;'><strong>Request Number:</strong></td>
                                <td style='padding: 12px; border: 1px solid #ddd;'>{$request['access_request_number']}</td>
                            </tr>
                            <tr>
                                <td style='padding: 12px; border: 1px solid #ddd;'><strong>Status:</strong></td>
                                <td style='padding: 12px; border: 1px solid #ddd;'>
                                    <span style='color: #f59e0b; font-weight: bold;'>
                                        PENDING TESTING
                                    </span>
                                </td>
                            </tr>
                        </table>
                        
                        <p>Please follow these steps:</p>
                        <ol style='margin-left: 20px; line-height: 1.6;'>
                            <li>Attempt to access the application with your credentials</li>
                            <li>Test all the functionalities that were requested</li>
                            <li>Return to the Access Request System to confirm the testing results</li>
                        </ol>
                    </div>
                    
                    <div style='margin-top: 30px; padding: 20px; background-color: #f3f4f6; border-radius: 8px;'>
                        <p style='margin: 0; color: #4B5563;'>
                            <strong>Note:</strong> If you encounter any issues during testing, please contact the IT Help Desk for assistance.
                        </p>
                    </div>
                </div>
            ";

            $mail->send();
            $message = "Request has been provisionally approved and moved to testing phase. The user has been notified.";
        } catch (Exception $e) {
            // Log email error but don't prevent successful update
            error_log("Email sending failed: {$mail->ErrorInfo}");
            $message = "Request has been provisionally approved and moved to testing phase, but email notification failed.";
        }
    } else {
        $message = "Request has been " . ($action === 'approve' ? 'approved' : 'declined') . " successfully";
    }

    // If request is approved by admin or rejected by anyone, move to history
    if ($next_status === 'approved' || $next_status === 'rejected') {
        // ... rest of the code for moving to history ...
    }

    $pdo->commit();
    $transaction_active = false;
    
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
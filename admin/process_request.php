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
    // Use a fresh PDO connection to avoid transaction conflicts
    $cleanPdo = getCleanPDOConnection();
    if (!$cleanPdo) {
        throw new Exception('Unable to establish database connection');
    }
    
    // Get the admin_users id for the current admin
    $adminQuery = $cleanPdo->prepare("SELECT id FROM uar.admin_users WHERE username = :username OR username = :employee_id");
    $adminQuery->execute([
        'username' => $_SESSION['admin_username'] ?? '',
        'employee_id' => $_SESSION['admin_id'] ?? ''
    ]);
    $adminRecord = $adminQuery->fetch(PDO::FETCH_ASSOC);
    $adminQuery->closeCursor();
    $admin_users_id = $adminRecord ? $adminRecord['id'] : null;

    if (!$admin_users_id) {
        // Try to find by role as a fallback
        $roleQuery = $cleanPdo->prepare("SELECT TOP 1 id FROM uar.admin_users WHERE role = :role");
        $roleQuery->execute(['role' => $_SESSION['role']]);
        $roleRecord = $roleQuery->fetch(PDO::FETCH_ASSOC);
        $roleQuery->closeCursor();
        $admin_users_id = $roleRecord ? $roleRecord['id'] : null;

        if (!$admin_users_id) {
            throw new Exception('Admin user record not found. Cannot complete approval process.');
        }
    }

    // Defer starting a transaction until just before the first write

    $request_id = $_POST['request_id'];
    $action = $_POST['action'];
    $review_notes = $_POST['review_notes'];
    $admin_id = $_SESSION['admin_id'];
    $role = $_SESSION['role'];

    // Get forwarding parameters if they exist
    $forward_to = $_POST['forward_to'] ?? null;
    $forward_user_id = $_POST['user_id'] ?? null;

    // Get current request status
    $stmt = $cleanPdo->prepare("SELECT * FROM uar.access_requests WHERE id = ?");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

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
                switch ($forward_to) {
                    case 'technical_support':
                        $next_status = 'pending_technical';
                        break;
                    case 'process_owner':
                        $next_status = 'pending_process_owner';
                        break;
                    default:
                        throw new Exception('Invalid forward destination - only technical_support and process_owner are allowed');
                }
            } else {
                $next_status = 'rejected';
            }
            $id_field = 'help_desk_id';
            $date_field = 'help_desk_review_date';
            $notes_field = 'help_desk_notes';
            break;

        case 'technical_support':
            $can_handle = ($current_status === 'pending_technical' || $current_status === 'pending_testing_setup' || $current_status === 'pending_testing_review');

            if ($current_status === 'pending_testing_setup' || $current_status === 'pending_testing_review') {
                // Handle testing setup phase
                if ($action === 'approve') {
                    $next_status = 'pending_testing';

                    // Update request with testing instructions and move to testing phase
                    $sql = "UPDATE uar.access_requests SET 
                            status = 'pending_testing',
                            testing_status = 'pending',
                            testing_instructions = :review_notes,
                            technical_id = :admin_users_id,
                            technical_review_date = GETDATE(),
                            technical_notes = COALESCE(technical_notes, '') + '\n\nTesting Setup: ' + :review_notes_2
                            WHERE id = :request_id";

                    // Start transaction for write
                    if (!$transaction_active) { $cleanPdo->beginTransaction(); $transaction_active = true; }
                    $stmt = $cleanPdo->prepare($sql);
                    $result = $stmt->execute([
                        'review_notes' => $review_notes,
                        'review_notes_2' => $review_notes,
                        'admin_users_id' => $admin_users_id,
                        'request_id' => $request_id
                    ]);

                    if (!$result) {
                        throw new Exception('Failed to update request with testing details');
                    }

                    // Send email to requestor with testing instructions
                    $mail = new PHPMailer(true);

                    try {
                        // Server settings
                        $mail->isSMTP();
                        $mail->Host = SMTP_HOST;
                        $mail->SMTPAuth = true;
                        $mail->Username = SMTP_USERNAME;
                        $mail->Password = SMTP_PASSWORD;
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port = SMTP_PORT;

                        // Recipients
                        $mail->setFrom(SMTP_FROM_EMAIL ?: SMTP_USERNAME, 'Access Request System');
                        $mail->addAddress($request['employee_email'], $request['requestor_name']);

                        // Content
                        $mail->isHTML(true);
                        $mail->Subject = 'Access Testing Instructions - ' . $request['access_request_number'];
                        $mail->Body = "
                            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                                <h2 style='color: #1F2937;'>Access Testing Instructions</h2>
                                <p>Dear {$request['requestor_name']},</p>

                                <p>Your access request has been set up for testing. Please follow the instructions below:</p>

                                <div style='margin-top: 20px;'>
                                    <h3 style='color: #1F2937; border-bottom: 2px solid #E5E7EB; padding-bottom: 10px;'>Testing Instructions</h3>
                                    <div style='background-color: #f3f4f6; padding: 15px; border-radius: 8px; margin-top: 10px;'>
                                        " . nl2br(htmlspecialchars($review_notes)) . "
                                    </div>
                                </div>

                                <div style='margin-top: 30px; padding: 20px; background-color: #f3f4f6; border-radius: 8px;'>
                                    <p style='margin: 0; color: #4B5563;'>
                                        <strong>Next Steps:</strong><br>
                                        1. Test your access using the instructions above<br>
                                        2. Return to the Access Request System to confirm your testing results<br>
                                        3. If you encounter any issues, please contact technical support
                                    </p>
                                </div>
                            </div>
                        ";

                        $mail->send();
                        $message = "Testing instructions sent to requestor successfully.";
                    } catch (Exception $e) {
                        error_log("Email sending failed: {$mail->ErrorInfo}");
                        $message = "Testing instructions processed, but email notification failed.";
                    }

                    if ($transaction_active) {
                        $cleanPdo->commit();
                        $transaction_active = false;
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => $message
                    ]);
                    exit();
                } else {
                    $next_status = 'rejected';
                }
            } else {
                // Handle regular technical review
                // Check if this request came from process owner by checking if process_owner_id is set
                $stmt = $cleanPdo->prepare("SELECT process_owner_id FROM uar.access_requests WHERE id = ?");
                $stmt->execute([$request_id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $stmt->closeCursor();
                $came_from_process_owner = !empty($result['process_owner_id']);

                // Technical support should recommend to admin, not automatically approve
                $next_status = ($action === 'approve') ? 'pending_admin' : 'rejected';
            }

            $id_field = 'technical_id';
            $date_field = 'technical_review_date';
            $notes_field = 'technical_notes';
            break;

        case 'process_owner':
            $can_handle = ($current_status === 'pending_process_owner');
            // Process owner should recommend to admin, not send back to help desk
            $next_status = ($action === 'approve') ? 'pending_admin' : 'rejected';
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

                    // Get a technical support user to assign the request to
                    $techStmt = $cleanPdo->prepare("
                        SELECT TOP 1 a.id 
                        FROM uar.admin_users a
                        INNER JOIN uar.employees e ON a.username = e.employee_id
                        WHERE e.role = 'technical_support'
                    ");
                    $techStmt->execute();
                    $techUser = $techStmt->fetch(PDO::FETCH_ASSOC);
                    $techStmt->closeCursor();
                    $techUserId = $techUser ? $techUser['id'] : null;

                    // Update the request to include admin approval and move to technical support
                    $sql = "UPDATE uar.access_requests SET 
                            status = :next_status,
                            admin_id = :admin_users_id,
                            admin_review_date = GETDATE(),
                            admin_notes = :review_notes,
                            testing_status = 'not_required'";
                    
                    if ($techUserId) {
                        $sql .= ", technical_id = :tech_user_id";
                    }
                    
                    $sql .= " WHERE id = :request_id";

                    if (!$transaction_active) { $cleanPdo->beginTransaction(); $transaction_active = true; }
                    $stmt = $cleanPdo->prepare($sql);
                    $params = [
                        'next_status' => $next_status,
                        'admin_users_id' => $admin_users_id,
                        'review_notes' => $review_notes,
                        'request_id' => $request_id
                    ];
                    
                    if ($techUserId) {
                        $params['tech_user_id'] = $techUserId;
                    }
                    
                    $result = $stmt->execute($params);

                    if (!$result) {
                        throw new Exception('Failed to update request status');
                    }

                    // Send email notification to technical support team
                    require_once '../vendor/autoload.php';
                    $mail = new PHPMailer(true);

                    try {
                        // Server settings
                        $mail->isSMTP();
                        $mail->Host = SMTP_HOST;
                        $mail->SMTPAuth = true;
                        $mail->Username = SMTP_USERNAME;
                        $mail->Password = SMTP_PASSWORD;
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port = SMTP_PORT;

                        // Recipients
                        $mail->setFrom(SMTP_FROM_EMAIL ?: SMTP_USERNAME, 'Access Request System');
                        // Add technical support team email here
                        $mail->addAddress('charlesondota@gmail.com', 'Technical Support Team');

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
                    if ($transaction_active) {
                        $cleanPdo->commit();
                        $transaction_active = false;
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => $message
                    ]);
                    exit();
                } else {
                    $next_status = 'approved';
                }
            } else if ($action === 'finalize_approval' && $current_status === 'pending_testing' && $request['testing_status'] === 'success') {
                // Final approval after successful testing
                $next_status = 'approved';
                $review_notes = "Access request approved after successful testing. " . $review_notes;
            } else if ($action === 'reject_after_testing' && $current_status === 'pending_testing' && $request['testing_status'] === 'failed') {
                // Rejection after failed testing
                $next_status = 'rejected';
                $review_notes = "Access request rejected due to failed testing. " . $review_notes;
            } else if ($action === 'retry_testing' && $current_status === 'pending_testing' && $request['testing_status'] === 'failed') {
                // Reset testing status for retry
                $next_status = 'pending_testing_setup';
                $sql = "UPDATE uar.access_requests SET 
                        status = :next_status,
                        testing_status = 'not_required',
                        testing_notes = COALESCE(testing_notes, '') + 'Previous testing failed. Retrying testing. ' + :review_notes
                        WHERE id = :request_id";

                $stmt = $cleanPdo->prepare($sql);
                $result = $stmt->execute([
                    'next_status' => $next_status,
                    'review_notes' => $review_notes,
                    'request_id' => $request_id
                ]);

                if (!$result) {
                    throw new Exception('Failed to update request status for testing retry');
                }

                $message = "Request has been sent back for testing retry.";
                if ($transaction_active) {
                    $cleanPdo->commit();
                    $transaction_active = false;
                }

                echo json_encode([
                    'success' => true,
                    'message' => $message
                ]);
                exit();
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

        // Verify the selected user exists and has the correct role in employees table
        $expected_role = $forward_to; // forward_to now directly contains the role name
        $userStmt = $cleanPdo->prepare("
            SELECT a.id, a.username, e.role as employee_role
            FROM uar.admin_users a
            INNER JOIN uar.employees e ON a.username = e.employee_id
            WHERE a.id = :user_id AND e.role = :role
        ");
        $userStmt->execute([
            'user_id' => $forward_user_id,
            'role' => $expected_role
        ]);
        $forwardUser = $userStmt->fetch(PDO::FETCH_ASSOC);
        $userStmt->closeCursor();

        if (!$forwardUser) {
            throw new Exception('Selected user is not valid for forwarding - user does not have the required role');
        }
    }

    // Update request status and add review details
    $sql = "UPDATE uar.access_requests SET 
            status = :next_status,
            $id_field = :admin_users_id,
            $date_field = GETDATE(),
            $notes_field = :review_notes";

    // If help desk is forwarding, set the next reviewer's ID
    if ($role === 'help_desk' && $action === 'approve') {
        switch ($forward_to) {
            case 'technical_support':
                $next_id_field = 'technical_id';
                break;
            case 'process_owner':
                $next_id_field = 'process_owner_id';
                break;
            default:
                throw new Exception('Invalid forward destination for ID field assignment');
        }
        $sql .= ", $next_id_field = :forward_user_id";
    }

    $sql .= " WHERE id = :request_id";

    if (!$transaction_active) { $cleanPdo->beginTransaction(); $transaction_active = true; }
    $stmt = $cleanPdo->prepare($sql);
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
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = SMTP_PORT;

            // Recipients
            $mail->setFrom(SMTP_FROM_EMAIL ?: SMTP_USERNAME, 'Access Request System');
            $mail->addAddress($request['employee_email'], $request['requestor_name']);

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

    // Send notification to specific user when help desk forwards request
    if ($role === 'help_desk' && $action === 'approve' && in_array($next_status, ['pending_technical', 'pending_process_owner'])) {
        // Get the forwarded user's email directly from employees table
        // admin_users.username matches employees.employee_id
        $notifyStmt = $cleanPdo->prepare("
            SELECT e.employee_email as email,
                   e.employee_name as name,
                   e.employee_id
            FROM uar.admin_users a
            INNER JOIN uar.employees e ON a.username = e.employee_id
            WHERE a.id = :forward_user_id
        ");
        $notifyStmt->execute(['forward_user_id' => $forward_user_id]);
        $forwardUser = $notifyStmt->fetch(PDO::FETCH_ASSOC);

        if ($forwardUser && !empty($forwardUser['email'])) {
            $mail = new PHPMailer(true);

            try {
                // Server settings
                $mail->isSMTP();
                $mail->Host = SMTP_HOST;
                $mail->SMTPAuth = true;
                $mail->Username = SMTP_USERNAME;
                $mail->Password = SMTP_PASSWORD;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = SMTP_PORT;

                // Recipients
                $mail->setFrom(SMTP_FROM_EMAIL ?: SMTP_USERNAME, 'Access Request System');
                $mail->addAddress($forwardUser['email'], $forwardUser['name']);

                // Content
                $mail->isHTML(true);
                $roleName = $forward_to === 'technical_support' ? 'Technical Support' : 'Process Owner';
                $mail->Subject = "New Access Request Forwarded - {$request['access_request_number']}";
                $mail->Body = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                        <h2 style='color: #1F2937;'>New Access Request Forwarded</h2>
                        <p>Dear {$forwardUser['name']},</p>
                        
                        <p>A new access request has been forwarded to you for review as {$roleName}.</p>
                        
                        <div style='margin-top: 20px; padding: 15px; background-color: #f8f9fa; border-left: 4px solid #007bff;'>
                            <h3 style='margin-top: 0; color: #1F2937;'>Request Details</h3>
                            <p><strong>Request Number:</strong> {$request['access_request_number']}</p>
                            <p><strong>Requestor:</strong> {$request['requestor_name']}</p>
                            <p><strong>Department:</strong> {$request['department']}</p>
                            <p><strong>Business Unit:</strong> {$request['business_unit']}</p>
                            <p><strong>System Type:</strong> {$request['system_type']}</p>
                            <p><strong>Help Desk Notes:</strong> {$review_notes}</p>
                        </div>

                        <p style='margin-top: 20px;'>
                            <a href='" . BASE_URL . "/{$forward_to}/requests.php' 
                               style='background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>
                                Review Request
                            </a>
                        </p>
                        
                        <p style='margin-top: 20px;'>Please review this request as soon as possible.</p>
                        
                        <p>Best regards,<br>IT Support System</p>
                    </div>
                ";

                $mail->send();
                $message .= " and notification sent to selected {$roleName}.";
            } catch (Exception $e) {
                error_log("Email sending failed: {$mail->ErrorInfo}");
                $message .= " but email notification failed.";
            }
        }
    }

    // If request is approved by admin, rejected by anyone, or recommended by any role, move to history
    $should_create_history = ($next_status === 'approved' || $next_status === 'rejected' ||
        ($role === 'superior' && $next_status === 'pending_help_desk') ||
        ($role === 'process_owner' && $next_status === 'pending_admin') ||
        ($role === 'technical_support' && $next_status === 'pending_admin'));

    if ($should_create_history) {
        // Get the current request data for history
        $requestDataQuery = $cleanPdo->prepare("
            SELECT 
                id, 
                access_request_number, 
                requestor_name, 
                employee_id, 
                employee_email AS email, 
                department, 
                business_unit, 
                system_type, 
                superior_id, 
                superior_notes,
                help_desk_id,
                help_desk_notes, 
                process_owner_id,
                process_owner_notes, 
                technical_id,
                technical_notes, 
                admin_id
            FROM 
                uar.access_requests 
            WHERE 
                id = :request_id
        ");

        $requestDataQuery->execute(['request_id' => $request_id]);
        $requestData = $requestDataQuery->fetch(PDO::FETCH_ASSOC);

        if ($requestData) {
            // Derive access_type and justification from child tables
            $childAccessType = '';
            $childJustification = '';
            try {
                $cj1 = $cleanPdo->prepare("SELECT TOP 1 justification, access_type FROM uar.individual_requests WHERE access_request_number = :arn");
                $cj1->execute(['arn' => $requestData['access_request_number']]);
                $cjr = $cj1->fetch(PDO::FETCH_ASSOC);
                if ($cjr) {
                    $childJustification = $cjr['justification'] ?? '';
                    $childAccessType = $cjr['access_type'] ?? '';
                } else {
                    $cj2 = $cleanPdo->prepare("SELECT TOP 1 justification, access_type FROM uar.group_requests WHERE access_request_number = :arn");
                    $cj2->execute(['arn' => $requestData['access_request_number']]);
                    $cjr = $cj2->fetch(PDO::FETCH_ASSOC);
                    if ($cjr) {
                        $childJustification = $cjr['justification'] ?? '';
                        $childAccessType = $cjr['access_type'] ?? '';
                    }
                }
            } catch (Exception $e) {
                $childAccessType = '';
                $childJustification = '';
            }
            // Insert into approval history with explicit schema and required columns
            $historyInsert = $cleanPdo->prepare("
                INSERT INTO uar.approval_history (
                    access_request_number,
                    requestor_name, 
                    employee_id, 
                    email, 
                    department, 
                    business_unit, 
                    access_type, 
                    system_type,
                    justification, 
                    duration_type, 
                    start_date, 
                    end_date,
                    contact_number,
                    superior_id, 
                    superior_notes,
                    help_desk_id, 
                    help_desk_notes,
                    process_owner_id, 
                    process_owner_notes,
                    technical_id, 
                    technical_notes,
                    admin_id, 
                    action, 
                    comments,
                    created_at
                ) VALUES (
                    :access_request_number,
                    :requestor_name, 
                    :employee_id, 
                    :email, 
                    :department, 
                    :business_unit, 
                    :access_type, 
                    :system_type,
                    :justification, 
                    :duration_type, 
                    :start_date, 
                    :end_date,
                    :contact_number,
                    :superior_id, 
                    :superior_notes,
                    :help_desk_id, 
                    :help_desk_notes,
                    :process_owner_id, 
                    :process_owner_notes,
                    :technical_id, 
                    :technical_notes,
                    :admin_id, 
                    :action, 
                    :comments,
                    GETDATE()
                )
            ");

            // Determine who made the final decision and what notes to use
            $decision_maker_notes = '';
            if ($role === 'admin') {
                $decision_maker_notes = $review_notes;
            } elseif ($role === 'superior') {
                $decision_maker_notes = $requestData['superior_notes'];
            } elseif ($role === 'help_desk') {
                $decision_maker_notes = $requestData['help_desk_notes'];
            } elseif ($role === 'technical_support') {
                $decision_maker_notes = $requestData['technical_notes'];
            } elseif ($role === 'process_owner') {
                $decision_maker_notes = $requestData['process_owner_notes'];
            }

            // Use admin_users.id for the acting reviewer, not employees.employee_id
            // This matches the integer FK columns in approval_history
            $currentRoleId = $admin_users_id;

            // Fallbacks for duration-related fields come from child tables as well
            $derivedDurationType = null;
            $derivedStart = null;
            $derivedEnd = null;
            try {
                if (!empty($childAccessType) || !empty($childJustification)) {
                    // reuse last child lookup result if available; if not, query for date fields
                    if (!isset($cjr) || !$cjr) {
                        $dj1 = $cleanPdo->prepare("SELECT TOP 1 access_duration AS duration_type, start_date, end_date FROM uar.individual_requests WHERE access_request_number = :arn");
                        $dj1->execute(['arn' => $requestData['access_request_number']]);
                        $cjr = $dj1->fetch(PDO::FETCH_ASSOC);
                    }
                    if (!$cjr) {
                        $dj2 = $cleanPdo->prepare("SELECT TOP 1 access_duration AS duration_type, start_date, end_date FROM uar.group_requests WHERE access_request_number = :arn");
                        $dj2->execute(['arn' => $requestData['access_request_number']]);
                        $cjr = $dj2->fetch(PDO::FETCH_ASSOC);
                    }
                    if ($cjr) {
                        $derivedDurationType = $cjr['duration_type'] ?? null;
                        $derivedStart = $cjr['start_date'] ?? null;
                        $derivedEnd = $cjr['end_date'] ?? null;
                    }
                }
            } catch (Exception $e) {
                $derivedDurationType = null;
                $derivedStart = null;
                $derivedEnd = null;
            }

            $historyParams = [
                'access_request_number' => $requestData['access_request_number'],
                'requestor_name' => $requestData['requestor_name'],
                'employee_id' => $requestData['employee_id'],
                'email' => $requestData['email'],
                'department' => $requestData['department'],
                'business_unit' => $requestData['business_unit'],
                'access_type' => $childAccessType,
                'system_type' => $requestData['system_type'],
                'justification' => $childJustification,
                'duration_type' => $derivedDurationType,
                'start_date' => $derivedStart,
                'end_date' => $derivedEnd,
                'contact_number' => '',
                'superior_id' => $role === 'superior' ? $currentRoleId : $requestData['superior_id'],
                'superior_notes' => $requestData['superior_notes'],
                'help_desk_id' => $role === 'help_desk' ? $currentRoleId : $requestData['help_desk_id'],
                'help_desk_notes' => $requestData['help_desk_notes'],
                'process_owner_id' => $role === 'process_owner' ? $currentRoleId : $requestData['process_owner_id'],
                'process_owner_notes' => $requestData['process_owner_notes'],
                'technical_id' => ($role === 'technical' || $role === 'technical_support') ? $currentRoleId : $requestData['technical_id'],
                'technical_notes' => $requestData['technical_notes'],
                'admin_id' => $role === 'admin' ? $currentRoleId : $requestData['admin_id'],
                'action' => $action === 'approve' ? 'approved' : 'rejected',
                'comments' => $decision_maker_notes
            ];

            $historyInsert->execute($historyParams);

            // Update message to indicate history was created
            if (($role === 'superior' && $next_status === 'pending_help_desk') ||
                ($role === 'process_owner' && $next_status === 'pending_admin') ||
                ($role === 'technical_support' && $next_status === 'pending_admin')
            ) {
                $message = "Request has been recommended and moved to review history.";
            } else {
                $message .= " and moved to review history.";
            }
        } else {
            // Log an error if we couldn't find the request data
            error_log("Failed to find request data for history record: Request ID {$request_id}");
        }
    }

    if ($transaction_active) {
        $cleanPdo->commit();
        $transaction_active = false;
    }

    echo json_encode([
        'success' => true,
        'message' => $message
    ]);
} catch (Exception $e) {
    if ($transaction_active) {
        $cleanPdo->rollBack();
        $transaction_active = false;
    }
    // Ensure clean state for next request
    ensureCleanTransaction($cleanPdo);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    // Clean up the connection
    $cleanPdo = null;
}

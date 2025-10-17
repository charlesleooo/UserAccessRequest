<?php
session_start();
require_once '../config.php';
require '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Check if the user needs to enter the encryption code
if (
    !isset($_SESSION['requests_verified']) || !$_SESSION['requests_verified'] ||
    (time() - $_SESSION['requests_verified_time'] > 1800)
) { // Expire after 30 minutes
    header('Location: requests_auth.php');
    exit();
}

// Track if a transaction is active
$transaction_active = false;

// Add the generateRequestNumber function at the top
function generateRequestNumber($pdo)
{
    global $transaction_active;
    try {
        $pdo->beginTransaction();
        $transaction_active = true;

        // Get the highest request number from both tables
        $sql = "SELECT MAX(CAST(SUBSTRING_INDEX(access_request_number, '-', -1) AS UNSIGNED)) as max_num 
               FROM (
                   SELECT access_request_number FROM access_requests
                   UNION ALL
                   SELECT access_request_number FROM approval_history
               ) combined";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get the next number (current max + 1)
        $nextNumber = ($result['max_num'] ?? 0) + 1;

        // Format the request number (REQ2025-003 format)
        $year = date('Y');
        $requestNumber = sprintf("REQ%d-%03d", $year, $nextNumber);

        $pdo->commit();
        $transaction_active = false;
        return $requestNumber;
    } catch (Exception $e) {
        if ($transaction_active) {
            $pdo->rollBack();
            $transaction_active = false;
        }
        throw new Exception("Failed to generate request number: " . $e->getMessage());
    }
}

// Handle approve/decline actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['request_id'])) {
    $action = $_POST['action'];
    $request_id = (int)$_POST['request_id'];
    $admin_id = $_SESSION['admin_id'];
    $review_notes = $_POST['review_notes'] ?? '';
    $admin_role = $_SESSION['role'] ?? 'admin'; // Get the admin's role

    try {
        // Get the admin_users.id that matches the employee's employee_id or username
        $adminStmt = $pdo->prepare("SELECT id FROM uar.admin_users WHERE username = :username OR username = :employee_id");
        $adminStmt->execute([
            'username' => $_SESSION['admin_username'] ?? '',
            'employee_id' => $_SESSION['admin_id'] ?? ''
        ]);
        $adminUser = $adminStmt->fetch(PDO::FETCH_ASSOC);

        if (!$adminUser) {
            throw new Exception('Admin user record not found. Please contact system administrator.');
        }

        $admin_users_id = $adminUser['id']; // This is the correct admin_id to use with approval_history

        // Start transaction
        $pdo->beginTransaction();
        $transaction_active = true;

        // 1. First get the request details before updating
        $sql = "SELECT * FROM uar.access_requests WHERE id = :request_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['request_id' => $request_id]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$request) {
            throw new Exception('Request not found');
        }

        // Check if the current user has the right role to handle this request
        $current_status = $request['status'];
        $can_handle = false;
        $next_status = '';

        switch ($admin_role) {
            case 'superior':
                $can_handle = ($current_status === 'pending_superior');
                $next_status = ($action === 'approve') ? 'pending_help_desk' : 'rejected';
                $id_field = 'superior_id';
                $date_field = 'superior_review_date';
                $notes_field = 'superior_notes';
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
                if ($action === 'approve') {
                    $next_status = ($request['access_type'] === 'System Application') ? 'pending_testing_setup' : 'approved';
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
            throw new Exception('You do not have permission to handle this request at its current stage');
        }

        // Special handling for System Application access type
        if ($action === 'approve' && $request['access_type'] === 'System Application' && $admin_role === 'admin') {
            // For System Application, set status to pending_testing instead of approved
            $sql = "UPDATE uar.access_requests SET 
                status = 'pending_testing_setup', 
                testing_status = 'pending',
                $id_field = :admin_id, 
                $date_field = GETDATE(), 
                $notes_field = :review_notes 
                WHERE id = :request_id";

            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
                'admin_id' => $admin_users_id,
                'review_notes' => $review_notes,
                'request_id' => $request_id
            ]);

            if (!$result) {
                throw new Exception('Failed to update request status');
            }

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
                $mail->Subject = "System Application Access Provisionally Approved - Testing Required - " . $request['access_request_number'];

                // Format system types if present
                $system_types_display = $request['system_type'] ?? 'N/A';

                // Format duration details
                $duration_details = $request['duration_type'] === 'permanent' ?
                    'Permanent' :
                    "Temporary (From: {$request['start_date']} To: {$request['end_date']})";

                $mail->Body = "
                    <div style='font-family: Arial, sans-serif; line-height: 1.6;'>
                        <div style='background-color: #4F46E5; color: white; padding: 20px; text-align: center; border-radius: 8px;'>
                            <h2 style='margin: 0;'>Access Request Provisionally Approved</h2>
                            <p style='margin: 5px 0 0;'>Technical team will provide testing instructions soon</p>
                        </div>

                        <div style='padding: 20px; background-color: #f8f9fa; border-radius: 8px; margin-top: 20px;'>
                            <p style='color: #1F2937; font-size: 16px;'>Dear {$request['requestor_name']},</p>
                            <p style='color: #1F2937;'>Your access request has been <strong>provisionally approved</strong> by the administrator. The technical support team will contact you shortly with detailed instructions for testing your access.</p>
                            
                            <div style='background-color: #fffbeb; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0;'>
                                <strong>Important:</strong> Please wait for testing instructions from the technical team. You will need to confirm your test results before your access is finalized.
                            </div>
                        </div>

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
                                            PENDING TESTING INSTRUCTIONS
                                        </span>
                                    </td>
                                </tr>
                            </table>

                            <h3 style='color: #1F2937; border-bottom: 2px solid #E5E7EB; padding-bottom: 10px;'>Access Details</h3>
                            <table style='border-collapse: collapse; width: 100%; margin-bottom: 20px;'>
                                <tr style='background-color: #f8f9fa;'>
                                    <td style='padding: 12px; border: 1px solid #ddd;'><strong>Access Type:</strong></td>
                                    <td style='padding: 12px; border: 1px solid #ddd;'>{$request['access_type']}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 12px; border: 1px solid #ddd;'><strong>System Type:</strong></td>
                                    <td style='padding: 12px; border: 1px solid #ddd;'>{$system_types_display}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 12px; border: 1px solid #ddd;'><strong>Duration:</strong></td>
                                    <td style='padding: 12px; border: 1px solid #ddd;'>{$duration_details}</td>
                                </tr>
                            </table>

                            <div style='margin-top: 30px; padding: 20px; background-color: #f8f9fa; border-radius: 8px;'>
                                <p style='margin: 0; color: #4B5563; font-size: 0.9em;'>This is an automated message from the System Administrator. Please do not reply to this email.</p>
                                <p style='margin: 10px 0 0; color: #4B5563; font-size: 0.9em;'>If you have any questions, please contact the IT Support team.</p>
                            </div>
                        </div>
                    </div>";

                $mail->AltBody = strip_tags($mail->Body);
                $mail->send();
            } catch (PHPMailerException $e) {
                // Log email error but don't prevent successful update
                error_log("Email sending failed: {$mail->ErrorInfo}");
            }

            $pdo->commit();
            $transaction_active = false;
            $_SESSION['success_message'] = "Request has been provisionally approved. The user will test the application and confirm the result.";
            header('Location: requests.php');
            exit();
        }

        // Standard handling for other access types or rejection actions
        if ($action === 'approve' || $action === 'decline') {
            if ($next_status === 'approved' || $next_status === 'rejected') {
                // Final approval/rejection - move to history
                $sql = "INSERT INTO uar.approval_history (
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
                    testing_status,
                    superior_id,
                    superior_notes,
                    technical_id,
                    technical_notes,
                    process_owner_id,
                    process_owner_notes
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
                    'not_required',
                    :superior_id,
                    :superior_notes,
                    :technical_id,
                    :technical_notes,
                    :process_owner_id,
                    :process_owner_notes
                )";

                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([
                    'access_request_number' => $request['access_request_number'],
                    'action' => $next_status === 'approved' ? 'approved' : 'rejected',
                    'requestor_name' => $request['requestor_name'],
                    'business_unit' => $request['business_unit'],
                    'department' => $request['department'],
                    'access_type' => $request['access_type'],
                    'admin_id' => $admin_users_id,
                    'comments' => $review_notes,
                    'system_type' => $request['system_type'],
                    'duration_type' => $request['duration_type'],
                    'start_date' => $request['start_date'],
                    'end_date' => $request['end_date'],
                    'justification' => $request['justification'],
                    'email' => $request['email'],
                    'contact_number' => $request['contact_number'] ?? 'Not provided',
                    'superior_id' => $request['superior_id'],
                    'superior_notes' => $request['superior_notes'],
                    'technical_id' => $request['technical_id'],
                    'technical_notes' => $request['technical_notes'],
                    'process_owner_id' => $request['process_owner_id'],
                    'process_owner_notes' => $request['process_owner_notes']
                ]);

                if (!$result) {
                    throw new Exception('Failed to insert into approval history');
                }

                // Delete from access_requests table
                $sql = "DELETE FROM uar.access_requests WHERE id = :request_id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['request_id' => $request_id]);
            } else {
                // Update status for next stage
                $sql = "UPDATE uar.access_requests SET 
                    status = :next_status,
                    $id_field = :admin_id,
                    $date_field = GETDATE(),
                    $notes_field = :review_notes
                    WHERE id = :request_id";

                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([
                    'next_status' => $next_status,
                    'admin_id' => $admin_users_id,
                    'review_notes' => $review_notes,
                    'request_id' => $request_id
                ]);

                if (!$result) {
                    throw new Exception('Failed to update request status');
                }
            }

            // Send email notification
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

                if ($next_status === 'approved' || $next_status === 'rejected') {
                    $mail->Subject = "Access Request " . ($next_status === 'approved' ? 'Approved' : 'Rejected') . " - " . $request['access_request_number'];
                    $status_color = $next_status === 'approved' ? '#059669' : '#DC2626';
                    $status_text = $next_status === 'approved' ? 'APPROVED' : 'REJECTED';
                } else {
                    $mail->Subject = "Access Request Status Update - " . $request['access_request_number'];
                    $status_color = '#3B82F6';
                    $status_text = 'IN PROGRESS';
                }

                // Format system types if present
                $system_types_display = isset($request['system_type']) ? $request['system_type'] : 'N/A';
                $contact_number = isset($request['contact_number']) ? $request['contact_number'] : 'Not provided';

                // Format duration details
                $duration_details = $request['duration_type'] === 'permanent' ?
                    'Permanent' :
                    "Temporary (From: {$request['start_date']} To: {$request['end_date']})";

                $mail->Body = "
                    <div style='font-family: Arial, sans-serif; line-height: 1.6;'>
                        <div style='background-color: {$status_color}; color: white; padding: 20px; text-align: center; border-radius: 8px;'>
                            <h2 style='margin: 0;'>Access Request {$status_text}</h2>
                        </div>

                        <div style='padding: 20px; background-color: #f8f9fa; border-radius: 8px; margin-top: 20px;'>
                            <p style='color: #1F2937; font-size: 16px;'>Dear {$request['requestor_name']},</p>
                            <p style='color: #1F2937;'>Your access request has been <strong>{$status_text}</strong>.</p>
                            " . (!empty($review_notes) ? "
                            <div style='background-color: white; padding: 15px; border-left: 4px solid {$status_color}; margin: 20px 0;'>
                                <strong>Notes:</strong><br>
                                {$review_notes}
                            </div>" : "") . "
                        </div>

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
                                        <span style='color: {$status_color}; font-weight: bold;'>{$status_text}</span>
                                    </td>
                                </tr>
                            </table>

                            <h3 style='color: #1F2937; border-bottom: 2px solid #E5E7EB; padding-bottom: 10px;'>System/Application Details</h3>
                            <table style='border-collapse: collapse; width: 100%; margin-bottom: 20px;'>
                                <tr style='background-color: #f8f9fa;'>
                                    <td style='padding: 12px; border: 1px solid #ddd;'><strong>Access Type:</strong></td>
                                    <td style='padding: 12px; border: 1px solid #ddd;'>{$request['access_type']}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 12px; border: 1px solid #ddd;'><strong>System/Application Type:</strong></td>
                                    <td style='padding: 12px; border: 1px solid #ddd;'>{$system_types_display}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 12px; border: 1px solid #ddd;'><strong>Duration:</strong></td>
                                    <td style='padding: 12px; border: 1px solid #ddd;'>{$duration_details}</td>
                                </tr>
                            </table>

                            <div style='margin-top: 30px; padding: 20px; background-color: #f8f9fa; border-radius: 8px;'>
                                <p style='margin: 0; color: #4B5563; font-size: 0.9em;'>This is an automated message from the System Administrator. Please do not reply to this email.</p>
                                <p style='margin: 10px 0 0; color: #4B5563; font-size: 0.9em;'>If you have any questions, please contact the IT Support team.</p>
                            </div>
                        </div>
                    </div>";

                $mail->AltBody = strip_tags($mail->Body);
                $mail->send();
            } catch (PHPMailerException $e) {
                // Log email error but don't prevent successful update
                error_log("Email sending failed: {$mail->ErrorInfo}");
            }

            $pdo->commit();
            $transaction_active = false;
            $_SESSION['success_message'] = "Request has been " . ($action === 'approve' ? 'approved' : 'declined') . " successfully.";
            header('Location: requests.php');
            exit();
        }
    } catch (Exception $e) {
        if ($transaction_active) {
            $pdo->rollBack();
            $transaction_active = false;
        }
        $_SESSION['error_message'] = $e->getMessage();
        header('Location: requests.php');
        exit();
    }
}

// Get all pending requests (excluding those that have been moved to approval history)
try {
    $sql = "SELECT r.*, a.username as reviewed_by_name 
            FROM uar.access_requests r 
            LEFT JOIN uar.admin_users a ON r.reviewed_by = a.id 
            WHERE r.status != 'approved' 
            ORDER BY r.submission_date DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Also check for any approved requests with testing_status 'success' that should be in approval_history
    $checkSql = "SELECT r.*, a.username as reviewed_by_name 
                FROM uar.access_requests r 
                LEFT JOIN uar.admin_users a ON r.reviewed_by = a.id 
                WHERE r.status = 'approved' AND r.testing_status = 'success'";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute();
    $approvedRequests = $checkStmt->fetchAll(PDO::FETCH_ASSOC);

    // Process any found requests to ensure they are moved to approval_history
    foreach ($approvedRequests as $request) {
        // Check if this request already exists in approval_history
        $historySql = "SELECT COUNT(*) FROM uar.approval_history 
                      WHERE access_request_number = :access_request_number";
        $historyStmt = $pdo->prepare($historySql);
        $historyStmt->execute(['access_request_number' => $request['access_request_number']]);
        $exists = $historyStmt->fetchColumn();

        if ($exists > 0) {
            // This request is already in approval_history, remove it from access_requests
            $deleteSql = "DELETE FROM uar.access_requests WHERE id = :request_id";
            $deleteStmt = $pdo->prepare($deleteSql);
            $deleteStmt->execute(['request_id' => $request['id']]);
        } else {
            // Need to move this to approval_history
            try {
                $pdo->beginTransaction();
                $transaction_active = true;

                // Insert into approval history
                $sql = "INSERT INTO uar.approval_history (
                        access_request_number, action, requestor_name, business_unit, department,
                        access_type, admin_id, comments, system_type, duration_type,
                        start_date, end_date, justification, email, contact_number,
                        testing_status, employee_id
                    ) VALUES (
                        :access_request_number, 'approved', :requestor_name, :business_unit, :department,
                        :access_type, :admin_id, :comments, :system_type, :duration_type,
                        :start_date, :end_date, :justification, :email, :contact_number,
                        'success', :employee_id
                    )";

                // Get the admin_users id for the current admin
                $adminQuery = $pdo->prepare("SELECT id FROM uar.admin_users WHERE username = :username OR username = :employee_id");
                $adminQuery->execute([
                    'username' => $_SESSION['admin_username'] ?? '',
                    'employee_id' => $_SESSION['admin_id'] ?? ''
                ]);
                $adminRecord = $adminQuery->fetch(PDO::FETCH_ASSOC);
                $admin_users_id = $adminRecord ? $adminRecord['id'] : null;

                if (!$admin_users_id) {
                    throw new Exception('Admin user record not found. Cannot complete approval.');
                }

                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([
                    'access_request_number' => $request['access_request_number'],
                    'requestor_name' => $request['requestor_name'],
                    'business_unit' => $request['business_unit'],
                    'department' => $request['department'],
                    'access_type' => $request['access_type'],
                    'admin_id' => $admin_users_id,
                    'comments' => $request['review_notes'] ?? 'Automatically approved after successful testing',
                    'system_type' => $request['system_type'],
                    'duration_type' => $request['duration_type'],
                    'start_date' => $request['start_date'],
                    'end_date' => $request['end_date'],
                    'justification' => $request['justification'],
                    'email' => $request['email'],
                    'contact_number' => $request['contact_number'] ?? 'Not provided',
                    'employee_id' => $request['employee_id']
                ]);

                if ($result) {
                    // Delete from access_requests
                    $deleteSql = "DELETE FROM uar.access_requests WHERE id = :request_id";
                    $deleteStmt = $pdo->prepare($deleteSql);
                    $deleteStmt->execute(['request_id' => $request['id']]);

                    $pdo->commit();
                    $transaction_active = false;
                } else {
                    if ($transaction_active) {
                        $pdo->rollBack();
                        $transaction_active = false;
                    }
                }
            } catch (Exception $e) {
                if ($transaction_active) {
                    $pdo->rollBack();
                    $transaction_active = false;
                }
                error_log("Error processing approved request: " . $e->getMessage());
            }
        }
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error fetching requests: " . $e->getMessage();
    $requests = [];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Requests</title>

    <!-- External CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Custom Styles -->
    <style>
        /* SweetAlert2 Customizations */
        .swal2-title {
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            font-weight: 600 !important;
            color: #1F2937 !important;
        }

        .swal2-html-container {
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
        }

        .swal2-popup {
            border-radius: 0.75rem !important;
            padding: 1.5rem !important;
        }

        .swal2-styled.swal2-confirm {
            border-radius: 0.5rem !important;
            font-weight: 500 !important;
            padding: 0.5rem 1.25rem !important;
        }

        .swal2-styled.swal2-cancel {
            border-radius: 0.5rem !important;
            font-weight: 500 !important;
            padding: 0.5rem 1.25rem !important;
        }
    </style>

    <!-- Tailwind Configuration -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        },
                        danger: {
                            DEFAULT: '#dc3545',
                            dark: '#c82333',
                        }
                    },
                }
            }
        }
    </script>
</head>

<body class="bg-gray-50">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Mobile menu button (for responsive design) -->
        <div class="lg:hidden fixed bottom-6 right-6 z-50">
            <button type="button" class="flex items-center justify-center w-14 h-14 rounded-full bg-primary-600 text-white shadow-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                <i class='bx bx-menu text-2xl'></i>
            </button>
        </div>

        <!-- Main Content -->
        <div class="flex-1 ml-72">
            <!-- Header -->
            <div class="bg-primary-900 border-b border-gray-200">
                <div class="flex justify-between items-center px-8 py-4">
                    <div>
                        <h2 class="text-2xl font-bold text-white">Access Requests</h2>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="relative">
                            <input type="text"
                                id="searchInput"
                                placeholder="Search requests..."
                                class="pl-10 pr-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20">
                            <i class='bx bx-search absolute left-3 top-2.5 text-gray-400'></i>
                        </div>
                        <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center">
                            <i class='bx bx-user text-xl text-gray-600'></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Area -->
            <div class="p-8">
                <div class="bg-white rounded-xl shadow-sm">
                    <div class="border-b border-gray-100 px-6 py-4">
                        <div class="flex justify-between items-center">
                            <h3 class="text-xl font-semibold text-gray-800">Access Requests</h3>
                            <div class="flex gap-2">
                                <button onclick="filterRequests('all')" class="px-3 py-1 text-sm bg-blue-50 text-primary rounded">All</button>
                                <button onclick="filterRequests('pending')" class="px-3 py-1 text-sm text-gray-500 hover:bg-gray-50 rounded">Pending</button>
                                <button onclick="filterRequests('approved')" class="px-3 py-1 text-sm text-gray-500 hover:bg-gray-50 rounded">Approved</button>
                                <button onclick="filterRequests('rejected')" class="px-3 py-1 text-sm text-gray-500 hover:bg-gray-50 rounded">Rejected</button>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-col">
                        <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                            <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                                <div class="shadow overflow-hidden border-b border-gray-200 sm:rounded-lg">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    UAR REF NO.
                                                </th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Requestor
                                                </th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Business Unit
                                                </th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Department
                                                </th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Date Requested
                                                </th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Days Pending
                                                </th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Date Needed
                                                </th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Status
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($requests as $request): ?>
                                                <tr class="cursor-pointer hover:bg-gray-50" onclick="window.location.href='view_request.php?id=<?php echo $request['id']; ?>'">
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($request['access_request_number']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($request['requestor_name']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($request['business_unit']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($request['department']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo date('M d, Y', strtotime($request['submission_date'])); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php
                                                        $submission_date = new DateTime($request['submission_date']);
                                                        $today = new DateTime();
                                                        $interval = $submission_date->diff($today);
                                                        echo $interval->days . ' day/s';
                                                        ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo date('M d, Y', strtotime($request['date_needed'] ?? $request['submission_date'])); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php
                                                        $statusClass = '';
                                                        $status = strtolower($request['status']);

                                                        switch ($status) {
                                                            case 'pending_superior':
                                                                $statusClass = 'bg-yellow-100 text-yellow-800';
                                                                $displayStatus = 'Pending Superior Review';
                                                                break;
                                                            case 'pending_technical':
                                                                $statusClass = 'bg-blue-100 text-blue-800';
                                                                $displayStatus = 'Pending Technical Review';
                                                                break;
                                                            case 'pending_process_owner':
                                                                $statusClass = 'bg-indigo-100 text-indigo-800';
                                                                $displayStatus = 'Pending Process Owner Review';
                                                                break;
                                                            case 'pending_admin':
                                                                $statusClass = 'bg-purple-100 text-purple-800';
                                                                $displayStatus = 'Pending Your Review';
                                                                break;
                                                            case 'pending_testing_setup':
                                                                $statusClass = 'bg-amber-100 text-amber-800';
                                                                $displayStatus = 'Pending Test Setup';
                                                                break;
                                                            case 'pending_testing':
                                                                $statusClass = 'bg-cyan-100 text-cyan-800';
                                                                $displayStatus = 'Pending Testing';
                                                                break;
                                                            case 'approved':
                                                                $statusClass = 'bg-green-100 text-green-800';
                                                                $displayStatus = 'Approved';
                                                                break;
                                                            case 'rejected':
                                                                $statusClass = 'bg-red-100 text-red-800';
                                                                $displayStatus = 'Rejected';
                                                                break;
                                                            default:
                                                                $statusClass = 'bg-gray-100 text-gray-800';
                                                                $displayStatus = ucfirst($status);
                                                        }
                                                        ?>
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                                            <?php echo $displayStatus; ?>
                                                        </span>
                                                        <?php if ($status === 'pending_testing'): ?>
                                                            <span class="ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $request['testing_status'] === 'success' ? 'bg-green-100 text-green-800' : ($request['testing_status'] === 'failed' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800'); ?>">
                                                                <?php echo ucfirst($request['testing_status']); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Details Modal -->
    <div id="detailsModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-xl w-[96%] max-w-7xl mx-auto shadow-xl flex flex-col max-h-[90vh]">
                <div class="flex items-center px-6 py-4 border-b border-gray-200 flex-shrink-0">
                    <div class="w-1/4">
                        <p class="text-sm font-medium text-gray-500">Request Number</p>
                        <p id="detail_request_number" class="text-lg font-semibold text-gray-900"></p>
                    </div>
                    <div class="flex-1 text-center">
                        <h3 class="text-xl font-semibold text-gray-800">Access Request Details</h3>
                    </div>
                    <div class="w-1/4 flex justify-end">
                        <button onclick="hideDetailsModal()" class="text-gray-500 hover:text-gray-700">
                            <i class='bx bx-x text-2xl'></i>
                        </button>
                    </div>
                </div>
                <div class="p-6 overflow-y-auto">
                    <div id="detailsModalContent">
                        <!-- Modal content will be populated by JavaScript -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Modified functions for SweetAlert2
        function showActionModal(requestId, action) {
            let title, confirmButtonText, confirmButtonColor, icon;

            switch (action) {
                case 'approve':
                    title = 'Approve Access Request';
                    confirmButtonText = 'Approve';
                    confirmButtonColor = '#10B981';
                    icon = 'success';
                    break;
                case 'decline':
                    title = 'Decline Access Request';
                    confirmButtonText = 'Decline';
                    confirmButtonColor = '#EF4444';
                    icon = 'warning';
                    break;
                case 'finalize_approval':
                    title = 'Finalize Approval After Successful Testing';
                    confirmButtonText = 'Finalize Approval';
                    confirmButtonColor = '#10B981';
                    icon = 'success';
                    break;
                case 'reject_after_testing':
                    title = 'Reject Request After Failed Testing';
                    confirmButtonText = 'Reject';
                    confirmButtonColor = '#EF4444';
                    icon = 'error';
                    break;
                case 'retry_testing':
                    title = 'Request Retest';
                    confirmButtonText = 'Request Retest';
                    confirmButtonColor = '#EAB308';
                    icon = 'info';
                    break;
                default:
                    title = 'Process Request';
                    confirmButtonText = 'Submit';
                    confirmButtonColor = '#0ea5e9';
                    icon = 'question';
            }

            Swal.fire({
                title: title,
                icon: icon,
                html: `
                    <form id="swalForm" class="mt-4">
                        <div class="mb-4 text-left">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Review Notes</label>
                            <textarea id="swal-review-notes" 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20"
                                      rows="3" 
                                      placeholder="${action === 'retry_testing' ? 'Add updated access details and testing instructions...' : 
                                                  (action.includes('approve') ? 'Add any approval notes here...' : 
                                                  'Please specify the reason...')}"></textarea>
                        </div>
                    </form>
                `,
                showCancelButton: true,
                confirmButtonColor: confirmButtonColor,
                cancelButtonColor: '#6B7280',
                confirmButtonText: confirmButtonText,
                cancelButtonText: 'Cancel',
                focusConfirm: false,
                customClass: {
                    container: 'swal-wide',
                    popup: 'rounded-xl shadow-xl',
                    title: 'text-xl font-semibold text-gray-800',
                    htmlContainer: 'text-left',
                },
                preConfirm: () => {
                    const reviewNotes = document.getElementById('swal-review-notes').value;

                    // Simple validation for decline reason
                    if ((action === 'decline' || action === 'reject_after_testing') && !reviewNotes.trim()) {
                        Swal.showValidationMessage('Please provide a reason for declining');
                        return false;
                    }

                    return {
                        reviewNotes: reviewNotes
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Create a form to submit
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.style.display = 'none';

                    // Add the necessary fields
                    const fields = {
                        'request_id': requestId,
                        'action': action,
                        'review_notes': result.value.reviewNotes
                    };

                    for (const [key, value] of Object.entries(fields)) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = key;
                        input.value = value;
                        form.appendChild(input);
                    }

                    // Add to body, submit, then remove
                    document.body.appendChild(form);

                    // Show loading state
                    Swal.fire({
                        title: 'Processing...',
                        html: `${action.includes('approve') ? 'Approving' : 'Declining'} the request...`,
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    form.submit();
                }
            });
        }

        function hideActionModal() {
            // No longer needed with SweetAlert2, keeping for compatibility
        }

        function showRequestDetails(requestId) {
            const modalContainer = document.getElementById('detailsModalContent');
            modalContainer.innerHTML = `
                <div class="flex justify-center items-center p-8">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
                </div>
            `;

            document.getElementById('detailsModal').classList.remove('hidden');

            fetch(`get_request_details.php?id=${requestId}`)
                .then(response => response.json())
                .then(response => {
                    if (!response.success) {
                        throw new Error(response.message || 'Failed to load request details');
                    }

                    const data = response.data;
                    document.getElementById('detail_request_number').textContent = data.access_request_number;

                    // Get the admin role from PHP session
                    const adminRole = '<?php echo $_SESSION['role'] ?? 'admin'; ?>';

                    // Determine if user can handle this request
                    let canHandle = false;
                    switch (adminRole) {
                        case 'superior':
                            canHandle = (data.status === 'pending_superior');
                            break;
                        case 'technical_support':
                            canHandle = (data.status === 'pending_technical');
                            break;
                        case 'process_owner':
                            canHandle = (data.status === 'pending_process_owner');
                            break;
                        case 'admin':
                            canHandle = (data.status === 'pending_admin');
                            break;
                    }

                    // Previous review comments
                    let reviewComments = '';

                    if (data.superior_review_notes && data.superior_review_notes.trim() !== '') {
                        reviewComments += `
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                                <i class='bx bx-message-detail text-primary-600 text-xl mr-2'></i>
                                Superior's Comments
                            </h3>
                            <div class="bg-gray-50 p-4 rounded-lg text-gray-700">
                                ${data.superior_review_notes}
                            </div>
                        </div>
                        `;
                    }

                    if (data.technical_review_notes && data.technical_review_notes.trim() !== '') {
                        reviewComments += `
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                                <i class='bx bx-code-alt text-primary-600 text-xl mr-2'></i>
                                Technical Review Comments
                            </h3>
                            <div class="bg-gray-50 p-4 rounded-lg text-gray-700">
                                ${data.technical_review_notes}
                            </div>
                        </div>
                        `;
                    }

                    if (data.process_owner_review_notes && data.process_owner_review_notes.trim() !== '') {
                        reviewComments += `
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                                <i class='bx bx-briefcase text-primary-600 text-xl mr-2'></i>
                                Process Owner Comments
                            </h3>
                            <div class="bg-gray-50 p-4 rounded-lg text-gray-700">
                                ${data.process_owner_review_notes}
                            </div>
                        </div>
                        `;
                    }

                    modalContainer.innerHTML = `
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            <!-- Request Overview -->
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                                    <i class='bx bx-info-circle text-primary-600 text-xl mr-2'></i>
                                    Request Overview
                                </h3>
                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Request Number:</span>
                                        <span class="font-medium text-gray-900">${data.access_request_number}</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-600">Status:</span>
                                        <div class="flex items-center ${
                                            data.status.includes('pending') ? 'bg-yellow-50' : 
                                            (data.status === 'approved' ? 'bg-green-50' : 'bg-red-50')
                                        } rounded-lg px-2 py-1">
                                            <span class="px-3 py-1 text-xs font-medium rounded-full ${
                                                data.status.includes('pending') ? 'bg-yellow-100 text-yellow-700' : 
                                                (data.status === 'approved' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700')
                                            }">
                                                ${data.status_display}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Submitted:</span>
                                        <span class="font-medium text-gray-900">
                                            ${new Date(data.submission_date).toLocaleString()}
                                        </span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Duration:</span>
                                        <span class="font-medium text-gray-900">
                                            ${data.duration_type === 'permanent' ? 'Permanent' : 
                                            `${new Date(data.start_date).toLocaleDateString()} - ${new Date(data.end_date).toLocaleDateString()}`}
                                        </span>
                                    </div>
                                </div>
                                
                                <!-- Action Buttons -->
                                <div class="mt-6 pt-6 border-t border-gray-100">
                                    <div class="flex flex-wrap gap-2">
                                        ${canHandle ? `
                                            <button onclick="showActionModal(${data.id}, 'approve')" 
                                                    class="inline-flex items-center px-3 py-1 bg-green-50 text-green-700 rounded-lg hover:bg-green-100">
                                                <i class='bx bx-check'></i>
                                                <span class="ml-1">Approve</span>
                                            </button>
                                            <button onclick="showActionModal(${data.id}, 'decline')" 
                                                    class="inline-flex items-center px-3 py-1 bg-red-50 text-red-700 rounded-lg hover:bg-red-100">
                                                <i class='bx bx-x'></i>
                                                <span class="ml-1">Decline</span>
                                            </button>
                                        ` : ''}
                                        
                                        ${adminRole === 'admin' && data.status === 'pending_testing' ? `
                                            ${data.testing_status === 'success' ? `
                                                <button onclick="showActionModal(${data.id}, 'finalize_approval')" 
                                                        class="inline-flex items-center px-3 py-1 bg-green-50 text-green-700 rounded-lg hover:bg-green-100">
                                                    <i class='bx bx-check-double'></i>
                                                    <span class="ml-1">Finalize Approval</span>
                                                </button>
                                            ` : data.testing_status === 'failed' ? `
                                                <button onclick="showActionModal(${data.id}, 'reject_after_testing')" 
                                                        class="inline-flex items-center px-3 py-1 bg-red-50 text-red-700 rounded-lg hover:bg-red-100">
                                                    <i class='bx bx-x'></i>
                                                    <span class="ml-1">Reject</span>
                                                </button>
                                                <button onclick="showActionModal(${data.id}, 'retry_testing')" 
                                                        class="inline-flex items-center px-3 py-1 bg-yellow-50 text-yellow-700 rounded-lg hover:bg-yellow-100">
                                                    <i class='bx bx-refresh'></i>
                                                    <span class="ml-1">Retry Testing</span>
                                                </button>
                                            ` : ''}
                                        ` : ''}
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Requestor Info -->
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                                    <i class='bx bx-user text-primary-600 text-xl mr-2'></i>
                                    Requestor Information
                                </h3>
                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Name:</span>
                                        <span class="font-medium text-gray-900">${data.requestor_name}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Business Unit:</span>
                                        <span class="font-medium text-gray-900">${data.business_unit}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Department:</span>
                                        <span class="font-medium text-gray-900">${data.department}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Email:</span>
                                        <span class="font-medium text-gray-900">${data.email}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Employee ID:</span>
                                        <span class="font-medium text-gray-900">${data.employee_id}</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Access Details -->
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                                    <i class='bx bx-lock-open text-primary-600 text-xl mr-2'></i>
                                    Access Details
                                </h3>
                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Access Type:</span>
                                        <span class="font-medium text-gray-900">${data.access_type}</span>
                                    </div>
                                    ${data.system_type ? `
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">System Type:</span>
                                        <span class="font-medium text-gray-900">${data.system_type}</span>
                                    </div>
                                    ` : ''}
                                    ${data.other_system_type ? `
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Other System:</span>
                                        <span class="font-medium text-gray-900">${data.other_system_type}</span>
                                    </div>
                                    ` : ''}
                                    ${data.role_access_type ? `
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Role Access Type:</span>
                                        <span class="font-medium text-gray-900">${data.role_access_type}</span>
                                    </div>
                                    ` : ''}
                                </div>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 lg:grid-cols-${reviewComments ? '2' : '1'} gap-6 mt-6">
                            <!-- Justification -->
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                                    <i class='bx bx-comment-detail text-primary-600 text-xl mr-2'></i>
                                    Justification
                                </h3>
                                <div class="bg-gray-50 p-4 rounded-lg text-gray-700">
                                    ${data.justification || 'No justification provided.'}
                                </div>
                            </div>
                            
                            ${data.review_history && data.review_history.length > 0 && !reviewComments ? `
                            <!-- Review History -->
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                                    <i class='bx bx-history text-primary-600 text-xl mr-2'></i>
                                    Review History
                                </h3>
                                <div class="space-y-4">
                                    ${data.review_history.map(review => `
                                        <div class="bg-gray-50 p-4 rounded-lg">
                                            <div class="flex justify-between items-center mb-2">
                                                <span class="font-medium text-gray-900">${review.role}</span>
                                                <span class="text-sm text-gray-500">${new Date(review.date).toLocaleString()}</span>
                                            </div>
                                            <div class="flex items-center mb-2">
                                                <span class="text-sm font-medium ${
                                                    review.action === 'Declined' ? 'text-red-600' : 
                                                    (review.action === 'Approved' ? 'text-green-600' : 'text-primary-600')
                                                }">${review.action}</span>
                                            </div>
                                            <p class="text-gray-700 text-sm">${review.note}</p>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                            ` : ''}
                        </div>
                        
                        ${reviewComments ? `
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
                            ${reviewComments}
                        </div>
                        ` : ''}
                        
                        ${data.testing_status ? `
                        <!-- Testing Status -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mt-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                                <i class='bx bx-test-tube text-primary-600 text-xl mr-2'></i>
                                Testing Status
                            </h3>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600">Status:</span>
                                <span class="px-3 py-1 text-xs font-medium rounded-full ${
                                    data.testing_status === 'success' ? 'bg-green-100 text-green-700' :
                                    data.testing_status === 'failed' ? 'bg-red-100 text-red-700' :
                                    'bg-yellow-100 text-yellow-700'
                                }">
                                    ${data.testing_status.charAt(0).toUpperCase() + data.testing_status.slice(1)}
                                </span>
                            </div>
                            ${data.testing_notes ? `
                            <div class="mt-4">
                                <span class="text-gray-600">Testing Notes:</span>
                                <div class="mt-2 bg-gray-50 p-4 rounded-lg text-gray-700">
                                    ${data.testing_notes}
                                </div>
                            </div>
                            ` : ''}
                        </div>
                        ` : ''}
                    `;
                })
                .catch(error => {
                    console.error('Error:', error);
                    modalContainer.innerHTML = `
                <div class="text-center py-8">
                    <div class="text-red-600 mb-2">
                        <i class='bx bx-error-circle text-3xl'></i>
                    </div>
                    <p class="text-red-600 font-medium">Error loading request details</p>
                    <p class="text-gray-500 text-sm mt-1">${error.message}</p>
                    <button onclick="hideDetailsModal()" 
                            class="mt-4 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                        Close
                    </button>
                </div>
            `;
                });
        }

        function hideDetailsModal() {
            document.getElementById('detailsModal').classList.add('hidden');
        }

        // New filter function
        function filterRequests(status) {
            const buttons = document.querySelectorAll('.flex.gap-2 button');
            buttons.forEach(button => {
                if (button.textContent.toLowerCase() === status || (status === 'all' && button.textContent === 'All')) {
                    button.classList.add('bg-blue-50', 'text-primary');
                    button.classList.remove('text-gray-500', 'hover:bg-gray-50');
                } else {
                    button.classList.remove('bg-blue-50', 'text-primary');
                    button.classList.add('text-gray-500', 'hover:bg-gray-50');
                }
            });

            const rows = document.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const statusCell = row.querySelector('td:nth-child(5) span');
                const rowStatus = statusCell.textContent.trim().toLowerCase();
                if (status === 'all' || rowStatus === status) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchText = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchText) ? '' : 'none';
            });
        });

        // Close modals when clicking outside
        document.getElementById('detailsModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideDetailsModal();
            }
        });

        // Show success/error message with SweetAlert2 if set in session
        <?php if (isset($_SESSION['success_message'])): ?>
            Swal.fire({
                title: 'Success!',
                text: '<?php echo addslashes($_SESSION['success_message']); ?>',
                icon: 'success',
                confirmButtonColor: '#0284c7'
            });
        <?php unset($_SESSION['success_message']);
        endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            Swal.fire({
                title: 'Error!',
                text: '<?php echo addslashes($_SESSION['error_message']); ?>',
                icon: 'error',
                confirmButtonColor: '#0284c7'
            });
        <?php unset($_SESSION['error_message']);
        endif; ?>
    </script>
</body>

</html>
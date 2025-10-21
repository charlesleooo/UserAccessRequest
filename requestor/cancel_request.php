<?php
session_start();
require_once '../config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/phpmailer/phpmailer/src/Exception.php';
require '../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require '../vendor/phpmailer/phpmailer/src/SMTP.php';

if (!isset($_SESSION['requestor_id'])) {
    header("Location: login.php");
    exit();
}

$requestorId = $_SESSION['requestor_id'];

// Check if request ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: request_history.php?error=invalid_request");
    exit();
}

$requestId = intval($_GET['id']);
$cancellationReason = $_POST['reason'] ?? '';

try {
    // Check if cancelled_requests table exists, create it if it doesn't
    $checkTableSql = "SHOW TABLES LIKE 'cancelled_requests'";
    $tableExists = $pdo->query($checkTableSql)->rowCount() > 0;

    if (!$tableExists) {
        // Create the cancelled_requests table
        $createTableSql = "CREATE TABLE `cancelled_requests` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `access_request_number` varchar(20) NOT NULL,
            `requestor_name` varchar(255) NOT NULL,
            `business_unit` varchar(50) NOT NULL,
            `department` varchar(100) NOT NULL,
            `email` varchar(255) NOT NULL,
            `employee_id` varchar(20) NOT NULL,
            `access_type` varchar(50) NOT NULL,
            `system_type` varchar(255) DEFAULT NULL,
            `other_system_type` varchar(255) DEFAULT NULL,
            `justification` text NOT NULL,
            `duration_type` varchar(20) DEFAULT NULL,
            `start_date` date DEFAULT NULL,
            `end_date` date DEFAULT NULL,
            `cancellation_reason` text NOT NULL,
            `cancelled_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

        $pdo->exec($createTableSql);
    }

    // Start transaction
    $pdo->beginTransaction();

    // First, get the request details and verify ownership
    $query = "SELECT * FROM uar.access_requests 
              WHERE id = :request_id 
              AND employee_id = :employee_id 
              AND status NOT IN ('approved', 'rejected', 'cancelled')";

    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':request_id' => $requestId,
        ':employee_id' => $requestorId
    ]);

    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        $pdo->rollBack();
        header("Location: request_history.php?error=not_found");
        exit();
    }

    // Check if cancelled history entry already exists to prevent duplicates
    $historyCheckStmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM uar.approval_history 
        WHERE access_request_number = :access_request_number 
        AND action = 'cancelled'
    ");
    $historyCheckStmt->execute([
        'access_request_number' => $request['access_request_number']
    ]);
    $historyExists = $historyCheckStmt->fetch(PDO::FETCH_ASSOC);
    
    // Only insert if history entry doesn't already exist
    if (!$historyExists || $historyExists['count'] == 0) {
        // Insert into approval_history
        $historySql = "INSERT INTO uar.approval_history (
            access_request_number, action, requestor_name, business_unit, department,
            access_type, comments, system_type, duration_type, start_date,
            end_date, justification, email, employee_id, created_at
        ) VALUES (
            :access_request_number, 'cancelled', :requestor_name, :business_unit, :department,
            :access_type, :comments, :system_type, :duration_type, :start_date,
            :end_date, :justification, :email, :employee_id, GETDATE()
        )";

        $historyStmt = $pdo->prepare($historySql);
        // access_requests doesn't store access_type/justification; resolve from child tables
        $childJust = '';
        $childAccessType = '';
        try {
            $cj1 = $pdo->prepare("SELECT TOP 1 justification, access_type FROM uar.individual_requests WHERE access_request_number = :arn");
            $cj1->execute(['arn' => $request['access_request_number']]);
            $cjr = $cj1->fetch(PDO::FETCH_ASSOC);
            if ($cjr) {
                $childJust = $cjr['justification'] ?? '';
                $childAccessType = $cjr['access_type'] ?? '';
            } else {
                $cj2 = $pdo->prepare("SELECT TOP 1 justification, access_type FROM uar.group_requests WHERE access_request_number = :arn");
                $cj2->execute(['arn' => $request['access_request_number']]);
                $cjr = $cj2->fetch(PDO::FETCH_ASSOC);
                if ($cjr) {
                    $childJust = $cjr['justification'] ?? '';
                    $childAccessType = $cjr['access_type'] ?? '';
                }
            }
        } catch (Exception $e) {
            $childJust = '';
            $childAccessType = '';
        }

        $historyResult = $historyStmt->execute([
        ':access_request_number' => $request['access_request_number'],
        ':requestor_name' => $request['requestor_name'],
        ':business_unit' => $request['business_unit'],
        ':department' => $request['department'],
        ':access_type' => ($childAccessType !== '' ? $childAccessType : ($request['system_type'] ?? 'individual')),
        ':comments' => "Request cancelled by requestor. Reason: " . $cancellationReason,
        ':system_type' => $request['system_type'],
        ':duration_type' => $request['duration_type'],
        ':start_date' => $request['start_date'],
        ':end_date' => $request['end_date'],
        ':justification' => ($childJust !== '' ? $childJust : ''),
        ':email' => $request['email'],
        ':employee_id' => $request['employee_id']
    ]);

        if (!$historyResult) {
            throw new PDOException("Failed to insert into approval history");
        }
    } else {
        // History entry already exists, skip insert to prevent duplicate
        error_log("Skipped duplicate history insert for cancelled request {$request['access_request_number']}");
    }

    // Insert into cancelled_requests
    $cancelSql = "INSERT INTO uar.cancelled_requests (
        access_request_number, requestor_name, business_unit, department,
        email, employee_id, access_type, system_type, other_system_type,
        justification, duration_type, start_date, end_date, cancellation_reason
    ) VALUES (
        :access_request_number, :requestor_name, :business_unit, :department,
        :email, :employee_id, :access_type, :system_type, :other_system_type,
        :justification, :duration_type, :start_date, :end_date, :cancellation_reason
    )";

    $cancelStmt = $pdo->prepare($cancelSql);
    $cancelResult = $cancelStmt->execute([
        ':access_request_number' => $request['access_request_number'],
        ':requestor_name' => $request['requestor_name'],
        ':business_unit' => $request['business_unit'],
        ':department' => $request['department'],
        ':email' => $request['email'],
        ':employee_id' => $request['employee_id'],
        ':access_type' => ($childAccessType !== '' ? $childAccessType : ($request['system_type'] ?? 'individual')),
        ':system_type' => $request['system_type'],
        ':other_system_type' => $request['other_system_type'],
        ':justification' => ($childJust !== '' ? $childJust : ''),
        ':duration_type' => $request['duration_type'],
        ':start_date' => $request['start_date'],
        ':end_date' => $request['end_date'],
        ':cancellation_reason' => $cancellationReason
    ]);

    if (!$cancelResult) {
        throw new PDOException("Failed to insert into cancelled_requests");
    }

    // Update access_requests status to cancelled
    $updateSql = "UPDATE uar.access_requests SET status = 'cancelled' WHERE id = :request_id";
    $updateStmt = $pdo->prepare($updateSql);
    $updateResult = $updateStmt->execute([':request_id' => $requestId]);

    if (!$updateResult) {
        throw new PDOException("Failed to update request status");
    }

    // Send email notification
    try {
        $mail = new PHPMailer(true);

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
        $mail->Subject = 'Access Request Cancelled - ' . $request['access_request_number'];

        // Email body
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <div style='background-color: #f8f9fa; padding: 20px; border-radius: 8px;'>
                    <h2 style='color: #1F2937; margin-bottom: 20px;'>Access Request Cancelled</h2>
                    <p>Dear {$request['requestor_name']},</p>
                    <p>Your access request has been cancelled.</p>
                    
                    <div style='background-color: white; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                        <h3 style='color: #1F2937; margin-bottom: 10px;'>Request Details:</h3>
                        <p><strong>Request Number:</strong> {$request['access_request_number']}</p>
                        <p><strong>Access Type:</strong> {$request['access_type']}</p>
                        <p><strong>Status:</strong> Cancelled</p>
                        <p><strong>Cancellation Reason:</strong> {$cancellationReason}</p>
                    </div>
                    
                    <p>If you need to submit a new request, please create one through the system.</p>
                </div>
            </div>";

        $mail->send();
    } catch (Exception $e) {
        // Log email error but continue with the cancellation
        error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
    }

    // Commit transaction
    $pdo->commit();

    header("Location: request_history.php?success=cancelled");
    exit();
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Error cancelling request: " . $e->getMessage());
    header("Location: request_history.php?error=db_error");
    exit();
}



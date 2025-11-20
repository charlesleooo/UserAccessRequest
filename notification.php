<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';

function sendConfirmationEmail($formData)
{
    $mail = new PHPMailer(true);

    try {
        // SMTP Configuration
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        // Email content preparation
        $mail->setFrom(SMTP_FROM_EMAIL ?: SMTP_USERNAME, SMTP_FROM_NAME);
        $mail->addAddress($formData['email'], $formData['requestor_name']);
        $mail->isHTML(true);
        $mail->Subject = 'User Access Request Confirmation';

        // Create a detailed HTML email body
        $emailBody = "
        <html>
        <body style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <h2>User Access Request Confirmation</h2>
            <p>Dear {$formData['requestor_name']},</p>
            <p>This email confirms that your User Access Request has been received. Here are the details:</p>
            
            <h3>Requestor Information</h3>
            <table style='width:100%; border-collapse: collapse;'>
                <tr>
                    <td style='border: 1px solid #ddd; padding: 8px;'><strong>Name:</strong></td>
                    <td style='border: 1px solid #ddd; padding: 8px;'>{$formData['requestor_name']}</td>
                </tr>
                <tr>
                    <td style='border: 1px solid #ddd; padding: 8px;'><strong>Company:</strong></td>
                    <td style='border: 1px solid #ddd; padding: 8px;'>{$formData['business_unit']}</td>
                </tr>
                <tr>
                    <td style='border: 1px solid #ddd; padding: 8px;'><strong>Department:</strong></td>
                    <td style='border: 1px solid #ddd; padding: 8px;'>{$formData['department']}</td>
                </tr>
                <tr>
                    <td style='border: 1px solid #ddd; padding: 8px;'><strong>Email:</strong></td>
                    <td style='border: 1px solid #ddd; padding: 8px;'>{$formData['email']}</td>
                </tr>
                <tr>
                    <td style='border: 1px solid #ddd; padding: 8px;'><strong>Contact Number:</strong></td>
                    <td style='border: 1px solid #ddd; padding: 8px;'>{$formData['contact_number']}</td>
                </tr>
            </table>

            <h3>Access Request Details</h3>
            <table style='width:100%; border-collapse: collapse;'>
                <tr>
                    <td style='border: 1px solid #ddd; padding: 8px;'><strong>Number of Access Requests:</strong></td>
                    <td style='border: 1px solid #ddd; padding: 8px;'>{$formData['access_request_number']}</td>
                </tr>
                <tr>
                    <td style='border: 1px solid #ddd; padding: 8px;'><strong>Access Type:</strong></td>
                    <td style='border: 1px solid #ddd; padding: 8px;'>{$formData['access_type']}</td>
                </tr>
                <tr>
                    <td style='border: 1px solid #ddd; padding: 8px;'><strong>Access Duration:</strong></td>
                    <td style='border: 1px solid #ddd; padding: 8px;'>" .
            ($formData['duration_type'] == 'permanent' ? 'Permanent' :
                "Temporary (From {$formData['start_date']} to {$formData['end_date']})") .
            "</td>
                </tr>
                <tr>
                    <td style='border: 1px solid #ddd; padding: 8px;'><strong>Justification:</strong></td>
                    <td style='border: 1px solid #ddd; padding: 8px;'>{$formData['justification']}</td>
                </tr>
            </table>

            <p>Your request will be processed by our IT department. You will be notified of the status of your access request.</p>
            
            <p>Best regards,<br>IT Support Team</p>
        </body>
        </html>";

        $mail->Body = $emailBody;

        // Send email
        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log the error
        error_log("Email send error: " . $mail->ErrorInfo);
        return false;
    }
}

function sendHelpDeskNotification($requestData)
{
    $mail = new PHPMailer(true);

    try {
        // SMTP Configuration
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        // Email content preparation
        $mail->setFrom(SMTP_FROM_EMAIL ?: SMTP_USERNAME, SMTP_FROM_NAME);

        // Add help desk recipients
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $stmt = $pdo->prepare("SELECT username as email, username FROM admin_users WHERE role = 'help_desk'");
        $stmt->execute();
        $helpDeskUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($helpDeskUsers)) {
            error_log("No help desk users found to notify");
            return false;
        }

        foreach ($helpDeskUsers as $user) {
            if (!empty($user['email'])) {
                $mail->addAddress($user['email'], $user['username']);
            }
        }

        $mail->isHTML(true);
        $mail->Subject = 'New Access Request Forwarded - ' . $requestData['access_request_number'];

        // Create a detailed HTML email body
        $emailBody = "
        <html>
        <body style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <h2>New Access Request Forwarded</h2>
            <p>A new access request has been forwarded by a superior and requires your review.</p>
            
            <h3>Request Details</h3>
            <table style='width:100%; border-collapse: collapse;'>
                <tr>
                    <td style='border: 1px solid #ddd; padding: 8px;'><strong>Request Number:</strong></td>
                    <td style='border: 1px solid #ddd; padding: 8px;'>{$requestData['access_request_number']}</td>
                </tr>
                <tr>
                    <td style='border: 1px solid #ddd; padding: 8px;'><strong>Requestor:</strong></td>
                    <td style='border: 1px solid #ddd; padding: 8px;'>{$requestData['requestor_name']}</td>
                </tr>
                <tr>
                    <td style='border: 1px solid #ddd; padding: 8px;'><strong>Department:</strong></td>
                    <td style='border: 1px solid #ddd; padding: 8px;'>{$requestData['department']}</td>
                </tr>
                <tr>
                    <td style='border: 1px solid #ddd; padding: 8px;'><strong>Access Type:</strong></td>
                    <td style='border: 1px solid #ddd; padding: 8px;'>{$requestData['access_type']}</td>
                </tr>
                <tr>
                    <td style='border: 1px solid #ddd; padding: 8px;'><strong>Superior Notes:</strong></td>
                    <td style='border: 1px solid #ddd; padding: 8px;'>{$requestData['superior_notes']}</td>
                </tr>
            </table>

            <p>Please review this request as soon as possible by logging into the system.</p>
            
            <p style='margin-top: 20px;'>
                <a href='http://localhost/UserAccessRequest/helpdesk/login.php' 
                   style='background-color: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>
                    Review Request
                </a>
            </p>
            
            <p>Best regards,<br>IT Support System</p>
        </body>
        </html>";

        $mail->Body = $emailBody;
        $mail->AltBody = strip_tags($emailBody);

        // Send email
        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log the error
        error_log("Help Desk notification error: " . $mail->ErrorInfo);
        return false;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic authentication for this endpoint to prevent abuse
    $providedKey = $_SERVER['HTTP_X_API_KEY'] ?? ($_POST['api_key'] ?? '');
    if (!EMAIL_API_KEY || $providedKey !== EMAIL_API_KEY) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    // Only validate these fields for initial request submissions
    if (isset($_POST['form_type']) && $_POST['form_type'] === 'initial_request') {
        // Sanitize and validate input
        $formData = [
            'requestor_name' => filter_input(INPUT_POST, 'requestor_name', FILTER_SANITIZE_STRING),
            'business_unit' => filter_input(INPUT_POST, 'company', FILTER_SANITIZE_STRING),
            'access_request_number' => filter_input(INPUT_POST, 'access_request_number', FILTER_SANITIZE_NUMBER_INT),
            'department' => filter_input(INPUT_POST, 'department', FILTER_SANITIZE_STRING),
            'email' => filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL),
            'contact_number' => filter_input(INPUT_POST, 'contact_number', FILTER_SANITIZE_STRING),
            'access_type' => filter_input(INPUT_POST, 'access_type', FILTER_SANITIZE_STRING),
            'duration_type' => filter_input(INPUT_POST, 'duration_type', FILTER_SANITIZE_STRING),
            'start_date' => filter_input(INPUT_POST, 'start_date', FILTER_SANITIZE_STRING),
            'end_date' => filter_input(INPUT_POST, 'end_date', FILTER_SANITIZE_STRING),
            'justification' => filter_input(INPUT_POST, 'justification', FILTER_SANITIZE_STRING)
        ];

        // Additional validations
        $errors = [];
        if (empty($formData['requestor_name'])) $errors[] = 'Requestor name is required';
        if (empty($formData['email']) || !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address';

        if (empty($errors)) {
            // Attempt to send confirmation email
            $emailSent = sendConfirmationEmail($formData);

            if ($emailSent) {
                // Prepare response
                $response = [
                    'success' => true,
                    'message' => 'Form submitted successfully. A confirmation email has been sent to ' . $formData['email']
                ];
            } else {
                $response = [
                    'success' => false,
                    'message' => 'Form submitted, but there was an issue sending the confirmation email.'
                ];
            }
        } else {
            $response = [
                'success' => false,
                'message' => implode(', ', $errors)
            ];
        }

        // Send JSON response
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}

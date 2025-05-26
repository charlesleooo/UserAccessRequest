<?php
session_start();
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug: Log POST data
error_log("POST data received: " . print_r($_POST, true));

// Check if user is logged in
if (!isset($_SESSION['requestor_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit();
}

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'data' => []
];

// Add PHPMailer requirements
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

require '../vendor/autoload.php';
require_once '../config.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Set response header
    header('Content-Type: application/json');

    // Check if user_forms is set (multi-user form submission)
    if (isset($_POST['user_forms'])) {
        // Debug: Log user_forms data
        error_log("user_forms received: " . $_POST['user_forms']);
        
        // Safely decode JSON
        $userForms = json_decode($_POST['user_forms'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode([
                'success' => false,
                'message' => 'Error parsing form data: ' . json_last_error_msg()
            ]);
            exit();
        }
        
        $all_success = true;
        $error_message = '';
        $first_access_request_number = '';
        $first_superior = null;
        
        // Make sure user_forms is an array
        if (!is_array($userForms)) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid form data structure'
            ]);
            exit();
        }
        
        foreach ($userForms as $form_index => $form) {
            // For each form, always re-query the max number
            $year = date('Y');
            $sql = "SELECT MAX(request_num) as max_num FROM (
                SELECT CAST(SUBSTRING_INDEX(access_request_number, '-', -1) AS UNSIGNED) as request_num 
                FROM access_requests 
                WHERE access_request_number LIKE :year_prefix
                UNION
                SELECT CAST(SUBSTRING_INDEX(access_request_number, '-', -1) AS UNSIGNED) as request_num 
                FROM approval_history 
                WHERE access_request_number LIKE :year_prefix
            ) combined";
            $stmt = $pdo->prepare($sql);
            $year_prefix = "UAR-REQ$year-%";
            $stmt->execute(['year_prefix' => $year_prefix]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $next_num = ($result['max_num'] ?? 0) + 1;
            $access_request_number = sprintf("UAR-REQ%d-%03d", $year, $next_num);
            if ($form_index === 0) {
                $first_access_request_number = $access_request_number;
            }

            // Prepare the SQL statement
            $sql = "INSERT INTO access_requests (
                requestor_name,
                business_unit,
                access_request_number,
                department,
                email,
                employee_id,
                request_date,
                access_type,
                system_type,
                application_system,
                other_system_type,
                role_access_type,
                duration_type,
                start_date,
                end_date,
                date_needed,
                access_level,
                usernames,
                justification,
                submission_date,
                status
            ) VALUES (
                :requestor_name,
                :business_unit,
                :access_request_number,
                :department,
                :email,
                :employee_id,
                :request_date,
                :access_type,
                :system_type,
                :application_system,
                :other_system_type,
                :role_access_type,
                :duration_type,
                :start_date,
                :end_date,
                :date_needed,
                :access_level,
                :usernames,
                :justification,
                NOW(),
                'pending_superior'
            )";
            $stmt = $pdo->prepare($sql);

            // Handle system type array if present
            $system_type = null;
            if (isset($form['system_type']) && is_array($form['system_type'])) {
                $system_type = implode(', ', $form['system_type']);
            }
            // Handle usernames array if present
            $usernames = null;
            if (isset($form['usernames']) && is_array($form['usernames'])) {
                $usernames = json_encode($form['usernames']);
            }
            // Execute with parameters
            $success = $stmt->execute([
                'requestor_name' => $_POST['requestor_name'],
                'business_unit' => $_POST['business_unit'],
                'access_request_number' => $access_request_number,
                'department' => $_POST['department'],
                'email' => $_POST['email'],
                'employee_id' => $_POST['employee_id'],
                'request_date' => $_POST['request_date'],
                'access_type' => $form['accessType'] ?? $form['access_type'] ?? null,
                'system_type' => $system_type,
                'application_system' => $form['application_system'] ?? null,
                'other_system_type' => $form['otherSystemType'] ?? $form['other_system_type'] ?? null,
                'role_access_type' => $form['roleAccessType'] ?? $form['role_access_type'] ?? null,
                'duration_type' => $form['durationType'] ?? $form['duration_type'] ?? null,
                'start_date' => (isset($form['durationType']) && $form['durationType'] === 'temporary') || (isset($form['duration_type']) && $form['duration_type'] === 'temporary') ? ($form['startDate'] ?? $form['start_date'] ?? null) : null,
                'end_date' => (isset($form['durationType']) && $form['durationType'] === 'temporary') || (isset($form['duration_type']) && $form['duration_type'] === 'temporary') ? ($form['endDate'] ?? $form['end_date'] ?? null) : null,
                'date_needed' => $form['date_needed'] ?? null,
                'access_level' => $form['accessLevel'] ?? $form['access_level'] ?? null,
                'usernames' => $usernames,
                'justification' => $form['justification'] ?? null
            ]);
            if (!$success) {
                $all_success = false;
                $error_message = 'Failed to insert record for user form #' . ($form_index + 1);
                break;
            }

            // Get the superior from the same department (only for first form, to avoid spamming)
            if ($form_index === 0) {
                $stmt = $pdo->prepare("SELECT employee_id, employee_name, employee_email 
                                      FROM employees 
                                      WHERE department = ? 
                                      AND role = 'superior' 
                                      LIMIT 1");
                $stmt->execute([$_POST['department']]);
                $superior = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$superior) {
                    $stmt = $pdo->prepare("SELECT employee_id, employee_name, employee_email 
                                         FROM employees 
                                         WHERE role = 'superior' 
                                         LIMIT 1");
                    $stmt->execute();
                    $superior = $stmt->fetch(PDO::FETCH_ASSOC);
                }
                $first_superior = $superior;
                // Update the request with the superior's ID
                $stmt = $pdo->prepare("UPDATE access_requests 
                                      SET superior_id = ? 
                                      WHERE access_request_number = ?");
                $stmt->execute([$superior['employee_id'], $access_request_number]);
            }
        }
        if ($all_success) {
            // Send email only for the first user form
            // (reuse your existing email code, using $first_access_request_number and $first_superior)
            // ... (existing email code here, using $userForms[0] for details) ...
            // For brevity, you can copy your email code and adjust variables as needed
            echo json_encode([
                'success' => true,
                'message' => "Access requests submitted successfully! First request number is $first_access_request_number."
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => $error_message
            ]);
        }
        exit();
    }

    // Generate access request number (UAR-REQ2025-XXX format)
    $year = date('Y');
    
    // Check both tables to find the highest request number
    $sql = "SELECT MAX(request_num) as max_num FROM (
        SELECT CAST(SUBSTRING_INDEX(access_request_number, '-', -1) AS UNSIGNED) as request_num 
        FROM access_requests 
        WHERE access_request_number LIKE :year_prefix
        UNION
        SELECT CAST(SUBSTRING_INDEX(access_request_number, '-', -1) AS UNSIGNED) as request_num 
        FROM approval_history 
        WHERE access_request_number LIKE :year_prefix
    ) combined";
    
    $stmt = $pdo->prepare($sql);
    $year_prefix = "UAR-REQ$year-%";
    $stmt->execute(['year_prefix' => $year_prefix]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $next_num = ($result['max_num'] ?? 0) + 1;
    $access_request_number = sprintf("UAR-REQ%d-%03d", $year, $next_num);

    // Verify the generated number doesn't exist in either table
    $check_sql = "SELECT 1 FROM (
        SELECT access_request_number FROM access_requests
        UNION
        SELECT access_request_number FROM approval_history
    ) combined WHERE access_request_number = :request_number";
    
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute(['request_number' => $access_request_number]);
    
    if ($check_stmt->rowCount() > 0) {
        throw new Exception('Generated request number already exists');
    }

    // Prepare the SQL statement
    $sql = "INSERT INTO access_requests (
        requestor_name,
        business_unit,
        access_request_number,
        department,
        email,
        employee_id,
        request_date,
        access_type,
        system_type,
        application_system,
        other_system_type,
        role_access_type,
        duration_type,
        start_date,
        end_date,
        date_needed,
        access_level,
        usernames,
        justification,
        submission_date,
        status
    ) VALUES (
        :requestor_name,
        :business_unit,
        :access_request_number,
        :department,
        :email,
        :employee_id,
        :request_date,
        :access_type,
        :system_type,
        :application_system,
        :other_system_type,
        :role_access_type,
        :duration_type,
        :start_date,
        :end_date,
        :date_needed,
        :access_level,
        :usernames,
        :justification,
        NOW(),
        'pending_superior'
    )";

    $stmt = $pdo->prepare($sql);

    // Handle system type array if present
    $system_type = null;
    if (isset($_POST['system_type']) && is_array($_POST['system_type'])) {
        $system_type = implode(', ', $_POST['system_type']);
    }

    // Handle usernames array if present
    $usernames = null;
    if (isset($_POST['usernames']) && is_array($_POST['usernames'])) {
        $usernames = json_encode($_POST['usernames']);
    }

    // Execute with parameters
    $success = $stmt->execute([
        'requestor_name' => $_POST['requestor_name'],
        'business_unit' => $_POST['business_unit'],
        'access_request_number' => $access_request_number,
        'department' => $_POST['department'],
        'email' => $_POST['email'],
        'employee_id' => $_POST['employee_id'],
        'request_date' => $_POST['request_date'],
        'access_type' => $_POST['access_type'],
        'system_type' => $system_type,
        'application_system' => $_POST['application_system'] ?? null,
        'other_system_type' => $_POST['other_system_type'] ?? null,
        'role_access_type' => $_POST['role_access_type'] ?? null,
        'duration_type' => $_POST['duration_type'],
        'start_date' => $_POST['duration_type'] === 'temporary' ? $_POST['start_date'] : null,
        'end_date' => $_POST['duration_type'] === 'temporary' ? $_POST['end_date'] : null,
        'date_needed' => $_POST['date_needed'] ?? null,
        'access_level' => $_POST['access_level'] ?? null,
        'usernames' => $usernames,
        'justification' => $_POST['justification']
    ]);

    if ($success) {
        // Get the superior from the same department
        $stmt = $pdo->prepare("SELECT employee_id, employee_name, employee_email 
                              FROM employees 
                              WHERE department = ? 
                              AND role = 'superior' 
                              LIMIT 1");
        $stmt->execute([$_POST['department']]);
        $superior = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$superior) {
            // If no superior found for the department, use a default superior
            $stmt = $pdo->prepare("SELECT employee_id, employee_name, employee_email 
                                 FROM employees 
                                 WHERE role = 'superior' 
                                 LIMIT 1");
            $stmt->execute();
            $superior = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        // Update the request with the superior's ID
        $stmt = $pdo->prepare("UPDATE access_requests 
                              SET superior_id = ? 
                              WHERE access_request_number = ?");
        $stmt->execute([$superior['employee_id'], $access_request_number]);

        // Create PHPMailer instance
        $mail = new PHPMailer(true);
        
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'charlesondota@gmail.com';
            $mail->Password   = 'crpf bbcb vodv xbjk';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            // Recipients
            $mail->setFrom('charlesondota@gmail.com', 'Access Request System');
            $mail->addAddress($_POST['email'], $_POST['requestor_name']); // Add requestor
            // $mail->addAddress($superior['employee_email'], $superior['employee_name']); // Remove superior
            // $mail->addAddress('charlesondota@gmail.com', 'System Administrator'); // Remove admin

            // Content
            $mail->isHTML(true);
            $mail->Subject = "Access Request Submitted - $access_request_number";
            
            // Format system types if present
            $system_types_display = '';
            if (isset($_POST['system_type']) && is_array($_POST['system_type'])) {
                $system_types_display = implode(', ', $_POST['system_type']);
                if (in_array('other', $_POST['system_type']) && !empty($_POST['other_system_type'])) {
                    $system_types_display = str_replace('other', $_POST['other_system_type'], $system_types_display);
                }
            }

            // Format duration details
            $duration_details = $_POST['duration_type'] === 'permanent' ? 
                'Permanent' : 
                "Temporary (From: {$_POST['start_date']} To: {$_POST['end_date']})";

            $mail->Body = "
                <h2>Access Request Details</h2>
                <div style='font-family: Arial, sans-serif; line-height: 1.6;'>
                    <div style='background-color: #fff3cd; border: 1px solid #ffeeba; color: #856404; padding: 15px; margin-bottom: 20px; border-radius: 4px;'>
                        <strong>Dear {$superior['employee_name']},</strong><br><br>
                        A new access request has been submitted by a member of your department and requires your review.
                        <p style='margin-top: 10px;'>
                            <a href='http://your-domain/superior/login.php' style='background-color: #0d6efd; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>Review Request</a>
                        </p>
                    </div>

                    <h3>Request Information:</h3>
                    <table style='border-collapse: collapse; width: 100%; margin-bottom: 20px;'>
                        <tr style='background-color: #f8f9fa;'>
                            <td style='padding: 8px; border: 1px solid #ddd;'><strong>Request Number:</strong></td>
                            <td style='padding: 8px; border: 1px solid #ddd;'>{$access_request_number}</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px; border: 1px solid #ddd;'><strong>Submission Date:</strong></td>
                            <td style='padding: 8px; border: 1px solid #ddd;'>" . date('Y-m-d H:i:s') . "</td>
                        </tr>
                        <tr style='background-color: #f8f9fa;'>
                            <td style='padding: 8px; border: 1px solid #ddd;'><strong>Status:</strong></td>
                            <td style='padding: 8px; border: 1px solid #ddd;'>Pending Your Review</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px; border: 1px solid #ddd;'><strong>Department Superior:</strong></td>
                            <td style='padding: 8px; border: 1px solid #ddd;'>{$superior['employee_name']}</td>
                        </tr>
                    </table>

                    <h3>Requestor Information:</h3>
                    <table style='border-collapse: collapse; width: 100%; margin-bottom: 20px;'>
                        <tr style='background-color: #f8f9fa;'>
                            <td style='padding: 8px; border: 1px solid #ddd;'><strong>Requestor:</strong></td>
                            <td style='padding: 8px; border: 1px solid #ddd;'>{$_POST['requestor_name']}</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px; border: 1px solid #ddd;'><strong>Business Unit:</strong></td>
                            <td style='padding: 8px; border: 1px solid #ddd;'>{$_POST['business_unit']}</td>
                        </tr>
                        <tr style='background-color: #f8f9fa;'>
                            <td style='padding: 8px; border: 1px solid #ddd;'><strong>Department:</strong></td>
                            <td style='padding: 8px; border: 1px solid #ddd;'>{$_POST['department']}</td>
                        </tr>
                        <tr style='background-color: #f8f9fa;'>
                            <td style='padding: 8px; border: 1px solid #ddd;'><strong>Email:</strong></td>
                            <td style='padding: 8px; border: 1px solid #ddd;'>{$_POST['email']}</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px; border: 1px solid #ddd;'><strong>Employee ID:</strong></td>
                            <td style='padding: 8px; border: 1px solid #ddd;'>{$_POST['employee_id']}</td>
                        </tr>
                    </table>

                    <h3>Access Details:</h3>
                    <table style='border-collapse: collapse; width: 100%; margin-bottom: 20px;'>
                        <tr style='background-color: #f8f9fa;'>
                            <td style='padding: 8px; border: 1px solid #ddd;'><strong>Access Type:</strong></td>
                            <td style='padding: 8px; border: 1px solid #ddd;'>{$_POST['access_type']}</td>
                        </tr>
                        " . (!empty($_POST['access_level']) ? "
                        <tr>
                            <td style='padding: 8px; border: 1px solid #ddd;'><strong>Access Level:</strong></td>
                            <td style='padding: 8px; border: 1px solid #ddd;'>{$_POST['access_level']}</td>
                        </tr>
                        " : "") . "
                        " . (!empty($_POST['usernames']) ? "
                        <tr>
                            <td style='padding: 8px; border: 1px solid #ddd;'><strong>Usernames:</strong></td>
                            <td style='padding: 8px; border: 1px solid #ddd;'>" . implode(', ', $_POST['usernames']) . "</td>
                        </tr>
                        " : "") . "
                        " . (!empty($system_types_display) ? "
                        <tr>
                            <td style='padding: 8px; border: 1px solid #ddd;'><strong>System/Application Type:</strong></td>
                            <td style='padding: 8px; border: 1px solid #ddd;'>{$system_types_display}</td>
                        </tr>
                        " : "") . "
                        " . (!empty($_POST['application_system']) ? "
                        <tr>
                            <td style='padding: 8px; border: 1px solid #ddd;'><strong>Application System:</strong></td>
                            <td style='padding: 8px; border: 1px solid #ddd;'>{$_POST['application_system']}</td>
                        </tr>
                        " : "") . "
                        <tr>
                            <td style='padding: 8px; border: 1px solid #ddd;'><strong>Duration:</strong></td>
                            <td style='padding: 8px; border: 1px solid #ddd;'>{$duration_details}</td>
                        </tr>
                        <tr style='background-color: #f8f9fa;'>
                            <td style='padding: 8px; border: 1px solid #ddd;'><strong>Justification:</strong></td>
                            <td style='padding: 8px; border: 1px solid #ddd;'>{$_POST['justification']}</td>
                        </tr>
                    </table>

                    <p style='color: #666; font-size: 0.9em;'>This is an automated message. Please do not reply to this email.</p>
                </div>
            ";

            $mail->AltBody = strip_tags($mail->Body);

            $mail->send();
            
            echo json_encode([
                'success' => true,
                'message' => "Access request submitted successfully! Your request number is $access_request_number. A confirmation email has been sent."
            ]);
        } catch (PHPMailerException $e) {
            // Log email error but don't prevent successful submission response
            error_log("Email sending failed: {$mail->ErrorInfo}");
            echo json_encode([
                'success' => true,
                'message' => "Access request submitted successfully! Your request number is $access_request_number. (Email notification failed)"
            ]);
        }
    } else {
        throw new Exception('Failed to insert record');
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

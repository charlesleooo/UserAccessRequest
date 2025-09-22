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
            error_log("JSON decode error: " . json_last_error_msg());
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
            error_log("userForms is not an array: " . gettype($userForms));
            echo json_encode([
                'success' => false,
                'message' => 'Invalid form data structure'
            ]);
            exit();
        }

        // Debug: Log the count of forms
        error_log("Processing " . count($userForms) . " forms");

        foreach ($userForms as $form_index => $form) {
            // Debug: Log the current form being processed
            error_log("Processing form #" . ($form_index + 1) . ": " . print_r($form, true));

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

            try {
                $stmt = $pdo->prepare($sql);
                $year_prefix = "$year-%";
                $stmt->execute(['year_prefix' => $year_prefix]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $next_num = ($result['max_num'] ?? 0) + 1;
                $access_request_number = sprintf("%d-%03d", $year, $next_num);

                // Log the generated request number for debugging
                error_log("Generated access_request_number: " . $access_request_number);

                if ($form_index === 0) {
                    $first_access_request_number = $access_request_number;
                }
            } catch (PDOException $e) {
                error_log("Error generating request number: " . $e->getMessage());
                throw new Exception("Failed to generate request number: " . $e->getMessage());
            }

            try {
                // Handle system type array if present
                $system_type = null;
                if (isset($form['system_type'])) {
                    $system_type = is_array($form['system_type']) ? implode(', ', $form['system_type']) : $form['system_type'];
                }

                // Handle usernames array if present
                $usernames = null;
                if (isset($form['user_names']) && is_array($form['user_names'])) {
                    $usernames = json_encode($form['user_names']);
                }

                // Simplify the SQL - use only essential fields to avoid errors
                $sql = "INSERT INTO access_requests (
                    requestor_name, business_unit, access_request_number, department, 
                    email, employee_id, request_date, access_type, system_type, 
                    application_system, role_access_type, duration_type, 
                    start_date, end_date, date_needed, justification, usernames, 
                    submission_date, status
                ) VALUES (
                    :requestor_name, :business_unit, :access_request_number, :department,
                    :email, :employee_id, :request_date, :access_type, :system_type,
                    :application_system, :role_access_type, :duration_type,
                    :start_date, :end_date, :date_needed, :justification, :usernames,
                    NOW(), 'pending_superior'
                )";

                $stmt = $pdo->prepare($sql);

                // Simplified parameters
                $params = [
                    'requestor_name' => $_POST['requestor_name'],
                    'business_unit' => $_POST['business_unit'],
                    'access_request_number' => $access_request_number,
                    'department' => $_POST['department'],
                    'email' => $_POST['email'],
                    'employee_id' => $_POST['employee_id'],
                    'request_date' => $_POST['request_date'],
                    'access_type' => $form['access_type'] ?? null,
                    'system_type' => $system_type,
                    'application_system' => $form['application_system'] ?? null,
                    'role_access_type' => $form['role_access_type'] ?? null,
                    'duration_type' => $form['duration_type'] ?? null,
                    'start_date' => (isset($form['duration_type']) && $form['duration_type'] === 'temporary') ? ($form['start_date'] ?? null) : null,
                    'end_date' => (isset($form['duration_type']) && $form['duration_type'] === 'temporary') ? ($form['end_date'] ?? null) : null,
                    'date_needed' => $form['date_needed'] ?? null,
                    'justification' => $form['justification'] ?? null,
                    'usernames' => $usernames
                ];

                // Debug parameters
                error_log("Executing SQL with params: " . print_r($params, true));

                $success = $stmt->execute($params);

                if (!$success) {
                    $all_success = false;
                    $error_message = 'Failed to insert record for user form #' . ($form_index + 1) . ': ' . implode(', ', $stmt->errorInfo());
                    error_log("SQL Error: " . implode(', ', $stmt->errorInfo()));
                    break;
                }
            } catch (PDOException $e) {
                error_log("PDO Exception in form insertion: " . $e->getMessage());
                $all_success = false;
                $error_message = 'Database error for user form #' . ($form_index + 1) . ': ' . $e->getMessage();
                break;
            }

            // Get the superior from the same department (only for first form, to avoid spamming)
            try {
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

                    // Log superior info
                    error_log("Superior found: " . print_r($superior, true));

                    // Update the request with the superior's ID
                    if ($superior) {
                        $stmt = $pdo->prepare("UPDATE access_requests 
                                              SET superior_id = ? 
                                              WHERE access_request_number = ?");
                        $stmt->execute([$superior['employee_id'], $access_request_number]);
                    } else {
                        error_log("No superior found for department: " . $_POST['department']);
                    }
                }
            } catch (PDOException $e) {
                error_log("Error updating superior info: " . $e->getMessage());
                // Don't fail the whole process if superior update fails
            }
        }

        if ($all_success) {
            // Simply respond with success, no need for email here
            echo json_encode([
                'success' => true,
                'message' => "Access requests submitted successfully! Request number is $first_access_request_number."
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => $error_message
            ]);
        }
        exit();
    }

    // Single request processing
    // Generate access request number (2025-XXX format)
    $year = date('Y');

    try {
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
        $year_prefix = "$year-%";
        $stmt->execute(['year_prefix' => $year_prefix]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $next_num = ($result['max_num'] ?? 0) + 1;
        $access_request_number = sprintf("%d-%03d", $year, $next_num);

        // Log the generated access request number for debugging
        error_log("Single request - Generated access_request_number: " . $access_request_number);

        // Handle system type array if present
        $system_type = null;
        if (isset($_POST['system_type'])) {
            $system_type = is_array($_POST['system_type']) ? implode(', ', $_POST['system_type']) : $_POST['system_type'];
        }

        // Handle usernames array if present
        $usernames = null;
        if (isset($_POST['usernames']) && is_array($_POST['usernames'])) {
            $usernames = json_encode($_POST['usernames']);
        }

        // Simplified SQL query with essential fields only
        $sql = "INSERT INTO access_requests (
            requestor_name, business_unit, access_request_number, department, 
            email, employee_id, request_date, access_type, system_type, 
            application_system, role_access_type, duration_type, 
            start_date, end_date, date_needed, justification, usernames,
            submission_date, status
        ) VALUES (
            :requestor_name, :business_unit, :access_request_number, :department,
            :email, :employee_id, :request_date, :access_type, :system_type,
            :application_system, :role_access_type, :duration_type,
            :start_date, :end_date, :date_needed, :justification, :usernames,
            NOW(), 'pending_superior'
        )";

        $stmt = $pdo->prepare($sql);

        // Set up parameters for execution
        $params = [
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
            'role_access_type' => $_POST['role_access_type'] ?? null,
            'duration_type' => $_POST['duration_type'],
            'start_date' => $_POST['duration_type'] === 'temporary' ? $_POST['start_date'] : null,
            'end_date' => $_POST['duration_type'] === 'temporary' ? $_POST['end_date'] : null,
            'date_needed' => $_POST['date_needed'] ?? null,
            'justification' => $_POST['justification'],
            'usernames' => $usernames
        ];

        // Debug parameters
        error_log("Single request - Executing SQL with params: " . print_r($params, true));

        // Execute with parameters
        $success = $stmt->execute($params);

        if ($success) {
            // Simply respond with success
            echo json_encode([
                'success' => true,
                'message' => "Access request submitted successfully! Your request number is $access_request_number."
            ]);
        } else {
            error_log("SQL Error in single request: " . implode(', ', $stmt->errorInfo()));
            throw new Exception('Failed to insert record: ' . implode(', ', $stmt->errorInfo()));
        }
    } catch (PDOException $e) {
        error_log("PDO Exception in single request: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    } catch (Exception $e) {
        error_log("General Exception in single request: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
} catch (Exception $e) {
    error_log("Outer Exception: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

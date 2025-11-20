<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Authorize: allow requestor, superior, help desk, or process owner
$isRequestor = isset($_SESSION['requestor_id']);
$isSuperior = (isset($_SESSION['admin_id']) && (($_SESSION['role'] ?? '') === 'superior'));
$isHelpDesk = (isset($_SESSION['admin_id']) && (($_SESSION['role'] ?? '') === 'help_desk'));
$isProcessOwner = (isset($_SESSION['admin_id']) && (($_SESSION['role'] ?? '') === 'process_owner'));
if (!$isRequestor && !$isSuperior && !$isHelpDesk && !$isProcessOwner) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Initialize response array
$response = ['success' => false, 'message' => '', 'data' => []];

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

require '../vendor/autoload.php';
require_once '../config.php';

try {
    $dsn = "sqlsrv:Server=" . DB_HOST . ";Database=" . DB_NAME . ";TrustServerCertificate=yes";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_UTF8,
    ]);

    header('Content-Type: application/json');

    // If submission originates from an Admin role, align employee_id
    if ($isSuperior || $isHelpDesk || $isProcessOwner) {
        if (!isset($_POST['employee_id']) || empty($_POST['employee_id'])) {
            $_POST['employee_id'] = $_SESSION['admin_id'];
        }
    }

    // Handle multi-user form submission
    if (isset($_POST['user_forms'])) {
        $userForms = json_decode($_POST['user_forms'], true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($userForms)) {
            echo json_encode(['success' => false, 'message' => 'Invalid form data']);
            exit();
        }

        $all_success = true;
        $error_message = '';

        // Generate request number with SERIALIZABLE isolation and locks
        $year = date('Y');
        $year_prefix = "$year-%";
        $pdo->exec("SET TRANSACTION ISOLATION LEVEL SERIALIZABLE");
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT MAX(CAST(RIGHT(access_request_number, LEN(access_request_number) - CHARINDEX('-', access_request_number)) AS INT)) as max_num FROM uar.access_requests WITH (UPDLOCK, HOLDLOCK) WHERE access_request_number LIKE :year_prefix");
            $stmt->execute(['year_prefix' => $year_prefix]);
            $max1 = $stmt->fetch(PDO::FETCH_ASSOC)['max_num'] ?? 0;

            $stmt = $pdo->prepare("SELECT MAX(CAST(RIGHT(access_request_number, LEN(access_request_number) - CHARINDEX('-', access_request_number)) AS INT)) as max_num FROM uar.approval_history WITH (UPDLOCK, HOLDLOCK) WHERE access_request_number LIKE :year_prefix");
            $stmt->execute(['year_prefix' => $year_prefix]);
            $max2 = $stmt->fetch(PDO::FETCH_ASSOC)['max_num'] ?? 0;

            $next_num = max($max1, $max2) + 1;
            $access_request_number = sprintf("%d-%03d", $year, $next_num);
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }

        // Insert main access request
        $pdo->beginTransaction();
        $sql = "INSERT INTO uar.access_requests (
                requestor_name, business_unit, access_request_number, department,
                employee_email, employee_id, request_date, system_type, other_system_type,
                submission_date, status
            ) VALUES (
                :requestor_name, :business_unit, :access_request_number, :department,
                :employee_email, :employee_id, :request_date, :system_type, :other_system_type,
                GETDATE(), 'pending_superior'
            )";
        $stmt = $pdo->prepare($sql);

        $allSystemTypes = array_map(function ($form) {
            if (isset($form['system_type'])) {
                return is_array($form['system_type']) ? implode(', ', $form['system_type']) : $form['system_type'];
            }
            return null;
        }, $userForms);
        $allSystemTypes = array_filter($allSystemTypes);
        $combinedSystemType = implode(', ', array_unique($allSystemTypes));

        $params = [
            'requestor_name' => $_POST['requestor_name'] ?? '',
            'business_unit' => $_POST['company'] ?? '',
            'access_request_number' => $access_request_number,
            'department' => $_POST['department'] ?? '',
            'employee_email' => ($_POST['employee_email'] ?? ($_POST['email'] ?? null)),
            'employee_id' => $_POST['employee_id'] ?? '',
            'request_date' => $_POST['request_date'] ?? date('Y-m-d'),
            'system_type' => $combinedSystemType,
            'other_system_type' => null
        ];
        $success = $stmt->execute($params);
        if (!$success) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Failed to insert main request record']);
            exit();
        }

        // Insert child rows
        foreach ($userForms as $form_index => $form) {
            $usernames = [];
            if (isset($form['user_names'])) {
                $usernames = is_array($form['user_names']) ? array_values(array_filter($form['user_names'])) : [$form['user_names']];
            }
            $requestTable = ($form['access_type'] === 'individual') ? 'uar.individual_requests' : 'uar.group_requests';
            $sql = "INSERT INTO {$requestTable} (
                        access_request_number, username, application_system, access_type,
                        access_duration, start_date, end_date, date_needed, justification
                    ) VALUES (
                        :access_request_number, :username, :application_system, :access_type,
                        :access_duration, :start_date, :end_date, :date_needed, :justification
                    )";
            $stmt = $pdo->prepare($sql);
            if (!empty($usernames)) {
                foreach ($usernames as $username) {
                    $requestParams = [
                        'access_request_number' => $access_request_number,
                        'username' => $username,
                        'application_system' => $form['application_system'] ?? null,
                        'access_type' => $form['role_access_type'] ?? null,
                        'access_duration' => $form['duration_type'] ?? null,
                        'start_date' => (isset($form['duration_type']) && $form['duration_type'] === 'temporary') ? ($form['start_date'] ?? null) : null,
                        'end_date' => (isset($form['duration_type']) && $form['duration_type'] === 'temporary') ? ($form['end_date'] ?? null) : null,
                        'date_needed' => $form['date_needed'] ?? null,
                        'justification' => $form['justification'] ?? null
                    ];
                    $success = $stmt->execute($requestParams);
                    if (!$success) {
                        $pdo->rollBack();
                        echo json_encode(['success' => false, 'message' => 'Failed to insert child record']);
                        exit();
                    }
                }
            } else {
                $requestParams = [
                    'access_request_number' => $access_request_number,
                    'username' => null,
                    'application_system' => $form['application_system'] ?? null,
                    'access_type' => $form['role_access_type'] ?? null,
                    'access_duration' => $form['duration_type'] ?? null,
                    'start_date' => (isset($form['duration_type']) && $form['duration_type'] === 'temporary') ? ($form['start_date'] ?? null) : null,
                    'end_date' => (isset($form['duration_type']) && $form['duration_type'] === 'temporary') ? ($form['end_date'] ?? null) : null,
                    'date_needed' => $form['date_needed'] ?? null,
                    'justification' => $form['justification'] ?? null
                ];
                $success = $stmt->execute($requestParams);
                if (!$success) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Failed to insert child record']);
                    exit();
                }
            }
        }

        $pdo->commit();

        // Assign a superior for the request
        try {
            $stmt = $pdo->prepare("SELECT TOP 1 employee_id, employee_name, employee_email FROM uar.employees WHERE department = ? AND role = 'superior'");
            $stmt->execute([$_POST['department']]);
            $superior = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$superior) {
                $stmt = $pdo->prepare("SELECT TOP 1 employee_id, employee_name, employee_email FROM uar.employees WHERE role = 'superior'");
                $stmt->execute();
                $superior = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            if ($superior) {
                $stmt = $pdo->prepare("UPDATE uar.access_requests SET superior_id = ? WHERE access_request_number = ?");
                $stmt->execute([$superior['employee_id'], $access_request_number]);
            }
        } catch (PDOException $e) {
            error_log("[Process Owner] Error updating superior info: " . $e->getMessage());
        }

        echo json_encode(['success' => true, 'message' => "Access requests submitted successfully! Request number is $access_request_number."]);
        exit();
    }

    // Single submission fallback (not used by current UI but supported)
    $year = date('Y');
    $year_prefix = "$year-%";
    $pdo->exec("SET TRANSACTION ISOLATION LEVEL SERIALIZABLE");
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT MAX(CAST(RIGHT(access_request_number, LEN(access_request_number) - CHARINDEX('-', access_request_number)) AS INT)) as max_num FROM uar.access_requests WITH (UPDLOCK, HOLDLOCK) WHERE access_request_number LIKE :year_prefix");
    $stmt->execute(['year_prefix' => $year_prefix]);
    $max1 = $stmt->fetch(PDO::FETCH_ASSOC)['max_num'] ?? 0;
    $stmt = $pdo->prepare("SELECT MAX(CAST(RIGHT(access_request_number, LEN(access_request_number) - CHARINDEX('-', access_request_number)) AS INT)) as max_num FROM uar.approval_history WITH (UPDLOCK, HOLDLOCK) WHERE access_request_number LIKE :year_prefix");
    $stmt->execute(['year_prefix' => $year_prefix]);
    $max2 = $stmt->fetch(PDO::FETCH_ASSOC)['max_num'] ?? 0;
    $next_num = max($max1, $max2) + 1;
    $access_request_number = sprintf("%d-%03d", $year, $next_num);
    $pdo->commit();

    $system_type = null;
    if (isset($_POST['system_type'])) {
        $system_type = is_array($_POST['system_type']) ? implode(', ', $_POST['system_type']) : $_POST['system_type'];
    }

    $pdo->beginTransaction();
    $sql = "INSERT INTO uar.access_requests (
                requestor_name, business_unit, access_request_number, department,
                employee_email, employee_id, request_date, system_type, other_system_type,
                submission_date, status
            ) VALUES (
                :requestor_name, :business_unit, :access_request_number, :department,
                :employee_email, :employee_id, :request_date, :system_type, :other_system_type,
                GETDATE(), 'pending_superior'
            )";
    $stmt = $pdo->prepare($sql);
    $params = [
        'requestor_name' => $_POST['requestor_name'] ?? '',
        'business_unit' => $_POST['company'] ?? '',
        'access_request_number' => $access_request_number,
        'department' => $_POST['department'] ?? '',
        'employee_email' => ($_POST['employee_email'] ?? ($_POST['email'] ?? null)),
        'employee_id' => $_POST['employee_id'] ?? '',
        'request_date' => $_POST['request_date'] ?? date('Y-m-d'),
        'system_type' => $system_type,
        'other_system_type' => $_POST['other_system_type'] ?? null
    ];
    $success = $stmt->execute($params);
    if (!$success) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to insert record']);
        exit();
    }

    $usernames = [];
    if (isset($_POST['usernames'])) {
        $usernames = is_array($_POST['usernames']) ? array_values(array_filter($_POST['usernames'])) : [$_POST['usernames']];
    }
    $requestTable = ($_POST['access_type'] === 'individual') ? 'uar.individual_requests' : 'uar.group_requests';
    $sql = "INSERT INTO {$requestTable} (
                access_request_number, username, application_system, access_type,
                access_duration, start_date, end_date, date_needed, justification
            ) VALUES (
                :access_request_number, :username, :application_system, :access_type,
                :access_duration, :start_date, :end_date, :date_needed, :justification
            )";
    $stmt = $pdo->prepare($sql);
    if (!empty($usernames)) {
        foreach ($usernames as $username) {
            $requestParams = [
                'access_request_number' => $access_request_number,
                'username' => $username,
                'application_system' => $_POST['application_system'] ?? null,
                'access_type' => $_POST['role_access_type'] ?? null,
                'access_duration' => $_POST['duration_type'] ?? null,
                'start_date' => ($_POST['duration_type'] ?? '') === 'temporary' ? ($_POST['start_date'] ?? null) : null,
                'end_date' => ($_POST['duration_type'] ?? '') === 'temporary' ? ($_POST['end_date'] ?? null) : null,
                'date_needed' => $_POST['date_needed'] ?? null,
                'justification' => $_POST['justification'] ?? null
            ];
            $success = $stmt->execute($requestParams);
            if (!$success) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Failed to insert child record']);
                exit();
            }
        }
    } else {
        $requestParams = [
            'access_request_number' => $access_request_number,
            'username' => null,
            'application_system' => $_POST['application_system'] ?? null,
            'access_type' => $_POST['role_access_type'] ?? null,
            'access_duration' => $_POST['duration_type'] ?? null,
            'start_date' => ($_POST['duration_type'] ?? '') === 'temporary' ? ($_POST['start_date'] ?? null) : null,
            'end_date' => ($_POST['duration_type'] ?? '') === 'temporary' ? ($_POST['end_date'] ?? null) : null,
            'date_needed' => $_POST['date_needed'] ?? null,
            'justification' => $_POST['justification'] ?? null
        ];
        $success = $stmt->execute($requestParams);
        if (!$success) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Failed to insert child record']);
            exit();
        }
    }
    $pdo->commit();

    echo json_encode(['success' => true, 'message' => "Access request submitted successfully! Your request number is $access_request_number."]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[Process Owner] Submit error: ' . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

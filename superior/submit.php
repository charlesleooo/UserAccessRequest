<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug: Log POST data
error_log("[Superior] POST data received: " . print_r($_POST, true));

// Authorize: allow either a logged-in requestor OR a logged-in superior
$isRequestor = isset($_SESSION['requestor_id']);
$isSuperior = (isset($_SESSION['admin_id']) && (($_SESSION['role'] ?? '') === 'superior'));
if (!$isRequestor && !$isSuperior) {
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
    $dsn = "sqlsrv:Server=" . DB_HOST . ";Database=" . DB_NAME . ";TrustServerCertificate=yes";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_UTF8,
    ]);

    // Set response header
    header('Content-Type: application/json');

    // If this submission originates from a Superior, ensure employee_id aligns with their session
    if ($isSuperior) {
        if (!isset($_POST['employee_id']) || empty($_POST['employee_id'])) {
            // Force employee_id to be the superior's employee_id
            $_POST['employee_id'] = $_SESSION['admin_id'];
        }
    }

    // Check if user_forms is set (multi-user form submission)
    if (isset($_POST['user_forms'])) {
        error_log("[Superior] user_forms received: " . $_POST['user_forms']);

        $userForms = json_decode($_POST['user_forms'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("[Superior] JSON decode error: " . json_last_error_msg());
            echo json_encode([
                'success' => false,
                'message' => 'Error parsing form data: ' . json_last_error_msg()
            ]);
            exit();
        }
        error_log("[Superior] Decoded userForms: " . print_r($userForms, true));

        $all_success = true;
        $error_message = '';
        $first_access_request_number = '';
        $first_superior = null;

        if (!is_array($userForms)) {
            error_log("[Superior] userForms is not an array: " . gettype($userForms));
            echo json_encode([
                'success' => false,
                'message' => 'Invalid form data structure'
            ]);
            exit();
        }

        error_log("[Superior] Processing " . count($userForms) . " forms");

        // Generate one request number for all forms with proper locking to prevent race conditions
        $year = date('Y');
        $year_prefix = "$year-%";
        $pdo->exec("SET TRANSACTION ISOLATION LEVEL SERIALIZABLE");
        $pdo->beginTransaction();
        try {
            $sql1 = "SELECT MAX(CAST(RIGHT(access_request_number, LEN(access_request_number) - CHARINDEX('-', access_request_number)) AS INT)) as max_num 
                         FROM uar.access_requests WITH (UPDLOCK, HOLDLOCK)
                         WHERE access_request_number LIKE :year_prefix";
            $stmt = $pdo->prepare($sql1);
            $stmt->execute(['year_prefix' => $year_prefix]);
            $result1 = $stmt->fetch(PDO::FETCH_ASSOC);
            $max1 = $result1['max_num'] ?? 0;

            $sql2 = "SELECT MAX(CAST(RIGHT(access_request_number, LEN(access_request_number) - CHARINDEX('-', access_request_number)) AS INT)) as max_num 
                         FROM uar.approval_history WITH (UPDLOCK, HOLDLOCK)
                         WHERE access_request_number LIKE :year_prefix";
            $stmt = $pdo->prepare($sql2);
            $stmt->execute(['year_prefix' => $year_prefix]);
            $result2 = $stmt->fetch(PDO::FETCH_ASSOC);
            $max2 = $result2['max_num'] ?? 0;

            $next_num = max($max1, $max2) + 1;
            $access_request_number = sprintf("%d-%03d", $year, $next_num);
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }

        $first_access_request_number = $access_request_number;
        error_log("[Superior] Generated access_request_number: " . $access_request_number);

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
        error_log("[Superior] Executing access_requests SQL with params: " . print_r($params, true));
        $success = $stmt->execute($params);
        if (!$success) {
            $all_success = false;
            $error_message = 'Failed to insert main request record: ' . implode(', ', $stmt->errorInfo());
            error_log("[Superior] SQL Error: " . implode(', ', $stmt->errorInfo()));
            $pdo->rollBack();
        } else {
            foreach ($userForms as $form_index => $form) {
                try {
                    $usernames = null;
                    if (isset($form['user_names'])) {
                        if (is_array($form['user_names'])) {
                            $filteredUsernames = array_filter($form['user_names'], function ($value) {
                                return !empty($value);
                            });
                            $usernames = $filteredUsernames;
                        } else {
                            $usernames = [$form['user_names']];
                        }
                        error_log("[Superior] Usernames for form: " . json_encode($usernames));
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
                    if (is_array($usernames) && !empty($usernames)) {
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
                            error_log("[Superior] Executing {$requestTable} SQL with params: " . print_r($requestParams, true));
                            $success = $stmt->execute($requestParams);
                            if (!$success) {
                                $all_success = false;
                                $error_message = "Failed to insert record for username {$username} in form #" . ($form_index + 1) . ': ' . implode(', ', $stmt->errorInfo());
                                error_log("[Superior] SQL Error: " . implode(', ', $stmt->errorInfo()));
                                $pdo->rollBack();
                                break 2;
                            }
                        }
                    } else {
                        $requestParams = [
                            'access_request_number' => $access_request_number,
                            'username' => is_array($usernames) && !empty($usernames) ? $usernames[0] : null,
                            'application_system' => $form['application_system'] ?? null,
                            'access_type' => $form['role_access_type'] ?? null,
                            'access_duration' => $form['duration_type'] ?? null,
                            'start_date' => (isset($form['duration_type']) && $form['duration_type'] === 'temporary') ? ($form['start_date'] ?? null) : null,
                            'end_date' => (isset($form['duration_type']) && $form['duration_type'] === 'temporary') ? ($form['end_date'] ?? null) : null,
                            'date_needed' => $form['date_needed'] ?? null,
                            'justification' => $form['justification'] ?? null
                        ];
                        error_log("[Superior] Executing {$requestTable} SQL with params: " . print_r($requestParams, true));
                        $success = $stmt->execute($requestParams);
                        if (!$success) {
                            $all_success = false;
                            $error_message = 'Failed to insert record for user form #' . ($form_index + 1) . ': ' . implode(', ', $stmt->errorInfo());
                            error_log("[Superior] SQL Error: " . implode(', ', $stmt->errorInfo()));
                            $pdo->rollBack();
                            break;
                        }
                    }
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    error_log("[Superior] PDO Exception in form insertion: " . $e->getMessage());
                    $all_success = false;
                    $error_message = 'Database error for user form #' . ($form_index + 1) . ': ' . $e->getMessage();
                    break;
                }
            }

            if ($all_success) {
                $pdo->commit();
            }

            // Assign a superior (same logic as requestor submit)
            try {
                $stmt = $pdo->prepare("SELECT TOP 1 employee_id, employee_name, employee_email 
                                        FROM uar.employees 
                                        WHERE department = ? 
                                        AND role = 'superior'");
                $stmt->execute([$_POST['department']]);
                $superior = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$superior) {
                    $stmt = $pdo->prepare("SELECT TOP 1 employee_id, employee_name, employee_email FROM uar.employees WHERE role = 'superior'");
                    $stmt->execute();
                    $superior = $stmt->fetch(PDO::FETCH_ASSOC);
                }
                $first_superior = $superior;
                error_log("[Superior] Superior found: " . print_r($superior, true));
                if ($superior) {
                    $stmt = $pdo->prepare("UPDATE uar.access_requests SET superior_id = ? WHERE access_request_number = ?");
                    $stmt->execute([$superior['employee_id'], $access_request_number]);
                } else {
                    error_log("[Superior] No superior found for department: " . ($_POST['department'] ?? ''));
                }
            } catch (PDOException $e) {
                error_log("[Superior] Error updating superior info: " . $e->getMessage());
            }
        }

        if ($all_success) {
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

    // Single request processing (unchanged apart from context)
    $year = date('Y');
    $year_prefix = "$year-%";
    try {
        $pdo->exec("SET TRANSACTION ISOLATION LEVEL SERIALIZABLE");
        $pdo->beginTransaction();
        $sql1 = "SELECT MAX(CAST(RIGHT(access_request_number, LEN(access_request_number) - CHARINDEX('-', access_request_number)) AS INT)) as max_num 
                     FROM uar.access_requests WITH (UPDLOCK, HOLDLOCK)
                     WHERE access_request_number LIKE :year_prefix";
        $stmt = $pdo->prepare($sql1);
        $stmt->execute(['year_prefix' => $year_prefix]);
        $result1 = $stmt->fetch(PDO::FETCH_ASSOC);
        $max1 = $result1['max_num'] ?? 0;
        $sql2 = "SELECT MAX(CAST(RIGHT(access_request_number, LEN(access_request_number) - CHARINDEX('-', access_request_number)) AS INT)) as max_num 
                     FROM uar.approval_history WITH (UPDLOCK, HOLDLOCK)
                     WHERE access_request_number LIKE :year_prefix";
        $stmt = $pdo->prepare($sql2);
        $stmt->execute(['year_prefix' => $year_prefix]);
        $result2 = $stmt->fetch(PDO::FETCH_ASSOC);
        $max2 = $result2['max_num'] ?? 0;
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
        error_log("[Superior] Single request - Executing access_requests SQL with params: " . print_r($params, true));
        $success = $stmt->execute($params);
        if (!$success) {
            error_log("[Superior] SQL Error in single request: " . implode(', ', $stmt->errorInfo()));
            $pdo->rollBack();
            throw new Exception('Failed to insert record: ' . implode(', ', $stmt->errorInfo()));
        }

        $usernames = null;
        if (isset($_POST['usernames'])) {
            if (is_array($_POST['usernames'])) {
                $filteredUsernames = array_filter($_POST['usernames'], function ($value) {
                    return !empty($value);
                });
                $usernames = $filteredUsernames;
            } else {
                $usernames = [$_POST['usernames']];
            }
            error_log("[Superior] Usernames for single request: " . json_encode($usernames));
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
        if (is_array($usernames) && !empty($usernames)) {
            foreach ($usernames as $username) {
                $requestParams = [
                    'access_request_number' => $access_request_number,
                    'username' => $username,
                    'application_system' => $_POST['application_system'] ?? null,
                    'access_type' => $_POST['role_access_type'] ?? null,
                    'access_duration' => $_POST['duration_type'],
                    'start_date' => $_POST['duration_type'] === 'temporary' ? $_POST['start_date'] : null,
                    'end_date' => $_POST['duration_type'] === 'temporary' ? $_POST['end_date'] : null,
                    'date_needed' => $_POST['date_needed'] ?? null,
                    'justification' => $_POST['justification']
                ];
                error_log("[Superior] Single request - Executing {$requestTable} SQL with params: " . print_r($requestParams, true));
                $success = $stmt->execute($requestParams);
                if (!$success) {
                    error_log("[Superior] SQL Error in single request username {$username}: " . implode(', ', $stmt->errorInfo()));
                    $pdo->rollBack();
                    throw new Exception("Failed to insert record for username {$username}: " . implode(', ', $stmt->errorInfo()));
                }
            }
        } else {
            $requestParams = [
                'access_request_number' => $access_request_number,
                'username' => is_array($usernames) && !empty($usernames) ? $usernames[0] : null,
                'application_system' => $_POST['application_system'] ?? null,
                'access_type' => $_POST['role_access_type'] ?? null,
                'access_duration' => $_POST['duration_type'],
                'start_date' => $_POST['duration_type'] === 'temporary' ? $_POST['start_date'] : null,
                'end_date' => $_POST['duration_type'] === 'temporary' ? $_POST['end_date'] : null,
                'date_needed' => $_POST['date_needed'] ?? null,
                'justification' => $_POST['justification']
            ];
            error_log("[Superior] Single request - Executing {$requestTable} SQL with params: " . print_r($requestParams, true));
            $success = $stmt->execute($requestParams);
            if (!$success) {
                error_log("[Superior] SQL Error in single request: " . implode(', ', $stmt->errorInfo()));
                $pdo->rollBack();
                throw new Exception('Failed to insert record: ' . implode(', ', $stmt->errorInfo()));
            }
        }
        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => "Access request submitted successfully! Your request number is $access_request_number."
        ]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("[Superior] PDO Exception in single request: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("[Superior] General Exception in single request: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} catch (Exception $e) {
    error_log("[Superior] Outer Exception: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

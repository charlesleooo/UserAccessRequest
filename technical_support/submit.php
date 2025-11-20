<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
require_once '../config.php';

// Authorize: must be Technical Support
if (!isset($_SESSION['admin_id']) || (($_SESSION['role'] ?? '') !== 'technical_support')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

try {
    // Support the multi-row create_request payload (FormData with user_forms JSON)
    if (isset($_POST['user_forms'])) {
        $userForms = json_decode($_POST['user_forms'], true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($userForms)) {
            echo json_encode(['success' => false, 'message' => 'Invalid form data']);
            exit();
        }

        // Generate request number using the same pattern as Process Owner (YYYY-###), locked across both tables
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

        // Combine system types for main record
        $allSystemTypes = array_map(function ($form) {
            if (isset($form['system_type'])) {
                return is_array($form['system_type']) ? implode(', ', $form['system_type']) : $form['system_type'];
            }
            return null;
        }, $userForms);
        $allSystemTypes = array_filter($allSystemTypes);
        $combinedSystemType = implode(', ', array_unique($allSystemTypes));

        // Insert main request with pending_superior status
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
            'employee_id' => $_POST['employee_id'] ?? ($_SESSION['admin_id'] ?? null),
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

        // Insert child rows (individual or group)
        foreach ($userForms as $form) {
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

        // Try to assign a superior based on department
        try {
            $stmt = $pdo->prepare("SELECT TOP 1 employee_id, employee_name, employee_email FROM uar.employees WHERE department = ? AND role = 'superior'");
            $stmt->execute([$_POST['department'] ?? '']);
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
            error_log("[Technical Support] Error updating superior info: " . $e->getMessage());
        }

        echo json_encode(['success' => true, 'message' => "Access requests submitted successfully! Request number is $access_request_number."]);
        exit();
    }

    // If no user_forms provided, it's an unexpected payload for TS
    echo json_encode(['success' => false, 'message' => 'No form data received']);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[Technical Support] Submit error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

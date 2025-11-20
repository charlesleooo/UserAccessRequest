<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id']) || (($_SESSION['role'] ?? '') !== 'admin')) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Helper respond
function respond($ok, $message, $extra = [])
{
    echo json_encode(array_merge(['success' => $ok, 'message' => $message], $extra));
    exit();
}

// Expect multi-row payload built in create_request.php (user_forms JSON)
if (!isset($_POST['user_forms'])) {
    respond(false, 'No form data received');
}

$userForms = json_decode($_POST['user_forms'], true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($userForms) || empty($userForms)) {
    respond(false, 'Invalid user_forms payload');
}

$requestor_name = $_POST['requestor_name'] ?? '';
$company = $_POST['company'] ?? '';
$department = $_POST['department'] ?? '';
$employee_id = $_POST['employee_id'] ?? ($_SESSION['admin_id'] ?? null);
$employee_email = $_POST['employee_email'] ?? null;
$request_date = $_POST['request_date'] ?? date('Y-m-d');

if (!$requestor_name || !$company || !$department || !$employee_id) {
    respond(false, 'Missing required requestor fields');
}

try {
    // Generate request number consistent with other roles: YYYY-### (3 digits)
    $year = date('Y');
    $year_prefix = "$year-%";
    $pdo->exec("SET TRANSACTION ISOLATION LEVEL SERIALIZABLE");
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT MAX(CAST(RIGHT(access_request_number, LEN(access_request_number) - CHARINDEX('-', access_request_number)) AS INT)) as max_num FROM uar.access_requests WITH (UPDLOCK, HOLDLOCK) WHERE access_request_number LIKE :prefix");
        $stmt->execute(['prefix' => $year_prefix]);
        $max1 = $stmt->fetch(PDO::FETCH_ASSOC)['max_num'] ?? 0;

        $stmt = $pdo->prepare("SELECT MAX(CAST(RIGHT(access_request_number, LEN(access_request_number) - CHARINDEX('-', access_request_number)) AS INT)) as max_num FROM uar.approval_history WITH (UPDLOCK, HOLDLOCK) WHERE access_request_number LIKE :prefix");
        $stmt->execute(['prefix' => $year_prefix]);
        $max2 = $stmt->fetch(PDO::FETCH_ASSOC)['max_num'] ?? 0;

        $next_num = max($max1, $max2) + 1;
        $access_request_number = sprintf('%d-%03d', $year, $next_num);
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

    // Derive combined system types from forms
    $allSystemTypes = [];
    foreach ($userForms as $f) {
        if (!empty($f['system_type'])) {
            $allSystemTypes[] = is_array($f['system_type']) ? implode(', ', $f['system_type']) : $f['system_type'];
        } elseif (!empty($f['application_system'])) { // fallback
            $allSystemTypes[] = $f['application_system'];
        }
    }
    $combinedSystemType = implode(', ', array_unique(array_filter($allSystemTypes)));

    // Insert main access request record
    $pdo->beginTransaction();
    $insertMain = $pdo->prepare("INSERT INTO uar.access_requests (
        requestor_name, business_unit, access_request_number, department,
        employee_email, employee_id, request_date, system_type, other_system_type,
        submission_date, status
    ) VALUES (
        :requestor_name, :business_unit, :access_request_number, :department,
        :employee_email, :employee_id, :request_date, :system_type, :other_system_type,
        GETDATE(), 'pending_superior'
    )");

    $insertMain->execute([
        ':requestor_name' => $requestor_name,
        ':business_unit' => $company,
        ':access_request_number' => $access_request_number,
        ':department' => $department,
        ':employee_email' => $employee_email,
        ':employee_id' => $employee_id,
        ':request_date' => $request_date,
        ':system_type' => $combinedSystemType,
        ':other_system_type' => null,
    ]);

    // Insert child rows into appropriate table (individual or group)
    foreach ($userForms as $form) {
        $accessCategory = $form['access_type'] ?? 'individual'; // 'individual' or 'group'
        $targetTable = ($accessCategory === 'group') ? 'uar.group_requests' : 'uar.individual_requests';
        $usernames = isset($form['user_names']) ? (is_array($form['user_names']) ? array_filter($form['user_names']) : [$form['user_names']]) : [];
        if (empty($usernames)) { // still insert placeholder row if needed
            $usernames = [null];
        }
        $stmtChild = $pdo->prepare("INSERT INTO {$targetTable} (
            access_request_number, username, application_system, access_type,
            access_duration, start_date, end_date, date_needed, justification
        ) VALUES (
            :access_request_number, :username, :application_system, :access_type,
            :access_duration, :start_date, :end_date, :date_needed, :justification
        )");
        foreach ($usernames as $uname) {
            $stmtChild->execute([
                ':access_request_number' => $access_request_number,
                ':username' => $uname,
                ':application_system' => $form['application_system'] ?? ($form['system_type'] ?? null),
                ':access_type' => $form['role_access_type'] ?? null,
                ':access_duration' => $form['duration_type'] ?? null,
                ':start_date' => (isset($form['duration_type']) && $form['duration_type'] === 'temporary') ? ($form['start_date'] ?? null) : null,
                ':end_date' => (isset($form['duration_type']) && $form['duration_type'] === 'temporary') ? ($form['end_date'] ?? null) : null,
                ':date_needed' => $form['date_needed'] ?? null,
                ':justification' => $form['justification'] ?? null,
            ]);
        }
    }

    $pdo->commit();

    // Assign a superior (same logic as other roles)
    try {
        $stmt = $pdo->prepare("SELECT TOP 1 employee_id FROM uar.employees WHERE department = ? AND role = 'superior'");
        $stmt->execute([$department]);
        $superior = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$superior) {
            $stmt = $pdo->query("SELECT TOP 1 employee_id FROM uar.employees WHERE role = 'superior'");
            $superior = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        if ($superior) {
            $stmt = $pdo->prepare("UPDATE uar.access_requests SET superior_id = ? WHERE access_request_number = ?");
            $stmt->execute([$superior['employee_id'], $access_request_number]);
        }
    } catch (Exception $e) {
        error_log('[Admin Submit] Superior assignment failed: ' . $e->getMessage());
    }

    respond(true, "Access requests submitted successfully! Request number is $access_request_number.", ['access_request_number' => $access_request_number]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[Admin Submit] Error: ' . $e->getMessage());
    respond(false, 'Error submitting request');
}

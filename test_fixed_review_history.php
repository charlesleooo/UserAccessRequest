<?php
session_start();
require_once 'config.php';

echo "<h2>Test Fixed Review History</h2>";

// Simulate a technical support user login
$_SESSION['admin_id'] = 'techsupp1';
$_SESSION['admin_username'] = 'technical support';
$_SESSION['role'] = 'technical_support';
$_SESSION['admin_name'] = 'technical support';

$admin_id = $_SESSION['admin_id'];
$admin_username = $_SESSION['admin_username'] ?? '';

echo "<h3>User Info:</h3>";
echo "<p>admin_id: $admin_id</p>";
echo "<p>admin_username: $admin_username</p>";

try {
    // First, get the admin_users.id from the database
    $adminQuery = $pdo->prepare("SELECT id FROM uar.admin_users WHERE username = :username OR username = :employee_id");
    $adminQuery->execute([
        'username' => $admin_username,
        'employee_id' => $admin_id
    ]);
    $adminRecord = $adminQuery->fetch(PDO::FETCH_ASSOC);
    $technical_id = $adminRecord ? $adminRecord['id'] : $admin_id;
    
    echo "<h3>Admin Record Found:</h3>";
    echo "<pre>";
    print_r($adminRecord);
    echo "</pre>";
    echo "<p>Using technical_id: $technical_id</p>";

    // Test the fixed query logic
    echo "<h3>Testing Fixed Query Logic:</h3>";
    
    // First get from access_requests table
    $stmt1 = $pdo->prepare("
        SELECT 
            ar.access_request_number,
            ar.requestor_name,
            ar.department,
            ar.business_unit,
            ar.access_level as access_type,
            ar.system_type,
            ar.technical_review_date as review_date,
            ar.technical_notes as review_notes,
            ar.status,
            '' as justification,
            ar.employee_id,
            ar.employee_email as email,
            '' as role_access_type,
            '' as duration_type,
            '' as start_date,
            '' as end_date,
            CASE 
                WHEN ar.status = 'rejected' AND ar.technical_id = :technical_id THEN 'Rejected'
                ELSE 'Approved/Forwarded'
            END as action
        FROM 
            uar.access_requests ar
        WHERE 
            ar.technical_id = :technical_id AND ar.technical_review_date IS NOT NULL
    ");
    
    $stmt1->execute(['technical_id' => $technical_id]);
    $access_requests = $stmt1->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h4>Access Requests Results:</h4>";
    echo "<p>Found " . count($access_requests) . " access request records</p>";
    if (count($access_requests) > 0) {
        echo "<pre>";
        print_r($access_requests);
        echo "</pre>";
    }
    
    // Then get from approval_history table
    $stmt2 = $pdo->prepare("
        SELECT 
            ah.access_request_number,
            ah.requestor_name,
            ah.department,
            ah.business_unit,
            ah.access_type,
            ah.system_type,
            ah.created_at as review_date,
            ah.technical_notes as review_notes,
            ah.action as status,
            ah.justification,
            ah.employee_id,
            ah.email,
            '' as role_access_type,
            ah.duration_type,
            ah.start_date,
            ah.end_date,
            CASE 
                WHEN ah.action = 'rejected' AND ah.technical_id = :technical_id THEN 'Rejected'
                ELSE 'Approved/Forwarded'
            END as action
        FROM 
            uar.approval_history ah
        WHERE 
            ah.technical_id = :technical_id
    ");
    
    $stmt2->execute(['technical_id' => $technical_id]);
    $approval_requests = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h4>Approval History Results:</h4>";
    echo "<p>Found " . count($approval_requests) . " approval history records</p>";
    if (count($approval_requests) > 0) {
        echo "<pre>";
        print_r($approval_requests);
        echo "</pre>";
    }
    
    // Combine the results
    $reviewed_requests = array_merge($access_requests, $approval_requests);
    
    // Sort by review_date descending
    usort($reviewed_requests, function($a, $b) {
        $dateA = strtotime($a['review_date'] ?? '1970-01-01');
        $dateB = strtotime($b['review_date'] ?? '1970-01-01');
        return $dateB - $dateA;
    });
    
    echo "<h4>Combined Results:</h4>";
    echo "<p>Total reviewed requests: " . count($reviewed_requests) . "</p>";
    if (count($reviewed_requests) > 0) {
        echo "<pre>";
        print_r($reviewed_requests);
        echo "</pre>";
    }

} catch (PDOException $e) {
    echo "<p style='color: red;'>Database error: " . $e->getMessage() . "</p>";
}
?>

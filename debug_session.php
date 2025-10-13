<?php
// Web-based debug script to check current user session
require_once 'config.php';

echo "<h2>Debugging Current User Session and Request Visibility</h2>";

// Check session information
echo "<h3>1. Session Information:</h3>";
if (isset($_SESSION['admin_id'])) {
    echo "Current Admin ID: " . $_SESSION['admin_id'] . "<br>";
    echo "Current Username: " . (isset($_SESSION['username']) ? $_SESSION['username'] : 'Not set') . "<br>";
    echo "Current Role: " . (isset($_SESSION['role']) ? $_SESSION['role'] : 'Not set') . "<br>";
} else {
    echo "No admin session found.<br>";
}

// Check what requests exist and their assignments
echo "<h3>2. All Requests and Their Assignments:</h3>";

// Technical requests
echo "<h4>Technical Support Requests:</h4>";
$stmt = $pdo->prepare("
    SELECT ar.id, ar.access_request_number, ar.requestor_name, ar.status, ar.technical_id
    FROM access_requests ar 
    WHERE ar.status IN ('pending_technical', 'pending_testing_setup', 'pending_testing_review')
    ORDER BY ar.id
");
$stmt->execute();
$tech_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($tech_requests)) {
    echo "No technical requests found.<br>";
} else {
    foreach ($tech_requests as $req) {
        echo "- Request #{$req['access_request_number']} from {$req['requestor_name']} (Status: {$req['status']}) → Assigned to Admin ID {$req['technical_id']}<br>";
    }
}

// Process owner requests
echo "<h4>Process Owner Requests:</h4>";
$stmt = $pdo->prepare("
    SELECT ar.id, ar.access_request_number, ar.requestor_name, ar.status, ar.process_owner_id
    FROM access_requests ar 
    WHERE ar.status = 'pending_process_owner'
    ORDER BY ar.id
");
$stmt->execute();
$po_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($po_requests)) {
    echo "No process owner requests found.<br>";
} else {
    foreach ($po_requests as $req) {
        echo "- Request #{$req['access_request_number']} from {$req['requestor_name']} (Status: {$req['status']}) → Assigned to Admin ID {$req['process_owner_id']}<br>";
    }
}

// Test what the current user would see
if (isset($_SESSION['admin_id'])) {
    $current_admin_id = $_SESSION['admin_id'];
    echo "<h3>3. What Current User (Admin ID {$current_admin_id}) Would See:</h3>";
    
    // Check technical requests
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM access_requests ar 
        WHERE ar.status IN ('pending_technical', 'pending_testing_setup', 'pending_testing_review')
        AND ar.technical_id = :admin_id
    ");
    $stmt->execute(['admin_id' => $current_admin_id]);
    $tech_count = $stmt->fetchColumn();
    
    // Check process owner requests
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM access_requests ar 
        WHERE ar.status = 'pending_process_owner'
        AND ar.process_owner_id = :admin_id
    ");
    $stmt->execute(['admin_id' => $current_admin_id]);
    $po_count = $stmt->fetchColumn();
    
    echo "Technical requests visible: {$tech_count}<br>";
    echo "Process owner requests visible: {$po_count}<br>";
    
    if ($tech_count == 0 && $po_count == 0) {
        echo "<strong>Result: No requests visible (this explains the empty page)</strong><br>";
    }
}

echo "<h3>4. All Admin Users:</h3>";
$stmt = $pdo->prepare("SELECT id, username, role FROM admin_users ORDER BY id");
$stmt->execute();
$adminUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($adminUsers as $user) {
    echo "- ID: {$user['id']}, Username: {$user['username']}, Role: {$user['role']}<br>";
}
?>

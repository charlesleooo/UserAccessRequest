<?php
session_start();
require_once '../config.php';

// Debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Track if a transaction is active
$transaction_active = false;

// Debug POST data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("POST Data: " . print_r($_POST, true));
}

// Check if user is logged in
if (!isset($_SESSION['requestor_id'])) {
    header('Location: login.php');
    exit();
}

// Get the user's ID from session
$requestor_id = $_SESSION['requestor_id'];
$user_email = $_SESSION['employee_email'] ?? '';

// Add debug code to see session variables
// echo '<pre>'; print_r($_SESSION); echo '</pre>'; exit;

// Process form submission for updating testing status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id']) && isset($_POST['testing_status'])) {
    error_log("Processing form submission");
    error_log("Request ID: " . $_POST['request_id']);
    error_log("Testing Status: " . $_POST['testing_status']);
    
    $request_id = (int)$_POST['request_id'];
    $testing_status = $_POST['testing_status'];
    $testing_notes = $_POST['testing_notes'] ?? '';
    
    try {
        // Start a transaction
        $pdo->beginTransaction();
        $transaction_active = true;
        error_log("Started transaction");
        
        // Verify this request belongs to the current user and is in pending_testing status
        $sql = "SELECT * FROM access_requests 
                WHERE id = :request_id 
                AND employee_id = :employee_id 
                AND (status = 'pending_testing' OR status = 'pending_testing_setup')";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'request_id' => $request_id,
            'employee_id' => $requestor_id
        ]);
        
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        error_log("Request data: " . print_r($request, true));
        
        if (!$request) {
            throw new Exception('Invalid request or you do not have permission to update this request.');
        }
        
        if ($testing_status === 'success') {
            error_log("Processing successful test");
            // Update the request status and testing status
            $sql = "UPDATE access_requests SET 
                    status = 'approved',
                    testing_status = 'success',
                    testing_notes = :testing_notes,
                    review_notes = 'Automatically approved after successful testing'
                    WHERE id = :request_id";
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
                'testing_notes' => $testing_notes,
                'request_id' => $request_id
            ]);
            
            if (!$result) {
                error_log("Database error: " . print_r($stmt->errorInfo(), true));
                throw new Exception('Failed to update request status.');
            }
            
            // After successful update, move to approval history
            error_log("Moving request to approval history");
            
            // Get the full request details
            $stmt = $pdo->prepare("SELECT * FROM access_requests WHERE id = ?");
            $stmt->execute([$request_id]);
            $requestData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$requestData) {
                throw new Exception('Failed to retrieve request data for approval history.');
            }
            
            // Insert into approval history
            $sql = "INSERT INTO approval_history (
                    access_request_number, action, requestor_name, business_unit, department,
                    access_type, admin_id, comments, system_type, duration_type,
                    start_date, end_date, justification, email, contact_number,
                    testing_status, employee_id
                ) VALUES (
                    :access_request_number, 'approved', :requestor_name, :business_unit, :department,
                    :access_type, :admin_id, :comments, :system_type, :duration_type,
                    :start_date, :end_date, :justification, :email, 'Not provided',
                    'success', :employee_id
                )";
            
            // Get the admin_users id for the admin who approved this request
            $adminQuery = $pdo->prepare("SELECT id FROM admin_users WHERE id = :admin_id OR username = :admin_id");
            $adminQuery->execute(['admin_id' => $requestData['admin_id']]);
            $adminRecord = $adminQuery->fetch(PDO::FETCH_ASSOC);
            $admin_users_id = $adminRecord ? $adminRecord['id'] : null;
            
            // If no admin_users id found, try to get any admin user as fallback
            if (!$admin_users_id) {
                $adminQuery = $pdo->prepare("SELECT id FROM admin_users WHERE role = 'admin' LIMIT 1");
                $adminQuery->execute();
                $adminRecord = $adminQuery->fetch(PDO::FETCH_ASSOC);
                $admin_users_id = $adminRecord ? $adminRecord['id'] : null;
                
                if (!$admin_users_id) {
                    throw new Exception('Admin user record not found. Cannot complete approval process.');
                }
            }
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
                'access_request_number' => $requestData['access_request_number'],
                'requestor_name' => $requestData['requestor_name'],
                'business_unit' => $requestData['business_unit'],
                'department' => $requestData['department'],
                'access_type' => $requestData['access_type'],
                'admin_id' => $admin_users_id,
                'comments' => 'Automatically approved after successful testing',
                'system_type' => $requestData['system_type'],
                'duration_type' => $requestData['duration_type'],
                'start_date' => $requestData['start_date'],
                'end_date' => $requestData['end_date'],
                'justification' => $requestData['justification'],
                'email' => $requestData['email'],
                'employee_id' => $requestData['employee_id']
            ]);
            
            if (!$result) {
                error_log("Database error when inserting to history: " . print_r($stmt->errorInfo(), true));
                throw new Exception('Failed to insert into approval history.');
            }
            
            // Delete from access_requests after successful move to history
            $stmt = $pdo->prepare("DELETE FROM access_requests WHERE id = ?");
            $result = $stmt->execute([$request_id]);
            
            if (!$result) {
                error_log("Database error when deleting: " . print_r($stmt->errorInfo(), true));
                throw new Exception('Failed to remove request after approval.');
            }
            
            error_log("Successfully moved approved request to history");
            $_SESSION['success_message'] = "Testing successful! Your access request has been automatically approved.";
        } else {
            // If testing failed, update status for technical support review
            $sql = "UPDATE access_requests 
                    SET testing_status = :testing_status, 
                        testing_notes = :testing_notes,
                        status = 'pending_testing_review'
                    WHERE id = :request_id";
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
                'testing_status' => $testing_status,
                'testing_notes' => $testing_notes,
                'request_id' => $request_id
            ]);
            
            if (!$result) {
                throw new Exception('Failed to update testing status.');
            }
            
            $_SESSION['success_message'] = "Testing status has been updated. The technical support team will review your testing results.";
        }
        
        // Commit the transaction
        $pdo->commit();
        $transaction_active = false;
        error_log("Transaction committed");
        
        // Redirect back to dashboard
        header('Location: dashboard.php');
        exit();
        
    } catch (Exception $e) {
        // Rollback the transaction on error
        if ($transaction_active) {
            $pdo->rollBack();
            $transaction_active = false;
        }
        error_log("Error occurred: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
        header('Location: dashboard.php');
        exit();
    }
}

// Get the request_id from the URL
$request_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Check if testing has already been submitted
$stmt = $pdo->prepare("SELECT testing_status FROM access_requests WHERE id = ? AND employee_id = ?");
$stmt->execute([$request_id, $requestor_id]);
$current_status = $stmt->fetchColumn();

if ($current_status && $current_status !== 'pending') {
    $_SESSION['info_message'] = "Testing results have already been submitted for this request.";
    header('Location: dashboard.php');
    exit();
}

// Get the request details
try {
    $sql = "SELECT * FROM access_requests 
            WHERE id = :request_id 
            AND employee_id = :employee_id 
            AND (status = 'pending_testing' OR status = 'pending_testing_setup')
            AND testing_status = 'pending'";
    
    // Debug SQL
    // echo "SQL: $sql<br>";
    // echo "request_id: $request_id<br>";
    // echo "employee_id: $requestor_id<br>";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'request_id' => $request_id,
        'employee_id' => $requestor_id
    ]);
    
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        // Debug message for troubleshooting
        // echo "Request not found. Please check your query parameters.";
        // exit;
        
        $_SESSION['error_message'] = "Request not found or you do not have permission to update it.";
        header('Location: dashboard.php');
        exit();
    }
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    header('Location: dashboard.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Testing Status</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex flex-col">
        <!-- Header -->
        <header class="bg-white shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
                <div class="flex items-center">
                    <img src="../logo.png" alt="Logo" class="h-16">
                </div>
                <div>
                    <a href="dashboard.php" class="text-gray-600 hover:text-primary-600 flex items-center">
                        <i class='bx bx-arrow-back mr-1'></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <div class="flex-grow">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 bg-blue-50 border-b border-blue-100 flex items-center">
                        <div class="rounded-full bg-blue-100 p-2 mr-3">
                            <i class='bx bx-test-tube text-blue-600 text-xl'></i>
                        </div>
                        <h1 class="text-xl font-semibold text-gray-800">Update Testing Status</h1>
                    </div>
                    
                    <div class="p-6">
                        <div class="mb-6 bg-blue-50 rounded-lg p-4 border border-blue-100">
                            <div class="flex items-start">
                                <div class="flex-shrink-0 pt-0.5">
                                    <i class='bx bx-info-circle text-blue-600 text-xl'></i>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-blue-800">Testing Instructions</h3>
                                    <div class="mt-2 text-sm text-blue-700">
                                        <p>After testing your application access, please update the status:</p>
                                        <ul class="list-disc pl-5 mt-1 space-y-1">
                                            <li>Select "Testing Successful" if you were able to access the system properly</li>
                                            <li>Select "Testing Failed" if you encountered any issues with the access</li>
                                        </ul>
                                        <p class="mt-2">An administrator will review your testing status and finalize your access request.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Request Details -->
                        <div class="mb-6 border border-gray-200 rounded-lg overflow-hidden">
                            <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                                <h3 class="font-medium text-gray-700">Request Details</h3>
                            </div>
                            <div class="p-4">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <p class="text-sm text-gray-500">Request Number:</p>
                                        <p class="font-medium"><?php echo htmlspecialchars($request['access_request_number']); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">Status:</p>
                                        <p>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                Pending Testing
                                            </span>
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">Access Type:</p>
                                        <p class="font-medium"><?php echo htmlspecialchars($request['access_type']); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">System Type:</p>
                                        <p class="font-medium"><?php echo htmlspecialchars($request['system_type'] ?? 'N/A'); ?></p>
                                    </div>
                                </div>
                                
                                <?php if (!empty($request['review_notes'])): ?>
                                <div class="mt-4 border-t border-gray-100 pt-4">
                                    <p class="text-sm text-gray-500">Admin Notes / Credentials:</p>
                                    <div class="mt-1 bg-yellow-50 p-3 rounded-md border border-yellow-100 text-sm whitespace-pre-line">
                                        <?php echo nl2br(htmlspecialchars($request['review_notes'])); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Testing Status Form -->
                        <form id="testingForm" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) . '?id=' . $request_id; ?>">
                            <input type="hidden" name="request_id" value="<?php echo htmlspecialchars($request['id']); ?>">
                            
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Testing Status</label>
                                <div class="space-y-3">
                                    <label class="flex items-center border border-gray-200 rounded-lg p-4 cursor-pointer hover:bg-green-50 hover:border-green-200 transition-colors">
                                        <input type="radio" name="testing_status" value="success" class="h-4 w-4 text-green-600 focus:ring-green-500" required>
                                        <div class="ml-3">
                                            <span class="block font-medium text-gray-900">Testing Successful</span>
                                            <span class="block text-sm text-gray-500">I was able to access the system successfully.</span>
                                        </div>
                                    </label>
                                    
                                    <label class="flex items-center border border-gray-200 rounded-lg p-4 cursor-pointer hover:bg-red-50 hover:border-red-200 transition-colors">
                                        <input type="radio" name="testing_status" value="failed" class="h-4 w-4 text-red-600 focus:ring-red-500" required>
                                        <div class="ml-3">
                                            <span class="block font-medium text-gray-900">Testing Failed</span>
                                            <span class="block text-sm text-gray-500">I was unable to access the system or encountered issues.</span>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mb-6">
                                <label for="testing_notes" class="block text-sm font-medium text-gray-700 mb-2">Testing Notes</label>
                                <textarea id="testing_notes" name="testing_notes" rows="4" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Please provide details about your testing experience. If you encountered issues, describe them here."></textarea>
                            </div>
                            
                            <div class="flex justify-end">
                                <a href="dashboard.php" class="mr-3 px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</a>
                                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    Submit Testing Result
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <footer class="bg-white shadow-sm-up mt-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <p class="text-center text-gray-500 text-sm">
                    &copy; 2025 Alsons/AWS. All rights reserved.
                </p>
            </div>
        </footer>
    </div>
    
    <script>
        // Show any success or error messages from session
        <?php if (isset($_SESSION['success_message']) && $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        Swal.fire({
            title: 'Success!',
            text: '<?php echo addslashes($_SESSION['success_message']); ?>',
            icon: 'success',
            confirmButtonColor: '#3085d6'
        });
        <?php unset($_SESSION['success_message']); endif; ?>
        
        <?php if (isset($_SESSION['error_message']) && $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        Swal.fire({
            title: 'Error!',
            text: '<?php echo addslashes($_SESSION['error_message']); ?>',
            icon: 'error',
            confirmButtonColor: '#3085d6'
        });
        <?php unset($_SESSION['error_message']); endif; ?>

        <?php if (isset($_SESSION['info_message'])): ?>
        Swal.fire({
            title: 'Information',
            text: '<?php echo addslashes($_SESSION['info_message']); ?>',
            icon: 'info',
            confirmButtonColor: '#3085d6'
        });
        <?php unset($_SESSION['info_message']); endif; ?>
        
        // Form validation
        document.getElementById('testingForm').addEventListener('submit', function(e) {
            const testingStatus = document.querySelector('input[name="testing_status"]:checked');
            const testingNotes = document.getElementById('testing_notes').value.trim();
            
            if (!testingStatus) {
                e.preventDefault();
                Swal.fire({
                    title: 'Validation Error',
                    text: 'Please select a testing status.',
                    icon: 'error',
                    confirmButtonColor: '#3085d6'
                });
                return;
            }
            
            if (testingStatus.value === 'failed' && testingNotes === '') {
                e.preventDefault();
                Swal.fire({
                    title: 'Validation Error',
                    text: 'Please provide testing notes describing the issues you encountered.',
                    icon: 'error',
                    confirmButtonColor: '#3085d6'
                });
                return;
            }
        });
    </script>
</body>
</html> 
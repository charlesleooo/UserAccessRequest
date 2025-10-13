<?php
session_start();
require_once '../config.php';
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

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
    header('Location: ../login.php');
    exit();
}

$requestor_id = $_SESSION['requestor_id'];

// Get the user's ID from session
$user_email = $_SESSION['employee_email'] ?? '';

// Add debug code to see session variables
// echo '<pre>'; print_r($_SESSION); echo '</pre>'; exit;

// Process form submission
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

        error_log("Checking request: ID=$request_id, Employee=$requestor_id");

        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        error_log("Request data: " . print_r($request, true));

        if (!$request) {
            throw new Exception('Request not found or you do not have permission to update it. Please verify the request is still pending testing.');
        }

        // Additional check for testing status
        if (!in_array($request['testing_status'], ['pending', 'not_required'])) {
            throw new Exception('This request has already been tested. Testing status: ' . $request['testing_status']);
        }

        // Determine next status based on testing result
        if ($testing_status === 'success') {
            // If testing is successful, automatically approve the request
            $next_status = 'approved';
            $success_message = 'Testing successful! Your access request has been automatically approved.';
        } else {
            // If testing failed, send back to technical support for review
            $next_status = 'pending_testing_review';
            $success_message = 'Testing status updated. Technical support will review your results.';
        }

        // Update the request status and testing status
        $sql = "UPDATE access_requests SET 
                testing_status = :testing_status,
                testing_notes = :testing_notes,
                status = :next_status
                WHERE id = :request_id 
                AND employee_id = :employee_id";

        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            'testing_status' => $testing_status,
            'testing_notes' => $testing_notes,
            'next_status' => $next_status,
            'request_id' => $request_id,
            'employee_id' => $requestor_id
        ]);

        if (!$result) {
            throw new Exception('Failed to update request status.');
        }

        // If approved, move to approval history
        if ($next_status === 'approved') {
            // Get the current request data for history
            $requestDataQuery = $pdo->prepare("
                SELECT 
                    access_request_number,
                    requestor_name, 
                    employee_id, 
                    employee_email as email, 
                    department, 
                    business_unit, 
                    'System Application' as access_type, 
                    system_type,
                    '' as justification, 
                    'permanent' as duration_type, 
                    NULL as start_date, 
                    NULL as end_date,
                    superior_id, 
                    superior_notes,
                    help_desk_id,
                    help_desk_notes, 
                    process_owner_id,
                    process_owner_notes, 
                    technical_id,
                    technical_notes,
                    admin_id,
                    testing_status
                FROM 
                    access_requests 
                WHERE 
                    id = :request_id
            ");

            $requestDataQuery->execute(['request_id' => $request_id]);
            $requestData = $requestDataQuery->fetch(PDO::FETCH_ASSOC);

            if ($requestData) {
                // Insert into approval_history table
                $historyInsert = $pdo->prepare("
                    INSERT INTO approval_history (
                        access_request_number,
                        requestor_name, 
                        employee_id, 
                        email, 
                        department, 
                        business_unit, 
                        access_type, 
                        system_type,
                        justification, 
                        duration_type, 
                        start_date, 
                        end_date,
                        superior_id, 
                        superior_notes,
                        help_desk_id, 
                        help_desk_notes,
                        process_owner_id, 
                        process_owner_notes,
                        technical_id, 
                        technical_notes,
                        admin_id, 
                        action, 
                        comments,
                        testing_status,
                        created_at
                    ) VALUES (
                        :access_request_number,
                        :requestor_name, 
                        :employee_id, 
                        :email, 
                        :department, 
                        :business_unit, 
                        :access_type, 
                        :system_type,
                        :justification, 
                        :duration_type, 
                        :start_date, 
                        :end_date,
                        :superior_id, 
                        :superior_notes,
                        :help_desk_id, 
                        :help_desk_notes,
                        :process_owner_id, 
                        :process_owner_notes,
                        :technical_id, 
                        :technical_notes,
                        :admin_id, 
                        'approved', 
                        :comments,
                        :testing_status,
                        NOW()
                    )
                ");

                $historyParams = [
                    'access_request_number' => $requestData['access_request_number'],
                    'requestor_name' => $requestData['requestor_name'],
                    'employee_id' => $requestData['employee_id'],
                    'email' => $requestData['email'],
                    'department' => $requestData['department'],
                    'business_unit' => $requestData['business_unit'],
                    'access_type' => $requestData['access_type'],
                    'system_type' => $requestData['system_type'],
                    'justification' => $requestData['justification'],
                    'duration_type' => $requestData['duration_type'],
                    'start_date' => $requestData['start_date'],
                    'end_date' => $requestData['end_date'],
                    'superior_id' => $requestData['superior_id'],
                    'superior_notes' => $requestData['superior_notes'],
                    'help_desk_id' => $requestData['help_desk_id'],
                    'help_desk_notes' => $requestData['help_desk_notes'],
                    'process_owner_id' => $requestData['process_owner_id'],
                    'process_owner_notes' => $requestData['process_owner_notes'],
                    'technical_id' => $requestData['technical_id'],
                    'technical_notes' => $requestData['technical_notes'],
                    'admin_id' => $requestData['admin_id'],
                    'comments' => 'Automatically approved after successful testing. Testing notes: ' . $testing_notes,
                    'testing_status' => $requestData['testing_status']
                ];

                $historyInsert->execute($historyParams);

                // Delete from access_requests table since it's now in history
                $deleteStmt = $pdo->prepare("DELETE FROM access_requests WHERE id = ?");
                $deleteStmt->execute([$request_id]);
            }
        }

        // Send email notification only for failed tests
        if ($testing_status === 'failed') {
            try {
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = SMTP_HOST;
                $mail->SMTPAuth = true;
                $mail->Username = SMTP_USERNAME;
                $mail->Password = SMTP_PASSWORD;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = SMTP_PORT;

                $mail->setFrom(SMTP_USERNAME, 'Access Request System');

                // Get technical support email
                $techSql = "SELECT email FROM admin_users WHERE id = :tech_id";
                $techStmt = $pdo->prepare($techSql);
                $techStmt->execute(['tech_id' => $request['technical_id']]);
                $techEmail = $techStmt->fetchColumn();

                if ($techEmail) {
                    $mail->addAddress($techEmail);
                    $mail->isHTML(true);
                    $mail->Subject = "Testing Failed - Action Required - Access Request {$request['access_request_number']}";
                    $mail->Body = "
                        <div style='font-family: Arial, sans-serif; padding: 20px;'>
                            <h2>Testing Failed - Action Required</h2>
                            <p>The user has reported that testing failed for access request {$request['access_request_number']}.</p>
                            <p><strong>Testing Status:</strong> Failed</p>
                            <p><strong>Testing Notes:</strong> {$testing_notes}</p>
                            <p>Please review the testing results and take appropriate action.</p>
                            <p><a href='" . BASE_URL . "technical_support/requests.php'>Click here to review</a></p>
                        </div>
                    ";
                    $mail->send();
                }
            } catch (Exception $e) {
                error_log("Failed to send email notification: " . $e->getMessage());
                // Continue processing even if email fails
            }
        }

        $pdo->commit();
        $transaction_active = false;
        error_log("Transaction committed");

        // Return JSON response for AJAX calls
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => $success_message
        ]);
        exit();
    } catch (Exception $e) {
        // Rollback the transaction on error
        if ($transaction_active) {
            $pdo->rollBack();
            $transaction_active = false;
        }
        error_log("Error occurred: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());

        // Return JSON error response for AJAX calls
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
        exit();
    }
}

// Get the request_id from the URL
$request_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Check if testing has already been submitted
$stmt = $pdo->prepare("SELECT testing_status FROM access_requests WHERE id = ?");
$stmt->execute([$request_id]);
$current_status = $stmt->fetchColumn();

error_log("Current testing status for request $request_id: $current_status");

if ($current_status && $current_status !== 'pending') {
    $_SESSION['info_message'] = "Testing results have already been submitted for this request.";
    header('Location: dashboard.php');
    exit();
}

// Get the request details
try {
    $sql = "SELECT ar.*, 
            (SELECT COALESCE(
                (SELECT access_type FROM individual_requests WHERE access_request_number = ar.access_request_number LIMIT 1),
                (SELECT access_type FROM group_requests WHERE access_request_number = ar.access_request_number LIMIT 1)
            )) as access_type
            FROM access_requests ar
            WHERE ar.id = :request_id 
            AND ar.employee_id = :employee_id
            AND (ar.status = 'pending_testing' OR ar.status = 'pending_testing_setup')";

    // Debug SQL
    error_log("SQL: $sql");
    error_log("request_id: $request_id");
    error_log("employee_id: $requestor_id");

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
    echo '<script>
        Swal.fire({
            title: "Error!",
            text: "Database error occurred. Please try again.",
            icon: "error",
            confirmButtonColor: "#3085d6"
        }).then(() => {
            window.location.href = "dashboard.php";
        });
    </script>';
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
                        <!-- Technical Support Instructions -->
                        <?php if (!empty($request['testing_instructions'])): ?>
                            <div class="mb-6 bg-green-50 rounded-lg p-4 border border-green-100">
                                <div class="flex items-start">
                                    <div class="flex-shrink-0 pt-0.5">
                                        <i class='bx bx-test-tube text-green-600 text-xl'></i>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-green-800">Testing Instructions from Technical Support</h3>
                                        <div class="mt-2 text-sm text-green-700 bg-white p-3 rounded border">
                                            <?php echo nl2br(htmlspecialchars($request['testing_instructions'])); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="mb-6 bg-blue-50 rounded-lg p-4 border border-blue-100">
                            <div class="flex items-start">
                                <div class="flex-shrink-0 pt-0.5">
                                    <i class='bx bx-info-circle text-blue-600 text-xl'></i>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-blue-800">Testing Instructions</h3>
                                    <div class="mt-2 text-sm text-blue-700">
                                        <p>Please test your system access and update the status below:</p>
                                        <ul class="list-disc pl-5 mt-1 space-y-1">
                                            <li>Select "Testing Successful" if you were able to access the system properly</li>
                                            <li>Select "Testing Failed" if you encountered any issues with the access</li>
                                        </ul>
                                        <p class="mt-2">An administrator will review your testing status and finalize your access request.</p>
                                    </div>
                                </div>
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
                &copy; <?= date('Y'); ?> Alsons Agribusiness Unit. All rights reserved.
            </p>
        </div>
    </footer>
    </div>

    <script>
        // Show any success or error messages from session
        <?php if (isset($_SESSION['success_message'])): ?>
            Swal.fire({
                title: 'Success!',
                text: '<?php echo addslashes($_SESSION['success_message']); ?>',
                icon: 'success',
                confirmButtonColor: '#3085d6'
            });
        <?php unset($_SESSION['success_message']);
        endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            Swal.fire({
                title: 'Error!',
                text: '<?php echo addslashes($_SESSION['error_message']); ?>',
                icon: 'error',
                confirmButtonColor: '#3085d6'
            });
        <?php unset($_SESSION['error_message']);
        endif; ?>

        <?php if (isset($_SESSION['info_message'])): ?>
            Swal.fire({
                title: 'Information',
                text: '<?php echo addslashes($_SESSION['info_message']); ?>',
                icon: 'info',
                confirmButtonColor: '#3085d6'
            });
        <?php unset($_SESSION['info_message']);
        endif; ?>

        // Form validation and submission
        document.getElementById('testingForm').addEventListener('submit', function(e) {
            e.preventDefault(); // Prevent default form submission

            const testingStatus = document.querySelector('input[name="testing_status"]:checked');
            const testingNotes = document.getElementById('testing_notes').value.trim();

            if (!testingStatus) {
                Swal.fire({
                    title: 'Validation Error',
                    text: 'Please select a testing status.',
                    icon: 'error',
                    confirmButtonColor: '#3085d6'
                });
                return;
            }

            if (testingStatus.value === 'failed' && testingNotes === '') {
                Swal.fire({
                    title: 'Validation Error',
                    text: 'Please provide testing notes describing the issues you encountered.',
                    icon: 'error',
                    confirmButtonColor: '#3085d6'
                });
                return;
            }

            // Show loading state
            Swal.fire({
                title: 'Processing...',
                text: 'Submitting your testing results...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Prepare form data
            const formData = new FormData(this);

            // Submit via AJAX
            fetch(this.action, {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    // Check if the response is ok
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }

                    // Check content type
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        // If it's not JSON, get the text and show it
                        return response.text().then(text => {
                            throw new Error('Server returned non-JSON response: ' + text.substring(0, 200));
                        });
                    }

                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Determine the icon and message based on testing status
                        const testingStatus = document.querySelector('input[name="testing_status"]:checked').value;
                        const icon = testingStatus === 'success' ? 'success' : 'info';
                        const title = testingStatus === 'success' ? 'Testing Successful!' : 'Testing Status Updated';

                        Swal.fire({
                            title: title,
                            text: data.message,
                            icon: icon,
                            confirmButtonColor: testingStatus === 'success' ? '#10B981' : '#3B82F6',
                            confirmButtonText: 'Continue to Dashboard'
                        }).then(() => {
                            // Redirect to dashboard
                            window.location.href = 'dashboard.php';
                        });
                    } else {
                        Swal.fire({
                            title: 'Unable to Process Request',
                            text: data.message || 'An error occurred while processing your request.',
                            icon: 'error',
                            confirmButtonColor: '#EF4444',
                            confirmButtonText: 'OK',
                            footer: 'Please contact support if this issue persists.'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error details:', error);

                    let errorTitle = 'Request Failed';
                    let errorMessage = 'An unexpected error occurred.';

                    if (error.message.includes('HTTP 404')) {
                        errorTitle = 'Page Not Found';
                        errorMessage = 'The testing status page could not be found. Please refresh and try again.';
                    } else if (error.message.includes('HTTP 500')) {
                        errorTitle = 'Server Error';
                        errorMessage = 'There was a server error processing your request. Please try again later.';
                    } else if (error.message.includes('Failed to fetch')) {
                        errorTitle = 'Connection Error';
                        errorMessage = 'Unable to connect to the server. Please check your internet connection and try again.';
                    } else if (error.message.includes('non-JSON response')) {
                        errorTitle = 'Invalid Response';
                        errorMessage = 'The server returned an unexpected response. Please contact support.';
                    } else {
                        errorMessage = error.message || 'Please try again or contact support if the problem persists.';
                    }

                    Swal.fire({
                        title: errorTitle,
                        text: errorMessage,
                        icon: 'error',
                        confirmButtonColor: '#EF4444',
                        confirmButtonText: 'OK',
                        footer: 'Error details have been logged for troubleshooting.'
                    });
                });
        });
    </script>
</body>

</html>
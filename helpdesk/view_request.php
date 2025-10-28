<?php
session_start();
require_once '../config.php';

// Check if help desk is logged in
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'help_desk') {
    header('Location: ../admin/login.php');
    exit();
}

// Check if the user needs to enter the encryption code
if (
    !isset($_SESSION['requests_verified']) || !$_SESSION['requests_verified'] ||
    (time() - $_SESSION['requests_verified_time'] > 1800)
) { // Expire after 30 minutes
    header('Location: requests_auth.php');
    exit();
}

// Check if request ID or access_request_number is provided
if (!isset($_GET['id']) && !isset($_GET['access_request_number'])) {
    header("Location: requests.php");
    exit();
}

$requestId = null;
$accessRequestNumber = null;
$fromHistory = isset($_GET['from_history']) && $_GET['from_history'] === 'true';

if (isset($_GET['id'])) {
    $requestId = intval($_GET['id']);
} elseif (isset($_GET['access_request_number'])) {
    $accessRequestNumber = $_GET['access_request_number'];
}

// Fetch the request details
try {
    if ($fromHistory && $accessRequestNumber) {
        // For requests from history, try to get data from approval_history first
        $mainQuery = "SELECT TOP 1 ah.*, 
                            ISNULL(e.employee_name, ah.requestor_name) as requestor_name,
                            ISNULL(e.employee_email, ah.email) as employee_email
                     FROM uar.approval_history ah
                     LEFT JOIN uar.employees e ON ah.employee_id = e.employee_id
                     WHERE ah.access_request_number = :access_request_number
                     ORDER BY ah.created_at DESC";

        $stmt = $pdo->prepare($mainQuery);
        $stmt->execute([':access_request_number' => $accessRequestNumber]);
        $historyRequest = $stmt->fetch(PDO::FETCH_ASSOC);

        // If not found in approval_history, try access_requests table
        if (!$historyRequest) {
            $fallbackQuery = "SELECT TOP 1 ar.*, e.employee_name as requestor_name
                             FROM uar.access_requests ar
                             LEFT JOIN uar.employees e ON ar.employee_id = e.employee_id
                             WHERE ar.access_request_number = :access_request_number
                             AND ar.help_desk_id IS NOT NULL";
            
            $stmt = $pdo->prepare($fallbackQuery);
            $stmt->execute([':access_request_number' => $accessRequestNumber]);
            $historyRequest = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$historyRequest) {
            header("Location: review_history.php");
            exit();
        }

        // Get all usernames, date_needed, and justification from individual_requests or group_requests
        // Note: SQLSRV PDO does not support reusing the same named parameter twice in a single statement
        $detailsQuery = "SELECT username, date_needed, justification, application_system, access_type, access_duration, start_date, end_date FROM uar.individual_requests WHERE access_request_number = :arn1 
                         UNION ALL
                         SELECT username, date_needed, justification, application_system, access_type, access_duration, start_date, end_date FROM uar.group_requests WHERE access_request_number = :arn2 
                         ORDER BY username";
        $stmt = $pdo->prepare($detailsQuery);
        $stmt->execute([':arn1' => $accessRequestNumber, ':arn2' => $accessRequestNumber]);
        $allDetailsResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Map fields to match access_requests structure
        $request = array(
            'id' => $historyRequest['request_id'] ?? $historyRequest['history_id'] ?? null,
            'access_request_number' => $historyRequest['access_request_number'],
            'requestor_name' => $historyRequest['requestor_name'],
            'employee_id' => $historyRequest['employee_id'],
            'employee_email' => $historyRequest['employee_email'] ?? $historyRequest['email'],
            'business_unit' => $historyRequest['business_unit'],
            'department' => $historyRequest['department'],
            'request_date' => $historyRequest['created_at'] ?? $historyRequest['submission_date'],
            'submission_date' => $historyRequest['created_at'] ?? $historyRequest['submission_date'],
            'system_type' => $historyRequest['system_type'],
            'access_level' => $historyRequest['access_type'] ?? $historyRequest['access_level'],
            'status' => 'completed', // Mark as completed for history requests
            'superior_notes' => $historyRequest['superior_notes'],
            'action' => $historyRequest['action'] ?? ($historyRequest['status'] === 'rejected' ? 'rejected' : 'approved'),
            'help_desk_notes' => $historyRequest['help_desk_notes'],
            'created_at' => $historyRequest['created_at'] ?? $historyRequest['help_desk_review_date']
        );

        // For history requests, we'll create details array for all users
        $requestDetails = [];
        if (!empty($allDetailsResults)) {
            foreach ($allDetailsResults as $detail) {
                $requestDetails[] = array(
                    'username' => $detail['username'] ?? 'N/A',
                    'application_system' => $detail['application_system'] ?? $historyRequest['system_type'] ?? 'N/A',
                    'role_access_type' => $detail['access_type'] ?? $historyRequest['access_type'] ?? $historyRequest['access_level'] ?? 'N/A',
                    'duration_type' => $detail['access_duration'] ?? $historyRequest['duration_type'] ?? 'N/A',
                    'start_date' => $detail['start_date'] ?? $historyRequest['start_date'] ?? null,
                    'end_date' => $detail['end_date'] ?? $historyRequest['end_date'] ?? null,
                    'date_needed' => $detail['date_needed'] ?? null,
                    'justification' => $detail['justification'] ?? $historyRequest['justification'] ?? 'N/A'
                );
            }
        } else {
            // Fallback if no details found
            $requestDetails = [array(
                'username' => 'N/A',
                'application_system' => $historyRequest['system_type'] ?? 'N/A',
                'role_access_type' => $historyRequest['access_type'] ?? $historyRequest['access_level'] ?? 'N/A',
                'duration_type' => $historyRequest['duration_type'] ?? 'N/A',
                'start_date' => $historyRequest['start_date'] ?? null,
                'end_date' => $historyRequest['end_date'] ?? null,
                'date_needed' => null,
                'justification' => $historyRequest['justification'] ?? 'N/A'
            )];
        }
    } else {
        // Original logic for pending requests
        // First check if it's an individual or group request
        $checkQuery = "SELECT COUNT(*) as count FROM uar.individual_requests WHERE access_request_number = (
                        SELECT access_request_number FROM uar.access_requests WHERE id = :request_id
                      )";
        $stmt = $pdo->prepare($checkQuery);
        $stmt->execute([':request_id' => $requestId]);
        $isIndividual = $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;

        $requestTable = $isIndividual ? 'uar.individual_requests' : 'uar.group_requests';

        // First get the main request details
        $mainQuery = "SELECT ar.*, e.employee_name as requestor_name
                     FROM uar.access_requests ar
                     LEFT JOIN uar.employees e ON ar.employee_id = e.employee_id
                     WHERE ar.id = :request_id";

        $stmt = $pdo->prepare($mainQuery);
        $stmt->execute([
            ':request_id' => $requestId
        ]);

        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$request) {
            header("Location: requests.php");
            exit();
        }

        // Then get all the individual entries for this request
        $detailsQuery = "SELECT r.username, r.application_system, r.access_type as role_access_type,
                                r.access_duration as duration_type, r.start_date, r.end_date,
                                r.date_needed, r.justification
                         FROM {$requestTable} r
                         WHERE r.access_request_number = :access_request_number
                         ORDER BY r.username";

        $stmt = $pdo->prepare($detailsQuery);
        $stmt->execute([
            ':access_request_number' => $request['access_request_number']
        ]);

        $requestDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get all employees with their roles for role-based forwarding and their corresponding admin_users id
    $usersQuery = "
        SELECT a.id,
               e.role,
               e.employee_name
        FROM uar.employees e
        LEFT JOIN uar.admin_users a ON a.username = e.employee_id
        WHERE e.role IN ('technical_support', 'process_owner') AND a.id IS NOT NULL
        ORDER BY e.role, e.employee_name
    ";
    $usersStmt = $pdo->prepare($usersQuery);
    $usersStmt->execute();
    $allUsers = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

    // Group users by role
    $usersByRole = [];
    foreach ($allUsers as $user) {
        $usersByRole[$user['role']][] = $user;
    }
} catch (PDOException $e) {
    error_log("Error fetching request details: " . $e->getMessage());
    header("Location: requests.php?error=db");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Request Details</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Boxicons -->
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <!-- Alpine.js for interactions -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e',
                            950: '#082f49',
                        }
                    },
                    fontFamily: {
                        'sans': ['Inter', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    <style>
        body {
            background-image: url('../requestor/bg2.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            font-family: 'Inter', sans-serif;
        }

        /* Status Badge Styles */
        .status-badge {
            @apply px-3 py-1.5 text-xs font-bold rounded-full shadow-md inline-block;
        }

        .status-pending {
            @apply bg-yellow-500 text-white border-2 border-yellow-600;
        }

        .status-approved {
            @apply bg-green-600 text-white border-2 border-green-700;
        }

        .status-rejected {
            @apply bg-red-600 text-white border-2 border-red-700;
        }
    </style>
</head>

<body class="bg-gray-50 font-sans">

    <!-- Sidebar -->
    <?php 
    $current_page = basename($_SERVER['PHP_SELF']);
    include 'sidebar.php'; 
    ?>

    <!-- Main Content -->
    <div class="flex-1 lg:ml-72 transition-all duration-300">
        <!-- Header -->
        <div class="bg-blue-900 border-b border-gray-200 sticky top-0 z-10 shadow-sm">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center px-4 md:px-6 py-4 gap-3">
                <div>
                    <h2 class="text-2xl md:text-4xl font-bold text-white"><?php echo $fromHistory ? 'Review History Details' : 'View Request Details'; ?></h2>
                    <p class="text-white text-sm md:text-lg mt-1">Request #<?php echo htmlspecialchars($request['access_request_number'] ?? 'N/A'); ?></p>
                </div>
                <div class="flex space-x-2">
                    <?php if ($fromHistory): ?>
                        <a href="review_history.php" class="inline-flex items-center px-4 py-2 bg-white text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                            <i class='bx bx-arrow-back mr-2'></i> Back to Review History
                        </a>
                    <?php else: ?>
                        <a href="requests.php" class="inline-flex items-center px-4 py-2 bg-white text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                            <i class='bx bx-arrow-back mr-2'></i> Back to Requests
                        </a>
                        <?php if ($request['status'] === 'pending_help_desk'): ?>
                            <button onclick="showApprovalModal()" class="px-4 py-2 bg-green-50 text-green-600 rounded-lg hover:bg-green-100 transition-colors">
                                <i class='bx bx-check-circle mr-2'></i> Review & Forward
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Review Information (for history requests) -->
        <?php if ($fromHistory): ?>
            <div class="p-6">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                        <i class='bx bx-check-shield text-primary-500 text-xl mr-2'></i>
                        Your Review
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <div class="space-y-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">Status:</span>
                                    <span class="font-medium <?php echo ($request['action'] === 'rejected') ? 'text-red-600' : 'text-green-600'; ?>">
                                        <?php echo htmlspecialchars($request['action'] ?? 'N/A'); ?>
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Review Date:</span>
                                    <span class="font-medium text-gray-900">
                                        <?php echo $request['created_at'] ? date('M d, Y H:i', strtotime($request['created_at'])) : 'N/A'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <div>
                                <span class="text-gray-600 block mb-2">Review Notes:</span>
                                <div class="text-gray-700">
                                    <?php echo nl2br(htmlspecialchars($request['help_desk_notes'] ?? 'No notes provided.')); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Requestor Information -->
        <div class="p-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-1 border-b border-gray-100 flex items-center">
                    <i class='bx bx-user text-primary-500 text-xl mr-2'></i>
                    Requestor Information
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Name:</span>
                                <span class="font-medium text-gray-900"><?php echo htmlspecialchars($request['requestor_name'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Employee ID:</span>
                                <span class="font-medium text-gray-900"><?php echo htmlspecialchars($request['employee_id'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Email:</span>
                                <span class="font-medium text-gray-900"><?php echo htmlspecialchars($request['employee_email'] ?? 'N/A'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Business Unit:</span>
                                <span class="font-medium text-gray-900"><?php echo htmlspecialchars($request['business_unit'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Department:</span>
                                <span class="font-medium text-gray-900"><?php echo htmlspecialchars($request['department'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Request Date:</span>
                                <span class="font-medium text-gray-900">
                                    <?php
                                    if (!empty($request['request_date'])) {
                                        $requestDate = new DateTime($request['request_date']);
                                        echo $requestDate->format('M d, Y');
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Access Details -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mt-6" >
                <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                    <i class='bx bx-lock-open text-primary-500 text-xl mr-2'></i>
                    Access Details
                </h3>
                <div class="space-y-4">
                    <?php foreach ($requestDetails as $index => $detail): ?>
                        <div class="bg-white p-4 rounded-lg mb-4 shadow-sm">
                            <h4 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100">
                                User Details #<?php echo $index + 1; ?>
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h4 class="text-sm font-medium text-gray-600 mb-2">Basic Information</h4>
                                    <div class="space-y-3">
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">User Name:</span>
                                            <span class="font-medium text-gray-900">
                                                <?php echo htmlspecialchars($detail['username'] ?? 'N/A'); ?>
                                            </span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Access Type:</span>
                                            <span class="font-medium text-gray-900"><?php echo htmlspecialchars($detail['role_access_type'] ?? 'N/A'); ?></span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">System Type:</span>
                                            <span class="font-medium text-gray-900"><?php echo htmlspecialchars($request['system_type'] ?? 'N/A'); ?></span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Application System:</span>
                                            <span class="font-medium text-gray-900"><?php echo htmlspecialchars($detail['application_system'] ?? 'N/A'); ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h4 class="text-sm font-medium text-gray-600 mb-2">Access Duration</h4>
                                    <div class="space-y-3">
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Duration Type:</span>
                                            <span class="font-medium text-gray-900">
                                                <?php
                                                if (isset($detail['duration_type'])) {
                                                    echo $detail['duration_type'] === 'permanent' ? 'Permanent' : 'Temporary';
                                                } else {
                                                    echo 'N/A';
                                                }
                                                ?>
                                            </span>
                                        </div>
                                        <?php if (isset($detail['duration_type']) && $detail['duration_type'] !== 'permanent'): ?>
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">Start Date:</span>
                                                <span class="font-medium text-gray-900">
                                                    <?php
                                                    if (!empty($detail['start_date'])) {
                                                        $startDate = new DateTime($detail['start_date']);
                                                        echo $startDate->format('M d, Y');
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                    ?>
                                                </span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">End Date:</span>
                                                <span class="font-medium text-gray-900">
                                                    <?php
                                                    if (!empty($detail['end_date'])) {
                                                        $endDate = new DateTime($detail['end_date']);
                                                        echo $endDate->format('M d, Y');
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                    ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Date Needed:</span>
                                            <span class="font-medium text-gray-900">
                                                <?php
                                                if (!empty($detail['date_needed'])) {
                                                    $dateNeeded = new DateTime($detail['date_needed']);
                                                    echo $dateNeeded->format('M d, Y');
                                                } else {
                                                    echo 'N/A';
                                                }
                                                ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg mt-4">
                                <h4 class="text-sm font-medium text-gray-600 mb-2">Justification</h4>
                                <div class="text-gray-700">
                                    <?php echo nl2br(htmlspecialchars($detail['justification'] ?? 'No justification provided.')); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Superior's Comments -->
            <?php if (!empty($request['superior_notes'])): ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mt-6"  data-aos-delay="350">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                        <i class='bx bx-message-square-detail text-primary-500 text-xl mr-2'></i>
                        Superior's Comments
                    </h3>
                    <div class="bg-gray-50 p-4 rounded-lg text-gray-700">
                        <?php echo nl2br(htmlspecialchars($request['superior_notes'])); ?>
                    </div>
                </div>
            <?php endif; ?>


            <!-- Actions removed in favor of modal -->
        </div>
    </div>


    <!-- sweetalert2 cdn -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Approval Modal (always rendered) -->
    <div id="approvalModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-xl w-full max-w-lg mx-auto shadow-xl">
                <div class="flex items-center px-6 py-4 border-b border-gray-200">
                    <div class="flex-1 text-center">
                        <h3 class="text-xl font-semibold text-gray-800 flex items-center justify-center">
                            <i class='bx bx-share text-green-600 text-2xl mr-2'></i>
                        Help Desk Review
                    </h3>
                    </div>
                    <button onclick="hideApprovalModal()" class="text-gray-500 hover:text-gray-700">
                        <i class='bx bx-x text-2xl'></i>
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <!-- Review History -->
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 mb-2">Review history</h4>
                        <div id="modalReviewHistory" class="space-y-3"></div>
                    </div>

                    <div>
                        <label for="modal_forward_to" class="block text-sm font-medium text-gray-700 mb-1">Forward To</label>
                        <select id="modal_forward_to" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500">
                                    <option value="technical_support">Technical Support</option>
                                    <option value="process_owner">Process Owner</option>
                                </select>
                            </div>

                    <div>
                        <label for="modal_user_id" class="block text-sm font-medium text-gray-700 mb-1">Select User</label>
                        <select id="modal_user_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500"></select>
                            </div>

                    <div>
                        <label for="modal_review_notes" class="block text-sm font-medium text-gray-700 mb-1">Review Notes</label>
                        <textarea id="modal_review_notes" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500" placeholder="Enter your review notes..."></textarea>
                            </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" onclick="hideApprovalModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">Cancel</button>
                        <button type="button" onclick="handleApproval('decline')" class="px-4 py-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition-colors">
                                    <i class='bx bx-x-circle mr-2'></i> Decline
                                </button>
                        <button type="button" onclick="handleApproval('approve')" class="px-4 py-2 bg-green-50 text-green-600 rounded-lg hover:bg-green-100 transition-colors">
                                    <i class='bx bx-check-circle mr-2'></i> Forward
                                </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // All users grouped by role
        const usersByRole = <?php echo json_encode($usersByRole); ?>;
        const currentRequestId = <?php echo $requestId ? intval($requestId) : 'null'; ?>;
        const reviewers = [];
        <?php
            // Normalize possible column names coming from different views
            $superiorNotes = $request['superior_notes']
                ?? $request['superior_review_notes']
                ?? null;
            $technicalNotes = $request['technical_notes'] ?? null;
            $processOwnerNotes = $request['process_owner_notes'] ?? null;
            $helpDeskNotes = $request['help_desk_notes'] ?? null;
            $adminNotes = $request['admin_notes'] ?? null;

            $roleBadges = [];
            $history = [];
            if (!empty($superiorNotes)) { $roleBadges[] = 'Superior'; $history[] = ['role' => 'Superior', 'notes' => $superiorNotes]; }
            if (!empty($helpDeskNotes)) { $roleBadges[] = 'Help Desk'; $history[] = ['role' => 'Help Desk', 'notes' => $helpDeskNotes]; }
            if (!empty($technicalNotes)) { $roleBadges[] = 'Technical Support'; $history[] = ['role' => 'Technical Support', 'notes' => $technicalNotes]; }
            if (!empty($processOwnerNotes)) { $roleBadges[] = 'Process Owner'; $history[] = ['role' => 'Process Owner', 'notes' => $processOwnerNotes]; }
            if (!empty($adminNotes)) { $roleBadges[] = 'Admin'; $history[] = ['role' => 'Admin', 'notes' => $adminNotes]; }
        ?>
        const reviewedBy = <?php echo json_encode($roleBadges); ?>;
        const reviewHistory = <?php echo json_encode($history); ?>;

        document.addEventListener('DOMContentLoaded', function() {
            // Initialize dropdowns in-page (legacy) and modal
            updateUserOptions();
            if (document.getElementById('approvalModal')) {
                renderReviewHistory();
                updateModalUserOptions();
            }
        });

        function scrollToReviewSection() {
            // Find the review section and scroll to it
            const reviewSection = document.getElementById('reviewForm');
            if (reviewSection) {
                reviewSection.scrollIntoView({
                    behavior: 'smooth'
                });
                // Focus on the textarea
                document.getElementById('review_notes').focus();
            }
        }

        function updateUserOptions() {
            const forwardTo = document.getElementById('forward_to').value;
            const userSelect = document.getElementById('user_id');

            // Clear existing options
            userSelect.innerHTML = '';

            // Get the appropriate user list based on selection
            const users = usersByRole[forwardTo] || [];

            // Add options to the select
            users.forEach(user => {
                const option = document.createElement('option');
                option.value = user.id; // This is admin_users.id needed for database
                option.textContent = user.employee_name;
                userSelect.appendChild(option);
            });

            // If no users found, add a placeholder
            if (users.length === 0) {
                const option = document.createElement('option');
                option.value = '';
                option.textContent = `No ${forwardTo} users available`;
                userSelect.appendChild(option);
            }
        }

        window.showApprovalModal = function showApprovalModal() {
            const modal = document.getElementById('approvalModal');
            if (!modal) return;
            modal.classList.remove('hidden');
            modal.style.display = 'block';
            // ensure modal dropdown is populated
            if (document.getElementById('modal_forward_to')) {
                updateModalUserOptions();
            }
            setTimeout(() => { const ta = document.getElementById('modal_review_notes'); if (ta) ta.focus(); }, 100);
            // Load latest review history and notes from API to ensure accuracy
            if (currentRequestId) {
                fetch(`../admin/get_request_details.php?id=${currentRequestId}`)
                    .then(r => r.json())
                    .then(resp => {
                        if (!resp || !resp.success || !resp.data) return;
                        populateFromApi(resp.data);
                    })
                    .catch(() => {});
            }
        }

        window.hideApprovalModal = function hideApprovalModal() {
            const modal = document.getElementById('approvalModal');
            if (!modal) return;
            modal.classList.add('hidden');
            modal.style.display = 'none';
            const notes = document.getElementById('modal_review_notes');
            if (notes) notes.value = '';
        }

        function populateReviewedBy() {
            const container = document.getElementById('reviewedByList');
            if (!container) return;
            container.innerHTML = '';
            (reviewedBy || []).forEach(role => {
                const span = document.createElement('span');
                span.className = 'px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-700';
                span.textContent = role;
                container.appendChild(span);
            });
            if ((reviewedBy || []).length === 0) {
                const span = document.createElement('span');
                span.className = 'text-sm text-gray-500';
                span.textContent = 'No reviews yet';
                container.appendChild(span);
            }
        }

        function updateModalUserOptions() {
            const forwardTo = document.getElementById('modal_forward_to').value;
            const userSelect = document.getElementById('modal_user_id');
            userSelect.innerHTML = '';
            const users = usersByRole[forwardTo] || [];
            users.forEach(u => {
                const opt = document.createElement('option');
                opt.value = u.id;
                opt.textContent = u.employee_name;
                userSelect.appendChild(opt);
            });
            if (users.length === 0) {
                const opt = document.createElement('option');
                opt.value = '';
                opt.textContent = `No ${forwardTo} users available`;
                userSelect.appendChild(opt);
            }
        }
        // keep modal user list synced on change
        document.addEventListener('change', (e) => {
            const target = e.target || e.srcElement;
            if (target && target.id === 'modal_forward_to') {
                updateModalUserOptions();
            }
        });

        window.handleApproval = function handleApproval(action) {
            const notesEl = document.getElementById('modal_review_notes');
            const forwardToEl = document.getElementById('modal_forward_to');
            const userIdEl = document.getElementById('modal_user_id');
            const notes = notesEl ? notesEl.value : '';
            const forwardTo = forwardToEl ? forwardToEl.value : '';
            const userId = userIdEl ? userIdEl.value : '';
            const requestId = <?php echo $requestId ? $requestId : 'null'; ?>;

            if (!requestId) {
                Swal.fire({ title: 'Error', text: 'Cannot process request from history view.', icon: 'error', confirmButtonColor: '#0ea5e9' });
                return;
            }

            if (!notes || !notes.trim()) {
                Swal.fire({ title: 'Review Notes Required', text: 'Please provide review notes before submitting your decision.', icon: 'warning', confirmButtonColor: '#0ea5e9' });
                return;
            }

            if (action === 'approve') {
                if (!userId) {
                    Swal.fire({ title: 'User Selection Required', text: 'Please select a user to forward the request to.', icon: 'warning', confirmButtonColor: '#0ea5e9' });
                    return;
                }
            }

            Swal.fire({
                title: action === 'approve' ? 'Forward Request?' : 'Decline Request?',
                text: action === 'approve' ? 'This will forward the request to the selected user.' : 'This will decline the request and notify the requestor.',
                icon: action === 'approve' ? 'question' : 'warning',
                showCancelButton: true,
                confirmButtonColor: action === 'approve' ? '#10B981' : '#EF4444',
                cancelButtonColor: '#6B7280',
                confirmButtonText: action === 'approve' ? 'Yes, Forward' : 'Yes, Decline',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    hideApprovalModal();
                    Swal.fire({ title: 'Processing...', html: 'Please wait while we process your request.', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

                    const params = new URLSearchParams();
                    params.append('request_id', requestId);
                    params.append('action', action);
                    params.append('review_notes', notes);
                    if (action === 'approve') {
                        params.append('forward_to', forwardTo);
                        params.append('user_id', userId);
                    }

                    fetch('../admin/process_request.php', { method: 'POST', body: params })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({ title: 'Success!', text: data.message, icon: 'success', confirmButtonColor: '#0ea5e9' }).then(() => { window.location.href = 'requests.php'; });
                            } else {
                                Swal.fire({ title: 'Error', text: data.message || 'An error occurred while processing your request.', icon: 'error', confirmButtonColor: '#0ea5e9' });
                            }
                        })
                        .catch(() => Swal.fire({ title: 'Error', text: 'An error occurred while processing your request.', icon: 'error', confirmButtonColor: '#0ea5e9' }));
                }
            });
        }

        function renderReviewHistory() {
            const wrap = document.getElementById('modalReviewHistory');
            if (!wrap) return;
            wrap.innerHTML = '';
            if (!reviewHistory || reviewHistory.length === 0) {
                const p = document.createElement('p');
                p.className = 'text-sm text-gray-500';
                p.textContent = 'No prior reviews.';
                wrap.appendChild(p);
                return;
            }
            reviewHistory.forEach(item => {
                const box = document.createElement('div');
                box.className = 'bg-gray-50 rounded-lg p-3 max-h-36 overflow-auto';
                const role = document.createElement('div');
                role.className = 'text-xs font-semibold text-gray-700 mb-1';
                role.textContent = item.role;
                const note = document.createElement('div');
                note.className = 'text-sm text-gray-700 whitespace-pre-wrap break-words';
                note.textContent = item.notes;
                box.appendChild(role);
                box.appendChild(note);
                wrap.appendChild(box);
            });
        }

        function populateFromApi(data) {
            // Build history array and deduplicate by role
            const historyMap = new Map();
            
            // Add notes from individual fields
            if (data.superior_review_notes && data.superior_review_notes.trim() !== '') { 
                historyMap.set('Superior', { role: 'Superior', notes: data.superior_review_notes }); 
            }
            if (data.help_desk_review_notes && data.help_desk_review_notes.trim() !== '') { 
                historyMap.set('Help Desk', { role: 'Help Desk', notes: data.help_desk_review_notes }); 
            }
            if (data.technical_review_notes && data.technical_review_notes.trim() !== '') { 
                historyMap.set('Technical Support', { role: 'Technical Support', notes: data.technical_review_notes }); 
            }
            if (data.process_owner_review_notes && data.process_owner_review_notes.trim() !== '') { 
                historyMap.set('Process Owner', { role: 'Process Owner', notes: data.process_owner_review_notes }); 
            }
            if (data.admin_review_notes && data.admin_review_notes.trim() !== '') { 
                historyMap.set('Admin', { role: 'Admin', notes: data.admin_review_notes }); 
            }
            
            // Also merge API-provided review_history list if present (this will override duplicates)
            if (Array.isArray(data.review_history)) {
                data.review_history.forEach(r => {
                    if (r && r.role && r.note) {
                        historyMap.set(r.role, { role: r.role, notes: r.note });
                    }
                });
            }
            
            // Convert map to array for rendering
            const history = Array.from(historyMap.values());
            
            // Render only the review history
            try {
                const wrap = document.getElementById('modalReviewHistory');
                if (wrap) {
                    wrap.innerHTML = '';
                    if (history.length === 0) {
                        const p = document.createElement('p');
                        p.className = 'text-sm text-gray-500';
                        p.textContent = 'No prior reviews.';
                        wrap.appendChild(p);
                    } else {
                        history.forEach(item => {
                            const box = document.createElement('div');
                            box.className = 'bg-gray-50 rounded-lg p-3 max-h-36 overflow-auto';
                            const role = document.createElement('div');
                            role.className = 'text-xs font-semibold text-gray-700 mb-1';
                            role.textContent = item.role;
                            const note = document.createElement('div');
                            note.className = 'text-sm text-gray-700 whitespace-pre-wrap break-words';
                            note.textContent = item.notes;
                            box.appendChild(role);
                            box.appendChild(note);
                            wrap.appendChild(box);
                        });
                    }
                }
            } catch (e) {
                // ignore
            }
        }
        // Fallback: bind button by ID if present and inline handler fails
        document.addEventListener('DOMContentLoaded', function() {
            const openBtn = document.getElementById('openApprovalModalBtn');
            if (openBtn) {
                openBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    window.showApprovalModal && window.showApprovalModal();
                });
            }
            // Close on Escape
            document.addEventListener('keydown', function(e){ if (e.key === 'Escape') { window.hideApprovalModal && window.hideApprovalModal(); } });
            // Close when clicking backdrop
            const m = document.getElementById('approvalModal');
            if (m) { m.addEventListener('click', function(e){ if (e.target === m) { window.hideApprovalModal && window.hideApprovalModal(); } }); }
        });
    </script>
</body>
<?php include '../footer.php'; ?>
</html>
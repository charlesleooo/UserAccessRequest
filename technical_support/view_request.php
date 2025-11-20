<?php
session_start();
require_once '../config.php';

// Check if technical support is logged in
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'technical_support') {
    header('Location: ../admin/login.php');
    exit();
}

// Check if the user needs to enter the encryption code (only for pending requests, not history)
$fromHistory = isset($_GET['from_history']) && $_GET['from_history'] === 'true';
if (!$fromHistory && (
    !isset($_SESSION['requests_verified']) || !$_SESSION['requests_verified'] ||
    (time() - $_SESSION['requests_verified_time'] > 1800)
)) { // Expire after 30 minutes
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
                             AND ar.technical_id IS NOT NULL";

            $stmt = $pdo->prepare($fallbackQuery);
            $stmt->execute([':access_request_number' => $accessRequestNumber]);
            $historyRequest = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$historyRequest) {
            header("Location: review_history.php");
            exit();
        }

        // Get all usernames, date_needed, and justification from individual_requests or group_requests
        $detailsQuery = "SELECT username, date_needed, justification, application_system, access_type, access_duration, start_date, end_date FROM uar.individual_requests WHERE access_request_number = :access_request_number 
                         UNION 
                         SELECT username, date_needed, justification, application_system, access_type, access_duration, start_date, end_date FROM uar.group_requests WHERE access_request_number = :access_request_number 
                         ORDER BY username";
        $stmt = $pdo->prepare($detailsQuery);
        $stmt->execute([':access_request_number' => $accessRequestNumber]);
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
            'technical_notes' => $historyRequest['technical_notes'],
            'created_at' => $historyRequest['created_at'] ?? $historyRequest['technical_review_date']
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
    <!-- Animate on Scroll -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        },
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
    <div class="flex-1 ml-72 transition-all duration-300">
        <!-- Header -->
        <div class="bg-blue-900 border-b border-gray-200 sticky top-0 z-10 shadow-sm">
            <div class="flex justify-between items-center px-6 py-4">
                <div>
                    <h2 class="text-4xl font-bold text-white">View Request Details</h2>
                    <p class="text-white text-lg mt-1">Request #<?php echo htmlspecialchars($request['access_request_number'] ?? 'N/A'); ?></p>
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
                        <?php if ($request['status'] === 'pending_technical'): ?>
                            <button id="openTsApprovalModalBtn" onclick="showTsApprovalModal()" class="px-4 py-2 bg-green-50 text-green-600 rounded-lg hover:bg-green-100 transition-colors">
                                <i class='bx bx-check-circle mr-2'></i> Review
                            </button>
                        <?php endif; ?>
                        <?php if ($request['status'] === 'pending_testing_review' || $request['status'] === 'pending_testing_setup'): ?>
                            <button id="openTsTestingModalBtn" onclick="showTsTestingModal()" class="px-4 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition-colors">
                                <i class='bx bx-edit mr-2'></i> Testing Instructions
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Your Review (for history requests) -->
        <?php if ($fromHistory && !empty($request['technical_notes'])): ?>
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
                                    <span class="text-gray-600">Action:</span>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo ($request['status'] === 'rejected') ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?>">
                                        <?php echo ($request['status'] === 'rejected') ? 'Rejected' : 'Approved/Forwarded'; ?>
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Review Date:</span>
                                    <span class="font-medium text-gray-900">
                                        <?php
                                        if (!empty($request['technical_review_date'])) {
                                            $reviewDate = new DateTime($request['technical_review_date']);
                                            echo $reviewDate->format('M d, Y');
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h4 class="text-sm font-medium text-gray-600 mb-2">Review Notes:</h4>
                            <div class="text-gray-700">
                                <?php echo nl2br(htmlspecialchars($request['technical_notes'])); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Requestor's Testing Failure Message (Top Priority) -->
        <?php if ($request['status'] === 'pending_testing_review' && !empty($request['testing_notes'])): ?>
            <div class="p-6">
                <div class="bg-red-50 rounded-xl p-6 border-2 border-red-200 shadow-sm">
                    <div class="flex items-start">
                        <div class="flex-shrink-0 pt-1">
                            <i class='bx bx-error-circle text-red-600 text-3xl'></i>
                        </div>
                        <div class="ml-4 flex-1">
                            <h3 class="text-xl font-bold text-red-800 mb-3">Testing Failed - Requestor's Feedback</h3>
                            <div class="bg-white rounded-lg p-4 border border-red-200">
                                <div class="text-sm text-red-700 whitespace-pre-wrap break-words leading-relaxed"><?php echo nl2br(htmlspecialchars($request['testing_notes'])); ?></div>
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
                                <span class="text-gray-600">Company:</span>
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
        </div>

        <div class="p-6">
            <!-- Access Details -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
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

            <?php if (!empty($request['review_notes'])): ?>
                <!-- Administrator Feedback (for pending requests) -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mt-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                        <i class='bx bx-message-square-detail text-primary-500 text-xl mr-2'></i>
                        Administrator Feedback
                    </h3>
                    <div class="bg-gray-50 p-4 rounded-lg text-gray-700">
                        <?php echo nl2br(htmlspecialchars($request['review_notes'])); ?>
                    </div>
                </div>
            <?php endif; ?>


            <!-- Superior's Comments -->
            <?php if ($fromHistory && !empty($request['superior_notes'])): ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mt-6" data-aos="fade-up" data-aos-duration="800" data-aos-delay="350">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                        <i class='bx bx-message-square-detail text-primary-500 text-xl mr-2'></i>
                        Superior's Comments
                    </h3>
                    <div class="bg-gray-50 p-4 rounded-lg text-gray-700">
                        <?php echo nl2br(htmlspecialchars($request['superior_notes'])); ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Help Desk Comments -->
            <?php if ($fromHistory && !empty($request['help_desk_notes'])): ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mt-6" data-aos="fade-up" data-aos-duration="800" data-aos-delay="350">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                        <i class='bx bx-message-square-detail text-primary-500 text-xl mr-2'></i>
                        Help Desk Comments
                    </h3>
                    <div class="bg-gray-50 p-4 rounded-lg text-gray-700">
                        <?php echo nl2br(htmlspecialchars($request['help_desk_notes'])); ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Process Owner Comments -->
            <?php if ($fromHistory && !empty($request['process_owner_notes'])): ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mt-6" data-aos="fade-up" data-aos-duration="800" data-aos-delay="350">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                        <i class='bx bx-message-square-detail text-primary-500 text-xl mr-2'></i>
                        Process Owner Comments
                    </h3>
                    <div class="bg-gray-50 p-4 rounded-lg text-gray-700">
                        <?php echo nl2br(htmlspecialchars($request['process_owner_notes'])); ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Actions -->
            <!-- Actions moved to modal -->

            <!-- Testing Setup moved to modal -->
        </div>
    </div>


    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize AOS animation library
            AOS.init();
        });

        function scrollToReviewSection(formId) {
            // Find the review section and scroll to it
            const reviewSection = document.getElementById(formId);
            if (reviewSection) {
                reviewSection.scrollIntoView({
                    behavior: 'smooth'
                });
                // Focus on the textarea
                const textareaId = formId === 'testingForm' ? 'testing_instructions' : 'review_notes';
                document.getElementById(textareaId).focus();
            }
        }

        function handleRequest(action) {
            const notes = document.getElementById('review_notes').value;
            const requestId = <?php echo $requestId ? $requestId : 'null'; ?>;

            if (!requestId) {
                Swal.fire({
                    title: 'Error',
                    text: 'Cannot process request from history view.',
                    icon: 'error',
                    confirmButtonColor: '#0ea5e9'
                });
                return;
            }

            if (!notes) {
                Swal.fire({
                    title: 'Review Notes Required',
                    text: 'Please provide technical review notes before submitting your decision.',
                    icon: 'warning',
                    confirmButtonColor: '#0ea5e9'
                });
                scrollToReviewSection('reviewForm');
                return;
            }

            Swal.fire({
                title: action === 'approve' ? 'Approve Request?' : 'Mark as Not Feasible?',
                text: action === 'approve' ? 'This will indicate that the request is technically feasible.' : 'This will mark the request as technically not feasible and notify the requestor.',
                icon: action === 'approve' ? 'question' : 'warning',
                showCancelButton: true,
                confirmButtonColor: action === 'approve' ? '#10B981' : '#EF4444',
                cancelButtonColor: '#6B7280',
                confirmButtonText: action === 'approve' ? 'Yes, Approve' : 'Yes, Not Feasible',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading state
                    Swal.fire({
                        title: 'Processing...',
                        html: 'Please wait while we process your request.',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Submit the form via AJAX
                    fetch('../admin/process_request.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `request_id=${requestId}&action=${action}&review_notes=${encodeURIComponent(notes)}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    title: 'Success!',
                                    text: data.message,
                                    icon: 'success',
                                    confirmButtonColor: '#0ea5e9'
                                }).then(() => {
                                    window.location.href = 'requests.php';
                                });
                            } else {
                                Swal.fire({
                                    title: 'Error',
                                    text: data.message || 'An error occurred while processing your request.',
                                    icon: 'error',
                                    confirmButtonColor: '#0ea5e9'
                                });
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            Swal.fire({
                                title: 'Error',
                                text: 'An error occurred while processing your request.',
                                icon: 'error',
                                confirmButtonColor: '#0ea5e9'
                            });
                        });
                }
            });
        }

        function handleTesting() {
            const instructions = document.getElementById('testing_instructions').value;
            const requestId = <?php echo $requestId; ?>;

            if (!instructions) {
                Swal.fire({
                    title: 'Testing Instructions Required',
                    text: 'Please provide testing instructions before submitting.',
                    icon: 'warning',
                    confirmButtonColor: '#0ea5e9'
                });
                scrollToReviewSection('testingForm');
                return;
            }

            Swal.fire({
                title: 'Send Testing Instructions?',
                text: 'This will send the testing instructions to the requestor.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#6B7280',
                confirmButtonText: 'Yes, Send',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading state
                    Swal.fire({
                        title: 'Processing...',
                        html: 'Please wait while we process your request.',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Submit the form via AJAX
                    fetch('../admin/process_request.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `request_id=${requestId}&action=approve&review_notes=${encodeURIComponent(instructions)}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    title: 'Success!',
                                    text: data.message || 'Testing instructions have been sent to the requestor.',
                                    icon: 'success',
                                    confirmButtonColor: '#0ea5e9'
                                }).then(() => {
                                    window.location.href = 'requests.php';
                                });
                            } else {
                                Swal.fire({
                                    title: 'Error',
                                    text: data.message || 'An error occurred while processing your request.',
                                    icon: 'error',
                                    confirmButtonColor: '#0ea5e9'
                                });
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            Swal.fire({
                                title: 'Error',
                                text: 'An error occurred while processing your request.',
                                icon: 'error',
                                confirmButtonColor: '#0ea5e9'
                            });
                        });
                }
            });
        }
    </script>

    <!-- Testing Instructions Modal (pending_testing_setup/review) -->
    <?php if (!$fromHistory && ($request['status'] === 'pending_testing_setup' || $request['status'] === 'pending_testing_review')): ?>
        <div id="tsTestingModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 hidden z-50">
            <div class="flex items-center justify-center min-h-screen p-2 sm:p-4">
                <div class="bg-white rounded-xl w-full max-w-lg mx-auto shadow-xl max-h-[90vh] overflow-y-auto">
                    <div class="flex items-center px-6 py-4 border-b border-gray-200">
                        <div class="flex-1 text-center">
                            <h3 class="text-xl font-semibold text-gray-800 flex items-center justify-center">
                                <i class='bx bx-test-tube text-blue-600 text-2xl mr-2'></i>
                                <?php echo $request['status'] === 'pending_testing_review' ? 'Testing Failed - Retest' : 'Testing Setup'; ?>
                            </h3>
                        </div>
                        <button onclick="hideTsTestingModal()" class="text-gray-500 hover:text-gray-700">
                            <i class='bx bx-x text-2xl'></i>
                        </button>
                    </div>
                    <div class="p-6 space-y-4">
                        <!-- Requestor's Testing Failure Message (Top Priority) -->
                        <?php if ($request['status'] === 'pending_testing_review' && !empty($request['testing_notes'])): ?>
                            <div class="bg-red-50 rounded-lg p-4 border-2 border-red-200 mb-4">
                                <div class="flex items-start">
                                    <div class="flex-shrink-0 pt-0.5">
                                        <i class='bx bx-error-circle text-red-600 text-2xl'></i>
                                    </div>
                                    <div class="ml-3 flex-1">
                                        <h4 class="text-lg font-semibold text-red-800 mb-2">Testing Failed - Requestor's Feedback</h4>
                                        <div class="bg-white rounded-lg p-3 border border-red-200">
                                            <div class="text-sm text-red-700 whitespace-pre-wrap break-words"><?php echo nl2br(htmlspecialchars($request['testing_notes'])); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Review History -->
                        <div>
                            <h4 class="text-sm font-semibold text-gray-700 mb-2">Review history</h4>
                            <div id="tsModalReviewHistory" class="space-y-3 max-h-60 sm:max-h-80 overflow-auto"></div>
                        </div>
                        <div>
                            <label for="ts_modal_testing_instructions" class="block text-sm font-medium text-gray-700 mb-1">Testing Instructions</label>
                            <textarea id="ts_modal_testing_instructions" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 resize-none" placeholder="Enter detailed instructions for the requestor to test the access..."></textarea>
                        </div>
                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" onclick="hideTsTestingModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">Cancel</button>
                            <button type="button" onclick="sendTestingInstructions()" class="px-4 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition-colors">
                                <i class='bx bx-send mr-2'></i> Send to Requestor
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script>
        function showTsTestingModal() {
            const m = document.getElementById('tsTestingModal');
            if (!m) return;
            m.classList.remove('hidden');
            m.style.display = 'block';
            populateTsReviewHistory();
            setTimeout(() => {
                document.getElementById('ts_modal_testing_instructions')?.focus();
            }, 100);
        }

        function hideTsTestingModal() {
            const m = document.getElementById('tsTestingModal');
            if (!m) return;
            m.classList.add('hidden');
            m.style.display = 'none';
            const ta = document.getElementById('ts_modal_testing_instructions');
            if (ta) ta.value = '';
        }

        function sendTestingInstructions() {
            const instructions = (document.getElementById('ts_modal_testing_instructions') || {}).value || '';
            const requestId = <?php echo $requestId ? $requestId : 'null'; ?>;
            if (!instructions.trim()) {
                Swal.fire({
                    title: 'Testing Instructions Required',
                    text: 'Please provide testing instructions before sending.',
                    icon: 'warning',
                    confirmButtonColor: '#0ea5e9'
                });
                return;
            }
            Swal.fire({
                title: 'Send Testing Instructions?',
                text: 'This will send the testing instructions to the requestor.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#6B7280',
                confirmButtonText: 'Yes, Send',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    hideTsTestingModal();
                    Swal.fire({
                        title: 'Processing...',
                        html: 'Please wait while we send the instructions.',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    fetch('../admin/process_request.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: `request_id=${requestId}&action=approve&review_notes=${encodeURIComponent(instructions)}`
                        })
                        .then(() => {
                            Swal.fire({
                                title: 'Success!',
                                text: 'Testing instructions have been sent to the requestor.',
                                icon: 'success',
                                confirmButtonColor: '#0ea5e9'
                            }).then(() => {
                                window.location.reload();
                            });
                        })
                        .catch(() => {
                            Swal.fire({
                                title: 'Success!',
                                text: 'Testing instructions have been sent to the requestor.',
                                icon: 'success',
                                confirmButtonColor: '#0ea5e9'
                            }).then(() => {
                                window.location.reload();
                            });
                        });
                }
            });
        }

        let tsReviewHistory = [];
        async function populateTsReviewHistory() {
            const requestId = <?php echo $requestId ? $requestId : 'null'; ?>;
            if (!requestId) return;
            try {
                const response = await fetch(`../admin/get_request_details.php?id=${requestId}`);
                const data = await response.json();
                if (data.success && data.data) {
                    const history = data.data.review_history || [];
                    const uniqueByRole = new Map();
                    history.forEach(item => {
                        if (item.note && item.note.trim()) {
                            uniqueByRole.set(item.role, {
                                role: item.role,
                                notes: item.note
                            });
                        }
                    });
                    tsReviewHistory = Array.from(uniqueByRole.values());
                    renderTsReviewHistory();
                } else {
                    renderTsReviewHistory(); // Still render to show "No prior reviews"
                }
            } catch (error) {
                console.error('Error fetching review history:', error);
                renderTsReviewHistory(); // Still render to show "No prior reviews"
            }
        }

        function renderTsReviewHistory() {
            const wrap = document.getElementById('tsModalReviewHistory');
            if (!wrap) return;
            wrap.innerHTML = '';
            if (!tsReviewHistory || tsReviewHistory.length === 0) {
                const p = document.createElement('p');
                p.className = 'text-sm text-gray-500';
                p.textContent = 'No prior reviews.';
                wrap.appendChild(p);
                return;
            }
            tsReviewHistory.forEach(item => {
                const box = document.createElement('div');
                box.className = 'bg-gray-50 rounded-lg p-3';
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

        // Fallback bindings
        document.addEventListener('DOMContentLoaded', function() {
            const btn = document.getElementById('openTsTestingModalBtn');
            if (btn) btn.addEventListener('click', function(e) {
                e.preventDefault();
                showTsTestingModal();
            });
            const m = document.getElementById('tsTestingModal');
            if (m) m.addEventListener('click', function(e) {
                if (e.target === m) hideTsTestingModal();
            });
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') hideTsTestingModal();
            });
        });
    </script>

    <!-- Technical Support Approval Modal (always rendered when pending_technical) -->
    <?php if (!$fromHistory && $request['status'] === 'pending_technical'): ?>
        <div id="tsApprovalModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 hidden z-50">
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="bg-white rounded-xl w-full max-w-lg mx-auto shadow-xl">
                    <div class="flex items-center px-6 py-4 border-b border-gray-200">
                        <div class="flex-1 text-center">
                            <h3 class="text-xl font-semibold text-gray-800 flex items-center justify-center">
                                <i class='bx bx-cog text-green-600 text-2xl mr-2'></i>
                                Technical Review
                            </h3>
                        </div>
                        <button onclick="hideTsApprovalModal()" class="text-gray-500 hover:text-gray-700">
                            <i class='bx bx-x text-2xl'></i>
                        </button>
                    </div>
                    <div class="p-6 space-y-4">
                        <div>
                            <h4 class="text-sm font-semibold text-gray-700 mb-2">Review history</h4>
                            <div id="tsModalReviewHistory" class="space-y-3"></div>
                        </div>
                        <div>
                            <label for="ts_modal_review_notes" class="block text-sm font-medium text-gray-700 mb-1">Technical Review Notes</label>
                            <textarea id="ts_modal_review_notes" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500" placeholder="Enter your technical review notes..."></textarea>
                        </div>
                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" onclick="hideTsApprovalModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">Cancel</button>
                            <button type="button" onclick="handleTsApproval('decline')" class="px-4 py-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition-colors">
                                <i class='bx bx-x-circle mr-2'></i> Not Feasible
                            </button>
                            <button type="button" onclick="handleTsApproval('approve')" class="px-4 py-2 bg-green-50 text-green-600 rounded-lg hover:bg-green-100 transition-colors">
                                <i class='bx bx-check-circle mr-2'></i> Approve
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script>
        const tsCurrentRequestId = <?php echo $requestId ? intval($requestId) : 'null'; ?>;
        const tsSeedHistory = (function() {
            const history = [];
            <?php
            $superiorNotes = $request['superior_notes'] ?? null;
            $helpDeskNotes = $request['help_desk_notes'] ?? null;
            $processOwnerNotes = $request['process_owner_notes'] ?? null;
            $technicalNotes = $request['technical_notes'] ?? null;
            $adminNotes = $request['admin_notes'] ?? null;
            ?>
            <?php if (!empty($superiorNotes)): ?>history.push({
                role: 'Superior',
                notes: <?php echo json_encode($superiorNotes); ?>
            });
        <?php endif; ?>
        <?php if (!empty($helpDeskNotes)): ?>history.push({
            role: 'Help Desk',
            notes: <?php echo json_encode($helpDeskNotes); ?>
        });
        <?php endif; ?>
        <?php if (!empty($processOwnerNotes)): ?>history.push({
            role: 'Process Owner',
            notes: <?php echo json_encode($processOwnerNotes); ?>
        });
        <?php endif; ?>
        <?php if (!empty($technicalNotes)): ?>history.push({
            role: 'Technical Support',
            notes: <?php echo json_encode($technicalNotes); ?>
        });
        <?php endif; ?>
        <?php if (!empty($adminNotes)): ?>history.push({
            role: 'Admin',
            notes: <?php echo json_encode($adminNotes); ?>
        });
        <?php endif; ?>
        return history;
        })();

        function showTsApprovalModal() {
            const m = document.getElementById('tsApprovalModal');
            if (!m) return;
            m.classList.remove('hidden');
            m.style.display = 'block';
            setTimeout(() => {
                document.getElementById('ts_modal_review_notes')?.focus();
            }, 100);
            renderTsHistory(tsSeedHistory);
            if (tsCurrentRequestId) {
                fetch(`../admin/get_request_details.php?id=${tsCurrentRequestId}`)
                    .then(r => r.json())
                    .then(resp => {
                        if (resp && resp.success && resp.data) renderTsHistoryFromApi(resp.data);
                    })
                    .catch(() => {});
            }
        }

        function hideTsApprovalModal() {
            const m = document.getElementById('tsApprovalModal');
            if (!m) return;
            m.classList.add('hidden');
            m.style.display = 'none';
            const ta = document.getElementById('ts_modal_review_notes');
            if (ta) ta.value = '';
        }

        function renderTsHistory(list) {
            const wrap = document.getElementById('tsModalReviewHistory');
            if (!wrap) return;
            wrap.innerHTML = '';
            if (!list || list.length === 0) {
                const p = document.createElement('p');
                p.className = 'text-sm text-gray-500';
                p.textContent = 'No prior reviews.';
                wrap.appendChild(p);
                return;
            }
            list.forEach(item => {
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

        function renderTsHistoryFromApi(data) {
            const map = new Map();
            if (data.superior_review_notes && data.superior_review_notes.trim() !== '') map.set('Superior', {
                role: 'Superior',
                notes: data.superior_review_notes
            });
            if (data.help_desk_review_notes && data.help_desk_review_notes.trim() !== '') map.set('Help Desk', {
                role: 'Help Desk',
                notes: data.help_desk_review_notes
            });
            if (data.process_owner_review_notes && data.process_owner_review_notes.trim() !== '') map.set('Process Owner', {
                role: 'Process Owner',
                notes: data.process_owner_review_notes
            });
            if (data.technical_review_notes && data.technical_review_notes.trim() !== '') map.set('Technical Support', {
                role: 'Technical Support',
                notes: data.technical_review_notes
            });
            if (data.admin_review_notes && data.admin_review_notes.trim() !== '') map.set('Admin', {
                role: 'Admin',
                notes: data.admin_review_notes
            });
            if (Array.isArray(data.review_history)) {
                data.review_history.forEach(r => {
                    if (r && r.role && r.note) map.set(r.role, {
                        role: r.role,
                        notes: r.note
                    });
                });
            }
            renderTsHistory(Array.from(map.values()));
        }

        function handleTsApproval(action) {
            const notes = (document.getElementById('ts_modal_review_notes') || {}).value || '';
            const requestId = <?php echo $requestId ? $requestId : 'null'; ?>;
            if (!requestId) {
                Swal.fire({
                    title: 'Error',
                    text: 'Cannot process request from history view.',
                    icon: 'error',
                    confirmButtonColor: '#0ea5e9'
                });
                return;
            }
            if (!notes.trim()) {
                Swal.fire({
                    title: 'Review Notes Required',
                    text: 'Please provide technical review notes before submitting your decision.',
                    icon: 'warning',
                    confirmButtonColor: '#0ea5e9'
                });
                return;
            }
            Swal.fire({
                title: action === 'approve' ? 'Approve Request?' : 'Mark as Not Feasible?',
                text: action === 'approve' ? 'This will indicate that the request is technically feasible.' : 'This will mark the request as technically not feasible and notify the requestor.',
                icon: action === 'approve' ? 'question' : 'warning',
                showCancelButton: true,
                confirmButtonColor: action === 'approve' ? '#10B981' : '#EF4444',
                cancelButtonColor: '#6B7280',
                confirmButtonText: action === 'approve' ? 'Yes, Approve' : 'Yes, Not Feasible',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    hideTsApprovalModal();
                    Swal.fire({
                        title: 'Processing...',
                        html: 'Please wait while we process your request.',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    fetch('../admin/process_request.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: `request_id=${requestId}&action=${action}&review_notes=${encodeURIComponent(notes)}`
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    title: 'Success!',
                                    text: data.message,
                                    icon: 'success',
                                    confirmButtonColor: '#0ea5e9'
                                }).then(() => {
                                    window.location.href = 'requests.php';
                                });
                            } else {
                                Swal.fire({
                                    title: 'Error',
                                    text: data.message || 'An error occurred while processing your request.',
                                    icon: 'error',
                                    confirmButtonColor: '#0ea5e9'
                                });
                            }
                        })
                        .catch(() => Swal.fire({
                            title: 'Error',
                            text: 'An error occurred while processing your request.',
                            icon: 'error',
                            confirmButtonColor: '#0ea5e9'
                        }));
                }
            });
        }
        // Bind button fallback and ESC/backdrop close
        document.addEventListener('DOMContentLoaded', function() {
            const btn = document.getElementById('openTsApprovalModalBtn');
            if (btn) btn.addEventListener('click', function(e) {
                e.preventDefault();
                showTsApprovalModal();
            });
            const m = document.getElementById('tsApprovalModal');
            if (m) m.addEventListener('click', function(e) {
                if (e.target === m) hideTsApprovalModal();
            });
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') hideTsApprovalModal();
            });
        });
    </script>
</body>
<?php include '../footer.php'; ?>

</html>
<?php
session_start();
require_once '../config.php';

// Check if process owner is logged in
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'process_owner') {
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
        $mainQuery = "SELECT ah.*, 
                            COALESCE(e.employee_name, ah.requestor_name) as requestor_name,
                            COALESCE(e.employee_email, ah.email) as employee_email
                     FROM approval_history ah
                     LEFT JOIN employees e ON ah.employee_id = e.employee_id
                     WHERE ah.access_request_number = :access_request_number
                     ORDER BY ah.created_at DESC
                     LIMIT 1";

        $stmt = $pdo->prepare($mainQuery);
        $stmt->execute([':access_request_number' => $accessRequestNumber]);
        $historyRequest = $stmt->fetch(PDO::FETCH_ASSOC);

        // If not found in approval_history, try access_requests table
        if (!$historyRequest) {
            $fallbackQuery = "SELECT ar.*, e.employee_name as requestor_name
                             FROM access_requests ar
                             LEFT JOIN employees e ON ar.employee_id = e.employee_id
                             WHERE ar.access_request_number = :access_request_number
                             AND ar.process_owner_id IS NOT NULL
                             LIMIT 1";
            
            $stmt = $pdo->prepare($fallbackQuery);
            $stmt->execute([':access_request_number' => $accessRequestNumber]);
            $historyRequest = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$historyRequest) {
            header("Location: review_history.php");
            exit();
        }

        // Get all usernames, date_needed, and justification from individual_requests or group_requests
        $detailsQuery = "SELECT username, date_needed, justification, application_system, access_type, access_duration, start_date, end_date FROM individual_requests WHERE access_request_number = :access_request_number 
                         UNION 
                         SELECT username, date_needed, justification, application_system, access_type, access_duration, start_date, end_date FROM group_requests WHERE access_request_number = :access_request_number 
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
            'process_owner_notes' => $historyRequest['process_owner_notes'],
            'created_at' => $historyRequest['created_at'] ?? $historyRequest['process_owner_review_date']
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
        $checkQuery = "SELECT COUNT(*) as count FROM individual_requests WHERE access_request_number = (
                        SELECT access_request_number FROM access_requests WHERE id = :request_id
                      )";
        $stmt = $pdo->prepare($checkQuery);
        $stmt->execute([':request_id' => $requestId]);
        $isIndividual = $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;

        $requestTable = $isIndividual ? 'individual_requests' : 'group_requests';

        // First get the main request details
        $mainQuery = "SELECT ar.*, e.employee_name as requestor_name
                     FROM access_requests ar
                     LEFT JOIN employees e ON ar.employee_id = e.employee_id
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
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="flex-1 ml-72 transition-all duration-300">
        <!-- Header -->
        <div class="bg-blue-900 border-b border-gray-200 sticky top-0 z-10 shadow-sm">
            <div class="flex justify-between items-center px-6 py-4">
                <div>
                    <h2 class="text-4xl font-bold text-white"><?php echo $fromHistory ? 'Review History Details' : 'View Request Details'; ?></h2>
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
                        <?php if ($request['status'] === 'pending_process_owner'): ?>
                            <button onclick="scrollToReviewSection()" class="px-4 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition-colors">
                                <i class='bx bx-edit mr-2'></i> Add Comments
                            </button>
                            <button onclick="handleRequest('decline')" class="px-4 py-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition-colors">
                                <i class='bx bx-x-circle mr-2'></i> Decline
                            </button>
                            <button onclick="handleRequest('approve')" class="px-4 py-2 bg-green-50 text-green-600 rounded-lg hover:bg-green-100 transition-colors">
                                <i class='bx bx-check-circle mr-2'></i> Approve
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
                                    <?php echo nl2br(htmlspecialchars($request['process_owner_notes'] ?? 'No notes provided.')); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Requestor Information -->
        <div class="p-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6" data-aos="fade-up" data-aos-duration="800">
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
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mt-6" data-aos="fade-up" data-aos-duration="800">
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
            <?php if (!empty($request['help_desk_notes'])): ?>
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

            <!-- Technical Comments -->
            <?php if (!empty($request['technical_notes'])): ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mt-6" data-aos="fade-up" data-aos-duration="800" data-aos-delay="350">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                        <i class='bx bx-message-square-detail text-primary-500 text-xl mr-2'></i>
                        Technical Support Comments
                    </h3>
                    <div class="bg-gray-50 p-4 rounded-lg text-gray-700">
                        <?php echo nl2br(htmlspecialchars($request['technical_notes'])); ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Actions -->
            <?php if (!$fromHistory && $request['status'] === 'pending_process_owner'): ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mt-6" data-aos="fade-up" data-aos-duration="800">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                        <i class='bx bx-check-circle text-primary-500 text-xl mr-2'></i>
                        Process Owner Review
                    </h3>
                    <div class="p-4">
                        <form id="reviewForm">
                            <input type="hidden" name="request_id" value="<?php echo $requestId; ?>">
                            <div class="mb-4">
                                <label for="review_notes" class="block text-sm font-medium text-gray-700 mb-2">Review Notes</label>
                                <textarea id="review_notes" name="review_notes" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500" placeholder="Enter your review notes..."></textarea>
                            </div>
                            <div class="flex justify-end space-x-4">
                                <button type="button" onclick="handleRequest('decline')" class="px-4 py-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition-colors">
                                    <i class='bx bx-x-circle mr-2'></i> Decline
                                </button>
                                <button type="button" onclick="handleRequest('approve')" class="px-4 py-2 bg-green-50 text-green-600 rounded-lg hover:bg-green-100 transition-colors">
                                    <i class='bx bx-check-circle mr-2'></i> Approve
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>


    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize AOS animation library
            AOS.init();
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
                    text: 'Please provide review notes before submitting your decision.',
                    icon: 'warning',
                    confirmButtonColor: '#0ea5e9'
                });
                scrollToReviewSection();
                return;
            }

            Swal.fire({
                title: action === 'approve' ? 'Approve Request?' : 'Decline Request?',
                text: action === 'approve' ? 'This will approve the request from a business process perspective.' : 'This will decline the request and notify the requestor.',
                icon: action === 'approve' ? 'question' : 'warning',
                showCancelButton: true,
                confirmButtonColor: action === 'approve' ? '#10B981' : '#EF4444',
                cancelButtonColor: '#6B7280',
                confirmButtonText: action === 'approve' ? 'Yes, Approve' : 'Yes, Decline',
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
    </script>
</body>

</html>
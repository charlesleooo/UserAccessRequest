<?php
session_start();
require_once '../config.php';

// Check if superior is logged in
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'superior') {
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
        // When coming from history, we have access_request_number already set

        // Get the request details from approval_history first, then access_requests as fallback
        $mainQuery = "SELECT TOP 1 ah.history_id as id, ah.access_request_number,
                            ah.requestor_name, ah.business_unit, ah.department,
                            ah.employee_id, ISNULL(e.employee_email, ah.email) as employee_email,
                            ah.access_type, ah.system_type, ah.justification,
                            ah.duration_type, ah.start_date, ah.end_date,
                            ah.contact_number, ah.testing_status,
                            ah.superior_notes, ah.help_desk_notes,
                            ah.technical_notes, ah.process_owner_notes,
                            ah.comments,
                            ah.action as status, ah.created_at,
                            ah.superior_id, ah.help_desk_id, ah.technical_id,
                            ah.process_owner_id, ah.admin_id,
                            ah.created_at as superior_review_date,
                            ah.created_at as help_desk_review_date,
                            ah.created_at as technical_review_date,
                            ah.created_at as process_owner_review_date,
                            ah.created_at as admin_review_date,
                            ah.created_at as submission_date,
                            ah.created_at as request_date
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
                             AND ar.superior_id IS NOT NULL";

            $stmt = $pdo->prepare($fallbackQuery);
            $stmt->execute([':access_request_number' => $accessRequestNumber]);
            $historyRequest = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$historyRequest) {
            header("Location: review_history.php");
            exit();
        }

        // Get all usernames and details from individual_requests or group_requests
        $detailsQuery = "SELECT username, date_needed, justification, application_system, access_type, access_duration, start_date, end_date 
                         FROM uar.individual_requests 
                         WHERE access_request_number = :arn1 
                         UNION ALL
                         SELECT username, date_needed, justification, application_system, access_type, access_duration, start_date, end_date 
                         FROM uar.group_requests 
                         WHERE access_request_number = :arn2 
                         ORDER BY username";
        $stmt = $pdo->prepare($detailsQuery);
        $stmt->execute([':arn1' => $accessRequestNumber, ':arn2' => $accessRequestNumber]);
        $allDetailsResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Map fields to match access_requests structure
        $request = $historyRequest;
        // Fallbacks for missing values when viewing from history
        if ((empty($request['access_type']) || $request['access_type'] === 'N/A') && isset($historyRequest['access_level'])) {
            $request['access_type'] = $historyRequest['access_level'];
        }

        // Format the request details array
        $requestDetails = [];
        foreach ($allDetailsResults as $detail) {
            $requestDetails[] = [
                'username' => $detail['username'],
                'application_system' => $detail['application_system'],
                'role_access_type' => $detail['access_type'],
                'duration_type' => $detail['access_duration'],
                'start_date' => $detail['start_date'],
                'end_date' => $detail['end_date'],
                'date_needed' => $detail['date_needed'],
                'justification' => $detail['justification']
            ];
        }
    } else {
        // When coming from pending requests, we have ID
        $requestIdInt = intval($requestId);

        // First check if it's an individual or group request
        $checkQuery = "SELECT COUNT(*) as count FROM uar.individual_requests WHERE access_request_number = (
                        SELECT access_request_number FROM uar.access_requests WHERE id = :request_id
                      )";
        $stmt = $pdo->prepare($checkQuery);
        $stmt->execute([':request_id' => $requestIdInt]);
        $isIndividual = $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;

        $requestTable = $isIndividual ? 'uar.individual_requests' : 'uar.group_requests';

        // Get the main request details by ID
        $mainQuery = "SELECT ar.*, e.employee_name as requestor_name
                     FROM uar.access_requests ar
                     LEFT JOIN uar.employees e ON ar.employee_id = e.employee_id
                     WHERE ar.id = :request_id";

        $stmt = $pdo->prepare($mainQuery);
        $stmt->execute([
            ':request_id' => $requestIdInt
        ]);

        $request = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$request) {
        header("Location: " . ($fromHistory ? "review_history.php" : "requests.php"));
        exit();
    }

    // Then get all the individual entries for this request (only for pending requests)
    if (!$fromHistory) {
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
    <div class="flex-1 ml-72 transition-all duration-300">
        <!-- Header -->
        <div class="bg-blue-900 border-b border-gray-200 sticky top-0 z-10 shadow-sm">
            <div class="flex justify-between items-center px-6 py-4">
                <div data-aos="fade-right" data-aos-duration="800">
                    <h2 class="text-4xl font-bold text-white">View Request Details</h2>
                    <p class="text-white text-lg mt-1">Request #<?php echo htmlspecialchars($request['access_request_number'] ?? 'N/A'); ?></p>
                </div>
                <div data-aos="fade-left" data-aos-duration="800" class="flex space-x-2">
                    <a href="<?php echo $fromHistory ? 'request_history.php' : 'requests.php'; ?>" class="inline-flex items-center px-4 py-2 bg-white text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                        <i class='bx bx-arrow-back mr-2'></i> Back to <?php echo $fromHistory ? 'Request History' : 'Requests'; ?>
                    </a>
                    <?php if ($request['status'] === 'pending_superior'): ?>
                        <button onclick="showApprovalModal()" class="px-4 py-2 bg-green-50 text-green-600 rounded-lg hover:bg-green-100 transition-colors">
                            <i class='bx bx-check-circle mr-2'></i> Review & Recommend
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Your Review Section (for approved/rejected requests from history) -->
        <?php if ($fromHistory && !empty($request['superior_notes'])):
            // Determine the superior's actual action
            // If request was rejected AND no help_desk review happened, then superior rejected it
            // Otherwise, superior approved/forwarded it
            $superiorRejected = false;
            if ($request['status'] === 'rejected') {
                // Check if help_desk reviewed after superior
                $superiorRejected = empty($request['help_desk_review_date']) || empty($request['help_desk_notes']);
            }
            $superiorAction = $superiorRejected ? 'Rejected' : 'Approved/Forwarded';
            $actionClass = $superiorRejected ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800';
        ?>
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
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $actionClass; ?>">
                                        <?php echo $superiorAction; ?>
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Review Date:</span>
                                    <span class="font-medium text-gray-900">
                                        <?php
                                        if (!empty($request['superior_review_date'])) {
                                            $reviewDate = new DateTime($request['superior_review_date']);
                                            echo $reviewDate->format('M d, Y H:i');
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
                                <?php echo nl2br(htmlspecialchars($request['superior_notes'])); ?>
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
                    <?php if ($fromHistory): ?>
                        <!-- For history requests, mirror the per-user card layout -->
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
                                                <span class="font-medium text-gray-900"><?php echo htmlspecialchars($detail['username'] ?? 'N/A'); ?></span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">Access Type:</span>
                                                <span class="font-medium text-gray-900"><?php echo htmlspecialchars(($detail['role_access_type'] ?? '') ?: ($request['access_type'] ?? 'N/A')); ?></span>
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
                                                            echo $EndDate->format('M d, Y');
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
                    <?php else: ?>
                        <!-- For pending requests, show detailed individual/group request details -->
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
                    <?php endif; ?>
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

        </div>
    </div>

    <!-- Approval Modal -->
    <div id="approvalModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-xl w-full max-w-md mx-auto shadow-xl">
                <div class="flex items-center px-6 py-4 border-b border-gray-200">
                    <div class="flex-1 text-center">
                        <h3 class="text-xl font-semibold text-gray-800 flex items-center justify-center">
                            <i class='bx bx-check-circle text-green-600 text-2xl mr-2'></i>
                            Your Review
                        </h3>
                    </div>
                    <button onclick="hideApprovalModal()" class="text-gray-500 hover:text-gray-700">
                        <i class='bx bx-x text-2xl'></i>
                    </button>
                </div>
                <div class="p-6">
                    <form id="approvalForm">
                        <input type="hidden" name="request_id" value="<?php echo $requestId; ?>">
                        <div class="mb-4">
                            <label for="modal_review_notes" class="block text-sm font-medium text-gray-700 mb-2">Review Notes</label>
                            <textarea id="modal_review_notes" name="review_notes" rows="4"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500"
                                placeholder="Enter your review notes..."></textarea>
                        </div>
                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="hideApprovalModal()"
                                class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                                Cancel
                            </button>
                            <button type="button" onclick="handleApproval('decline')"
                                class="px-4 py-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition-colors">
                                <i class='bx bx-x-circle mr-2'></i> Reject
                            </button>
                            <button type="button" onclick="handleApproval('approve')"
                                class="px-4 py-2 bg-green-50 text-green-600 rounded-lg hover:bg-green-100 transition-colors">
                                <i class='bx bx-check-circle mr-2'></i> Recommend
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Page loaded
        });

        function showApprovalModal() {
            document.getElementById('approvalModal').classList.remove('hidden');
            // Focus on the textarea when modal opens
            setTimeout(() => {
                document.getElementById('modal_review_notes').focus();
            }, 100);
        }

        function hideApprovalModal() {
            document.getElementById('approvalModal').classList.add('hidden');
            // Clear the form when closing
            document.getElementById('modal_review_notes').value = '';
        }

        function handleApproval(action) {
            const notes = document.getElementById('modal_review_notes').value;
            const requestId = <?php echo $fromHistory ? 'null' : intval($requestId); ?>;

            <?php if ($fromHistory): ?>
                // This function should not be called when viewing from history
                return;
            <?php endif; ?>

            if (!notes.trim()) {
                Swal.fire({
                    title: 'Review Notes Required',
                    text: 'Please provide review notes before submitting your decision.',
                    icon: 'warning',
                    confirmButtonColor: '#0ea5e9'
                });
                return;
            }

            Swal.fire({
                title: action === 'approve' ? 'Recommend Request?' : 'Decline Request?',
                text: action === 'approve' ? 'This will forward the request to the next approval stage.' : 'This will decline the request and notify the requestor.',
                icon: action === 'approve' ? 'question' : 'warning',
                showCancelButton: true,
                confirmButtonColor: action === 'approve' ? '#10B981' : '#EF4444',
                cancelButtonColor: '#6B7280',
                confirmButtonText: action === 'approve' ? 'Yes, Recommend' : 'Yes, Decline',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Hide the modal first
                    hideApprovalModal();

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

        // Close modal when clicking outside
        document.getElementById('approvalModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideApprovalModal();
            }
        });
    </script>
</body>
<?php include '../footer.php'; ?>

</html>
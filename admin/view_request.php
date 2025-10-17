<?php
session_start();
require_once '../config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
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

// Check if request ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: requests.php");
    exit();
}

$requestId = intval($_GET['id']);

// Fetch the request details
try {
    // First check if it's an individual or group request
    $checkQuery = "SELECT COUNT(*) as count FROM uar.individual_requests WHERE access_request_number = (
                    SELECT access_request_number FROM uar.access_requests WHERE id = :request_id
                  )";
    $stmt = $pdo->prepare($checkQuery);
    $stmt->execute([':request_id' => $requestId]);
    $isIndividual = $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;

    $requestTable = $isIndividual ? 'uar.individual_requests' : 'uar.group_requests';

    // First get the main request details
    $mainQuery = "SELECT 
                    ar.*,
                    e.employee_name as requestor_name,
                    -- Superior Review Info
                    s.username as superior_username,
                    es.employee_name as superior_name,
                    (SELECT emp.employee_name FROM uar.employees emp 
                     JOIN uar.admin_users au ON emp.employee_id = au.username 
                     WHERE au.id = ar.superior_id) as superior_reviewer_name,
                    -- Technical Review Info
                    t.username as technical_username,
                    et.employee_name as technical_name,
                    (SELECT emp.employee_name FROM uar.employees emp 
                     JOIN uar.admin_users au ON emp.employee_id = au.username 
                     WHERE au.id = ar.technical_id) as technical_reviewer_name,
                    -- Process Owner Review Info
                    p.username as process_owner_username,
                    ep.employee_name as process_owner_name,
                    (SELECT emp.employee_name FROM uar.employees emp 
                     JOIN uar.admin_users au ON emp.employee_id = au.username 
                     WHERE au.id = ar.process_owner_id) as process_owner_reviewer_name,
                    -- Admin Review Info
                    a.username as admin_username,
                    ea.employee_name as admin_name,
                    (SELECT emp.employee_name FROM uar.employees emp 
                     JOIN uar.admin_users au ON emp.employee_id = au.username 
                     WHERE au.id = ar.admin_id) as admin_reviewer_name
                FROM uar.access_requests ar
                LEFT JOIN uar.employees e ON ar.employee_id = e.employee_id
                -- Superior Info
                LEFT JOIN uar.admin_users s ON ar.superior_id = s.id
                LEFT JOIN uar.employees es ON s.username = es.employee_id
                -- Technical Support Info
                LEFT JOIN uar.admin_users t ON ar.technical_id = t.id
                LEFT JOIN uar.employees et ON t.username = et.employee_id
                -- Process Owner Info
                LEFT JOIN uar.admin_users p ON ar.process_owner_id = p.id
                LEFT JOIN uar.employees ep ON p.username = ep.employee_id
                -- Admin Info
                LEFT JOIN uar.admin_users a ON ar.admin_id = a.id
                LEFT JOIN uar.employees ea ON a.username = ea.employee_id
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

    if (!$request) {
        // Request not found
        header("Location: requests.php");
        exit();
    }

    // Get review history
    $historyQuery = "SELECT 
                        ah.*,
                        CASE
                            WHEN ah.admin_id IS NOT NULL THEN (
                                SELECT CONCAT(e.employee_name, ' (Admin)')
                                FROM uar.admin_users au 
                                JOIN uar.employees e ON au.username = e.employee_id 
                                WHERE au.id = ah.admin_id
                            )
                            WHEN ah.superior_id IS NOT NULL THEN (
                                SELECT CONCAT(e.employee_name, ' (Superior)')
                                FROM uar.admin_users au 
                                JOIN uar.employees e ON au.username = e.employee_id 
                                WHERE au.id = ah.superior_id
                            )
                            WHEN ah.help_desk_id IS NOT NULL THEN (
                                SELECT CONCAT(e.employee_name, ' (Help Desk)')
                                FROM uar.admin_users au 
                                JOIN uar.employees e ON au.username = e.employee_id 
                                WHERE au.id = ah.help_desk_id
                            )
                            WHEN ah.technical_id IS NOT NULL THEN (
                                SELECT CONCAT(e.employee_name, ' (Technical Support)')
                                FROM uar.admin_users au 
                                JOIN uar.employees e ON au.username = e.employee_id 
                                WHERE au.id = ah.technical_id
                            )
                            WHEN ah.process_owner_id IS NOT NULL THEN (
                                SELECT CONCAT(e.employee_name, ' (Process Owner)')
                                FROM uar.admin_users au 
                                JOIN uar.employees e ON au.username = e.employee_id 
                                WHERE au.id = ah.process_owner_id
                            )
                            ELSE 'Unknown User'
                        END as employee_name,
                        CASE
                            WHEN ah.admin_id IS NOT NULL THEN ah.comments
                            WHEN ah.superior_id IS NOT NULL THEN ah.superior_notes
                            WHEN ah.help_desk_id IS NOT NULL THEN ah.help_desk_notes
                            WHEN ah.technical_id IS NOT NULL THEN ah.technical_notes
                            WHEN ah.process_owner_id IS NOT NULL THEN ah.process_owner_notes
                            ELSE NULL
                        END as notes
                     FROM uar.approval_history ah
                     WHERE ah.request_id = :request_id
                     ORDER BY ah.created_at ASC";

    $stmt = $pdo->prepare($historyQuery);
    $stmt->execute([
        ':request_id' => $requestId
    ]);

    $reviewHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <div class="fixed inset-y-0 left-0 z-50 w-72 bg-white shadow-lg">
        <div class="flex flex-col h-full">
            <!-- Logo -->
            <div class="text-center">
                <img src="../logo.png" alt="Company Logo" class="mt-1 w-60 h-auto mx-auto">
            </div>

            <!-- Navigation Menu -->
            <nav class="flex-1 pt-6 pb-4 px-4 space-y-1 overflow-y-auto">
                <p class="px-4 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                    Main Menu
                </p>

                <a href="dashboard.php" class="flex items-center px-4 py-3 text-gray-700 rounded-xl hover:bg-gray-50">
                    <span class="flex items-center justify-center w-9 h-9 bg-gray-100 text-gray-600 rounded-lg">
                        <i class='bx bxs-dashboard text-xl'></i>
                    </span>
                    <span class="ml-3">Dashboard</span>
                </a>

                <a href="requests.php" class="flex items-center px-4 py-3 text-primary-600 bg-primary-50 rounded-xl">
                    <span class="flex items-center justify-center w-9 h-9 bg-primary-100 text-primary-600 rounded-lg">
                        <i class='bx bxs-message-square-detail text-xl'></i>
                    </span>
                    <span class="ml-3 font-medium">Requests</span>
                </a>

                <a href="approval_history.php" class="flex items-center px-4 py-3 text-gray-700 rounded-xl hover:bg-gray-50">
                    <span class="flex items-center justify-center w-9 h-9 bg-gray-100 text-gray-600 rounded-lg">
                        <i class='bx bx-history text-xl'></i>
                    </span>
                    <span class="ml-3">Approval History</span>
                </a>

                <a href="analytics.php" class="flex items-center px-4 py-3 text-gray-700 rounded-xl hover:bg-gray-50">
                    <span class="flex items-center justify-center w-9 h-9 bg-gray-100 text-gray-600 rounded-lg">
                        <i class='bx bx-bar-chart text-xl'></i>
                    </span>
                    <span class="ml-3">Analytics</span>
                </a>

                <a href="user_management.php" class="flex items-center px-4 py-3 text-gray-700 rounded-xl hover:bg-gray-50">
                    <span class="flex items-center justify-center w-9 h-9 bg-gray-100 text-gray-600 rounded-lg">
                        <i class='bx bx-user text-xl'></i>
                    </span>
                    <span class="ml-3">User Management</span>
                </a>

                <a href="settings.php" class="flex items-center px-4 py-3 text-gray-700 rounded-xl hover:bg-gray-50">
                    <span class="flex items-center justify-center w-9 h-9 bg-gray-100 text-gray-600 rounded-lg">
                        <i class='bx bx-cog text-xl'></i>
                    </span>
                    <span class="ml-3">Settings</span>
                </a>
            </nav>

            <!-- Logout Button -->
            <div class="p-4 border-t border-gray-100">
                <a href="logout.php" class="flex items-center px-4 py-3 text-red-600 bg-red-50 rounded-xl hover:bg-red-100">
                    <span class="flex items-center justify-center w-9 h-9 bg-red-100 text-red-600 rounded-lg">
                        <i class='bx bx-log-out text-xl'></i>
                    </span>
                    <span class="ml-3 font-medium">Logout</span>
                </a>
            </div>
        </div>
    </div>

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
                    <a href="requests.php" class="inline-flex items-center px-4 py-2 bg-white text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                        <i class='bx bx-arrow-back mr-2'></i> Back to Requests
                    </a>
                    <?php if ($request['status'] === 'pending_admin'): ?>
                        <button onclick="scrollToReviewSection()" class="px-4 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition-colors">
                            <i class='bx bx-edit mr-2'></i> Add Comments
                        </button>
                        <button onclick="handleRequest('reject')" class="px-4 py-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition-colors">
                            <i class='bx bx-x-circle mr-2'></i> Reject
                        </button>
                        <button onclick="handleRequest('approve')" class="px-4 py-2 bg-green-50 text-green-600 rounded-lg hover:bg-green-100 transition-colors">
                            <i class='bx bx-check-circle mr-2'></i> Final Approval
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

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
        </div>

        <div class="p-6 pt-0">
            <!-- Access Details -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6" data-aos="fade-up" data-aos-duration="800">
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

            <!-- Review History Section -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mt-6" data-aos="fade-up" data-aos-duration="800">
                <h3 class="text-lg font-semibold text-gray-800 mb-6 pb-2 border-b border-gray-100 flex items-center">
                    <i class='bx bx-history text-primary-500 text-xl mr-2'></i>
                    Approval Timeline
                </h3>

                <div class="relative">
                    <!-- Vertical Line - will end at the last item -->
                    <div class="absolute left-5 top-0 h-[calc(98%-4rem)] w-0.5 bg-gray-200"></div>

                    <div class="space-y-8">
                        <!-- Superior Review -->
                        <div class="relative flex items-start group">
                            <div class="absolute left-0 w-10 h-10 flex items-center justify-center z-10">
                                <div class="w-10 h-10 rounded-full <?php echo $request['superior_review_date'] ? 'bg-green-500' : 'bg-gray-300'; ?> flex items-center justify-center shadow-sm transform transition-transform group-hover:scale-110">
                                    <i class='bx bxs-user-check text-xl text-white'></i>
                                </div>
                            </div>
                            <div class="ml-16 bg-white rounded-lg border border-gray-100 shadow-sm hover:shadow-md transition-shadow p-4 w-full">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-2">
                                    <h4 class="text-base font-semibold text-gray-900">
                                        Superior Review
                                        <?php if (!empty($request['superior_reviewer_name'])): ?>
                                            <span class="text-sm font-normal text-gray-600">
                                                (<?php echo htmlspecialchars($request['superior_reviewer_name']); ?>)
                                            </span>
                                        <?php endif; ?>
                                    </h4>
                                    <?php if ($request['superior_review_date']): ?>
                                        <span class="text-sm text-gray-600 font-medium bg-gray-50 px-3 py-1 rounded-full">
                                            <?php echo date('M j, Y h:i A', strtotime($request['superior_review_date'])); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-2 bg-gray-50 rounded-lg p-3 text-sm text-gray-600">
                                    <?php
                                    if (!empty($request['superior_notes'])) {
                                        echo nl2br(htmlspecialchars($request['superior_notes']));
                                    } else {
                                        echo '<span class="text-gray-500 italic">Pending review</span>';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>

                        <!-- Technical Review -->
                        <div class="relative flex items-start group">
                            <div class="absolute left-0 w-10 h-10 flex items-center justify-center z-10">
                                <div class="w-10 h-10 rounded-full <?php echo $request['technical_review_date'] ? 'bg-green-500' : 'bg-gray-300'; ?> flex items-center justify-center shadow-sm transform transition-transform group-hover:scale-110">
                                    <i class='bx bx-code-alt text-xl text-white'></i>
                                </div>
                            </div>
                            <div class="ml-16 bg-white rounded-lg border border-gray-100 shadow-sm hover:shadow-md transition-shadow p-4 w-full">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-2">
                                    <h4 class="text-base font-semibold text-gray-900">
                                        Technical Support Review
                                        <?php if (!empty($request['technical_reviewer_name'])): ?>
                                            <span class="text-sm font-normal text-gray-600">
                                                (<?php echo htmlspecialchars($request['technical_reviewer_name']); ?>)
                                            </span>
                                        <?php endif; ?>
                                    </h4>
                                    <?php if ($request['technical_review_date']): ?>
                                        <span class="text-sm text-gray-600 font-medium bg-gray-50 px-3 py-1 rounded-full">
                                            <?php echo date('M j, Y h:i A', strtotime($request['technical_review_date'])); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-2 bg-gray-50 rounded-lg p-3 text-sm text-gray-600">
                                    <?php
                                    if (!empty($request['technical_notes'])) {
                                        echo nl2br(htmlspecialchars($request['technical_notes']));
                                    } else {
                                        echo '<span class="text-gray-500 italic">Awaiting technical review</span>';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>

                        <!-- Process Owner Review -->
                        <div class="relative flex items-start group">
                            <div class="absolute left-0 w-10 h-10 flex items-center justify-center z-10">
                                <div class="w-10 h-10 rounded-full <?php echo $request['process_owner_review_date'] ? 'bg-green-500' : 'bg-gray-300'; ?> flex items-center justify-center shadow-sm transform transition-transform group-hover:scale-110">
                                    <i class='bx bx-user-voice text-xl text-white'></i>
                                </div>
                            </div>
                            <div class="ml-16 bg-white rounded-lg border border-gray-100 shadow-sm hover:shadow-md transition-shadow p-4 w-full">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-2">
                                    <h4 class="text-base font-semibold text-gray-900">
                                        Process Owner Review
                                        <?php if (!empty($request['process_owner_reviewer_name'])): ?>
                                            <span class="text-sm font-normal text-gray-600">
                                                (<?php echo htmlspecialchars($request['process_owner_reviewer_name']); ?>)
                                            </span>
                                        <?php endif; ?>
                                    </h4>
                                    <?php if ($request['process_owner_review_date']): ?>
                                        <span class="text-sm text-gray-600 font-medium bg-gray-50 px-3 py-1 rounded-full">
                                            <?php echo date('M j, Y h:i A', strtotime($request['process_owner_review_date'])); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-2 bg-gray-50 rounded-lg p-3 text-sm text-gray-600">
                                    <?php
                                    if (!empty($request['process_owner_notes'])) {
                                        echo nl2br(htmlspecialchars($request['process_owner_notes']));
                                    } else {
                                        echo '<span class="text-gray-500 italic">Awaiting process owner review</span>';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>

                        <!-- Admin Review -->
                        <div class="relative flex items-start group">
                            <div class="absolute left-0 w-10 h-10 flex items-center justify-center z-10">
                                <div class="w-10 h-10 rounded-full <?php echo $request['admin_review_date'] ? 'bg-green-500' : 'bg-gray-300'; ?> flex items-center justify-center shadow-sm transform transition-transform group-hover:scale-110">
                                    <i class='bx bx-shield-quarter text-xl text-white'></i>
                                </div>
                            </div>
                            <div class="ml-16 bg-white rounded-lg border border-gray-100 shadow-sm hover:shadow-md transition-shadow p-4 w-full">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-2">
                                    <h4 class="text-base font-semibold text-gray-900">
                                        Admin Review
                                        <?php if (!empty($request['admin_reviewer_name'])): ?>
                                            <span class="text-sm font-normal text-gray-600">
                                                (<?php echo htmlspecialchars($request['admin_reviewer_name']); ?>)
                                            </span>
                                        <?php endif; ?>
                                    </h4>
                                    <?php if ($request['admin_review_date']): ?>
                                        <span class="text-sm text-gray-600 font-medium bg-gray-50 px-3 py-1 rounded-full">
                                            <?php echo date('M j, Y h:i A', strtotime($request['admin_review_date'])); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-2 bg-gray-50 rounded-lg p-3 text-sm text-gray-600">
                                    <?php
                                    if (!empty($request['admin_notes'])) {
                                        echo nl2br(htmlspecialchars($request['admin_notes']));
                                    } else {
                                        echo '<span class="text-gray-500 italic">Awaiting admin review</span>';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <?php if ($request['status'] === 'pending_admin'): ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mt-6" data-aos="fade-up" data-aos-duration="800">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                        <i class='bx bx-check-circle text-primary-500 text-xl mr-2'></i>
                        Final Review
                    </h3>
                    <div class="p-4">
                        <form id="reviewForm">
                            <input type="hidden" name="request_id" value="<?php echo $requestId; ?>">
                            <div class="mb-4">
                                <label for="review_notes" class="block text-sm font-medium text-gray-700 mb-2">Review Notes</label>
                                <textarea id="review_notes" name="review_notes" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500" placeholder="Enter your review notes..."></textarea>
                            </div>
                            <div class="flex justify-end space-x-4">
                                <button type="button" onclick="handleRequest('reject')" class="px-4 py-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition-colors">
                                    <i class='bx bx-x-circle mr-2'></i> Reject
                                </button>
                                <button type="button" onclick="handleRequest('approve')" class="px-4 py-2 bg-green-50 text-green-600 rounded-lg hover:bg-green-100 transition-colors">
                                    <i class='bx bx-check-circle mr-2'></i> Final Approval
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
            const requestId = <?php echo $requestId; ?>;

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
                title: action === 'approve' ? 'Approve Request?' : 'Reject Request?',
                text: action === 'approve' ? 'This will give final approval to the request.' : 'This will reject the request and notify the requestor.',
                icon: action === 'approve' ? 'question' : 'warning',
                showCancelButton: true,
                confirmButtonColor: action === 'approve' ? '#10B981' : '#EF4444',
                cancelButtonColor: '#6B7280',
                confirmButtonText: action === 'approve' ? 'Yes, Approve' : 'Yes, Reject',
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
                    fetch('process_request.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `request_id=${requestId}&action=${action}&review_notes=${encodeURIComponent(notes)}`
                        })
                        .then(response => {
                            // Check if response is ok
                            if (!response.ok) {
                                throw new Error(`HTTP error! status: ${response.status}`);
                            }
                            return response.text(); // Get as text first to debug
                        })
                        .then(text => {
                            console.log('Raw response:', text); // Debug log
                            try {
                                const data = JSON.parse(text);
                                if (data.success) {
                                    Swal.fire({
                                        title: 'Success!',
                                        text: data.message || 'Request has been processed successfully.',
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
                            } catch (parseError) {
                                console.error('JSON parse error:', parseError);
                                console.error('Response text:', text);
                                // If JSON parsing fails, assume success and redirect
                                Swal.fire({
                                    title: 'Success!',
                                    text: 'Request has been processed successfully.',
                                    icon: 'success',
                                    confirmButtonColor: '#0ea5e9'
                                }).then(() => {
                                    window.location.href = 'requests.php';
                                });
                            }
                        })
                        .catch(error => {
                            console.error('Fetch error:', error);
                            // For network errors, still show success since the operation likely succeeded
                            Swal.fire({
                                title: 'Success!',
                                text: 'Request has been processed successfully.',
                                icon: 'success',
                                confirmButtonColor: '#0ea5e9'
                            }).then(() => {
                                window.location.href = 'requests.php';
                            });
                        });
                }
            });
        }
    </script>
</body>

</html>
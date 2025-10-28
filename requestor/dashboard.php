<?php
session_start();
require_once '../config.php';


if (!isset($_SESSION['requestor_id'])) {
    header("Location: login.php");
    exit();
}

$requestorId = $_SESSION['requestor_id'];
$username = $_SESSION['username'] ?? 'User';
// Get first name only final
$firstName = explode(' ', $username)[0];

try {
    // Get counts from both access_requests and approval_history
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM uar.access_requests WHERE employee_id = ?) as total_pending,
            (SELECT COUNT(*) FROM uar.approval_history WHERE requestor_name = ? AND action = 'approved') as approved,
            (SELECT COUNT(*) FROM uar.approval_history WHERE requestor_name = ? AND action = 'rejected') as rejected,
            (SELECT COUNT(*) FROM uar.access_requests WHERE employee_id = ? AND status LIKE 'pending%') as pending,
            (SELECT COUNT(*) FROM uar.approval_history WHERE requestor_name = ? AND action = 'cancelled') as cancelled
    ");

    $stmt->execute([$requestorId, $username, $username, $requestorId, $username]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    $pending = $data['pending'] ?? 0;
    $approved = $data['approved'] ?? 0;
    $rejected = $data['rejected'] ?? 0;
    $cancelled = $data['cancelled'] ?? 0;
    $total = $data['total_pending'] + $approved + $rejected + $cancelled;

    $approvalRate = $total > 0 ? round(($approved / $total) * 100, 1) : 0;
    $declineRate = $total > 0 ? round(($rejected / $total) * 100, 1) : 0;

    // Get recent requests from both tables
    $recentRequestsQuery = "
        (SELECT TOP 5
            id,
            access_request_number,
            'pending' as source,
            status,
            submission_date as request_date,
            system_type,
            'System Application' as access_type,
            testing_status
         FROM uar.access_requests 
         WHERE employee_id = :employee_id)
        UNION ALL
        (SELECT TOP 5
            history_id as id,
            access_request_number,
            'history' as source,
            action as status,
            created_at as request_date,
            system_type,
            access_type,
            ISNULL(testing_status, 'not_required') as testing_status
         FROM uar.approval_history 
         WHERE requestor_name = :username)
        ORDER BY request_date DESC";

    $stmt = $pdo->prepare($recentRequestsQuery);
    $stmt->execute([
        'employee_id' => $requestorId,
        'username' => $username
    ]);
    $recentRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $total = $approved = $rejected = $pending = $cancelled = 0;
    $approvalRate = $declineRate = 0;
    $recentRequests = [];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Requestor Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Boxicons -->
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <!-- Alpine.js for interactions -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.1/dist/cdn.min.js"></script>
    <!-- Animate on Scroll -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <!-- Flowbite CSS -->
    <link href="https://cdn.jsdelivr.net/npm/flowbite@2.5.1/dist/flowbite.min.css" rel="stylesheet" />
    <!-- Flowbite JS -->
    <script src="https://cdn.jsdelivr.net/npm/flowbite@2.5.1/dist/flowbite.min.js"></script>

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
                            950: '#172554',
                        },
                    },
                    fontFamily: {
                        'sans': ['Inter', 'system-ui', 'sans-serif'],
                    },
                }
            }
        }
    </script>


    <style>
        body {
            font-family: 'Inter', system-ui, sans-serif;
        }

        [x-cloak] {
            display: none !important;
        }

        .sidebar-transition {
            transition-property: transform, margin, width;
            transition-duration: 300ms;
        }

        /* Progress Bar */
        .progress-container {
            width: 100%;
            height: 4px;
            background-color: #e2e8f0;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 999;
        }

        .dark .progress-container {
            background-color: #1f2937;
        }

        .progress-bar {
            height: 4px;
            background: linear-gradient(90deg, #0ea5e9, #38bdf8);
            width: 0%;
            transition: width 0.3s ease;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .dark ::-webkit-scrollbar-track {
            background: #1f2937;
        }

        ::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 10px;
        }

        .dark ::-webkit-scrollbar-thumb {
            background: #4b5563;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #a1a1a1;
        }

        .dark ::-webkit-scrollbar-thumb:hover {
            background: #6b7280;
        }

        /* Enhanced Card Styles */
        .stat-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid rgba(226, 232, 240, 0.8);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .dark .stat-card {
            background: linear-gradient(135deg, #1f2937 0%, #111827 100%);
            border: 1px solid rgba(31, 41, 55, 0.8);
        }

        /* Enhanced Table Styles */
        .enhanced-table {
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .enhanced-table thead {
            background: linear-gradient(90deg, #1d4ed8 0%, #1e3a8a 100%) !important;
        }

        .dark .enhanced-table thead {
            background: linear-gradient(90deg, #1d4ed8 0%, #1e3a8a 100%) !important;
        }

        .enhanced-table tr {
            transition: all 0.2s ease;
        }

        .enhanced-table tr:hover {
            background-color: rgba(241, 245, 249, 0.5);
        }

        .dark .enhanced-table tr:hover {
            background-color: rgba(31, 41, 55, 0.5);
        }

        /* Status Badge Styles */
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-weight: 500;
            font-size: 0.75rem;
            transition: all 0.2s ease;
        }

        .status-badge:hover {
            transform: scale(1.05);
        }

        /* Button Styles */
        .action-button {
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .action-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        /* Workflow Diagram Styles */
        .workflow-step {
            position: relative;
            transition: all 0.3s ease;
        }

        .workflow-step:hover {
            transform: translateY(-5px);
        }

        .workflow-step::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, #e2e8f0 0%, #cbd5e1 100%);
            top: 50%;
            left: 100%;
            transform: translateY(-50%);
        }

        .dark .workflow-step::after {
            background: linear-gradient(90deg, #374151 0%, #1f2937 100%);
        }

        .workflow-step:last-child::after {
            display: none;
        }

        /* Responsive table */
        @media (max-width: 640px) {
            .responsive-table-card {
                display: block;
                margin-bottom: 1rem;
                border-radius: 0.5rem;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
                overflow: hidden;
            }

            .responsive-table-card td {
                display: flex;
                padding: 0.75rem 1rem;
                border-bottom: 1px solid #e5e7eb;
            }

            .responsive-table-card td:before {
                content: attr(data-label);
                font-weight: 600;
                width: 40%;
                margin-right: 1rem;
            }

            .responsive-table-card thead {
                display: none;
            }

            .responsive-table-card tr {
                display: block;
                margin-bottom: 1rem;
                border-radius: 0.5rem;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
                overflow: hidden;
                background-color: white;
            }
        }
    </style>
</head>

<body class="bg-gray-100" x-data="{ sidebarOpen: true }" x-init="$store.app = { sidebarOpen: true }">

    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Sidebar Overlay for mobile/tablet -->
    <div
        x-show="sidebarOpen"
        @click="sidebarOpen = false"
        class="fixed inset-0 bg-black bg-opacity-40 z-40 md:hidden"
        x-transition:enter="transition-opacity ease-in-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-in-out duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        aria-hidden="true">
    </div>

    <!-- Main Content -->
    <div class="transition-all duration-300" :class="sidebarOpen ? 'md:ml-72' : 'ml-0'">
        <!-- Header -->
        <div class="bg-gradient-to-r from-blue-700 to-blue-900 border-b border-blue-800 sticky top-0 z-10 shadow-lg">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center px-4 md:px-8 py-4 gap-3">
                <div class="flex items-center" data-aos="fade-right" data-aos-duration="800">
                    <!-- Hamburger button for toggling sidebar -->
                    <button
                        @click="sidebarOpen = !sidebarOpen"
                        type="button"
                        class="inline-flex items-center justify-center rounded-lg p-2 text-white hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-300 mr-3 md:mr-4 transition-all"
                        aria-label="Toggle sidebar">
                        <svg class="w-6 h-6" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                            <path clip-rule="evenodd" fill-rule="evenodd" d="M2 4.75A.75.75 0 012.75 4h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 4.75zm0 10.5a.75.75 0 01.75-.75h7.5a.75.75 0 010 1.5h-7.5a.75.75 0 01-.75-.75zM2 10a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 10z"></path>
                        </svg>
                    </button>
                    <div>
                        <h2 class="text-2xl md:text-3xl lg:text-4xl font-bold text-white">Dashboard</h2>
                    </div>
                </div>

                <!-- Data Privacy Notice -->
                <div class="relative" x-data="{ privacyNoticeOpen: false }" @mouseover="privacyNoticeOpen = true" @mouseleave="privacyNoticeOpen = false">
                    <button type="button" class="inline-flex items-center p-2 text-white bg-blue-800 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-300 transition-all">
                        <svg class="w-5 h-5" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="sr-only">Privacy info</span>
                    </button>
                    <div x-cloak x-show="privacyNoticeOpen"
                        class="absolute right-0 mt-2 w-72 p-4 bg-white rounded-lg shadow-xl text-gray-700 text-sm z-50 border border-gray-200"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 transform scale-95"
                        x-transition:enter-end="opacity-100 transform scale-100"
                        x-transition:leave="transition ease-in duration-75"
                        x-transition:leave-start="opacity-100 transform scale-100"
                        x-transition:leave-end="opacity-0 transform scale-95">
                        <div class="flex items-start mb-2">
                            <svg class="w-5 h-5 text-blue-600 mr-2 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                            </svg>
                            <p class="font-semibold text-gray-900">Data Privacy Notice</p>
                        </div>
                        <p class="ml-7 text-gray-600">Your data is used solely for processing access requests and is handled according to our internal privacy policy.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="p-4 md:p-8" data-aos="fade-up" data-aos-duration="800">
            <!-- Stats Cards -->
            <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-3 md:gap-6 mb-8">
                <div onclick="filterRequests('all')" class="stat-card rounded-xl p-6 flex items-center bg-gradient-to-br from-blue-500 via-blue-400 to-blue-300 cursor-pointer hover:shadow-lg transition-all duration-300">
                    <div class="bg-gradient-to-br from-blue-500 via-white to-blue-300 p-3 rounded-full shadow-lg">
                        <i class='bx bx-folder text-2xl text-blue-600'></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-white">Total Requests</p>
                        <h4 class="text-2xl font-bold text-white"><?php echo $total; ?></h4>
                    </div>
                </div>
                <div onclick="filterRequests('approved')" class="stat-card rounded-xl p-6 flex items-center bg-gradient-to-br from-green-500 via-green-400 to-green-300 cursor-pointer hover:shadow-lg transition-all duration-300">
                    <div class="bg-gradient-to-br from-green-500 via-white to-green-300 p-3 rounded-full shadow-lg">
                        <i class='bx bx-check-circle text-2xl text-green-600'></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-white">Approved</p>
                        <h4 class="text-2xl font-bold text-white"><?php echo $approved; ?></h4>
                    </div>
                </div>
                <div onclick="filterRequests('pending')" class="stat-card rounded-xl p-6 flex items-center bg-gradient-to-br from-yellow-500 via-yellow-400 to-yellow-300 cursor-pointer hover:shadow-lg transition-all duration-300">
                    <div class="bg-gradient-to-br from-yellow-500 via-white to-yellow-300 p-3 rounded-full shadow-lg">
                        <i class='bx bx-time text-2xl text-yellow-600'></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-white">Pending</p>
                        <h4 class="text-2xl font-bold text-white"><?php echo $pending; ?></h4>
                    </div>
                </div>
                <div onclick="filterRequests('rejected')" class="stat-card rounded-xl p-6 flex items-center bg-gradient-to-br from-red-500 via-red-400 to-red-300 cursor-pointer hover:shadow-lg transition-all duration-300">
                    <div class="bg-gradient-to-br from-red-500 via-white to-red-300 p-3 rounded-full shadow-lg">
                        <i class='bx bx-x-circle text-2xl text-red-600'></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-white">Rejected</p>
                        <h4 class="text-2xl font-bold text-white"><?php echo $rejected; ?></h4>
                    </div>
                </div>
            </div>

            <!-- Requests Table -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden enhanced-table">
                <div class="px-6 py-4 bg-white border-b border-gray-200 flex justify-between items-center">
                    <h2 class="text-lg font-medium text-gray-800">My Access Requests</h2>

                </div>
                <!-- Pending Testing Requests Section -->
                <?php
                // Get pending testing requests for this user
                $pendingTestingRequestsQuery = "
                    SELECT ar.*, at.access_type
                    FROM uar.access_requests ar
                    OUTER APPLY (
                        SELECT TOP 1 x.access_type
                        FROM (
                            SELECT ir.access_type FROM uar.individual_requests ir WHERE ir.access_request_number = ar.access_request_number
                            UNION ALL
                            SELECT gr.access_type FROM uar.group_requests gr WHERE gr.access_request_number = ar.access_request_number
                        ) AS x
                    ) AS at
                    WHERE ar.employee_id = :employee_id 
                    AND ar.status = 'pending_testing' 
                    ORDER BY ar.submission_date DESC
                ";
                $stmt = $pdo->prepare($pendingTestingRequestsQuery);
                $stmt->execute(['employee_id' => $requestorId]);
                $pendingTestingRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Get regular requests
                $requestsQuery = "SELECT * FROM uar.access_requests 
                            WHERE employee_id = :employee_id 
                            AND status != 'pending_testing'
                            AND status != 'cancelled'
                            ORDER BY submission_date DESC";
                $stmt = $pdo->prepare($requestsQuery);
                $stmt->execute(['employee_id' => $requestorId]);
                $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (count($pendingTestingRequests) > 0):
                ?>
                    <div class="p-4 bg-yellow-50 border-b border-yellow-100 pending-testing-section">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <i class='bx bx-test-tube text-yellow-600 text-xl'></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-yellow-800">Testing Required</h3>
                                <div class="mt-2 text-sm text-yellow-700">
                                    <p>The following requests require testing before final approval. Please test your access and update the status:</p>
                                </div>

                                <div class="mt-4 space-y-4">
                                    <?php foreach ($pendingTestingRequests as $request): ?>
                                        <div class="bg-white p-4 rounded-lg border border-yellow-200 flex justify-between items-center">
                                            <div>
                                                <div class="flex items-center">
                                                    <span class="font-medium text-gray-900"><?php echo htmlspecialchars($request['access_request_number']); ?></span>
                                                    <span class="ml-2 px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                        Testing Required
                                                    </span>
                                                </div>
                                                <div class="mt-1 text-sm text-gray-600">
                                                    <span><?php echo isset($request['access_type']) ? htmlspecialchars($request['access_type']) : 'System Application'; ?></span>
                                                    <?php if ($request['system_type']): ?>
                                                        <span class="mx-1">•</span>
                                                        <span><?php echo htmlspecialchars($request['system_type']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ($request['testing_status'] === 'pending'): ?>
                                                    <div class="mt-2 text-xs text-yellow-700">
                                                        <i class='bx bx-info-circle'></i>
                                                        <?php
                                                        $access_type = isset($request['access_type']) ? $request['access_type'] : 'System Application';
                                                        $testing_reason = $access_type === 'System Application' ?
                                                            'System Application access' :
                                                            'Admin role access';
                                                        echo "Testing required for {$testing_reason}. Please test and provide feedback.";
                                                        ?>
                                                    </div>
                                                <?php elseif ($request['testing_status'] === 'success'): ?>
                                                    <div class="mt-2 text-xs text-green-700">
                                                        <i class='bx bx-check-circle'></i> Testing successful - awaiting final approval
                                                    </div>
                                                <?php elseif ($request['testing_status'] === 'failed'): ?>
                                                    <div class="mt-2 text-xs text-red-700">
                                                        <i class='bx bx-x-circle'></i> Testing failed - awaiting admin response
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <?php if ($request['testing_status'] === 'pending'): ?>
                                                    <a href="testing_status.php?id=<?php echo $request['id']; ?>"
                                                        class="inline-flex items-center px-3 py-1 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                        <i class='bx bx-test-tube mr-1'></i> Test
                                                    </a>
                                                <?php elseif ($request['testing_status'] === 'success' || $request['testing_status'] === 'failed'): ?>
                                                    <span class="inline-flex items-center px-3 py-1 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-gray-100">
                                                        Awaiting Admin Review
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Regular Requests Table -->
                <div class="flex flex-col">
                    <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                        <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                            <div class="shadow overflow-hidden border-b border-gray-200 sm:rounded-lg">
                                <table class="min-w-full divide-y divide-gray-200 responsive-table">
                                    <thead class="text-xs text-white uppercase bg-gradient-to-r from-blue-700 to-blue-900 border-b-2 border-blue-950">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left font-semibold tracking-wider">
                                                UAR REF NO.
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left font-semibold tracking-wider">
                                                Status
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left font-semibold tracking-wider">
                                                Date Requested
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left font-semibold tracking-wider">
                                                Days Pending
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left font-semibold tracking-wider">
                                                Date Needed
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($requests as $request):
                                            // Calculate days pending
                                            $submissionDate = new DateTime($request['submission_date']);
                                            $today = new DateTime();
                                            $daysPending = $submissionDate->diff($today)->days;
                                        ?>
                                            <tr class="hover:bg-gray-50 cursor-pointer hover:shadow-md transition-all duration-200 hover:bg-gray-50 odd:bg-gray-100" onclick="viewRequest(<?php echo $request['id']; ?>)">
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900" data-label="UAR REF NO.">
                                                    <?php echo htmlspecialchars($request['access_request_number']); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <?php
                                                    $statusClass = '';
                                                    $status = strtolower($request['status']);

                                                    switch ($status) {
                                                        case 'pending_superior':
                                                            $statusClass = 'bg-yellow-100 text-yellow-800';
                                                            $displayStatus = 'Pending Superior Review';
                                                            break;
                                                        case 'pending_help_desk':
                                                            $statusClass = 'bg-yellow-100 text-yellow-800';
                                                            $displayStatus = 'Pending Help Desk Review';
                                                            break;
                                                        case 'pending_technical':
                                                        case 'pending_technical_support':
                                                            $statusClass = 'bg-yellow-100 text-yellow-800';
                                                            $displayStatus = 'Pending Technical Review';
                                                            break;
                                                        case 'pending_process_owner':
                                                            $statusClass = 'bg-yellow-100 text-yellow-800';
                                                            $displayStatus = 'Pending Process Owner Review';
                                                            break;
                                                        case 'pending_admin':
                                                            $statusClass = 'bg-yellow-100 text-yellow-800';
                                                            $displayStatus = 'Pending Admin Review';
                                                            break;
                                                        case 'pending_testing':
                                                        case 'pending_testing_setup':
                                                        case 'pending_testing_review':
                                                            $statusClass = 'bg-yellow-100 text-yellow-800';
                                                            $displayStatus = 'Pending Testing';
                                                            break;
                                                        case 'approved':
                                                            $statusClass = 'bg-green-100 text-green-800';
                                                            $displayStatus = 'Approved';
                                                            break;
                                                        case 'rejected':
                                                            $statusClass = 'bg-red-100 text-red-800';
                                                            $displayStatus = 'Rejected';
                                                            break;
                                                        case 'cancelled':
                                                            $statusClass = 'bg-gray-100 text-gray-800';
                                                            $displayStatus = 'Cancelled';
                                                            break;
                                                        default:
                                                            $statusClass = 'bg-gray-100 text-gray-800';
                                                            $displayStatus = ucfirst(str_replace('_', ' ', $status));
                                                    }
                                                    ?>
                                                    <span class="status-badge <?php echo $statusClass; ?>">
                                                        <?php echo $displayStatus; ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" data-label="Date Requested">
                                                    <?php echo date('M d, Y', strtotime($request['submission_date'])); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" data-label="Days Pending">
                                                    <?php echo $daysPending; ?> days
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" data-label="Date Needed">
                                                    <?php
                                                    $dateNeeded = new DateTime($request['date_needed'] ?? 'now');
                                                    echo $dateNeeded->format('M d, Y');
                                                    ?>
                                                </td>

                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Add JavaScript for handling testing status updates -->
    <script>
        function updateTestingStatus(requestId, status) {
            Swal.fire({
                title: 'Update Testing Status',
                text: 'Please provide any notes about the testing process:',
                input: 'textarea',
                showCancelButton: true,
                confirmButtonText: 'Submit',
                showLoaderOnConfirm: true,
                preConfirm: (notes) => {
                    return fetch('testing_status.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `request_id=${requestId}&testing_status=${status}&testing_notes=${encodeURIComponent(notes)}`,
                        })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(response.statusText);
                            }
                            return response.json();
                        })
                        .catch(error => {
                            Swal.showValidationMessage(`Request failed: ${error}`);
                        });
                },
                allowOutsideClick: () => !Swal.isLoading()
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Success!',
                        text: 'Testing status has been updated.',
                        icon: 'success'
                    }).then(() => {
                        window.location.reload();
                    });
                }
            });
        }

        function viewRequest(requestId) {
            window.location.href = `view_request.php?id=${requestId}`;
        }
    </script>

    <script>
        // Initialize AOS animation library
        document.addEventListener('DOMContentLoaded', function() {
            AOS.init({
                duration: 800,
                easing: 'ease-in-out',
                once: true,
                offset: 100
            });

            // Progress bar functionality
            window.onscroll = function() {
                const winScroll = document.body.scrollTop || document.documentElement.scrollTop;
                const height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
                const scrolled = (winScroll / height) * 100;
                document.getElementById("progressBar").style.width = scrolled + "%";
            };

            // Set sidebar state based on screen size
            function checkScreenSize() {
                const app = Alpine.store('app') || document.querySelector('[x-data]').__x.$data;
                if (window.innerWidth < 768) {
                    app.sidebarOpen = false;
                } else {
                    app.sidebarOpen = true;
                }
            }

            // Check on resize
            window.addEventListener('resize', checkScreenSize);

            // Initial check
            setTimeout(checkScreenSize, 50);
        });

        function viewRequest(requestId) {
            window.location.href = `view_request.php?id=${requestId}`;
        }

        function cancelRequest(requestId) {
            if (confirm("Are you sure you want to cancel this request? This action cannot be undone.")) {
                window.location.href = `cancel_request.php?id=${requestId}`;
            }
        }

        // Add filtering functionality
        function filterRequests(status) {
            const rows = document.querySelectorAll('tbody tr');
            const pendingTestingSection = document.querySelector('.pending-testing-section');

            rows.forEach(row => {
                const statusCell = row.querySelector('td:nth-child(2)');
                const statusText = statusCell.textContent.toLowerCase();

                if (status === 'all') {
                    row.style.display = '';
                } else if (status === 'pending') {
                    // Show all rows that include 'pending' in their status text
                    row.style.display = statusText.includes('pending') ? '' : 'none';
                } else if (status === 'cancelled') {
                    // Show only rows with 'cancelled' status
                    row.style.display = statusText.includes('cancelled') ? '' : 'none';
                } else {
                    // For 'approved' and 'rejected', show only rows with that specific status
                    row.style.display = statusText.includes(status) && !statusText.includes('pending') && !statusText.includes('cancelled') ? '' : 'none';
                }
            });

            // Handle the pending testing section
            if (pendingTestingSection) {
                if (status === 'pending' || status === 'all') {
                    pendingTestingSection.style.display = ''; // Show for 'pending' and 'all'
                } else {
                    pendingTestingSection.style.display = 'none'; // Hide for other statuses
                }
            }

            // Update active state of stat cards
            document.querySelectorAll('.stat-card').forEach(card => {
                card.classList.remove('ring-4', 'ring-gray-600', 'ring-opacity-100');
            });
            // Add a slight delay to ensure the click event finishes before applying ring
            setTimeout(() => {
                event.currentTarget.classList.add('ring-4', 'ring-white', 'ring-opacity-50');
            }, 50);
        }
    </script>

</body>
<?php include '../footer.php'; ?>

</html>
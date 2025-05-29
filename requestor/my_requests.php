<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['requestor_id'])) {
    header("Location: login.php");
    exit();
}

$requestorId = $_SESSION['requestor_id'];
$username = $_SESSION['username'] ?? 'User';

// Initialize filter variables
$statusFilter = $_GET['status'] ?? 'all';
$dateFilter = $_GET['date'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';

// Prepare base query
$query = "SELECT ar.*, e.employee_name as requestor_name 
          FROM access_requests ar
          LEFT JOIN employees e ON ar.employee_id = e.employee_id
          WHERE ar.employee_id = :employee_id
          AND ar.status IN ('pending_superior', 'pending_technical', 'pending_process_owner', 'pending_admin', 'approved', 'rejected', 'pending_testing', 'pending_testing_setup', 'pending_testing_review', 'cancelled')";

// Add filters
$params = [':employee_id' => $requestorId];

if ($statusFilter !== 'all') {
    // Check if status is an array (multiple statuses selected)
    if (is_array($statusFilter)) {
        $placeholders = [];
        $i = 0;
        foreach ($statusFilter as $status) {
            $key = ":status".$i;
            $placeholders[] = $key;
            $params[$key] = $status;
            $i++;
        }
        $query .= " AND ar.status IN (" . implode(',', $placeholders) . ")";
    } else {
        $query .= " AND ar.status = :status";
        $params[':status'] = $statusFilter;
    }
}

if ($dateFilter !== 'all') {
    switch ($dateFilter) {
        case 'today':
            $query .= " AND DATE(ar.submission_date) = CURDATE()";
            break;
        case 'week':
            $query .= " AND ar.submission_date >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)";
            break;
        case 'month':
            $query .= " AND ar.submission_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
            break;
    }
}

if (!empty($searchQuery)) {
    $query .= " AND (ar.access_type LIKE :search 
               OR ar.access_request_number LIKE :search 
               OR ar.business_unit LIKE :search)";
    $params[':search'] = "%$searchQuery%";
}

// Add sorting
$query .= " ORDER BY ar.submission_date DESC";

try {
    // Prepare and execute the query
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug - Print the query and parameters
    echo "<!-- SQL Query: $query -->";
    echo "<!-- Params: " . json_encode($params) . " -->";
    echo "<!-- Result Count: " . count($requests) . " -->";
} catch (PDOException $e) {
    error_log("Error fetching requests: " . $e->getMessage());
    $requests = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Requests</title>
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
    <!-- DataTables -->
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.tailwindcss.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.tailwindcss.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
                        },
                        danger: {
                            DEFAULT: '#dc3545',
                            dark: '#c82333',
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
            background-image: url('bg2.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            font-family: 'Inter', sans-serif;
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
        
        ::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #a1a1a1;
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
        .status-testing {
            @apply bg-blue-500 text-white border-2 border-blue-600;
        }
        
        /* Responsive table */
        @media (max-width: 768px) {
            .responsive-table thead {
                display: none;
            }
            
            .responsive-table tr {
                display: block;
                margin-bottom: 1rem;
                border-radius: 0.5rem;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                background-color: white;
            }
            
            .responsive-table td {
                display: flex;
                padding: 0.75rem 1rem;
                border-bottom: 1px solid #e5e7eb;
                text-align: left !important;
                justify-content: flex-start !important;
            }
            
            .responsive-table td:before {
                content: attr(data-label);
                font-weight: 600;
                width: 120px;
                flex-shrink: 0;
                margin-right: 1rem;
            }
            
            .responsive-table td:last-child {
                border-bottom: none;
            }
            
            .dataTables_wrapper .dataTables_paginate .paginate_button {
                padding: 0.3em 0.8em;
            }
            
            .dataTables_wrapper .dataTables_filter input {
                width: 120px;
            }
            
            /* Ensure proper spacing for status badges */
            .status-badge {
                display: inline-block;
                margin-left: auto;
            }
            
            /* Adjust filter section for mobile */
            .filter-section {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-section > div {
                margin-bottom: 0.5rem;
            }
        }
        
        [x-cloak] {
            display: none !important;
        }
    </style>
</head>
<body class="bg-gray-50 font-sans" x-data="{ sidebarOpen: true }" x-init="$store.app = { sidebarOpen: true }">
<!-- Progress bar at the top of the page -->
<div class="progress-container">
    <div class="progress-bar" id="progressBar"></div>
</div>
<div class="progress-container">
    <div class="progress-bar" id="progressBar"></div>
</div>

<!-- Sidebar -->
<div class="fixed inset-y-0 left-0 z-50 w-72 bg-white shadow-card"
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
    x-show="sidebarOpen"
    x-transition:enter="transition-transform ease-in-out duration-500"
    x-transition:enter-start="-translate-x-full"
    x-transition:enter-end="translate-x-0"
    x-transition:leave="transition-transform ease-in-out duration-500"
    x-transition:leave-start="translate-x-0"
    x-transition:leave-end="-translate-x-full"
    aria-hidden="false">
    <div class="flex flex-col h-full">
        <div class="text-center mt-4">
            <img src="../logo.png" alt="Logo" class="w-48 mx-auto">
        </div>
        <nav class="flex-1 pt-4 px-3 space-y-1 overflow-y-auto">
            <a href="dashboard.php" class="flex items-center p-3 text-gray-700 rounded-xl transition-all duration-200 hover:bg-gray-50 hover:text-primary-600 group">
                <span class="flex items-center justify-center w-10 h-10 bg-gray-100 text-gray-600 rounded-xl mr-3 group-hover:bg-primary-50 group-hover:text-primary-600 transition-all duration-200">
                    <i class='bx bxs-dashboard text-xl'></i>
                </span>
                <span class="font-medium">Dashboard</span>
            </a>
            <a href="create_request.php" class="flex items-center p-3 text-gray-700 rounded-xl transition-all duration-200 hover:bg-gray-50 hover:text-primary-600 group">
                <span class="flex items-center justify-center w-10 h-10 bg-gray-100 text-gray-600 rounded-xl mr-3 group-hover:bg-primary-50 group-hover:text-primary-600 transition-all duration-200">
                    <i class='bx bx-send text-xl'></i>
                </span>
                <span class="font-medium">Create Request</span>
            </a>
            <a href="my_requests.php" class="flex items-center p-3 text-primary-600 bg-primary-50 rounded-xl transition-all duration-200 group">
                <span class="flex items-center justify-center w-10 h-10 bg-primary-100 text-primary-600 rounded-xl mr-3">
                    <i class='bx bx-list-ul text-xl'></i>
                </span>
                <span class="font-medium">My Requests</span>
            </a>
            <a href="request_history.php" class="flex items-center p-3 text-gray-700 rounded-xl transition-all duration-200 hover:bg-gray-50 hover:text-primary-600 group">
                <span class="flex items-center justify-center w-10 h-10 bg-gray-100 text-gray-600 rounded-xl mr-3 group-hover:bg-primary-50 group-hover:text-primary-600 transition-all duration-200">
                    <i class='bx bx-history text-xl'></i>
                </span>
                <span class="font-medium">Request History</span>
            </a>
        </nav>

        <div class="p-3 mt-auto">
            <a href="logout.php" class="flex items-center p-3 text-red-600 bg-red-50 rounded-xl transition-all duration-200 hover:bg-red-100 group">
                <span class="flex items-center justify-center w-10 h-10 bg-red-100 text-red-600 rounded-xl mr-3">
                    <i class='bx bx-log-out text-xl'></i>
                </span>
                <span class="font-medium">Logout</span>
            </a>
        </div>

        <div class="px-4 py-4 border-t border-gray-100">
            <div class="flex items-center space-x-3">
                <div class="flex items-center justify-center w-10 h-10 rounded-full bg-primary-100 text-primary-600">
                    <i class='bx bxs-user text-xl'></i>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($username); ?></p>
                    <p class="text-xs text-gray-500">Requestor</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Mobile menu toggle -->
<div class="fixed top-4 left-4 z-50 md:hidden">
    <button type="button" class="p-2 bg-white rounded-lg shadow-md text-gray-700" @click="open = !open">
        <i class='bx bx-menu text-2xl'></i>
    </button>
</div>

<!-- Main Content -->
<!-- Mobile menu toggle -->
<div class="fixed bottom-4 right-4 z-50 md:hidden">
    <button @click="sidebarOpen = !sidebarOpen" 
            class="flex items-center justify-center w-14 h-14 rounded-full bg-primary-600 text-white shadow-lg hover:bg-primary-700 focus:outline-none transition-all duration-300 transform hover:scale-105">
        <i class='bx bx-menu text-2xl' x-show="!sidebarOpen"></i>
        <i class='bx bx-x text-2xl' x-show="sidebarOpen"></i>
    </button>
</div>

<!-- Mobile header with menu toggle -->
<div class="bg-white sticky top-0 z-20 shadow-sm md:hidden">
    <div class="flex justify-between items-center px-4 py-2">
        <img src="../logo.png" alt="Logo" class="h-10">
        <button @click="sidebarOpen = !sidebarOpen" class="p-2 rounded-lg hover:bg-gray-100">
            <i class='bx bx-menu text-2xl'></i>
        </button>
    </div>
</div>

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

<div class="transition-all duration-300" :class="sidebarOpen ? 'md:ml-72' : 'ml-0'">
    <!-- Header -->
    <div class="bg-blue-600 border-b border-gray-200 sticky top-0 z-10 shadow-sm">
        <div class="flex justify-between items-center px-6 py-4" style = "padding-left: 0px;">
            <div data-aos="fade-right" data-aos-duration="800" class="flex items-center">
                <!-- Hamburger button for toggling sidebar -->
                <button 
                    @click="sidebarOpen = !sidebarOpen"
                    class="inline-flex items-center justify-center rounded-md p-2 text-gray-700 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary-500 mr-4"
                    aria-label="Toggle sidebar"
                >
                    <i class='bx bx-menu text-2xl bg-white rounded-lg p-2'></i>
                </button>
                <div>
                    <h2 class="text-4xl font-bold text-white">
                        <?php if (is_array($statusFilter) && in_array('approved', $statusFilter) && in_array('rejected', $statusFilter)): ?>
                            Request History
                        <?php else: ?>
                            My Access Requests
                        <?php endif; ?>
                    </h2>
                    <p class="text-white text-lg mt-1">
                        <?php if (is_array($statusFilter) && in_array('approved', $statusFilter) && in_array('rejected', $statusFilter)): ?>
                            View your previously approved and rejected requests
                        <?php else: ?>
                            Track and manage your submitted access requests
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <div data-aos="fade-left" data-aos-duration="800" class="hidden md:block">
                <div class="flex items-center space-x-2 text-sm bg-primary-50 text-primary-700 px-4 py-2 rounded-lg">
                    <i class='bx bx-time-five'></i>
                    <span id="current_time"></span>
                </div>
            </div>
        </div>
    </div>

    <div class="p-6">
        <!-- Stats -->
        <!-- Stats section removed as requested -->

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8" data-aos="fade-up" data-aos-duration="800">
            <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select id="status" name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="pending_testing" <?php echo $statusFilter === 'pending_testing' ? 'selected' : ''; ?>>Pending Testing</option>
                        <option value="approved" <?php echo $statusFilter === 'approved' || (is_array($statusFilter) && in_array('approved', $statusFilter)) ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $statusFilter === 'rejected' || (is_array($statusFilter) && in_array('rejected', $statusFilter)) ? 'selected' : ''; ?>>Rejected</option>
                        <?php if (is_array($statusFilter) && in_array('approved', $statusFilter) && in_array('rejected', $statusFilter)): ?>
                        <option value="history" selected>History (Approved & Rejected)</option>
                        <?php else: ?>
                        <option value="history">History (Approved & Rejected)</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div>
                    <label for="date" class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                    <select id="date" name="date" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="all" <?php echo $dateFilter === 'all' ? 'selected' : ''; ?>>All Time</option>
                        <option value="today" <?php echo $dateFilter === 'today' ? 'selected' : ''; ?>>Today</option>
                        <option value="week" <?php echo $dateFilter === 'week' ? 'selected' : ''; ?>>This Week</option>
                        <option value="month" <?php echo $dateFilter === 'month' ? 'selected' : ''; ?>>This Month</option>
                    </select>
                </div>
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <div class="relative">
                        <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" 
                            placeholder="Search requests..." 
                            class="w-full border border-gray-300 rounded-lg pl-10 pr-4 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class='bx bx-search text-gray-400'></i>
                        </div>
                    </div>
                </div>
                <div class="md:col-span-3 flex justify-end space-x-3">
                    <a href="my_requests.php" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                        Reset
                    </a>
                    <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">
                        Apply Filters
                    </button>
                </div>
            </form>
        </div>

        <!-- Requests Table -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden" data-aos="fade-up" data-aos-duration="800">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-800">Access Requests</h3>
                <a href="create_request.php" class="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">
                    <i class='bx bx-plus mr-2'></i> New Request
                </a>
            </div>
            <div class="overflow-x-auto">
                <?php if (!empty($requests)): ?>
                <table id="requestsTable" class="min-w-full divide-y divide-gray-200 responsive-table">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Request No.</th>
                            <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Business Unit</th>
                            <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Access Type</th>
                            <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($requests as $request): ?>
                        <tr class="hover:bg-gray-50 transition-colors odd:bg-gray-100">
                            <td class="px-6 py-4 whitespace-nowrap" data-label="Request No.">
                                <span class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($request['access_request_number'] ?? 'N/A'); ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap" data-label="Business Unit">
                                <span class="text-sm text-gray-700"><?php echo htmlspecialchars($request['business_unit'] ?? 'N/A'); ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap" data-label="Access Type">
                                <div class="flex items-center text-sm text-gray-700">
                                    <?php 
                                    $iconClass = 'text-primary-500';
                                    $icon = 'bx-window-alt';
                                    
                                    if ($request['access_type'] === 'PC Access - Network') {
                                        $icon = 'bx-desktop';
                                    } elseif ($request['access_type'] === 'Email Access') {
                                        $icon = 'bx-envelope';
                                    } elseif ($request['access_type'] === 'Server Access') {
                                        $icon = 'bx-server';
                                    } elseif ($request['access_type'] === 'Internet Access') {
                                        $icon = 'bx-globe';
                                    } elseif ($request['access_type'] === 'Printer Access') {
                                        $icon = 'bx-printer';
                                    } elseif ($request['access_type'] === 'Active Directory Access (MS ENTRA ID)') {
                                        $icon = 'bx-folder-open';
                                    } elseif ($request['access_type'] === 'Firewall Access') {
                                        $icon = 'bx-shield-quarter';
                                    } elseif ($request['access_type'] === 'Wi-Fi/Access Point Access') {
                                        $icon = 'bx-wifi';
                                    } elseif ($request['access_type'] === 'TNA Biometric Device Access') {
                                        $icon = 'bx-fingerprint';
                                    } elseif ($request['access_type'] === 'USB/PC-port Access') {
                                        $icon = 'bx-usb';
                                    } elseif ($request['access_type'] === 'CCTV Access') {
                                        $icon = 'bx-cctv';
                                    }
                                    ?>
                                    <i class='bx <?php echo $icon; ?> text-lg mr-2 <?php echo $iconClass; ?>'></i>
                                    <span><?php echo htmlspecialchars($request['access_type'] ?? 'N/A'); ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap" data-label="Status">
                                <?php 
                                $statusClass = '';
                                $status = strtolower($request['status'] ?? 'pending');
                                $adminReviewDate = $request['admin_review_date'] ?? null;
                                
                                switch ($status) {
                                    case 'pending_superior':
                                        $statusClass = 'bg-yellow-100 text-yellow-800 border border-yellow-200';
                                        $displayStatus = 'Pending Superior Review';
                                        break;
                                    case 'pending_technical':
                                        $statusClass = 'bg-blue-100 text-blue-800 border border-blue-200';
                                        $displayStatus = 'Pending Technical Review';
                                        break;
                                    case 'pending_process_owner':
                                        $statusClass = 'bg-indigo-100 text-indigo-800 border border-indigo-200';
                                        $displayStatus = 'Pending Process Owner Review';
                                        break;
                                    case 'pending_admin':
                                        $statusClass = 'bg-purple-100 text-purple-800 border border-purple-200';
                                        $displayStatus = 'Pending Admin Review';
                                        break;
                                    case 'pending_testing':
                                        $statusClass = 'bg-cyan-100 text-cyan-800 border border-cyan-200';
                                        $displayStatus = 'Pending Testing';
                                        break;
                                    case 'approved':
                                        $statusClass = 'bg-green-100 text-green-800 border border-green-200';
                                        $displayStatus = 'Approved';
                                        break;
                                    case 'rejected':
                                        $statusClass = 'bg-red-100 text-red-800 border border-red-200';
                                        $displayStatus = 'Rejected';
                                        break;
                                    case 'cancelled':
                                        $statusClass = 'bg-gray-100 text-gray-800 border border-gray-200';
                                        $displayStatus = 'Cancelled';
                                        break;
                                    default:
                                        $statusClass = 'bg-gray-100 text-gray-800 border border-gray-200';
                                        $displayStatus = ucfirst($status);
                                }
                                ?>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium <?php echo $statusClass; ?>">
                                    <?php echo $displayStatus; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap" data-label="Date">
                                <span class="text-sm text-gray-700">
                                    <?php 
                                    $date = new DateTime($request['submission_date'] ?? 'now');
                                    echo $date->format('M d, Y'); 
                                    ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right" data-label="Actions">
                                <a href="view_request.php?id=<?php echo $request['id']; ?>" 
                                   class="inline-flex items-center px-3 py-1.5 text-primary-600 hover:text-primary-800 transition-colors">
                                    <i class='bx bx-show mr-1'></i> View
                                </a>
                                <?php if ($status === 'pending_testing' && $request['testing_status'] === 'pending'): ?>
                                <a href="testing_status.php?id=<?php echo $request['id']; ?>" 
                                   class="inline-flex items-center px-3 py-1.5 text-blue-600 hover:text-blue-800 transition-colors">
                                    <i class='bx bx-test-tube mr-1'></i> Test
                                </a>
                                <?php endif; ?>
                                <?php if ($status === 'pending' || ($status !== 'approved' && $status !== 'rejected' && !$adminReviewDate)): ?>
                                <button class="cancel-request inline-flex items-center px-3 py-1.5 text-red-600 hover:text-red-800 transition-colors" data-id="<?php echo $request['id']; ?>">
                                    <i class='bx bx-x mr-1'></i> Cancel
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="p-8 text-center">
                    <div class="bg-gray-50 rounded-xl py-10 px-6">
                        <i class="bx bx-folder-open text-gray-400 text-5xl mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No requests found</h3>
                        <p class="text-gray-500 mb-6">You haven't submitted any access requests yet or no results match your filters.</p>
                        <a href="create_request.php" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors inline-flex items-center">
                            <i class='bx bx-plus mr-2'></i> Create New Request
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    // Set sidebar state based on screen size
    function checkScreenSize() {
        if (window.innerWidth < 768) {
            Alpine.store('app').sidebarOpen = false;
        } else {
            Alpine.store('app').sidebarOpen = true;
        }
    }
    
    // Check on resize and on load
    window.addEventListener('resize', checkScreenSize);
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(checkScreenSize, 50);
        
        // Progress bar functionality
        window.onscroll = function() {
            const winScroll = document.body.scrollTop || document.documentElement.scrollTop;
            const height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
            const scrolled = (winScroll / height) * 100;
            document.getElementById("progressBar").style.width = scrolled + "%";
        };
        
        // Initialize AOS animation library
        AOS.init();
        
        // Show success/error messages
        <?php if (isset($_GET['success']) && $_GET['success'] === 'cancelled'): ?>
        Swal.fire({
            title: 'Success!',
            text: 'Your request has been cancelled successfully.',
            icon: 'success',
            timer: 3000,
            showConfirmButton: false
        });
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
        Swal.fire({
            title: 'Error!',
            text: <?php 
                $errorMsg = 'An error occurred. Please try again.';
                if ($_GET['error'] === 'not_found') {
                    $errorMsg = 'Request not found.';
                } elseif ($_GET['error'] === 'cannot_cancel') {
                    $errorMsg = 'This request cannot be cancelled.';
                } elseif ($_GET['error'] === 'invalid_request') {
                    $errorMsg = 'Invalid request.';
                }
                echo json_encode($errorMsg);
            ?>,
            icon: 'error'
        });
        <?php endif; ?>
        
        // Setup cancel request buttons
        document.querySelectorAll('.cancel-request').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const requestId = this.getAttribute('data-id');
                cancelRequest(requestId);
            });
        });
        
        // Setup DataTable if there's data
        if (document.getElementById('requestsTable')) {
            $('#requestsTable').DataTable({
                responsive: true,
                paging: true,
                searching: false, // We have our own search
                info: true,
                language: {
                    paginate: {
                        previous: '<i class="bx bx-chevron-left"></i>',
                        next: '<i class="bx bx-chevron-right"></i>'
                    }
                },
                dom: 'rtip', // Only show table, info and pagination
                pageLength: 10,
                order: [[4, 'desc']], // Sort by Date column (index 4) in descending order
                columnDefs: [
                    {
                        // Apply special rendering to Status column (index 3)
                        targets: 3,
                        className: 'dt-body-center'
                    }
                ],
                drawCallback: function() {
                    // Ensure status badges are visible after table redraw
                    $('.status-badge').css('display', 'inline-block');
                }
            });
        }

        // Handle the status filter dropdown 
        document.getElementById('status').addEventListener('change', function() {
            if (this.value === 'history') {
                // Create a new URL with both approved and rejected status parameters
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.delete('status');
                currentUrl.searchParams.append('status', 'approved');
                currentUrl.searchParams.append('status', 'rejected');
                
                // Redirect to the new URL
                window.location.href = currentUrl.toString();
                return false;
            }
        });

        // Progress bar handler
        window.onscroll = function() {
            const winScroll = document.body.scrollTop || document.documentElement.scrollTop;
            const height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
            const scrolled = (winScroll / height) * 100;
            document.getElementById("progressBar").style.width = scrolled + "%";
        };
        
        // Current time display
        function updateTime() {
            const now = new Date();
            const timeElement = document.getElementById('current_time');
            if (timeElement) {
                timeElement.textContent = now.toLocaleTimeString('en-US', { 
                    hour: '2-digit', 
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: true 
                });
            }
        }
        
        updateTime();
        setInterval(updateTime, 1000);
    });

    // Unified cancel request function that works with both onclick and event listeners
    function cancelRequest(requestId) {
        Swal.fire({
            title: 'Cancel Request',
            html: `
                <div class="mb-4">
                    <label for="reason" class="block text-sm font-medium text-gray-700 mb-2 text-left">
                        Please provide a reason for cancellation
                    </label>
                    <textarea id="reason" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        rows="4" placeholder="Enter your reason for cancellation..."></textarea>
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, cancel it!',
            cancelButtonText: 'No, keep it',
            preConfirm: () => {
                const reason = document.getElementById('reason').value;
                if (!reason) {
                    Swal.showValidationMessage('Please provide a cancellation reason');
                    return false;
                }
                return reason;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading modal
                Swal.fire({
                    title: 'Processing Cancellation',
                    html: `
                        <div class="text-center">
                            <div class="mb-4">
                                <i class="bx bx-loader-alt bx-spin text-4xl text-primary-600"></i>
                            </div>
                            <p class="text-gray-600">Please wait while your request is being cancelled...</p>
                        </div>
                    `,
                    allowOutsideClick: false,
                    showConfirmButton: false
                });

                // Create a form and submit it
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'cancel_request.php?id=' + requestId;
                
                const reasonInput = document.createElement('input');
                reasonInput.type = 'hidden';
                reasonInput.name = 'reason';
                reasonInput.value = result.value;
                
                form.appendChild(reasonInput);
                document.body.appendChild(form);
                form.submit();
            }
        });
    }
</script>
</body>
</html> 

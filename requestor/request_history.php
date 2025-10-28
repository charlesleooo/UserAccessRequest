<?php
// Start session
session_start();

// Include database configuration
require_once '../config.php';

// Check if user is logged in
if (!isset($_SESSION['requestor_id'])) {
    header('Location: login.php');
    exit();
}

// Get requestor ID from session
$requestorId = $_SESSION['requestor_id'];

// Get filter parameters from URL
$statusFilter = $_GET['status'] ?? 'all';
$dateFilter = $_GET['date'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';

// Prepare base query for all requests (both pending and processed)
// Prioritize access_requests table status over approval_history to avoid inconsistencies
$query = "SELECT 
            source,
            request_id,
            access_request_number,
            status,
            created_at,
            admin_username,
            business_unit,
            department,
            access_type,
            system_type,
            justification,
            email,
            employee_id,
            requestor_name
          FROM (
            SELECT 
              source,
              request_id,
              access_request_number,
              status,
              created_at,
              admin_username,
              business_unit,
              department,
              access_type,
              system_type,
              justification,
              email,
              employee_id,
              requestor_name,
              ROW_NUMBER() OVER (PARTITION BY access_request_number ORDER BY 
                CASE 
                  WHEN source = 'pending' THEN 0  -- Prioritize access_requests table
                  ELSE 1 
                END, 
                created_at DESC
              ) as rn
            FROM (
              SELECT 
                'pending' as source,
                id as request_id,
                access_request_number,
                status,
                submission_date as created_at,
                NULL as admin_username,
                business_unit,
                department,
                'System Application' as access_type,
                system_type,
                NULL as justification,
                employee_email as email,
                employee_id,
                requestor_name
              FROM uar.access_requests 
              WHERE employee_id = :employee_id
              
              UNION ALL
              
              SELECT 
                'history' as source,
                ah.history_id as request_id,
                ah.access_request_number,
                ah.action as status,
                ah.created_at,
                (SELECT username FROM uar.admin_users WHERE id = ah.admin_id) as admin_username,
                ah.business_unit,
                ah.department,
                ah.access_type,
                ah.system_type,
                ah.justification,
                ah.email,
                ah.employee_id,
                ah.requestor_name
              FROM uar.approval_history ah
              WHERE ah.employee_id = :employee_id2
                AND ah.action IN ('approved', 'rejected', 'cancelled')  -- Only show final statuses
            ) all_requests
          ) ranked_requests
          WHERE rn = 1";

// Add filters
$params = [
    ':employee_id' => $requestorId,
    ':employee_id2' => $requestorId
];
$whereConditions = [];

if ($statusFilter !== 'all') {
    if ($statusFilter === 'history') {
        $whereConditions[] = "source = 'history'";
    } elseif ($statusFilter === 'pending') {
        $whereConditions[] = "source = 'pending'";
    } else {
        $whereConditions[] = "status = :status";
        $params[':status'] = $statusFilter;
    }
}

if ($dateFilter !== 'all') {
    switch ($dateFilter) {
        case 'today':
            $whereConditions[] = "CAST(created_at AS DATE) = CAST(GETDATE() AS DATE)";
            break;
        case 'week':
            $whereConditions[] = "created_at >= DATEADD(WEEK, -1, CAST(GETDATE() AS DATE))";
            break;
        case 'month':
            $whereConditions[] = "created_at >= DATEADD(MONTH, -1, CAST(GETDATE() AS DATE))";
            break;
    }
}

if (!empty($searchQuery)) {
    $whereConditions[] = "(access_request_number LIKE :search 
                OR business_unit LIKE :search 
                OR department LIKE :search
                OR access_type LIKE :search
                OR system_type LIKE :search)";
    $params[':search'] = "%$searchQuery%";
}

// Wrap the UNION query and apply WHERE conditions
if (!empty($whereConditions)) {
    $query = "SELECT * FROM ($query) as combined_results WHERE " . implode(' AND ', $whereConditions);
}

// Sort by status priority: pending (any type), approved, rejected, then by UAR reference number in descending order
$query .= " ORDER BY 
    CASE 
        WHEN source = 'pending' OR LOWER(status) LIKE 'pending%' THEN 0
        WHEN status = 'approved' THEN 1
        WHEN status = 'rejected' THEN 2
        ELSE 3
    END,
    access_request_number DESC";

try {
    // Prepare and execute the query
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get counts for the dashboard - Using separate queries for SQL Server compatibility
    $pendingStmt = $pdo->prepare("SELECT COUNT(*) as count FROM uar.access_requests WHERE employee_id = :employee_id AND status LIKE 'pending%'");
    $pendingStmt->execute([':employee_id' => $requestorId]);
    $pendingCount = $pendingStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    $approvedStmt = $pdo->prepare("SELECT COUNT(*) as count FROM uar.approval_history WHERE employee_id = :employee_id AND action = 'approved'");
    $approvedStmt->execute([':employee_id' => $requestorId]);
    $approvedCount = $approvedStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    $rejectedStmt = $pdo->prepare("SELECT COUNT(*) as count FROM uar.approval_history WHERE employee_id = :employee_id AND action = 'rejected'");
    $rejectedStmt->execute([':employee_id' => $requestorId]);
    $rejectedCount = $rejectedStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    $cancelledStmt = $pdo->prepare("SELECT COUNT(*) as count FROM uar.approval_history WHERE employee_id = :employee_id AND action = 'cancelled'");
    $cancelledStmt->execute([':employee_id' => $requestorId]);
    $cancelledCount = $cancelledStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    $counts = [
        'pending' => $pendingCount,
        'approved' => $approvedCount,
        'rejected' => $rejectedCount,
        'cancelled' => $cancelledCount
    ];

    $pending = $counts['pending'] ?? 0;
    $approved = $counts['approved'] ?? 0;
    $rejected = $counts['rejected'] ?? 0;
    $cancelled = $counts['cancelled'] ?? 0;
    $total = $pending + $approved + $rejected + $cancelled;
    
} catch (PDOException $e) {
    error_log("Error fetching request history: " . $e->getMessage());
    $requests = [];
    $total = $approved = $rejected = $cancelled = $pending = 0;
    $errorMessage = "Database Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request History</title>
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
    <!-- jQuery for basic functionality -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
                        danger: {
                            DEFAULT: '#dc3545',
                            dark: '#c82333',
                        }
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
            padding: 4px 12px;
            font-size: 12px;
            font-weight: 500;
            border-radius: 9999px;
            display: inline-block;
        }

        .status-pending {
            background-color: #fbbf24;
            color: white;
        }

        .status-approved {
            background-color: #10b981;
            color: white;
        }

        .status-rejected {
            background-color: #ef4444;
            color: white;
        }

        .status-cancelled {
            background-color: #6b7280;
            color: white;
        }

        /* Responsive table */
        @media (max-width: 768px) {
            .overflow-x-auto {
                overflow-x: auto;
            }

            .min-w-full {
                min-width: 600px;
            }
        }

        .status-cancelled {
            @apply bg-gray-600 text-white border-2 border-gray-700;
        }

        [x-cloak] {
            display: none !important;
        }

        .sidebar-transition {
            transition-property: transform, margin, width;
            transition-duration: 300ms;
        }

        @media (max-width: 768px) {
            .sidebar-open {
                transform: translateX(0);
            }

            .sidebar-closed {
                transform: translateX(-100%);
            }
        }
    </style>
</head>

<body class="bg-gray-100" x-data="{ sidebarOpen: true }" x-init="$store.app = { sidebarOpen: true }">

    <!-- Progress bar at the top of the page -->
    <div class="progress-container">
        <div class="progress-bar" id="progressBar"></div>
    </div>
    <!-- Progress bar -->
    <div class="progress-container">
        <div class="progress-bar" id="progressBar"></div>
    </div>

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
                        <h2 class="text-2xl md:text-3xl lg:text-4xl font-bold text-white">Request History</h2>
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
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1 a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                            </svg>
                            <p class="font-semibold text-gray-900">Data Privacy Notice</p>
                        </div>
                        <p class="ml-7 text-gray-600">Your data is used solely for processing access requests and is handled according to our internal privacy policy.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="p-4 md:p-6" data-aos="fade-up" data-aos-duration="800">
            <?php if (isset($errorMessage)): ?>
            <!-- Error Message -->
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-6" role="alert">
                <div class="flex items-center">
                    <i class='bx bx-error-circle text-2xl mr-3'></i>
                    <div>
                        <p class="font-bold">Error Loading Request History</p>
                        <p class="text-sm"><?php echo htmlspecialchars($errorMessage); ?></p>
                        <p class="text-xs mt-1">Please check the error log at logs/error.log for more details.</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Stats Overview -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="stat-card rounded-xl p-6 flex items-center bg-gradient-to-br from-yellow-500 via-yellow-400 to-yellow-300">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 p-3 mr-4 bg-gradient-to-br from-yellow-500 via-white to-yellow-300 rounded-full shadow-lg">
                            <i class='bx bx-time text-2xl text-yellow-600'></i>
                        </div>
                        <div>
                            <p class="text-sm text-white">Pending</p>
                            <p class="text-2xl font-bold text-white"><?php echo $pending; ?></p>
                        </div>
                    </div>
                </div>
                <div class="stat-card rounded-xl p-6 flex items-center bg-gradient-to-br from-blue-500 via-blue-400 to-blue-300">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 p-3 mr-4 bg-gradient-to-br from-blue-500 via-white to-blue-300 rounded-full shadow-lg">
                            <i class='bx bx-folder text-2xl text-blue-600'></i>
                        </div>
                        <div>
                            <p class="text-sm text-white">Total Processed</p>
                            <p class="text-2xl font-bold text-white"><?php echo $total; ?></p>
                        </div>
                    </div>
                </div>
                <div class="stat-card rounded-xl p-6 flex items-center bg-gradient-to-br from-green-500 via-green-400 to-green-300">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 p-3 mr-4 bg-gradient-to-br from-green-500 via-white to-green-300 rounded-full shadow-lg">
                            <i class='bx bx-check-circle text-2xl text-green-600'></i>
                        </div>
                        <div>
                            <p class="text-sm text-white">Approved</p>
                            <p class="text-2xl font-bold text-white"><?php echo $approved; ?></p>
                        </div>
                    </div>
                </div>
                <div class="stat-card rounded-xl p-6 flex items-center bg-gradient-to-br from-red-500 via-red-400 to-red-300">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 p-3 mr-4 bg-gradient-to-br from-red-500 via-white to-red-300 rounded-full shadow-lg">
                            <i class='bx bx-x-circle text-2xl text-red-600'></i>
                        </div>
                        <div>
                            <p class="text-sm text-white">Rejected</p>
                            <p class="text-2xl font-bold text-white"><?php echo $rejected; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Options -->
            <div class="bg-white rounded-xl shadow-md border border-gray-200 p-6 mb-6">
                <div class="flex items-center justify-between p-4 mb-4 bg-gradient-to-r from-blue-700 to-blue-900 rounded-lg shadow-md border-b-4 border-blue-950">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-white mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L12 11.414V15a1 1 0 01-.293.707l-2 2A1 1 0 018 17v-5.586L3.293 6.707A1 1 0 013 6V3z" clip-rule="evenodd"></path>
                        </svg>
                        <h3 class="text-lg font-semibold text-white">Filter Options</h3>
                    </div>
                    <div class="h-6 w-px bg-white opacity-30"></div>
                </div>
                <form id="filter-form" method="get" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select id="status" name="status" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                            <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All</option>
                            <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            <option value="history" <?php echo $statusFilter === 'history' ? 'selected' : ''; ?>>History (All Completed)</option>
                        </select>
                    </div>
                    <div>
                        <label for="date" class="block text-sm font-medium text-gray-700 mb-2">Date Range</label>
                        <select id="date" name="date" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                            <option value="all" <?php echo $dateFilter === 'all' ? 'selected' : ''; ?>>All Time</option>
                            <option value="today" <?php echo $dateFilter === 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="week" <?php echo $dateFilter === 'week' ? 'selected' : ''; ?>>This Week</option>
                            <option value="month" <?php echo $dateFilter === 'month' ? 'selected' : ''; ?>>This Month</option>
                        </select>
                    </div>
                    <div>
                        <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                        <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Search requests..." class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full text-white bg-gradient-to-r from-blue-700 to-blue-900 hover:from-blue-800 hover:to-blue-950 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center inline-flex items-center justify-center transition-all">
                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L12 11.414V15a1 1 0 01-.293.707l-2 2A1 1 0 018 17v-5.586L3.293 6.707A1 1 0 013 6V3z" clip-rule="evenodd"></path>
                            </svg>
                            Apply Filters
                        </button>
                    </div>
                </form>
            </div>

            <!-- Request History Table -->
            <div class="bg-white rounded-xl shadow-md border border-gray-200">
                <div class="border-b border-gray-200 px-6 py-4 bg-gray-50">
                    <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-3">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-blue-700 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0015.414 6L12 2.586A2 2 0 0010.586 2H6zm5 6a1 1 0 10-2 0v3.586l-1.293-1.293a1 1 0 10-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 11.586V8z" clip-rule="evenodd"></path>
                            </svg>
                            <h3 class="text-xl font-semibold text-gray-800">Request History</h3>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button onclick="filterRequests('all')" class="px-3 py-1.5 text-sm font-medium bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 transition-colors">All</button>
                            <button onclick="filterRequests('pending')" class="px-3 py-1.5 text-sm font-medium text-gray-600 bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors">Pending</button>
                            <button onclick="filterRequests('approved')" class="px-3 py-1.5 text-sm font-medium text-gray-600 bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors">Approved</button>
                            <button onclick="filterRequests('rejected')" class="px-3 py-1.5 text-sm font-medium text-gray-600 bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors">Rejected</button>
                        </div>
                    </div>
                </div>
                <div class="flex flex-col">
                    <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                        <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                            <div class="overflow-hidden">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="text-xs text-white uppercase bg-gradient-to-r from-blue-700 to-blue-900 border-b-2 border-blue-950">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left font-semibold tracking-wider">
                                                UAR REF NO.
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left font-semibold tracking-wider">
                                                Date Requested
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left font-semibold tracking-wider">
                                                Days Since
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left font-semibold tracking-wider">
                                                Status
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php if (count($requests) > 0): ?>
                                            <?php foreach ($requests as $request): ?>
                                                <tr class="cursor-pointer hover:bg-gray-50" onclick="window.location.href='view_request.php?id=<?php echo $request['request_id']; ?>'">
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($request['access_request_number']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php
                                                        $date = new DateTime($request['created_at'] ?? 'now');
                                                        echo $date->format('M d, Y');
                                                        ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php
                                                        $today = new DateTime('now');
                                                        $date = new DateTime($request['created_at'] ?? 'now');
                                                        $interval = $today->diff($date);
                                                        $daysSince = $interval->days;
                                                        echo $daysSince . ' day/s';
                                                        ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php
                                                        $statusClass = '';
                                                        $status = strtolower($request['status']);   
                                                        $displayStatus = '';

                                                        switch ($status) {
                                                            case 'pending_superior':
                                                                $statusClass = 'bg-yellow-100 text-yellow-800';
                                                                $displayStatus = 'Pending Superior Review';
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
                                                            case 'pending_help_desk':
                                                                $statusClass = 'bg-yellow-100 text-yellow-800';
                                                                $displayStatus = 'Pending Help Desk Review';
                                                                break;
                                                            case 'pending':
                                                                $statusClass = 'bg-yellow-100 text-yellow-800';
                                                                $displayStatus = 'Pending';
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
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                                            <?php echo $displayStatus; ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                                    <div class="flex flex-col items-center">
                                                        <i class="bx bx-folder-open text-5xl text-gray-300 mb-2"></i>
                                                        <p class="text-lg font-medium">No request history found</p>
                                                        <p class="text-sm mt-1">Try adjusting your filters or create new access requests</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
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
        });

        // Initialize Alpine store for sidebar state if Alpine.js is loaded
        $(document).ready(function() {
            if (typeof Alpine !== 'undefined') {
                if (!Alpine.store) {
                    // If Alpine.store is not available yet, wait for Alpine to initialize
                    document.addEventListener('alpine:init', () => {
                        Alpine.store('sidebar', {
                            open: true
                        });
                    });
                } else {
                    // If Alpine.store is already available
                    Alpine.store('sidebar', {
                        open: true
                    });
                }
            }

            // Initialize AOS
            AOS.init();

            // Display current time (guard if element is missing)
            function updateTime() {
                const target = document.getElementById('current_time');
                if (!target) return;
                const now = new Date();
                target.textContent = now.toLocaleTimeString([], {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
            }
            updateTime();
            setInterval(updateTime, 1000);

            // Progress bar on scroll
            window.onscroll = function() {
                let winScroll = document.body.scrollTop || document.documentElement.scrollTop;
                let height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
                let scrolled = (winScroll / height) * 100;
                document.getElementById("progressBar").style.width = scrolled + "%";
            };
        });

        // Filter function to match admin design
        function filterRequests(status) {
            const buttons = document.querySelectorAll('.flex.gap-2 button, .flex.flex-wrap.gap-2 button');
            buttons.forEach(button => {
                if (button.textContent.toLowerCase().trim() === status || (status === 'all' && button.textContent.trim() === 'All')) {
                    button.classList.add('bg-blue-50', 'text-blue-700');
                    button.classList.remove('text-gray-600', 'bg-gray-50');
                } else {
                    button.classList.remove('bg-blue-50', 'text-blue-700');
                    button.classList.add('text-gray-600', 'bg-gray-50');
                }
            });

            const rows = document.querySelectorAll('tbody tr');
            rows.forEach(row => {
                // Skip the empty state row
                if (row.querySelector('td[colspan]')) {
                    return;
                }

                const statusCell = row.querySelector('td:last-child span');
                if (!statusCell) return;

                const rowStatus = statusCell.textContent.trim().toLowerCase();
                if (status === 'all' || rowStatus === status) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
    </script>
</body>
<?php include '../footer.php'; ?>
</html>
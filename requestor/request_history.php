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
$query = "SELECT 
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
          FROM access_requests 
          WHERE employee_id = :employee_id
          
          UNION ALL
          
          SELECT 
            'history' as source,
            ah.history_id as request_id,
            ah.access_request_number,
            ah.action as status,
            ah.created_at,
            (SELECT username FROM admin_users WHERE id = ah.admin_id) as admin_username,
            ah.business_unit,
            ah.department,
            ah.access_type,
            ah.system_type,
            ah.justification,
            ah.email,
            ah.employee_id,
            ah.requestor_name
          FROM approval_history ah
          WHERE ah.employee_id = :employee_id";

// Add filters - Fixed to use WHERE instead of HAVING
$params = [':employee_id' => $requestorId];
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
            $whereConditions[] = "DATE(created_at) = CURDATE()";
            break;
        case 'week':
            $whereConditions[] = "created_at >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)";
            break;
        case 'month':
            $whereConditions[] = "created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
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

// Sort by created date in descending order (newest first)
$query .= " ORDER BY created_at DESC";

try {
    // Prepare and execute the query
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get counts for the dashboard
    $countStmt = $pdo->prepare("
        SELECT
            (SELECT COUNT(*) FROM access_requests WHERE employee_id = ? AND status LIKE 'pending%') as pending,
            (SELECT COUNT(*) FROM approval_history WHERE employee_id = ? AND action = 'approved') as approved,
            (SELECT COUNT(*) FROM approval_history WHERE employee_id = ? AND action = 'rejected') as rejected,
            (SELECT COUNT(*) FROM approval_history WHERE employee_id = ? AND action = 'cancelled') as cancelled
    ");

    $countStmt->execute([$requestorId, $requestorId, $requestorId, $requestorId]);
    $counts = $countStmt->fetch(PDO::FETCH_ASSOC);

    $pending = $counts['pending'] ?? 0;
    $approved = $counts['approved'] ?? 0;
    $rejected = $counts['rejected'] ?? 0;
    $cancelled = $counts['cancelled'] ?? 0;
    $total = $pending + $approved + $rejected + $cancelled;
} catch (PDOException $e) {
    error_log("Error fetching request history: " . $e->getMessage());
    $requests = [];
    $total = $approved = $rejected = $cancelled = $pending = 0;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request History</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
        <div class="bg-blue-900 border-b border-gray-200 sticky top-0 z-10 shadow-sm">
            <div class="flex justify-between items-center px-6 py-4">
                <div data-aos="fade-right" data-aos-duration="800" class="flex items-center">
                    <!-- Hamburger button for toggling sidebar -->
                    <button
                        @click="sidebarOpen = !sidebarOpen"
                        class="inline-flex items-center justify-center rounded-md p-2 text-white hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-white mr-4"
                        aria-label="Toggle sidebar">
                        <i class='bx bx-menu text-2xl'></i>
                    </button>
                    <div>
                        <h2 class="text-4xl font-bold text-white">Request History</h2>
                    </div>
                </div>
                <!-- Data Privacy Notice -->
                <div class="relative" x-data="{ privacyNoticeOpen: false }" @mouseover="privacyNoticeOpen = true" @mouseleave="privacyNoticeOpen = false">
                    <button class="text-white hover:text-blue-200 focus:outline-none">
                        <i class='bx bx-info-circle text-2xl'></i>
                    </button>
                    <div x-cloak x-show="privacyNoticeOpen"
                        class="absolute right-0 mt-2 w-64 p-4 bg-white rounded-md shadow-lg text-gray-700 text-sm z-50"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 transform scale-95"
                        x-transition:enter-end="opacity-100 transform scale-100"
                        x-transition:leave="transition ease-in duration-75"
                        x-transition:leave-start="opacity-100 transform scale-100"
                        x-transition:leave-end="opacity-0 transform scale-95">
                        <p class="font-semibold mb-2">Data Privacy Notice</p>
                        <p>Your data is used solely for processing access requests and is handled according to our internal privacy policy.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="p-6" data-aos="fade-up" data-aos-duration="800">
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
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Filter Options</h3>
                <form id="filter-form" method="get" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select id="status" name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                            <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All</option>
                            <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            <option value="history" <?php echo $statusFilter === 'history' ? 'selected' : ''; ?>>History (All Completed)</option>
                        </select>
                    </div>
                    <div>
                        <label for="date" class="block text-sm font-medium text-gray-700 mb-1">Date Range</label>
                        <select id="date" name="date" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                            <option value="all" <?php echo $dateFilter === 'all' ? 'selected' : ''; ?>>All Time</option>
                            <option value="today" <?php echo $dateFilter === 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="week" <?php echo $dateFilter === 'week' ? 'selected' : ''; ?>>This Week</option>
                            <option value="month" <?php echo $dateFilter === 'month' ? 'selected' : ''; ?>>This Month</option>
                        </select>
                    </div>
                    <div>
                        <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Search requests..." class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full bg-primary-600 hover:bg-primary-700 text-white rounded-lg px-4 py-2 transition-colors">
                            <i class='bx bx-filter-alt mr-2'></i> Apply Filters
                        </button>
                    </div>
                </form>
            </div>

            <!-- Request History Table -->
            <div class="bg-white rounded-xl shadow-sm">
                <div class="border-b border-gray-100 px-6 py-4">
                    <div class="flex justify-between items-center">
                        <h3 class="text-xl font-semibold text-gray-800">Request History</h3>
                        <div class="flex gap-2">
                            <button onclick="filterRequests('all')" class="px-3 py-1 text-sm bg-blue-50 text-primary rounded">All</button>
                            <button onclick="filterRequests('pending')" class="px-3 py-1 text-sm text-gray-500 hover:bg-gray-50 rounded">Pending</button>
                            <button onclick="filterRequests('approved')" class="px-3 py-1 text-sm text-gray-500 hover:bg-gray-50 rounded">Approved</button>
                            <button onclick="filterRequests('rejected')" class="px-3 py-1 text-sm text-gray-500 hover:bg-gray-50 rounded">Rejected</button>
                        </div>
                    </div>
                </div>
                <div class="flex flex-col">
                    <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                        <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                            <div class="shadow overflow-hidden border-b border-gray-200 sm:rounded-lg">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                UAR REF NO.
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Requestor
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Business Unit
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Department
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Date Requested
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Days Since
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
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
                                                        // Get requestor name from the request data or use email as fallback
                                                        $requestorName = $request['requestor_name'] ?? 'Unknown User';
                                                        echo htmlspecialchars($requestorName);
                                                        ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($request['business_unit']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($request['department']); ?>
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
                                                        $status = $request['source'] === 'pending' ? 'Pending' : ucfirst(strtolower($request['status']));
                                                        $displayStatus = '';

                                                        switch ($status) {
                                                            case 'Pending':
                                                                $statusClass = 'bg-yellow-100 text-yellow-800';
                                                                $displayStatus = 'Pending';
                                                                break;
                                                            case 'Approved':
                                                                $statusClass = 'bg-green-100 text-green-800';
                                                                $displayStatus = 'Approved';
                                                                break;
                                                            case 'Rejected':
                                                                $statusClass = 'bg-red-100 text-red-800';
                                                                $displayStatus = 'Rejected';
                                                                break;
                                                            case 'Cancelled':
                                                                $statusClass = 'bg-gray-100 text-gray-800';
                                                                $displayStatus = 'Cancelled';
                                                                break;
                                                            default:
                                                                $statusClass = 'bg-gray-100 text-gray-800';
                                                                $displayStatus = ucfirst($status);
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
            const buttons = document.querySelectorAll('.flex.gap-2 button');
            buttons.forEach(button => {
                if (button.textContent.toLowerCase() === status || (status === 'all' && button.textContent === 'All')) {
                    button.classList.add('bg-blue-50', 'text-primary');
                    button.classList.remove('text-gray-500', 'hover:bg-gray-50');
                } else {
                    button.classList.remove('bg-blue-50', 'text-primary');
                    button.classList.add('text-gray-500', 'hover:bg-gray-50');
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

</html>
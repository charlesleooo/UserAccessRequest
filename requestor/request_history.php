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

// Prepare base query for approval history
$query = "SELECT ah.*, admin.username as admin_username
          FROM approval_history ah 
          LEFT JOIN admin_users admin ON ah.admin_id = admin.id
          WHERE ah.requestor_name = :requestor_name";

// Add filters
$params = [':requestor_name' => $username];

if ($statusFilter !== 'all') {
    if ($statusFilter === 'history') {
        $query .= " AND ah.action IN ('approved', 'rejected', 'cancelled')";
    } else {
        $query .= " AND ah.action = :status";
        $params[':status'] = $statusFilter;
    }
}

if ($dateFilter !== 'all') {
    switch ($dateFilter) {
        case 'today':
            $query .= " AND DATE(ah.created_at) = CURDATE()";
            break;
        case 'week':
            $query .= " AND ah.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)";
            break;
        case 'month':
            $query .= " AND ah.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
            break;
    }
}

if (!empty($searchQuery)) {
    $query .= " AND (ah.access_type LIKE :search 
               OR ah.access_request_number LIKE :search 
               OR ah.business_unit LIKE :search
               OR ah.requestor_name LIKE :search)";
    $params[':search'] = "%$searchQuery%";
}

// Add sorting
$query .= " ORDER BY ah.created_at DESC";

try {
    // Prepare and execute the query
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get counts for the dashboard
    $countStmt = $pdo->prepare("SELECT 
        COUNT(*) as total,
        SUM(action = 'approved') as approved,
        SUM(action = 'rejected') as rejected,
        SUM(action = 'cancelled') as cancelled
        FROM approval_history
        WHERE requestor_name = ?");
    $countStmt->execute([$username]);
    $counts = $countStmt->fetch(PDO::FETCH_ASSOC);
    
    $total = $counts['total'] ?? 0;
    $approved = $counts['approved'] ?? 0;
    $rejected = $counts['rejected'] ?? 0;
    $cancelled = $counts['cancelled'] ?? 0;
} catch (PDOException $e) {
    error_log("Error fetching request history: " . $e->getMessage());
    $requests = [];
    $total = $approved = $rejected = $cancelled = 0;
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
        .status-cancelled {
            @apply bg-gray-600 text-white border-2 border-gray-700;
        }
        
        [x-cloak] {
            display: none !important;
        }
    </style>
</head>
<body class="bg-gray-50 font-sans">
<!-- Progress bar -->
<div class="progress-container">
    <div class="progress-bar" id="progressBar"></div>
</div>

<!-- Sidebar -->
<div class="fixed inset-y-0 left-0 z-50 w-72 bg-white shadow-card transform transition-transform duration-300 overflow-hidden" x-data="{open: true}">
    <div class="flex flex-col h-full">
        <div class="text-center p-5 flex items-center justify-center border-b border-gray-100">
            <img src="../logo.png" alt="Logo" class="w-48 mx-auto transition-all duration-300 hover:scale-105">
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
            <a href="my_requests.php" class="flex items-center p-3 text-gray-700 rounded-xl transition-all duration-200 hover:bg-gray-50 hover:text-primary-600 group">
                <span class="flex items-center justify-center w-10 h-10 bg-gray-100 text-gray-600 rounded-xl mr-3 group-hover:bg-primary-50 group-hover:text-primary-600 transition-all duration-200">
                    <i class='bx bx-list-ul text-xl'></i>
                </span>
                <span class="font-medium">My Requests</span>
            </a>
            <a href="request_history.php" class="flex items-center p-3 text-primary-600 bg-primary-50 rounded-xl transition-all duration-200 group">
                <span class="flex items-center justify-center w-10 h-10 bg-primary-100 text-primary-600 rounded-xl mr-3">
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

<!-- Main Content -->
<div class="ml-0 md:ml-72 transition-all duration-300">
    <!-- Header -->
    <div class="bg-white border-b border-gray-200 sticky top-0 z-10 shadow-sm">
        <div class="flex justify-between items-center px-6 py-4">
            <div data-aos="fade-right" data-aos-duration="800">
                <h2 class="text-2xl font-bold text-gray-800">Request History</h2>
                <p class="text-gray-600 text-lg mt-1">View all your previously processed requests</p>
            </div>
            <div data-aos="fade-left" data-aos-duration="800" class="hidden md:block">
                <div class="flex items-center space-x-2 text-sm bg-primary-50 text-primary-700 px-4 py-2 rounded-lg">
                    <i class='bx bx-time-five'></i>
                    <span id="current_time"></span>
                </div>
            </div>
        </div>
    </div>

    <div class="p-6" data-aos="fade-up" data-aos-duration="800">
        <!-- Stats Overview -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0 p-3 mr-4 bg-blue-100 rounded-full">
                        <i class='bx bx-folder text-2xl text-blue-600'></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Total Processed</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo $total; ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0 p-3 mr-4 bg-green-100 rounded-full">
                        <i class='bx bx-check-circle text-2xl text-green-600'></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Approved</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo $approved; ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0 p-3 mr-4 bg-red-100 rounded-full">
                        <i class='bx bx-x-circle text-2xl text-red-600'></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Rejected</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo $rejected; ?></p>
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
        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <div class="overflow-hidden">
                <table id="requests-table" class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Request No.</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Access Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Processed Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if(count($requests) > 0): ?>
                            <?php foreach($requests as $request): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($request['access_request_number']); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <?php echo htmlspecialchars($request['access_type']); ?>
                                            <?php if(!empty($request['system_type'])): ?>
                                                <span class="text-xs text-gray-500 block"><?php echo htmlspecialchars($request['system_type']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($request['department']); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($request['business_unit']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php 
                                        $statusClass = '';
                                        $status = strtolower($request['action'] ?? '');
                                        
                                        if ($status === 'approved') {
                                            $statusClass = 'status-approved';
                                            $bgClass = 'bg-green-100';
                                        } elseif ($status === 'rejected') {
                                            $statusClass = 'status-rejected';
                                            $bgClass = 'bg-red-100';
                                        } elseif ($status === 'cancelled') {
                                            $statusClass = 'status-cancelled';
                                            $bgClass = 'bg-gray-100';
                                        }
                                        ?>
                                        <div class="flex justify-center items-center <?php echo $bgClass; ?> rounded-lg px-2 py-1">
                                            <span class="status-badge <?php echo $statusClass; ?>">
                                                <?php echo ucfirst($status); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-700">
                                        <?php 
                                        $date = new DateTime($request['created_at'] ?? 'now');
                                        echo $date->format('M d, Y'); 
                                        ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <a href="view_history_detail.php?id=<?php echo $request['history_id']; ?>" class="inline-flex items-center px-3 py-1.5 text-sm bg-primary-100 text-primary-700 rounded-lg hover:bg-primary-200 transition-colors">
                                            <i class='bx bx-show mr-1'></i> View
                                        </a>
                                        <a href="tcpdf_print_record.php?id=<?php echo $request['history_id']; ?>" class="inline-flex items-center px-3 py-1.5 text-sm bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition-colors ml-1">
                                            <i class='bx bx-file-pdf mr-1'></i> PDF
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Initialize DataTables with custom styling
$(document).ready(function() {
    $('#requests-table').DataTable({
        responsive: true,
        lengthMenu: [10, 25, 50, 100],
        pageLength: 10,
        dom: 'Bfrtip',
        buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search...",
            emptyTable: '<div class="flex flex-col items-center justify-center py-6"><i class="bx bx-folder-open text-5xl text-gray-300 mb-2"></i><p>No request history found</p><p class="text-sm mt-1">Try adjusting your filters or create new access requests</p></div>'
        }
    });
    
    // Initialize AOS
    AOS.init();
    
    // Display current time
    function updateTime() {
        const now = new Date();
        document.getElementById('current_time').textContent = now.toLocaleTimeString([], {
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
</script>
</body>
</html> 
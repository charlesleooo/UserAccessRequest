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
          WHERE ar.employee_id = :employee_id";

// Add filters
$params = [':employee_id' => $requestorId];

if ($statusFilter !== 'all') {
    $query .= " AND ar.status = :status";
    $params[':status'] = $statusFilter;
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
    
    // Get counts for the dashboard
    $countStmt = $pdo->prepare("SELECT 
        COUNT(*) as total,
        SUM(status = 'approved') as approved,
        SUM(status = 'rejected') as rejected,
        SUM(status = 'pending') as pending
        FROM access_requests
        WHERE employee_id = ?");
    $countStmt->execute([$requestorId]);
    $counts = $countStmt->fetch(PDO::FETCH_ASSOC);
    
    $total = $counts['total'] ?? 0;
    $approved = $counts['approved'] ?? 0;
    $rejected = $counts['rejected'] ?? 0;
    $pending = $counts['pending'] ?? 0;
} catch (PDOException $e) {
    error_log("Error fetching requests: " . $e->getMessage());
    $requests = [];
    $total = $approved = $rejected = $pending = 0;
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
            <a href="my_requests.php" class="flex items-center p-3 text-primary-600 bg-primary-50 rounded-xl transition-all duration-200 group">
                <span class="flex items-center justify-center w-10 h-10 bg-primary-100 text-primary-600 rounded-xl mr-3">
                    <i class='bx bx-list-ul text-xl'></i>
                </span>
                <span class="font-medium">My Requests</span>
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
<div class="ml-0 md:ml-72 transition-all duration-300">
    <!-- Header -->
    <div class="bg-white border-b border-gray-200 sticky top-0 z-10 shadow-sm">
        <div class="flex justify-between items-center px-6 py-4">
            <div data-aos="fade-right" data-aos-duration="800">
                <h2 class="text-2xl font-bold text-gray-800">My Access Requests</h2>
                <p class="text-gray-600 text-lg mt-1">Track and manage your submitted access requests</p>
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
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8" data-aos="fade-up" data-aos-duration="800">
            <div class="bg-white rounded-xl border border-gray-200 p-6 flex items-center shadow-sm hover:shadow-md transition-all duration-300">
                <div class="bg-primary-50 p-3 rounded-xl">
                    <i class='bx bx-folder text-2xl text-primary-600'></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-500">Total Requests</p>
                    <h4 class="text-2xl font-bold text-gray-900"><?php echo $total; ?></h4>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-6 flex items-center shadow-sm hover:shadow-md transition-all duration-300">
                <div class="bg-green-50 p-3 rounded-xl">
                    <i class='bx bx-check-circle text-2xl text-green-600'></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-500">Approved</p>
                    <h4 class="text-2xl font-bold text-gray-900"><?php echo $approved; ?></h4>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-6 flex items-center shadow-sm hover:shadow-md transition-all duration-300">
                <div class="bg-yellow-50 p-3 rounded-xl">
                    <i class='bx bx-time text-2xl text-yellow-600'></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-500">Pending</p>
                    <h4 class="text-2xl font-bold text-gray-900"><?php echo $pending; ?></h4>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-6 flex items-center shadow-sm hover:shadow-md transition-all duration-300">
                <div class="bg-red-50 p-3 rounded-xl">
                    <i class='bx bx-x-circle text-2xl text-red-600'></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-500">Rejected</p>
                    <h4 class="text-2xl font-bold text-gray-900"><?php echo $rejected; ?></h4>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8" data-aos="fade-up" data-aos-duration="800">
            <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select id="status" name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                        <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
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
                <table id="requestsTable" class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Request No.</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Business Unit</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Access Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($requests as $request): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900">
                                <?php echo htmlspecialchars($request['access_request_number'] ?? 'N/A'); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-gray-700">
                                <?php echo htmlspecialchars($request['business_unit'] ?? 'N/A'); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-gray-700">
                                <div class="flex items-center">
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
                                    <?php echo htmlspecialchars($request['access_type'] ?? 'N/A'); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php 
                                $statusClass = '';
                                $status = strtolower($request['status'] ?? 'pending');
                                
                                if ($status === 'pending') {
                                    $statusClass = 'status-pending';
                                    $bgClass = 'bg-yellow-100';
                                } elseif ($status === 'approved') {
                                    $statusClass = 'status-approved';
                                    $bgClass = 'bg-green-100';
                                } elseif ($status === 'rejected') {
                                    $statusClass = 'status-rejected';
                                    $bgClass = 'bg-red-100';
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
                                $date = new DateTime($request['submission_date'] ?? 'now');
                                echo $date->format('M d, Y'); 
                                ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <a href="view_request.php?id=<?php echo $request['id']; ?>" class="text-primary-600 hover:text-primary-800 mr-3">
                                    <i class='bx bx-show'></i> View
                                </a>
                                <?php if ($status === 'pending'): ?>
                                <a href="cancel_request.php?id=<?php echo $request['id']; ?>" class="text-red-600 hover:text-red-800" 
                                   onclick="return confirm('Are you sure you want to cancel this request?');">
                                    <i class='bx bx-x'></i> Cancel
                                </a>
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
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize AOS animation library
        AOS.init();
        
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
                    hour12: true 
                });
            }
        }
        
        updateTime();
        setInterval(updateTime, 1000);
    });
</script>
</body>
</html> 
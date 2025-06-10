<?php
session_start();
require_once '../config.php';
require_once 'analytics_functions.php';

// Authentication check
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Get quick stats for the dashboard
try {
    // Get analytics data
    $statsData = getDashboardStats($pdo);
    
    // Get pending requests count
    $stmt = $pdo->query("SELECT COUNT(*) FROM access_requests WHERE status = 'pending'");
    $pendingRequests = $stmt->fetchColumn();
    
    // Get today's approvals count
    $todayDate = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM approval_history WHERE action = 'approved' AND DATE(created_at) = :today");
    $stmt->execute([':today' => $todayDate]);
    $approvedToday = $stmt->fetchColumn();
    
    // Get today's rejections count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM approval_history WHERE action = 'rejected' AND DATE(created_at) = :today");
    $stmt->execute([':today' => $todayDate]);
    $rejectedToday = $stmt->fetchColumn();
    
    // Get recent requests
    $stmt = $pdo->query("SELECT * FROM access_requests ORDER BY submission_date DESC LIMIT 5");
    $recentRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent approval history
    $stmt = $pdo->query("SELECT h.*, a.username as admin_username 
                        FROM approval_history h 
                        LEFT JOIN admin_users a ON h.admin_id = a.id 
                        ORDER BY h.created_at DESC LIMIT 5");
    $recentApprovals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get access type distribution for mini chart
    $stmt = $pdo->query("SELECT 
                         access_type,
                         COUNT(*) as count
                         FROM approval_history
                         GROUP BY access_type
                         ORDER BY count DESC
                         LIMIT 4");
    $accessTypeDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // Handle database errors gracefully
    error_log("Dashboard data fetch error: " . $e->getMessage());
    $pendingRequests = 0;
    $approvedToday = 0;
    $rejectedToday = 0;
    $statsData = [
        'total' => 0,
        'approved' => 0,
        'approval_rate' => 0,
        'decline_rate' => 0
    ];
    $recentRequests = [];
    $recentApprovals = [];
    $accessTypeDistribution = [];
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UAR Dashboard</title>

    <!-- External CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    
    <!-- Tailwind Configuration -->
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
</head>
<body>
<!-- Sidebar -->
<div class="fixed inset-y-0 left-0 z-50 w-72 bg-white shadow-lg transform transition-transform duration-300">
    <div class="flex flex-col h-full">
        <!-- Logo -->
        <div class="text-center">
            <img src="../logo.png" alt="Alsons Agribusiness Logo" class="mt-1 w-60 h-auto mx-auto">
        </div><br>

        <!-- Navigation Menu -->
        <nav class="flex-1 pt-6 pb-4 px-4 space-y-1 overflow-y-auto">
            <p class="px-4 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                Main Menu
            </p>
            
            <a href="#" class="flex items-center px-4 py-3 text-primary-600 bg-primary-50 rounded-xl transition-all hover:bg-primary-100 group">
                <span class="flex items-center justify-center w-9 h-9 bg-primary-100 text-primary-600 rounded-lg group-hover:bg-primary-200">
                    <i class='bx bxs-dashboard text-xl'></i>
                </span>
                <span class="ml-3 font-medium">Dashboard</span>
            </a>
            
            <a href="analytics.php" class="flex items-center px-4 py-3 text-gray-700 rounded-xl transition-all hover:bg-gray-50 hover:text-primary-600 group">
                <span class="flex items-center justify-center w-9 h-9 bg-gray-100 text-gray-600 rounded-lg group-hover:bg-primary-50 group-hover:text-primary-600">
                    <i class='bx bx-line-chart text-xl'></i>
                </span>
                <span class="ml-3">Analytics</span>
            </a>
            
            <a href="requests.php" class="flex items-center px-4 py-3 text-gray-700 rounded-xl transition-all hover:bg-gray-50 hover:text-primary-600 group">
                <span class="flex items-center justify-center w-9 h-9 bg-gray-100 text-gray-600 rounded-lg group-hover:bg-primary-50 group-hover:text-primary-600">
                    <i class='bx bxs-message-square-detail text-xl'></i>
                </span>
                <span class="ml-3">Requests</span>
            </a>
            
            <a href="approval_history.php" class="flex items-center px-4 py-3 text-gray-700 rounded-xl transition-all hover:bg-gray-50 hover:text-primary-600 group">
                <span class="flex items-center justify-center w-9 h-9 bg-gray-100 text-gray-600 rounded-lg group-hover:bg-primary-50 group-hover:text-primary-600">
                    <i class='bx bx-history text-xl'></i>
                </span>
                <span class="ml-3">Approval History</span>
            </a>

            <!-- Add a divider -->
            <div class="my-4 border-t border-gray-100"></div>
            
            <p class="px-4 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                Account
            </p>
            
            <a href="user_management.php" class="flex items-center px-4 py-3 text-gray-700 rounded-xl transition-all hover:bg-gray-50 hover:text-primary-600 group">
                <span class="flex items-center justify-center w-9 h-9 bg-gray-100 text-gray-600 rounded-lg group-hover:bg-primary-50 group-hover:text-primary-600">
                    <i class='bx bx-user text-xl'></i>
                </span>
                <span class="ml-3">User Management</span>
            </a>
            
            <a href="settings.php" class="flex items-center px-4 py-3 text-gray-700 rounded-xl transition-all hover:bg-gray-50 hover:text-primary-600 group">
                <span class="flex items-center justify-center w-9 h-9 bg-gray-100 text-gray-600 rounded-lg group-hover:bg-primary-50 group-hover:text-primary-600">
                    <i class='bx bx-cog text-xl'></i>
                </span>
                <span class="ml-3">Settings</span>
            </a>
        </nav>
        
        <!-- Logout Button -->
        <div class="p-4 border-t border-gray-100">
            <a href="logout.php" class="flex items-center px-4 py-3 text-red-600 bg-red-50 rounded-xl transition-all hover:bg-red-100 group">
                <span class="flex items-center justify-center w-9 h-9 bg-red-100 text-red-600 rounded-lg group-hover:bg-red-200">
                    <i class='bx bx-log-out text-xl group-hover:rotate-90 transition-transform duration-300'></i>
                </span>
                <span class="ml-3 font-medium">Logout</span>
            </a>
        </div>

        <!-- User Profile -->
        <div class="px-4 py-4 border-t border-gray-100">
            <div class="flex items-center space-x-3">
                <div class="flex-shrink-0">
                    <div class="w-10 h-10 rounded-full bg-primary-100 flex items-center justify-center text-primary-600">
                        <i class='bx bxs-user text-xl'></i>
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900 truncate">
                    <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?>
                </p>
                <p class="text-xs text-gray-500 truncate">
                    <?php echo htmlspecialchars($_SESSION['role'] ?? 'Role'); ?>
                </p>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- Mobile menu button (for responsive design) -->
<div class="lg:hidden fixed bottom-6 right-6 z-50">
    <button type="button" class="flex items-center justify-center w-14 h-14 rounded-full bg-primary-600 text-white shadow-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
        <i class='bx bx-menu text-2xl'></i>
    </button>
</div>

        <!-- Main Content -->
        <div class="flex-1 ml-64">
            <!-- Header -->
            <div class="bg-white border-b border-gray-200 sticky top-0 z-10 shadow-sm">
                <div class="flex justify-between items-center px-8 py-4">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">User Access Request System</h2>
                        <p class="text-gray-600 text-sm mt-1">
                            Welcome back, <?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?>
                        </p>
                    </div>
                    <div class="flex items-center gap-6">
                        <div class="relative">
                            <input type="text" 
                                   placeholder="Search..." 
                                   class="pl-10 pr-4 py-2 w-64 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                            <i class='bx bx-search absolute left-3 top-2.5 text-gray-400'></i>
                        </div>
                        <div class="relative group">
                            <button class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center hover:bg-gray-200 transition-colors">
                                <i class='bx bx-user text-xl text-gray-600'></i>
                            </button>
                            <div class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-100 invisible group-hover:visible opacity-0 group-hover:opacity-100 transition-all">
                                <div class="p-3 border-b border-gray-100">
                                    <p class="text-sm font-medium text-gray-900">Admin Account</p>
                                    <p class="text-xs text-gray-500 mt-0.5"><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></p>
                                </div>
                                <a href="logout.php" class="flex items-center px-4 py-3 text-sm text-red-600 hover:bg-red-50">
                                    <i class='bx bx-log-out mr-2'></i>
                                    Logout
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Area -->
            <div class="p-8">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <!-- Analytics Card -->
                    <a href="analytics.php" class="group relative bg-white border border-gray-200 rounded-xl shadow-sm p-6 hover:shadow-md transition-all duration-300 overflow-hidden">
                        <div class="relative z-10">
                            <div class="flex justify-between items-center mb-4">
                                <div>
                                    <h3 class="text-2xl font-bold text-gray-800 mb-1">Analytics</h3>
                                    <p class="text-gray-600">View system analytics</p>
                                </div>
                                <div class="text-primary bg-indigo-50 p-3 rounded-lg group-hover:scale-110 transition-transform">
                                    <i class='bx bx-line-chart text-2xl'></i>
                                </div>
                            </div>
                            
                            <!-- Mini Analytics Data -->
                            <div class="grid grid-cols-2 gap-2 mt-3 mb-4">
                                <div class="bg-gray-50 p-2 rounded-lg">
                                    <p class="text-xs text-gray-500">Approval Rate</p>
                                    <p class="text-lg font-bold text-primary"><?php echo $statsData['approval_rate']; ?>%</p>
                                </div>
                                <div class="bg-gray-50 p-2 rounded-lg">
                                    <p class="text-xs text-gray-500">Total Requests</p>
                                    <p class="text-lg font-bold text-gray-800"><?php echo number_format($statsData['total']); ?></p>
                                </div>
                            </div>
                            
                            <div class="flex items-center text-primary">
                                <span class="text-sm font-medium">View Details</span>
                                <i class='bx bx-right-arrow-alt ml-2 group-hover:translate-x-1 transition-transform'></i>
                            </div>
                        </div>
                        <div class="absolute inset-0 bg-gradient-to-r from-primary/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    </a>

                    <!-- Requests Card -->
                    <a href="requests.php" class="group relative bg-white border border-gray-200 rounded-xl shadow-sm p-6 hover:shadow-md transition-all duration-300 overflow-hidden">
                        <div class="relative z-10">
                            <div class="flex justify-between items-center mb-4">
                                <div>
                                    <h3 class="text-2xl font-bold text-gray-800 mb-1">Requests</h3>
                                    <p class="text-gray-600">Manage access requests</p>
                                </div>
                                <div class="text-primary bg-indigo-50 p-3 rounded-lg group-hover:scale-110 transition-transform">
                                    <i class='bx bxs-message-square-detail text-2xl'></i>
                                </div>
                            </div>
                            
                            <!-- Mini Requests Data -->
                            <?php if (!empty($recentRequests)): ?>
                            <div class="border-t border-gray-100 pt-3 mb-4">
                                <p class="text-xs font-medium text-gray-500 mb-2">Recent Requests</p>
                                <?php foreach(array_slice($recentRequests, 0, 2) as $request): ?>
                                <div class="flex items-center mb-2 text-sm">
                                    <span class="w-2 h-2 rounded-full mr-2 <?php echo $request['status'] === 'pending' ? 'bg-yellow-400' : ($request['status'] === 'approved' ? 'bg-green-400' : 'bg-red-400'); ?>"></span>
                                    <span class="truncate"><?php echo htmlspecialchars($request['requestor_name']); ?></span>
                                    <span class="ml-auto text-xs text-gray-500"><?php echo date('M d', strtotime($request['submission_date'])); ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            
                            <div class="flex items-center text-primary">
                                <span class="text-sm font-medium">View Details</span>
                                <i class='bx bx-right-arrow-alt ml-2 group-hover:translate-x-1 transition-transform'></i>
                            </div>
                        </div>
                        <div class="absolute inset-0 bg-gradient-to-r from-primary/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    </a>

                    <!-- Approval History Card -->
                    <a href="approval_history.php" class="group relative bg-white border border-gray-200 rounded-xl shadow-sm p-6 hover:shadow-md transition-all duration-300 overflow-hidden">
                        <div class="relative z-10">
                            <div class="flex justify-between items-center mb-4">
                                <div>
                                    <h3 class="text-2xl font-bold text-gray-800 mb-1">Approval History</h3>
                                    <p class="text-gray-600">View past approvals</p>
                                </div>
                                <div class="text-primary bg-indigo-50 p-3 rounded-lg group-hover:scale-110 transition-transform">
                                    <i class='bx bx-history text-2xl'></i>
                                </div>
                            </div>
                            
                            <!-- Mini Approval History Data -->
                            <?php if (!empty($recentApprovals)): ?>
                            <div class="border-t border-gray-100 pt-3 mb-4">
                                <p class="text-xs font-medium text-gray-500 mb-2">Recent Activity</p>
                                <?php foreach(array_slice($recentApprovals, 0, 2) as $approval): ?>
                                <div class="flex items-center mb-2 text-sm">
                                    <span class="w-2 h-2 rounded-full mr-2 <?php echo $approval['action'] === 'approved' ? 'bg-green-400' : 'bg-red-400'; ?>"></span>
                                    <span class="truncate"><?php echo htmlspecialchars($approval['requestor_name']); ?></span>
                                    <span class="ml-auto text-xs text-gray-500"><?php echo date('M d', strtotime($approval['created_at'])); ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            
                            <div class="flex items-center text-primary">
                                <span class="text-sm font-medium">View Details</span>
                                <i class='bx bx-right-arrow-alt ml-2 group-hover:translate-x-1 transition-transform'></i>
                            </div>
                        </div>
                        <div class="absolute inset-0 bg-gradient-to-r from-primary/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    </a>
                </div>

                <!-- Quick Stats Section -->
                <div class="mt-8">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Quick Statistics</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <!-- Pending Requests -->
                        <div class="bg-white rounded-xl border border-gray-200 p-6">
                            <div class="flex items-center">
                                <div class="bg-yellow-50 p-3 rounded-lg">
                                    <i class='bx bx-time text-2xl text-yellow-500'></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm text-gray-500">Pending Requests</p>
                                    <h4 class="text-2xl font-bold text-gray-900"><?php echo $pendingRequests; ?></h4>
                                </div>
                            </div>
                        </div>

                        <!-- Approved Today -->
                        <div class="bg-white rounded-xl border border-gray-200 p-6">
                            <div class="flex items-center">
                                <div class="bg-green-50 p-3 rounded-lg">
                                    <i class='bx bx-check-circle text-2xl text-green-500'></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm text-gray-500">Approved Today</p>
                                    <h4 class="text-2xl font-bold text-gray-900"><?php echo $approvedToday; ?></h4>
                                </div>
                            </div>
                        </div>

                        <!-- Rejected Today -->
                        <div class="bg-white rounded-xl border border-gray-200 p-6">
                            <div class="flex items-center">
                                <div class="bg-red-50 p-3 rounded-lg">
                                    <i class='bx bx-x-circle text-2xl text-red-500'></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm text-gray-500">Rejected Today</p>
                                    <h4 class="text-2xl font-bold text-gray-900"><?php echo $rejectedToday; ?></h4>
                                </div>
                            </div>
                        </div>

                        <!-- Total Requests -->
                        <div class="bg-white rounded-xl border border-gray-200 p-6">
                            <div class="flex items-center">
                                <div class="bg-blue-50 p-3 rounded-lg">
                                    <i class='bx bx-folder text-2xl text-blue-500'></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm text-gray-500">Total Requests</p>
                                    <h4 class="text-2xl font-bold text-gray-900"><?php echo number_format($statsData['total']); ?></h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Additional Data Section -->
                <div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Recent Requests -->
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                        <div class="px-6 py-4 border-b border-gray-100">
                            <div class="flex justify-between items-center">
                                <h3 class="text-lg font-semibold text-gray-800">Recent Requests</h3>
                                <a href="requests.php" class="text-sm text-primary hover:text-primary-dark flex items-center">
                                    View All
                                    <i class='bx bx-right-arrow-alt ml-1'></i>
                                </a>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Requestor</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Business Unit</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (!empty($recentRequests)): ?>
                                        <?php foreach($recentRequests as $request): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($request['requestor_name']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($request['business_unit']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($request['access_type']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                    <?php echo $request['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 
                                                        ($request['status'] === 'approved' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'); ?>">
                                                    <?php echo ucfirst($request['status']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('M d, Y', strtotime($request['submission_date'])); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">No recent requests found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Access Type Distribution -->
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                        <div class="px-6 py-4 border-b border-gray-100">
                            <div class="flex justify-between items-center">
                                <h3 class="text-lg font-semibold text-gray-800">Access Type Distribution</h3>
                                <a href="analytics.php" class="text-sm text-primary hover:text-primary-dark flex items-center">
                                    View Analytics
                                    <i class='bx bx-right-arrow-alt ml-1'></i>
                                </a>
                            </div>
                        </div>
                        <div class="p-6">
                            <?php if (!empty($accessTypeDistribution)): ?>
                                <div class="h-64">
                                    <canvas id="accessTypeChart"></canvas>
                                </div>
                                <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                                <script>
                                    // Access Type Distribution Chart
                                    document.addEventListener('DOMContentLoaded', function() {
                                        const ctx = document.getElementById('accessTypeChart').getContext('2d');
                                        
                                        // Common colors
                                        const colors = [
                                            'rgb(0, 132, 255)',   // Facebook Messenger Blue
                                            'rgb(34, 197, 94)',   // Green
                                            'rgb(249, 115, 22)',  // Orange
                                            'rgb(139, 92, 246)'   // Purple
                                        ];
                                        
                                        new Chart(ctx, {
                                            type: 'doughnut',
                                            data: {
                                                labels: [
                                                    <?php foreach($accessTypeDistribution as $item): ?>
                                                    "<?php echo addslashes($item['access_type']); ?>",
                                                    <?php endforeach; ?>
                                                ],
                                                datasets: [{
                                                    data: [
                                                        <?php foreach($accessTypeDistribution as $item): ?>
                                                        <?php echo $item['count']; ?>,
                                                        <?php endforeach; ?>
                                                    ],
                                                    backgroundColor: colors,
                                                    borderWidth: 1
                                                }]
                                            },
                                            options: {
                                                responsive: true,
                                                maintainAspectRatio: false,
                                                plugins: {
                                                    legend: {
                                                        position: 'bottom',
                                                        labels: {
                                                            padding: 20,
                                                            usePointStyle: true
                                                        }
                                                    },
                                                    tooltip: {
                                                        callbacks: {
                                                            label: function(context) {
                                                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                                                const value = context.raw;
                                                                const percentage = ((value / total) * 100).toFixed(1);
                                                                return `${context.label}: ${value} (${percentage}%)`;
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        });
                                    });
                                </script>
                            <?php else: ?>
                                <div class="flex items-center justify-center h-64">
                                    <p class="text-gray-500">No data available</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php
session_start();
require_once '../config.php';
require_once '../admin/analytics_functions.php';

// Authentication check
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'help_desk') {
    header("Location: ../admin/login.php");
    exit();
}

// Get quick stats for the dashboard
try {
    // Get pending requests count
    $stmt = $pdo->query("SELECT COUNT(*) FROM access_requests WHERE status IN ('pending_help_desk', 'pending_technical', 'pending_testing_setup', 'pending_testing_review')");
    $pendingRequests = $stmt->fetchColumn();
    
    // Get today's technical reviews count
    $todayDate = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM approval_history WHERE action = 'technical_review' AND DATE(created_at) = :today");
    $stmt->execute([':today' => $todayDate]);
    $technicalReviewsToday = $stmt->fetchColumn();
    
    // Get pending reviews count
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM access_requests WHERE status = 'pending_help_desk'");
    $stmt->execute();
    $pendingCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Get reviews completed today
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM access_requests 
        WHERE help_desk_id = ? 
        AND DATE(help_desk_review_date) = CURDATE()
    ");
    $stmt->execute([$_SESSION['admin_id']]);
    $reviewsToday = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get recent requests
    $stmt = $pdo->prepare("
        SELECT ar.*, 
        CASE 
            WHEN ar.status = 'pending_help_desk' THEN 'Pending Your Review'
            WHEN ar.status = 'pending_technical' THEN 'Forwarded to Technical'
            WHEN ar.status = 'pending_process_owner' THEN 'Forwarded to Process Owner'
            WHEN ar.status = 'approved' THEN 'Approved'
            WHEN ar.status = 'rejected' THEN 'Rejected'
            ELSE ar.status
        END as status_display
        FROM access_requests ar 
        WHERE ar.status = 'pending_help_desk'
        ORDER BY ar.submission_date DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $recentRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Dashboard data fetch error: " . $e->getMessage());
    $pendingRequests = 0;
    $technicalReviewsToday = 0;
    $pendingCount = 0;
    $reviewsToday = 0;
    $recentRequests = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help Desk Dashboard - UAR System</title>

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
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-100">
    <div class="flex min-h-screen">
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
                    
                    <a href="#" class="flex items-center px-4 py-3 text-primary-600 bg-primary-50 rounded-xl">
                        <span class="flex items-center justify-center w-9 h-9 bg-primary-100 text-primary-600 rounded-lg">
                            <i class='bx bxs-dashboard text-xl'></i>
                        </span>
                        <span class="ml-3 font-medium">Dashboard</span>
                    </a>
                    
                    <a href="requests.php" class="flex items-center px-4 py-3 text-gray-700 rounded-xl hover:bg-gray-50">
                        <span class="flex items-center justify-center w-9 h-9 bg-gray-100 text-gray-600 rounded-lg">
                            <i class='bx bxs-message-square-detail text-xl'></i>
                        </span>
                        <span class="ml-3">Help Desk Reviews</span>
                    </a>
                    
                    <a href="review_history.php" class="flex items-center px-4 py-3 text-gray-700 rounded-xl hover:bg-gray-50">
                        <span class="flex items-center justify-center w-9 h-9 bg-gray-100 text-gray-600 rounded-lg">
                            <i class='bx bx-history text-xl'></i>
                        </span>
                        <span class="ml-3">Review History</span>
                    </a>
                    
                    <a href="user_management.php" class="flex items-center px-4 py-3 text-gray-700 rounded-xl transition-all hover:bg-gray-50 hover:text-primary-600 group">
                        <span class="flex items-center justify-center w-9 h-9 bg-gray-100 text-gray-600 rounded-lg group-hover:bg-primary-50 group-hover:text-primary-600">
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
                    <a href="../admin/logout.php" class="flex items-center px-4 py-3 text-red-600 bg-red-50 rounded-xl hover:bg-red-100">
                        <span class="flex items-center justify-center w-9 h-9 bg-red-100 text-red-600 rounded-lg">
                            <i class='bx bx-log-out text-xl'></i>
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
                                <?php echo htmlspecialchars($_SESSION['admin_username']); ?>
                            </p>
                            <p class="text-xs text-gray-500 truncate">
                                Help Desk
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 ml-72">
            <!-- Header -->
            <div class="bg-white border-b border-gray-200 sticky top-0 z-10">
                <div class="px-8 py-4">
                    <h1 class="text-2xl font-bold text-gray-800">Help Desk Dashboard</h1>
                    <p class="text-gray-600 mt-1">Welcome back, <?php echo htmlspecialchars($_SESSION['admin_username']); ?></p>
                </div>
            </div>

            <!-- Content -->
            <div class="p-8">
                <!-- Quick Stats -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div class="bg-white rounded-xl shadow p-6">
                        <div class="flex items-center">
                            <div class="bg-primary-100 p-3 rounded-lg">
                                <i class='bx bx-time text-2xl text-primary-600'></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm text-gray-500">Pending Reviews</p>
                                <h4 class="text-2xl font-bold text-gray-900"><?php echo $pendingRequests; ?></h4>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow p-6">
                        <div class="flex items-center">
                            <div class="bg-green-100 p-3 rounded-lg">
                                <i class='bx bx-check-circle text-2xl text-green-600'></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm text-gray-500">Reviews Today</p>
                                <h4 class="text-2xl font-bold text-gray-900"><?php echo $technicalReviewsToday; ?></h4>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Requests Table -->
                <div class="bg-white rounded-xl shadow">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-gray-800">Recent Requests for Technical Review</h3>
                            <a href="requests.php" class="text-primary-600 hover:text-primary-700 text-sm font-medium">View All</a>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">UAR REF NO.</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Requestor</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Business Unit</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Requested</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Days Pending</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Needed</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (!empty($recentRequests)): ?>
                                    <?php foreach($recentRequests as $request): ?>
                                    <tr class="hover:bg-gray-50 cursor-pointer" onclick="showRequestDetails(<?php echo $request['id']; ?>)">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($request['access_request_number']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($request['requestor_name']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($request['business_unit']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($request['department']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo date('M d, Y', strtotime($request['submission_date'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php 
                                                $submission_date = new DateTime($request['submission_date']);
                                                $today = new DateTime();
                                                $interval = $submission_date->diff($today);
                                                echo $interval->days . ' day/s';
                                            ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" data-label="Date Needed">
                                            <?php echo date('M d, Y', strtotime($request['date_needed'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                <?php echo htmlspecialchars($request['status_display']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="px-6 py-4 text-center text-sm text-gray-500">
                                            No recent requests found
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
</body>
</html> 
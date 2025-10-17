<?php
session_start();
require_once '../config.php';
require_once '../admin/analytics_functions.php';

// Authentication check
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'superior') {
    header("Location: ../admin/login.php");
    exit();
}

// Get quick stats for the dashboard
try {
    // Get analytics data
    $statsData = getDashboardStats($pdo);

    // Get pending requests count
    $stmt = $pdo->query("SELECT COUNT(*) FROM uar.access_requests WHERE status = 'pending_superior'");
    $pendingRequests = $stmt->fetchColumn();

    // Get today's approvals count
    $todayDate = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM uar.approval_history WHERE action = 'approved' AND CAST(created_at AS DATE) = :today");
    $stmt->execute([':today' => $todayDate]);
    $approvedToday = $stmt->fetchColumn();

    // Get today's rejections count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM uar.approval_history WHERE action = 'rejected' AND CAST(created_at AS DATE) = :today");
    $stmt->execute([':today' => $todayDate]);
    $rejectedToday = $stmt->fetchColumn();

    // Get recent requests with date_needed from individual_requests and group_requests
    $stmt = $pdo->query("SELECT TOP 5 ar.*, 
            CASE 
                WHEN ar.status = 'pending_superior' THEN 'Pending Your Review'
                WHEN ar.status = 'pending_help_desk' THEN 'Pending Help Desk Review'
                WHEN ar.status = 'pending_technical' THEN 'Pending Technical Review'
                WHEN ar.status = 'pending_process_owner' THEN 'Pending Process Owner Review'
                WHEN ar.status = 'pending_admin' THEN 'Pending Admin Review'
                WHEN ar.status = 'approved' THEN 'Approved'
                WHEN ar.status = 'rejected' THEN 'Rejected'
                ELSE ar.status
            END as status_display,
            (SELECT ISNULL(
                (SELECT TOP 1 date_needed FROM uar.individual_requests WHERE access_request_number = ar.access_request_number),
                (SELECT TOP 1 date_needed FROM uar.group_requests WHERE access_request_number = ar.access_request_number)
            )) as date_needed
            FROM uar.access_requests ar 
            WHERE ar.status = 'pending_superior' 
            ORDER BY ar.submission_date DESC");
    $recentRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recent approval history
    $stmt = $pdo->query("SELECT TOP 5 h.*, a.username as admin_username 
                        FROM uar.approval_history h 
                        LEFT JOIN uar.admin_users a ON h.admin_id = a.id 
                        ORDER BY h.created_at DESC");
    $recentApprovals = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Superior Dashboard - UAR System</title>

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
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 ml-72">
            <!-- Header -->
            <div class="bg-blue-900 border-b border-gray-200 sticky top-0 z-10">
                <div class="px-8 py-4">
                    <h1 class="text-4xl font-bold text-white">Superior Dashboard</h1>

                </div>
            </div>

            <!-- Content -->
            <div class="p-8">
                <!-- Quick Stats -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="stat-card rounded-xl p-6 flex items-center bg-gradient-to-br from-blue-500 via-blue-400 to-blue-300 cursor-pointer hover:shadow-lg transition-all duration-300">
                        <div class="flex items-center">
                            <div class="bg-gradient-to-br from-blue-500 via-white to-blue-300 p-3 rounded-full shadow-lg">
                                <i class='bx bx-time text-2xl text-primary-600'></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-lg font-bold text-white">Pending Requests</p>
                                <h4 class="text-2xl font-bold text-white"><?php echo $pendingRequests; ?></h4>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card rounded-xl p-6 flex items-center bg-gradient-to-br from-green-500 via-green-400 to-green-300 cursor-pointer hover:shadow-lg transition-all duration-300">
                        <div class="flex items-center">
                            <div class="bg-gradient-to-br from-green-500 via-white to-green-300 p-3 rounded-full shadow-lg">
                                <i class='bx bx-check-circle text-2xl text-green-600'></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-lg font-bold text-white">Approved Today</p>
                                <h4 class="text-2xl font-bold text-white"><?php echo $approvedToday; ?></h4>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card rounded-xl p-6 flex items-center bg-gradient-to-br from-red-500 via-red-400 to-red-300 cursor-pointer hover:shadow-lg transition-all duration-300">
                        <div class="flex items-center">
                            <div class="bg-gradient-to-br from-red-500 via-white to-red-300 p-3 rounded-full shadow-lg">
                                <i class='bx bx-x-circle text-2xl text-red-600'></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-lg font-bold text-white">Rejected Today</p>
                                <h4 class="text-2xl font-bold text-white"><?php echo $rejectedToday; ?></h4>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Requests Table -->
                <div class="bg-white rounded-xl shadow">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-gray-800">Recent Requests</h3>

                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">UAR REF NO.</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Requestor</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Requested</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Days Pending</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Needed</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (!empty($recentRequests)): ?>
                                    <?php foreach ($recentRequests as $request): ?>
                                        <tr class="hover:bg-gray-50 cursor-pointer" onclick="showRequestDetails(<?php echo $request['id']; ?>)">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($request['access_request_number']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($request['requestor_name']); ?>
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
                                        <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">
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

    <script>
        function showRequestDetails(requestId) {
            // Redirect to requests page with the request ID and show_modal parameter
            window.location.href = `requests.php?id=${requestId}&show_modal=true`;
        }
    </script>
</body>

</html>
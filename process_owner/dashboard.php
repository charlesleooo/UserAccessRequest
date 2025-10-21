<?php
session_start();
require_once '../config.php';
require_once '../admin/analytics_functions.php';

// Authentication check
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'process_owner') {
    header("Location: ../admin/login.php");
    exit();
}

// Get quick stats for the dashboard
try {
    // Get pending requests count
    $stmt = $pdo->query("SELECT COUNT(*) FROM uar.access_requests WHERE status = 'pending_process_owner'");
    $pendingRequests = $stmt->fetchColumn();

    // Get today's process reviews count
    $todayDate = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM uar.approval_history WHERE action = 'process_review' AND CAST(created_at AS DATE) = :today");
    $stmt->execute([':today' => $todayDate]);
    $processReviewsToday = $stmt->fetchColumn();

    // Get recent requests (using DISTINCT to avoid duplicates)
    $stmt = $pdo->prepare("
        SELECT DISTINCT ar.*, 
            CASE 
                WHEN ar.status = 'pending_superior' THEN 'Pending Superior Review'
                WHEN ar.status = 'pending_help_desk' THEN 'Pending Help Desk Review'
                WHEN ar.status = 'pending_technical' THEN 'Pending Technical Review'
                WHEN ar.status = 'pending_process_owner' THEN 'Pending Your Review'
                WHEN ar.status = 'pending_admin' THEN 'Pending Admin Review'
                WHEN ar.status = 'approved' THEN 'Approved'
                WHEN ar.status = 'rejected' THEN 'Rejected'
                ELSE ar.status
            END as status_display,
            ISNULL(ir.date_needed, gr.date_needed) as date_needed
        FROM uar.access_requests ar 
        LEFT JOIN uar.individual_requests ir ON ar.access_request_number = ir.access_request_number
        LEFT JOIN uar.group_requests gr ON ar.access_request_number = gr.access_request_number
        WHERE ar.status = 'pending_process_owner' 
        ORDER BY ar.submission_date DESC
    ");
    $stmt->execute();
    $recentRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Dashboard data fetch error: " . $e->getMessage());
    $pendingRequests = 0;
    $processReviewsToday = 0;
    $recentRequests = [];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Process Owner Dashboard - UAR System</title>

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
                        },
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
            <div class="bg-primary-900 border-b border-gray-200 sticky top-0 z-10">
                <div class="px-8 py-4">
                    <h1 class="text-2xl font-bold text-white">Process Owner Dashboard</h1>
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
                                <h4 class="text-2xl font-bold text-gray-900"><?php echo $processReviewsToday; ?></h4>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Requests Table -->
                <div class="bg-white rounded-xl shadow">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-gray-800">Recent Requests for Process Review</h3>
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
                                    <?php foreach ($recentRequests as $request): ?>
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
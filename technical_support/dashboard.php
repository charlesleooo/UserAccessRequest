<?php
session_start();
require_once '../config.php';

// Authentication check
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'technical_support') {
    header("Location: ../admin/login.php");
    exit();
}

// Get quick stats for the dashboard
try {
    // Get pending requests count
    $stmt = $pdo->query("SELECT COUNT(*) FROM uar.access_requests WHERE status IN ('pending_technical', 'pending_testing_setup', 'pending_testing_review')");
    $pendingRequests = $stmt->fetchColumn();

    // Get today's technical reviews count
    $todayDate = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM uar.approval_history WHERE action = 'technical_review' AND CAST(created_at AS DATE) = :today");
    $stmt->execute([':today' => $todayDate]);
    $technicalReviewsToday = $stmt->fetchColumn();

    // Get today's approved requests count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM uar.approval_history WHERE action = 'approved' AND CAST(created_at AS DATE) = :today");
    $stmt->execute([':today' => $todayDate]);
    $approvedToday = $stmt->fetchColumn();

    // Get today's rejected requests count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM uar.approval_history WHERE action = 'rejected' AND CAST(created_at AS DATE) = :today");
    $stmt->execute([':today' => $todayDate]);
    $rejectedToday = $stmt->fetchColumn();

    // Get recent requests with date_needed from individual_requests and group_requests
    $stmt = $pdo->query("SELECT DISTINCT ar.*, 
            CASE 
                WHEN ar.status = 'pending_superior' THEN 'Pending Superior Review'
                WHEN ar.status = 'pending_help_desk' THEN 'Pending Your Review'
                WHEN ar.status = 'pending_technical' THEN 'Pending Technical Review'
                WHEN ar.status = 'pending_process_owner' THEN 'Pending Process Owner Review'
                WHEN ar.status = 'pending_admin' THEN 'Pending Admin Review'
                WHEN ar.status = 'approved' THEN 'Approved'
                WHEN ar.status = 'rejected' THEN 'Rejected'
                ELSE ar.status
            END as status_display,
            ISNULL(ir.date_needed, gr.date_needed) as date_needed
            FROM uar.access_requests ar 
            LEFT JOIN uar.individual_requests ir ON ar.access_request_number = ir.access_request_number
            LEFT JOIN uar.group_requests gr ON ar.access_request_number = gr.access_request_number
            WHERE ar.status IN ('pending_technical', 'pending_testing_setup')
            ORDER BY ar.submission_date DESC");
    $recentRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Dashboard data fetch error: " . $e->getMessage());
    $pendingRequests = 0;
    $technicalReviewsToday = 0;
    $approvedToday = 0;
    $rejectedToday = 0;
    $recentRequests = [];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>

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
                    <h1 class="text-2xl font-bold text-white">Dashboard</h1>
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
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Company</th>
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

    <script>
        function showRequestDetails(requestId) {
            // Redirect to requests page with the request ID and show_modal parameter
            window.location.href = `requests.php?id=${requestId}&show_modal=true`;
        }
    </script>
</body>
<?php include '../footer.php'; ?>

</html>
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
    $stmt = $pdo->query("SELECT COUNT(*) FROM uar.access_requests WHERE status IN ('pending_help_desk', 'pending_technical', 'pending_testing_setup', 'pending_testing_review')");
    $pendingRequests = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM uar.access_requests WHERE status = 'pending_help_desk'");
    $stmt->execute();
    $yourPending = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM uar.access_requests
        WHERE help_desk_id = ?
        AND CAST(help_desk_review_date AS DATE) = CAST(GETDATE() AS DATE)
    ");
    $stmt->execute([$_SESSION['admin_id']]);
    $reviewsToday = (int)$stmt->fetchColumn();

    $filters = [];
    $analyticsData = getAnalyticsData($pdo, $filters);
    $statsData = getDashboardStats($pdo, $filters);
    $totalRequestsOverall = (int)($statsData['total'] ?? 0);

    // Today approvals and rejections (finalized decisions)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM uar.approval_history WHERE action = 'approved' AND CAST(created_at AS DATE) = CAST(GETDATE() AS DATE)");
    $stmt->execute();
    $approvedToday = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM uar.approval_history WHERE action = 'rejected' AND CAST(created_at AS DATE) = CAST(GETDATE() AS DATE)");
    $stmt->execute();
    $rejectedToday = (int)$stmt->fetchColumn();

    // Recent activity from approval history
    $stmt = $pdo->query("SELECT TOP 1 requestor_name, created_at FROM uar.approval_history ORDER BY created_at DESC");
    $recentActivity = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    $stmt = $pdo->prepare("
        SELECT TOP 10 ar.*,
        CASE 
            WHEN ar.status = 'pending_help_desk' THEN 'Pending Your Review'
            WHEN ar.status = 'pending_technical' THEN 'Pending Technical Review'
            WHEN ar.status = 'pending_testing_setup' THEN 'Pending Testing Setup'
            WHEN ar.status = 'pending_testing_review' THEN 'Pending Testing Review'
            WHEN ar.status = 'pending_process_owner' THEN 'Pending Process Owner Review'
            WHEN ar.status = 'approved' THEN 'Approved'
            WHEN ar.status = 'rejected' THEN 'Rejected'
            ELSE ar.status
        END as status_display,
        (SELECT COALESCE(
            (SELECT TOP 1 date_needed FROM uar.individual_requests WHERE access_request_number = ar.access_request_number),
            (SELECT TOP 1 date_needed FROM uar.group_requests WHERE access_request_number = ar.access_request_number)
        )) as date_needed
        FROM uar.access_requests ar 
        WHERE ar.status IN ('pending_help_desk','pending_technical','pending_testing_setup','pending_testing_review')
        ORDER BY ar.submission_date DESC
    ");
    $stmt->execute();
    $recentRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Dashboard data fetch error: " . $e->getMessage());
    $pendingRequests = 0;
    $yourPending = 0;
    $reviewsToday = 0;
    $recentRequests = [];
    $analyticsData = ['accessTypeDistribution' => [], 'businessUnitAnalysis' => []];
    $statsData = ['total' => 0, 'approved' => 0, 'approval_rate' => 0, 'decline_rate' => 0];
    $totalRequestsOverall = 0;
    $approvedToday = 0;
    $rejectedToday = 0;
    $recentActivity = null;
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
        <div class="flex-1 lg:ml-72">
            <!-- Header -->
            <div class="bg-blue-900 border-b border-gray-200 sticky top-0 z-10">
                <div class="px-4 md:px-8 py-4">
                    <h1 class="text-xl md:text-2xl font-bold text-white">Dashboard</h1>
                </div>
            </div>

            <!-- Content -->
            <div class="p-4 md:p-8 space-y-6">
                <!-- Stats Overview -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <!-- Total Requests Card -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Total Requests</p>
                                <p class="text-3xl font-bold text-gray-900 mt-2"><?php echo number_format($totalRequestsOverall); ?></p>
                            </div>
                            <div class="bg-blue-100 rounded-lg p-3">
                                <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                </svg>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-4">All-time submissions</p>
                    </div>

                    <!-- Pending Requests Card -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Pending Review</p>
                                <p class="text-3xl font-bold text-yellow-600 mt-2"><?php echo $pendingRequests; ?></p>
                            </div>
                            <div class="bg-yellow-100 rounded-lg p-3">
                                <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-4">Awaiting action</p>
                    </div>

                    <!-- Approved Today Card -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Approved Today</p>
                                <p class="text-3xl font-bold text-green-600 mt-2"><?php echo $approvedToday; ?></p>
                            </div>
                            <div class="bg-green-100 rounded-lg p-3">
                                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-4">Final approvals</p>
                    </div>

                    <!-- Rejected Today Card -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Rejected Today</p>
                                <p class="text-3xl font-bold text-red-600 mt-2"><?php echo $rejectedToday; ?></p>
                            </div>
                            <div class="bg-red-100 rounded-lg p-3">
                                <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-4">Final rejections</p>
                    </div>
                </div>

                <!-- Quick Action Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Analytics Card -->
                    <a href="analytics.php" class="block group">
                        <div class="bg-gradient-to-br from-purple-500 to-purple-700 rounded-lg shadow-sm hover:shadow-md transition-all p-6 text-white">
                            <div class="flex items-center justify-between mb-4">
                                <div class="bg-white/20 rounded-lg p-3">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                    </svg>
                                </div>
                                <svg class="w-5 h-5 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </div>
                            <h3 class="text-lg font-semibold mb-1">Analytics</h3>
                            <p class="text-sm text-purple-100">View detailed insights & reports</p>
                            <div class="mt-4 pt-4 border-t border-white/20">
                                <p class="text-2xl font-bold"><?php echo $statsData['approval_rate']; ?>%</p>
                                <p class="text-xs text-purple-100">Approval Rate</p>
                            </div>
                        </div>
                    </a>

                    <!-- Requests Card -->
                    <a href="requests.php" class="block group">
                        <div class="bg-gradient-to-br from-blue-500 to-blue-700 rounded-lg shadow-sm hover:shadow-md transition-all p-6 text-white">
                            <div class="flex items-center justify-between mb-4">
                                <div class="bg-white/20 rounded-lg p-3">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                                    </svg>
                                </div>
                                <svg class="w-5 h-5 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </div>
                            <h3 class="text-lg font-semibold mb-1">Manage Requests</h3>
                            <p class="text-sm text-blue-100">Review & process submissions</p>
                            <div class="mt-4 pt-4 border-t border-white/20">
                                <p class="text-2xl font-bold"><?php echo $yourPending; ?></p>
                                <p class="text-xs text-blue-100">Your Queue</p>
                            </div>
                        </div>
                    </a>

                    <!-- History Card -->
                    <a href="review_history.php" class="block group">
                        <div class="bg-gradient-to-br from-indigo-500 to-indigo-700 rounded-lg shadow-sm hover:shadow-md transition-all p-6 text-white">
                            <div class="flex items-center justify-between mb-4">
                                <div class="bg-white/20 rounded-lg p-3">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <svg class="w-5 h-5 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </div>
                            <h3 class="text-lg font-semibold mb-1">Review History</h3>
                            <p class="text-sm text-indigo-100">Past approval decisions</p>
                            <div class="mt-4 pt-4 border-t border-white/20">
                                <?php if ($recentActivity): ?>
                                    <p class="text-sm truncate font-medium"><?php echo htmlspecialchars($recentActivity['requestor_name']); ?></p>
                                    <p class="text-xs text-indigo-100">Latest: <?php echo date('M d, Y', strtotime($recentActivity['created_at'])); ?></p>
                                <?php else: ?>
                                    <p class="text-sm">No recent activity</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Analytics Overview -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Request Trends Chart -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">Request Trends</h3>
                            <a href="analytics.php" class="text-sm text-blue-600 hover:text-blue-700 font-medium">View Details →</a>
                        </div>
                        <canvas id="requestTrendsChart" height="80"></canvas>
                    </div>

                    <!-- System Type Distribution -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">System Distribution</h3>
                            <a href="analytics.php" class="text-sm text-blue-600 hover:text-blue-700 font-medium">View Details →</a>
                        </div>
                        <canvas id="systemDistributionChart" height="80"></canvas>
                    </div>
                </div>

                <!-- Department Overview -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Top Departments by Requests</h3>
                        <a href="analytics.php" class="text-sm text-blue-600 hover:text-blue-700 font-medium">View All →</a>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <?php
                        // Get top 3 departments
                        $deptQuery = "SELECT TOP 3 department, COUNT(*) as count 
                                     FROM uar.access_requests 
                                     WHERE department IS NOT NULL 
                                     GROUP BY department 
                                     ORDER BY count DESC";
                        $deptStmt = $pdo->query($deptQuery);
                        $topDepartments = $deptStmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (!empty($topDepartments)) {
                            foreach ($topDepartments as $index => $dept) {
                                $colors = [
                                    ['bg' => 'bg-blue-100', 'text' => 'text-blue-600', 'border' => 'border-blue-200'],
                                    ['bg' => 'bg-purple-100', 'text' => 'text-purple-600', 'border' => 'border-purple-200'],
                                    ['bg' => 'bg-indigo-100', 'text' => 'text-indigo-600', 'border' => 'border-indigo-200']
                                ];
                                $color = $colors[$index];
                        ?>
                            <div class="border <?php echo $color['border']; ?> rounded-lg p-4 <?php echo $color['bg']; ?>">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1">
                                        <p class="text-sm font-medium <?php echo $color['text']; ?> truncate"><?php echo htmlspecialchars($dept['department']); ?></p>
                                        <p class="text-2xl font-bold <?php echo $color['text']; ?> mt-1"><?php echo number_format($dept['count']); ?></p>
                                        <p class="text-xs text-gray-600 mt-1">Requests</p>
                                    </div>
                                    <div class="<?php echo $color['bg']; ?> p-2 rounded-lg">
                                        <svg class="w-6 h-6 <?php echo $color['text']; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        <?php
                            }
                        } else {
                            echo '<div class="col-span-3 text-center text-gray-500 py-4">No department data available</div>';
                        }
                        ?>
                    </div>
                </div>

                <!-- Recent Requests Table -->
                <div class="bg-white rounded-xl shadow">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-gray-800">Recent Pending Requests</h3>
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
                                        <tr class="hover:bg-gray-50 cursor-pointer" onclick="window.location='view_request.php?id=<?php echo $request['id']; ?>'">
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
                                                $submission_date->setTime(0, 0, 0);
                                                $today->setTime(0, 0, 0);
                                                $days_diff = $today->diff($submission_date)->days;
                                                echo $days_diff . ' days';
                                                ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" data-label="Date Needed">
                                                <?php echo !empty($request['date_needed']) ? date('M d, Y', strtotime($request['date_needed'])) : 'N/A'; ?>
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
        // Request Trends Chart - Last 7 days
        <?php
        $trendQuery = "SELECT 
                        CONVERT(varchar, submission_date, 23) as date,
                        COUNT(*) as count
                      FROM uar.access_requests
                      WHERE submission_date >= DATEADD(day, -7, GETDATE())
                      GROUP BY CONVERT(varchar, submission_date, 23)
                      ORDER BY date";
        $trendStmt = $pdo->query($trendQuery);
        $trendData = $trendStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $trendLabels = [];
        $trendCounts = [];
        foreach ($trendData as $row) {
            $trendLabels[] = date('M d', strtotime($row['date']));
            $trendCounts[] = (int)$row['count'];
        }
        ?>
        
        const trendCtx = document.getElementById('requestTrendsChart').getContext('2d');
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($trendLabels); ?>,
                datasets: [{
                    label: 'Requests',
                    data: <?php echo json_encode($trendCounts); ?>,
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: 'rgb(59, 130, 246)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        cornerRadius: 8
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // System Distribution Chart - Split comma-separated values
        <?php
        $systemQuery = "SELECT system_type FROM uar.access_requests WHERE system_type IS NOT NULL";
        $systemStmt = $pdo->query($systemQuery);
        $allSystemTypes = $systemStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $systemCounts = [];
        foreach ($allSystemTypes as $row) {
            // Split by comma and trim each value
            $systems = array_map('trim', explode(',', $row['system_type']));
            foreach ($systems as $system) {
                if (!empty($system)) {
                    if (!isset($systemCounts[$system])) {
                        $systemCounts[$system] = 0;
                    }
                    $systemCounts[$system]++;
                }
            }
        }
        
        // Sort by count and get top 5
        arsort($systemCounts);
        $systemCounts = array_slice($systemCounts, 0, 5, true);
        
        $systemLabels = array_keys($systemCounts);
        $systemValues = array_values($systemCounts);
        ?>
        
        const systemCtx = document.getElementById('systemDistributionChart').getContext('2d');
        new Chart(systemCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($systemLabels); ?>,
                datasets: [{
                    data: <?php echo json_encode($systemValues); ?>,
                    backgroundColor: [
                        'rgba(59, 130, 246, 0.8)',   // Blue
                        'rgba(16, 185, 129, 0.8)',   // Green
                        'rgba(249, 115, 22, 0.8)',   // Orange
                        'rgba(139, 92, 246, 0.8)',   // Purple
                        'rgba(236, 72, 153, 0.8)'    // Pink
                    ],
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            padding: 15,
                            font: {
                                size: 12
                            },
                            generateLabels: function(chart) {
                                const data = chart.data;
                                if (data.labels.length && data.datasets.length) {
                                    return data.labels.map((label, i) => {
                                        const value = data.datasets[0].data[i];
                                        return {
                                            text: `${label} (${value})`,
                                            fillStyle: data.datasets[0].backgroundColor[i],
                                            hidden: false,
                                            index: i
                                        };
                                    });
                                }
                                return [];
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        cornerRadius: 8,
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    </script>
    
</body>
<?php include '../footer.php'; ?>
</html>
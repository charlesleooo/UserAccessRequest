<?php
session_start();
require_once '../config.php';

// Authentication check
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'uar_admin') {
    header("Location: login.php");
    exit();
}

// Fetch analytics data
try {
    // Total users by role
    $roleStmt = $pdo->query("
        SELECT role, COUNT(*) as count 
        FROM uar.employees 
        WHERE role IS NOT NULL 
        GROUP BY role
    ");
    $roleData = $roleStmt->fetchAll(PDO::FETCH_ASSOC);

    // Total users by company
    $companyStmt = $pdo->query("
        SELECT company, COUNT(*) as count 
        FROM uar.employees 
        GROUP BY company 
        ORDER BY count DESC
    ");
    $companyData = $companyStmt->fetchAll(PDO::FETCH_ASSOC);

    // Total users by department
    $deptStmt = $pdo->query("
        SELECT TOP 10 department, COUNT(*) as count 
        FROM uar.employees 
        GROUP BY department 
        ORDER BY count DESC
    ");
    $departmentData = $deptStmt->fetchAll(PDO::FETCH_ASSOC);

    // Active vs Inactive users
    $activeCount = $pdo->query("SELECT COUNT(*) FROM uar.employees")->fetchColumn();
    $inactiveCount = $pdo->query("SELECT COUNT(*) FROM uar.employees_archive")->fetchColumn();

    // Request statistics
    $totalRequests = $pdo->query("
        SELECT COUNT(*) FROM (
            SELECT access_request_number FROM uar.access_requests
            UNION
            SELECT access_request_number FROM uar.approval_history
        ) as all_requests
    ")->fetchColumn();

    $pendingRequests = $pdo->query("
        SELECT COUNT(*) FROM uar.access_requests 
        WHERE status LIKE 'pending%'
    ")->fetchColumn();

    $approvedRequests = $pdo->query("
        SELECT COUNT(*) FROM uar.approval_history 
        WHERE action = 'approved'
    ")->fetchColumn();

    $rejectedRequests = $pdo->query("
        SELECT COUNT(*) FROM uar.approval_history 
        WHERE action = 'rejected'
    ")->fetchColumn();

    // Requests by status
    $requestsByStatus = $pdo->query("
        SELECT status, COUNT(*) as count 
        FROM uar.access_requests 
        GROUP BY status
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Recent activity (last 30 days)
    $recentActivity = $pdo->query("
        SELECT 
            CAST(submission_date AS DATE) as date,
            COUNT(*) as count
        FROM uar.access_requests
        WHERE submission_date >= DATEADD(day, -30, GETDATE())
        GROUP BY CAST(submission_date AS DATE)
        ORDER BY date DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Requests by System Type
    $requestsBySystemType = $pdo->query("
        SELECT 
            COALESCE(system_type, 'Not Specified') as system_type,
            COUNT(*) as count
        FROM uar.access_requests
        GROUP BY system_type
        ORDER BY count DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Requests by Access Level
    $requestsByAccessLevel = $pdo->query("
        SELECT 
            COALESCE(access_level, 'Not Specified') as access_level,
            COUNT(*) as count
        FROM uar.access_requests
        GROUP BY access_level
        ORDER BY count DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Requests by Business Unit
    $requestsByBusinessUnit = $pdo->query("
        SELECT 
            business_unit,
            COUNT(*) as count
        FROM uar.access_requests
        GROUP BY business_unit
        ORDER BY count DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Requests by Department
    $requestsByDepartment = $pdo->query("
        SELECT TOP 10
            department,
            COUNT(*) as count
        FROM uar.access_requests
        GROUP BY department
        ORDER BY count DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Testing Status Breakdown
    $testingStatusData = $pdo->query("
        SELECT 
            COALESCE(testing_status, 'not_started') as testing_status,
            COUNT(*) as count
        FROM uar.access_requests
        GROUP BY testing_status
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Average Processing Time (in days)
    $avgProcessingTime = $pdo->query("
        SELECT 
            AVG(DATEDIFF(day, submission_date, 
                COALESCE(admin_review_date, process_owner_review_date, 
                         technical_review_date, help_desk_review_date, 
                         superior_review_date, GETDATE()))) as avg_days
        FROM uar.access_requests
        WHERE status NOT LIKE 'pending%'
    ")->fetchColumn();

    // Monthly request trends (last 6 months)
    $monthlyTrends = $pdo->query("
        SELECT 
            FORMAT(submission_date, 'yyyy-MM') as month,
            COUNT(*) as total_requests,
            SUM(CASE WHEN status LIKE '%approved%' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status LIKE '%rejected%' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN status LIKE 'pending%' THEN 1 ELSE 0 END) as pending
        FROM uar.access_requests
        WHERE submission_date >= DATEADD(month, -6, GETDATE())
        GROUP BY FORMAT(submission_date, 'yyyy-MM')
        ORDER BY month ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Requests by Access Type from approval history
    $requestsByAccessType = $pdo->query("
        SELECT 
            access_type,
            COUNT(*) as count
        FROM uar.approval_history
        GROUP BY access_type
        ORDER BY count DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Completed requests in last 30 days
    $completedLast30Days = $pdo->query("
        SELECT COUNT(*) 
        FROM uar.approval_history 
        WHERE created_at >= DATEADD(day, -30, GETDATE())
    ")->fetchColumn();

    // Average requests per day
    $avgRequestsPerDay = $pdo->query("
        SELECT 
            CAST(AVG(daily_count) AS DECIMAL(10,2)) as avg_count
        FROM (
            SELECT 
                CAST(submission_date AS DATE) as date,
                COUNT(*) as daily_count
            FROM uar.access_requests
            WHERE submission_date >= DATEADD(day, -30, GETDATE())
            GROUP BY CAST(submission_date AS DATE)
        ) as daily_stats
    ")->fetchColumn();
} catch (Exception $e) {
    error_log("Analytics error: " . $e->getMessage());
    $roleData = [];
    $companyData = [];
    $departmentData = [];
    $activeCount = 0;
    $inactiveCount = 0;
    $totalRequests = 0;
    $pendingRequests = 0;
    $approvedRequests = 0;
    $rejectedRequests = 0;
    $requestsByStatus = [];
    $recentActivity = [];
    $requestsBySystemType = [];
    $requestsByAccessLevel = [];
    $requestsByBusinessUnit = [];
    $requestsByDepartment = [];
    $testingStatusData = [];
    $avgProcessingTime = 0;
    $monthlyTrends = [];
    $requestsByAccessType = [];
    $completedLast30Days = 0;
    $avgRequestsPerDay = 0;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Analytics - UAR Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>

<body class="bg-gray-50">
    <div class="flex min-h-screen">
        <?php include 'sidebar.php'; ?>

        <div class="flex-1 ml-72">
            <!-- Header -->
            <div class="bg-gradient-to-r from-indigo-600 to-purple-600 border-b border-gray-200 sticky top-0 z-30 shadow-lg">
                <div class="flex justify-between items-center px-8 py-4">
                    <div>
                        <h2 class="text-2xl font-bold text-white">System Analytics</h2>
                        <p class="text-indigo-100 text-sm mt-1">Advanced reporting and insights</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <select class="px-4 py-2 bg-white/20 text-white rounded-lg border border-white/30 focus:outline-none focus:ring-2 focus:ring-white/50">
                            <option value="30">Last 30 Days</option>
                            <option value="60">Last 60 Days</option>
                            <option value="90">Last 90 Days</option>
                            <option value="365">Last Year</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Content Area -->
            <div class="p-6">
                <!-- Top Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                    <!-- Total Users -->
                    <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-6 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-blue-100 text-sm font-medium">Total Users</p>
                                <h3 class="text-3xl font-bold mt-2"><?php echo number_format($activeCount + $inactiveCount); ?></h3>
                                <p class="text-blue-100 text-xs mt-1">
                                    <span class="text-green-200"><?php echo $activeCount; ?> Active</span> •
                                    <span class="text-red-200"><?php echo $inactiveCount; ?> Inactive</span>
                                </p>
                            </div>
                            <div class="bg-white/20 rounded-full p-4">
                                <i class='bx bx-user text-4xl'></i>
                            </div>
                        </div>
                    </div>

                    <!-- Total Requests -->
                    <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl shadow-lg p-6 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-purple-100 text-sm font-medium">Total Requests</p>
                                <h3 class="text-3xl font-bold mt-2"><?php echo number_format($totalRequests); ?></h3>
                                <p class="text-purple-100 text-xs mt-1">All time requests</p>
                            </div>
                            <div class="bg-white/20 rounded-full p-4">
                                <i class='bx bx-file text-4xl'></i>
                            </div>
                        </div>
                    </div>

                    <!-- Pending Requests -->
                    <div class="bg-gradient-to-br from-yellow-500 to-yellow-600 rounded-xl shadow-lg p-6 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-yellow-100 text-sm font-medium">Pending Requests</p>
                                <h3 class="text-3xl font-bold mt-2"><?php echo number_format($pendingRequests); ?></h3>
                                <p class="text-yellow-100 text-xs mt-1">Awaiting approval</p>
                            </div>
                            <div class="bg-white/20 rounded-full p-4">
                                <i class='bx bx-time text-4xl'></i>
                            </div>
                        </div>
                    </div>

                    <!-- Approval Rate -->
                    <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-6 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-green-100 text-sm font-medium">Approval Rate</p>
                                <h3 class="text-3xl font-bold mt-2">
                                    <?php
                                    $total = $approvedRequests + $rejectedRequests;
                                    $rate = $total > 0 ? round(($approvedRequests / $total) * 100, 1) : 0;
                                    echo $rate . '%';
                                    ?>
                                </h3>
                                <p class="text-green-100 text-xs mt-1"><?php echo $approvedRequests; ?> approved</p>
                            </div>
                            <div class="bg-white/20 rounded-full p-4">
                                <i class='bx bx-check-circle text-4xl'></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Request Stats -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                    <!-- Avg Processing Time -->
                    <div class="bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-xl shadow-lg p-6 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-indigo-100 text-sm font-medium">Avg Processing Time</p>
                                <h3 class="text-3xl font-bold mt-2"><?php echo round($avgProcessingTime ?? 0, 1); ?></h3>
                                <p class="text-indigo-100 text-xs mt-1">days to complete</p>
                            </div>
                            <div class="bg-white/20 rounded-full p-4">
                                <i class='bx bx-timer text-4xl'></i>
                            </div>
                        </div>
                    </div>

                    <!-- Completed (Last 30 Days) -->
                    <div class="bg-gradient-to-br from-teal-500 to-teal-600 rounded-xl shadow-lg p-6 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-teal-100 text-sm font-medium">Completed (30 Days)</p>
                                <h3 class="text-3xl font-bold mt-2"><?php echo number_format($completedLast30Days); ?></h3>
                                <p class="text-teal-100 text-xs mt-1">requests processed</p>
                            </div>
                            <div class="bg-white/20 rounded-full p-4">
                                <i class='bx bx-check-double text-4xl'></i>
                            </div>
                        </div>
                    </div>

                    <!-- Daily Average -->
                    <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl shadow-lg p-6 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-orange-100 text-sm font-medium">Daily Average</p>
                                <h3 class="text-3xl font-bold mt-2"><?php echo number_format($avgRequestsPerDay ?? 0, 1); ?></h3>
                                <p class="text-orange-100 text-xs mt-1">requests per day</p>
                            </div>
                            <div class="bg-white/20 rounded-full p-4">
                                <i class='bx bx-calendar text-4xl'></i>
                            </div>
                        </div>
                    </div>

                    <!-- Rejected Requests -->
                    <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-xl shadow-lg p-6 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-red-100 text-sm font-medium">Rejected Requests</p>
                                <h3 class="text-3xl font-bold mt-2"><?php echo number_format($rejectedRequests); ?></h3>
                                <p class="text-red-100 text-xs mt-1">all time rejections</p>
                            </div>
                            <div class="bg-white/20 rounded-full p-4">
                                <i class='bx bx-x-circle text-4xl'></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row 1 -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- Requests by System Type -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Requests by System Type</h3>
                        <div class="h-80">
                            <canvas id="systemTypeChart"></canvas>
                        </div>
                    </div>

                    <!-- Request Status Distribution -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Request Status Distribution</h3>
                        <div class="h-80">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Charts Row 2 -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                    <!-- Requests by Access Level -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Requests by Access Level</h3>
                        <div class="h-80">
                            <canvas id="accessLevelChart"></canvas>
                        </div>
                    </div>

                    <!-- Requests by Access Type -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Requests by Access Type</h3>
                        <div class="h-80">
                            <canvas id="accessTypeChart"></canvas>
                        </div>
                    </div>

                    <!-- Testing Status -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Testing Status</h3>
                        <div class="h-80">
                            <canvas id="testingStatusChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Charts Row 3 -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- Users by Role -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Users by Role</h3>
                        <div class="h-80">
                            <canvas id="roleChart"></canvas>
                        </div>
                    </div>

                    <!-- Monthly Request Trends -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Monthly Request Trends (Last 6 Months)</h3>
                        <div class="h-80">
                            <canvas id="monthlyTrendsChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Charts Row 2 -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                    <!-- Users by Company -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Users by Company</h3>
                        <div class="space-y-3">
                            <?php foreach (array_slice($companyData, 0, 5) as $company):
                                $maxCount = !empty($companyData) ? $companyData[0]['count'] : 1;
                                $percentage = ($company['count'] / $maxCount) * 100;
                            ?>
                                <div>
                                    <div class="flex justify-between items-center mb-1">
                                        <span class="text-sm font-medium text-gray-700"><?php echo htmlspecialchars($company['company']); ?></span>
                                        <span class="text-sm text-gray-500"><?php echo $company['count']; ?> users</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-indigo-600 h-2 rounded-full" style="width: <?php echo $percentage . '%'; ?>"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Requests by Business Unit -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Requests by Business Unit</h3>
                        <div class="space-y-3">
                            <?php foreach (array_slice($requestsByBusinessUnit, 0, 5) as $bu):
                                $maxCount = !empty($requestsByBusinessUnit) ? $requestsByBusinessUnit[0]['count'] : 1;
                                $percentage = ($bu['count'] / $maxCount) * 100;
                            ?>
                                <div>
                                    <div class="flex justify-between items-center mb-1">
                                        <span class="text-sm font-medium text-gray-700"><?php echo htmlspecialchars($bu['business_unit']); ?></span>
                                        <span class="text-sm text-gray-500"><?php echo $bu['count']; ?> requests</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-green-600 h-2 rounded-full" style="width: <?php echo $percentage . '%'; ?>"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Requests by Department -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Requests by Department</h3>
                        <div class="space-y-3">
                            <?php foreach (array_slice($requestsByDepartment, 0, 5) as $dept):
                                $maxCount = !empty($requestsByDepartment) ? $requestsByDepartment[0]['count'] : 1;
                                $percentage = ($dept['count'] / $maxCount) * 100;
                            ?>
                                <div>
                                    <div class="flex justify-between items-center mb-1">
                                        <span class="text-sm font-medium text-gray-700"><?php echo htmlspecialchars($dept['department']); ?></span>
                                        <span class="text-sm text-gray-500"><?php echo $dept['count']; ?> requests</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-orange-600 h-2 rounded-full" style="width: <?php echo $percentage . '%'; ?>"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- User Activity Section -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- Top User Departments -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Top User Departments</h3>
                        <div class="space-y-3">
                            <?php foreach (array_slice($departmentData, 0, 8) as $dept):
                                $maxCount = !empty($departmentData) ? $departmentData[0]['count'] : 1;
                                $percentage = ($dept['count'] / $maxCount) * 100;
                            ?>
                                <div>
                                    <div class="flex justify-between items-center mb-1">
                                        <span class="text-sm font-medium text-gray-700"><?php echo htmlspecialchars($dept['department']); ?></span>
                                        <span class="text-sm text-gray-500"><?php echo $dept['count']; ?> users</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-purple-600 h-2 rounded-full" style="width: <?php echo $percentage . '%'; ?>"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Active vs Inactive -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">User Status</h3>
                        <div class="h-64">
                            <canvas id="activeChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity Timeline -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Request Activity (Last 30 Days)</h3>
                    <div class="h-80">
                        <canvas id="activityChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Prepare data for charts
        const roleData = <?php echo json_encode($roleData); ?>;
        const companyData = <?php echo json_encode($companyData); ?>;
        const requestsByStatus = <?php echo json_encode($requestsByStatus); ?>;
        const recentActivity = <?php echo json_encode(array_reverse($recentActivity)); ?>;
        const activeCount = <?php echo $activeCount; ?>;
        const inactiveCount = <?php echo $inactiveCount; ?>;
        const requestsBySystemType = <?php echo json_encode($requestsBySystemType); ?>;
        const requestsByAccessLevel = <?php echo json_encode($requestsByAccessLevel); ?>;
        const requestsByAccessType = <?php echo json_encode($requestsByAccessType); ?>;
        const testingStatusData = <?php echo json_encode($testingStatusData); ?>;
        const monthlyTrends = <?php echo json_encode($monthlyTrends); ?>;

        // Chart.js default config
        Chart.defaults.font.family = "'Inter', sans-serif";
        Chart.defaults.color = '#6B7280';

        // Users by Role Chart (Doughnut)
        const roleCtx = document.getElementById('roleChart').getContext('2d');
        new Chart(roleCtx, {
            type: 'doughnut',
            data: {
                labels: roleData.map(r => r.role.replace(/_/g, ' ').toUpperCase()),
                datasets: [{
                    data: roleData.map(r => r.count),
                    backgroundColor: [
                        '#6366F1', '#8B5CF6', '#EC4899', '#F59E0B',
                        '#10B981', '#3B82F6', '#EF4444', '#14B8A6'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            padding: 15,
                            font: {
                                size: 12
                            }
                        }
                    }
                }
            }
        });

        // Request Status Chart (Pie)
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'pie',
            data: {
                labels: requestsByStatus.map(s => s.status.replace(/_/g, ' ').toUpperCase()),
                datasets: [{
                    data: requestsByStatus.map(s => s.count),
                    backgroundColor: [
                        '#FBBF24', '#3B82F6', '#8B5CF6', '#EC4899',
                        '#10B981', '#EF4444', '#F97316'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            padding: 15,
                            font: {
                                size: 12
                            }
                        }
                    }
                }
            }
        });

        // Active vs Inactive Chart (Doughnut)
        const activeCtx = document.getElementById('activeChart').getContext('2d');
        new Chart(activeCtx, {
            type: 'doughnut',
            data: {
                labels: ['Active', 'Inactive'],
                datasets: [{
                    data: [activeCount, inactiveCount],
                    backgroundColor: ['#10B981', '#EF4444'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            font: {
                                size: 12
                            }
                        }
                    }
                }
            }
        });

        // Activity Timeline Chart (Line)
        const activityCtx = document.getElementById('activityChart').getContext('2d');
        new Chart(activityCtx, {
            type: 'line',
            data: {
                labels: recentActivity.map(a => new Date(a.date).toLocaleDateString('en-US', {
                    month: 'short',
                    day: 'numeric'
                })),
                datasets: [{
                    label: 'Requests',
                    data: recentActivity.map(a => a.count),
                    borderColor: '#6366F1',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });

        // Requests by System Type Chart (Bar)
        const systemTypeCtx = document.getElementById('systemTypeChart').getContext('2d');
        new Chart(systemTypeCtx, {
            type: 'bar',
            data: {
                labels: requestsBySystemType.map(s => s.system_type),
                datasets: [{
                    label: 'Requests',
                    data: requestsBySystemType.map(s => s.count),
                    backgroundColor: '#6366F1',
                    borderWidth: 0,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });

        // Requests by Access Level Chart (Doughnut)
        const accessLevelCtx = document.getElementById('accessLevelChart').getContext('2d');
        new Chart(accessLevelCtx, {
            type: 'doughnut',
            data: {
                labels: requestsByAccessLevel.map(a => a.access_level),
                datasets: [{
                    data: requestsByAccessLevel.map(a => a.count),
                    backgroundColor: [
                        '#3B82F6', '#8B5CF6', '#EC4899', '#F59E0B',
                        '#10B981', '#EF4444', '#14B8A6', '#F97316'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            font: {
                                size: 11
                            }
                        }
                    }
                }
            }
        });

        // Requests by Access Type Chart (Pie)
        const accessTypeCtx = document.getElementById('accessTypeChart').getContext('2d');
        new Chart(accessTypeCtx, {
            type: 'pie',
            data: {
                labels: requestsByAccessType.map(a => a.access_type),
                datasets: [{
                    data: requestsByAccessType.map(a => a.count),
                    backgroundColor: [
                        '#10B981', '#3B82F6', '#F59E0B', '#EF4444',
                        '#8B5CF6', '#EC4899', '#14B8A6', '#F97316'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            font: {
                                size: 11
                            }
                        }
                    }
                }
            }
        });

        // Testing Status Chart (Doughnut)
        const testingStatusCtx = document.getElementById('testingStatusChart').getContext('2d');
        new Chart(testingStatusCtx, {
            type: 'doughnut',
            data: {
                labels: testingStatusData.map(t => t.testing_status.replace(/_/g, ' ').toUpperCase()),
                datasets: [{
                    data: testingStatusData.map(t => t.count),
                    backgroundColor: [
                        '#6B7280', '#10B981', '#EF4444', '#F59E0B'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            font: {
                                size: 11
                            }
                        }
                    }
                }
            }
        });

        // Monthly Trends Chart (Line with multiple datasets)
        const monthlyTrendsCtx = document.getElementById('monthlyTrendsChart').getContext('2d');
        new Chart(monthlyTrendsCtx, {
            type: 'line',
            data: {
                labels: monthlyTrends.map(m => {
                    const [year, month] = m.month.split('-');
                    return new Date(year, month - 1).toLocaleDateString('en-US', {
                        month: 'short',
                        year: 'numeric'
                    });
                }),
                datasets: [{
                        label: 'Total Requests',
                        data: monthlyTrends.map(m => m.total_requests),
                        borderColor: '#6366F1',
                        backgroundColor: 'rgba(99, 102, 241, 0.1)',
                        fill: false,
                        tension: 0.4,
                        borderWidth: 2
                    },
                    {
                        label: 'Approved',
                        data: monthlyTrends.map(m => m.approved),
                        borderColor: '#10B981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        fill: false,
                        tension: 0.4,
                        borderWidth: 2
                    },
                    {
                        label: 'Rejected',
                        data: monthlyTrends.map(m => m.rejected),
                        borderColor: '#EF4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        fill: false,
                        tension: 0.4,
                        borderWidth: 2
                    },
                    {
                        label: 'Pending',
                        data: monthlyTrends.map(m => m.pending),
                        borderColor: '#F59E0B',
                        backgroundColor: 'rgba(245, 158, 11, 0.1)',
                        fill: false,
                        tension: 0.4,
                        borderWidth: 2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            padding: 15,
                            font: {
                                size: 11
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
    </script>
</body>

</html>
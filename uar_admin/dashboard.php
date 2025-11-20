<?php
session_start();
require_once '../config.php';

// Authentication check
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'uar_admin') {
    header("Location: login.php");
    exit();
}

// Get system-wide statistics
try {
    // Total requests
    $stmt = $pdo->query("SELECT COUNT(*) FROM uar.access_requests");
    $totalRequests = (int)$stmt->fetchColumn();

    // Pending requests by status
    $stmt = $pdo->query("SELECT COUNT(*) FROM uar.access_requests WHERE status = 'pending_admin'");
    $pendingAdmin = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM uar.access_requests WHERE status = 'pending_superior'");
    $pendingSuperior = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM uar.access_requests WHERE status = 'pending_technical'");
    $pendingTechnical = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM uar.access_requests WHERE status = 'pending_process_owner'");
    $pendingProcessOwner = (int)$stmt->fetchColumn();

    // Approved/Rejected from history
    $stmt = $pdo->query("SELECT COUNT(*) FROM uar.approval_history WHERE action = 'approved'");
    $totalApproved = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM uar.approval_history WHERE action = 'rejected'");
    $totalRejected = (int)$stmt->fetchColumn();

    // Total users
    $stmt = $pdo->query("SELECT COUNT(*) FROM uar.employees");
    $totalUsers = (int)$stmt->fetchColumn();

    // Total admins
    $stmt = $pdo->query("SELECT COUNT(*) FROM uar.admin_users");
    $totalAdmins = (int)$stmt->fetchColumn();

    // Recent requests
    $stmt = $pdo->query("
        SELECT TOP 10 
            ar.*,
            ISNULL(ir.access_type, gr.access_type) as access_type
        FROM uar.access_requests ar
        LEFT JOIN uar.individual_requests ir ON ar.access_request_number = ir.access_request_number
        LEFT JOIN uar.group_requests gr ON ar.access_request_number = gr.access_request_number
        ORDER BY ar.submission_date DESC
    ");
    $recentRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $totalRequests = $pendingAdmin = $pendingSuperior = $pendingTechnical = $pendingProcessOwner = 0;
    $totalApproved = $totalRejected = $totalUsers = $totalAdmins = 0;
    $recentRequests = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UAR Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body class="bg-gray-50">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 ml-72">
            <!-- Header -->
            <div class="bg-gradient-to-r from-indigo-600 to-purple-600 border-b border-gray-200 sticky top-0 z-30 shadow-lg">
                <div class="flex justify-between items-center px-8 py-4">
                    <div>
                        <h2 class="text-2xl font-bold text-white">UAR System Administration</h2>
                        <p class="text-indigo-100 text-sm mt-1">User Access Request Management Dashboard</p>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="relative group">
                            <button class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center hover:bg-white/30 transition-colors backdrop-blur-sm">
                                <i class='bx bx-user text-xl text-white'></i>
                            </button>
                            <div class="absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-lg border border-gray-100 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all">
                                <div class="p-3 border-b border-gray-100">
                                    <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'UAR Administrator'); ?></p>
                                    <p class="text-xs text-gray-500 mt-0.5"><?php echo htmlspecialchars($_SESSION['admin_email'] ?? $_SESSION['admin_username'] ?? ''); ?></p>
                                </div>
                                <a href="logout.php" class="flex items-center px-4 py-3 text-sm text-red-600 hover:bg-red-50 transition-colors rounded-b-lg">
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
                <!-- Overview Stats -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- Total Requests -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Total Requests</p>
                                <h3 class="text-3xl font-bold text-gray-900 mt-2"><?php echo number_format($totalRequests); ?></h3>
                            </div>
                            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                <i class='bx bx-file text-2xl text-blue-600'></i>
                            </div>
                        </div>
                    </div>

                    <!-- Total Approved -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Approved</p>
                                <h3 class="text-3xl font-bold text-green-600 mt-2"><?php echo number_format($totalApproved); ?></h3>
                            </div>
                            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                                <i class='bx bx-check-circle text-2xl text-green-600'></i>
                            </div>
                        </div>
                    </div>

                    <!-- Total Rejected -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Rejected</p>
                                <h3 class="text-3xl font-bold text-red-600 mt-2"><?php echo number_format($totalRejected); ?></h3>
                            </div>
                            <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                                <i class='bx bx-x-circle text-2xl text-red-600'></i>
                            </div>
                        </div>
                    </div>

                    <!-- Total Users -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Total Users</p>
                                <h3 class="text-3xl font-bold text-purple-600 mt-2"><?php echo number_format($totalUsers); ?></h3>
                            </div>
                            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                                <i class='bx bx-group text-2xl text-purple-600'></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pending Requests by Stage -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
                    <h3 class="text-lg font-semibold text-gray-900 mb-6">Pending Requests by Stage</h3>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div class="bg-yellow-50 rounded-lg p-4 border border-yellow-200">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium text-yellow-800">Superior Review</span>
                                <span class="text-2xl font-bold text-yellow-600"><?php echo $pendingSuperior; ?></span>
                            </div>
                            <div class="w-full bg-yellow-200 rounded-full h-2">
                                <div class="bg-yellow-600 h-2 rounded-full" style="width: <?php echo $totalRequests > 0 ? ($pendingSuperior / $totalRequests * 100) : 0; ?>%"></div>
                            </div>
                        </div>

                        <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium text-blue-800">Technical Review</span>
                                <span class="text-2xl font-bold text-blue-600"><?php echo $pendingTechnical; ?></span>
                            </div>
                            <div class="w-full bg-blue-200 rounded-full h-2">
                                <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo $totalRequests > 0 ? ($pendingTechnical / $totalRequests * 100) : 0; ?>%"></div>
                            </div>
                        </div>

                        <div class="bg-indigo-50 rounded-lg p-4 border border-indigo-200">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium text-indigo-800">Process Owner</span>
                                <span class="text-2xl font-bold text-indigo-600"><?php echo $pendingProcessOwner; ?></span>
                            </div>
                            <div class="w-full bg-indigo-200 rounded-full h-2">
                                <div class="bg-indigo-600 h-2 rounded-full" style="width: <?php echo $totalRequests > 0 ? ($pendingProcessOwner / $totalRequests * 100) : 0; ?>%"></div>
                            </div>
                        </div>

                        <div class="bg-purple-50 rounded-lg p-4 border border-purple-200">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium text-purple-800">Admin Review</span>
                                <span class="text-2xl font-bold text-purple-600"><?php echo $pendingAdmin; ?></span>
                            </div>
                            <div class="w-full bg-purple-200 rounded-full h-2">
                                <div class="bg-purple-600 h-2 rounded-full" style="width: <?php echo $totalRequests > 0 ? ($pendingAdmin / $totalRequests * 100) : 0; ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Requests -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-gray-900">Recent Requests</h3>
                        <a href="requests.php" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium transition-colors">
                            View All <i class='bx bx-right-arrow-alt ml-1'></i>
                        </a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Request Number</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Requestor</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Business Unit</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Access Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (!empty($recentRequests)): ?>
                                    <?php foreach ($recentRequests as $request): ?>
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-indigo-600">
                                                <?php echo htmlspecialchars($request['access_request_number']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo htmlspecialchars($request['requestor_name']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($request['business_unit']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($request['access_type'] ?? 'N/A'); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php
                                                // All pending statuses = yellow, approved = green, rejected = red
                                                $status = $request['status'];
                                                if ($status === 'approved') {
                                                    $statusClass = 'bg-green-100 text-green-800';
                                                } elseif ($status === 'rejected') {
                                                    $statusClass = 'bg-red-100 text-red-800';
                                                } elseif (strpos($status, 'pending') === 0) {
                                                    $statusClass = 'bg-yellow-100 text-yellow-800';
                                                } else {
                                                    $statusClass = 'bg-gray-100 text-gray-800';
                                                }
                                                ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                                    <?php echo ucwords(str_replace('_', ' ', $request['status'])); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo date('M d, Y', strtotime($request['submission_date'])); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                            <i class='bx bx-inbox text-4xl mb-2'></i>
                                            <p>No recent requests found</p>
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
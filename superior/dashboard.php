<?php
session_start();
require_once '../config.php';


// Authentication check
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'superior') {
    header("Location: ../admin/login.php");
    exit();
}

// Get quick stats for the dashboard
try {
    
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
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Boxicons -->
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <!-- Alpine.js for interactions -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.1/dist/cdn.min.js"></script>
    <!-- Flowbite CSS -->
    <link href="https://cdn.jsdelivr.net/npm/flowbite@2.5.1/dist/flowbite.min.css" rel="stylesheet" />
    <!-- Flowbite JS -->
    <script src="https://cdn.jsdelivr.net/npm/flowbite@2.5.1/dist/flowbite.min.js"></script>
    <!-- Include Design System -->
    <?php require_once '../includes/design_system.php'; ?>

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
                            950: '#172554',
                        }
                    },
                    fontFamily: {
                        'sans': ['Inter', 'system-ui', 'sans-serif'],
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-gray-100" x-data="{ sidebarOpen: true }">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 transition-all duration-300" :class="sidebarOpen ? 'md:ml-72' : 'ml-0'">
            <!-- Header -->
            <div class="<?php echo getComponentClass('header'); ?>">
                <div class="<?php echo getComponentClass('header', 'container'); ?>">
                    <div class="flex items-center">
                        <?php renderHamburgerButton(); ?>
                        <h1 class="<?php echo getComponentClass('header', 'title'); ?>">Superior Dashboard</h1>
                    </div>
                    <?php renderPrivacyNotice(); ?>
                </div>
            </div>

            <!-- Content -->
            <div class="p-4 md:p-8">
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
                <div class="<?php echo getComponentClass('card'); ?>">
                    <div class="<?php echo getComponentClass('section_header_white'); ?>">
                        <h3 class="<?php echo getComponentClass('section_header_white', 'title'); ?>">Recent Requests</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <?php 
                            $columns = ['UAR REF NO.', 'Requestor', 'Date Requested', 'Days Pending', 'Date Needed', 'Status'];
                            renderTableHeader($columns);
                            ?>
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
<?php include '../footer.php'; ?>
</html>
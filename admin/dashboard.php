<?php
session_start();
require_once '../config.php';

// Authentication check
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

function formatStatus($status)
{
    return ucwords(str_replace('_', ' ', $status));
}

// Resolve current admin_users.id for scoping queries
$admin_users_id = null;
try {
    $stmt = $pdo->prepare("SELECT id FROM uar.admin_users WHERE username = :username OR username = :employee_id");
    $stmt->execute([
        'username' => $_SESSION['admin_username'] ?? '',
        'employee_id' => $_SESSION['admin_id'] ?? ''
    ]);
    $admin_users_id = $stmt->fetchColumn();
} catch (PDOException $e) {
    $admin_users_id = null;
}

// Get quick stats for the dashboard
try {
    // Count of requests relevant to this admin (pending_admin and assigned to me or unassigned)
    $pendingWhere = "uar.enum2str\$access_requests\$status(ar.status) = 'pending_admin'";
    $assignmentFilter = $admin_users_id ? " AND (ar.admin_id IS NULL OR ar.admin_id = :admin_user_id)" : "";

    // Total relevant requests (show only those I can act on)
    $sql = "SELECT COUNT(*) FROM uar.access_requests ar WHERE $pendingWhere" . $assignmentFilter;
    $stmt = $pdo->prepare($sql);
    if ($admin_users_id) { $stmt->bindValue(':admin_user_id', $admin_users_id, PDO::PARAM_INT); }
    $stmt->execute();
    $totalRequests = (int)$stmt->fetchColumn();

    // Approved requests overall (for rate cards)
    $stmt = $pdo->query("SELECT COUNT(*) FROM uar.access_requests WHERE uar.enum2str\$access_requests\$status(status) = 'approved'");
    $approvedRequests = (int)$stmt->fetchColumn();

    // Calculate approval rate
    $approvalRate = $totalRequests > 0 ? round(($approvedRequests / $totalRequests) * 100, 1) : 0;
    $declineRate = $totalRequests > 0 ? round((($totalRequests - $approvedRequests) / $totalRequests) * 100, 1) : 0;

    $statsData = [
        'total' => $totalRequests,
        'approved' => $approvedRequests,
        'approval_rate' => $approvalRate,
        'decline_rate' => $declineRate
    ];

    // Pending requests count (scoped)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM uar.access_requests ar WHERE $pendingWhere" . $assignmentFilter);
    if ($admin_users_id) { $stmt->bindValue(':admin_user_id', $admin_users_id, PDO::PARAM_INT); }
    $stmt->execute();
    $pendingRequests = (int)$stmt->fetchColumn();

    // Today's approvals/rejections (by me)
    $todayDate = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM uar.approval_history WHERE action = 1 AND CAST(created_at AS DATE) = :today" . ($admin_users_id ? " AND admin_id = :admin_user_id" : ""));
    $params = [':today' => $todayDate];
    if ($admin_users_id) { $params[':admin_user_id'] = $admin_users_id; }
    $stmt->execute($params);
    $approvedToday = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM uar.approval_history WHERE action = 2 AND CAST(created_at AS DATE) = :today" . ($admin_users_id ? " AND admin_id = :admin_user_id" : ""));
    $stmt->execute($params);
    $rejectedToday = (int)$stmt->fetchColumn();

    // Recent requests relevant to me (pending_admin only)
    $sqlRecent = "
        SELECT TOP 5 
            ar.access_request_number,
            ar.requestor_name,
            ar.business_unit,
            ar.submission_date,
            uar.enum2str\$access_requests\$status(ar.status) as status,
            ISNULL(ir.access_type, gr.access_type) as access_type
        FROM uar.access_requests ar
        LEFT JOIN uar.individual_requests ir ON ar.access_request_number = ir.access_request_number
        LEFT JOIN uar.group_requests gr ON ar.access_request_number = gr.access_request_number
        WHERE $pendingWhere" . $assignmentFilter . "
        ORDER BY ar.submission_date DESC";
    $stmt = $pdo->prepare($sqlRecent);
    if ($admin_users_id) { $stmt->bindValue(':admin_user_id', $admin_users_id, PDO::PARAM_INT); }
    $stmt->execute();
    $recentRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recent approval history by me
    $stmt = $pdo->prepare("SELECT TOP 5 h.*, a.username as admin_username 
                           FROM uar.approval_history h 
                           LEFT JOIN uar.admin_users a ON h.admin_id = a.id 
                           " . ($admin_users_id ? "WHERE h.admin_id = :admin_user_id " : "") .
                           "ORDER BY h.created_at DESC");
    if ($admin_users_id) { $stmt->bindValue(':admin_user_id', $admin_users_id, PDO::PARAM_INT); }
    $stmt->execute();
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
                        },
                        danger: {
                            DEFAULT: '#dc3545',
                            dark: '#c82333',
                        }
                    },

                }
            }
        }
    </script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        .mobile-menu-overlay {
            backdrop-filter: blur(4px);
        }

        .sidebar-backdrop {
            backdrop-filter: blur(8px);
            background: rgba(0, 0, 0, 0.1);
        }

        .card-hover {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        .fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .mobile-nav-hidden {
            transform: translateX(-100%);
        }

        .mobile-nav-visible {
            transform: translateX(0);
        }

        /* Scrollbar styling */
        .custom-scrollbar::-webkit-scrollbar {
            width: 4px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 2px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    </style>
</head>

<body class="bg-gray-50 font-sans">
    <!-- Mobile Overlay -->
    <div id="mobileOverlay" class="lg:hidden fixed inset-0 z-40 bg-black bg-opacity-25 mobile-menu-overlay invisible opacity-0 transition-all duration-300"></div>

    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Mobile menu button -->
    <div class="lg:hidden fixed top-4 left-4 z-50">
        <button id="mobileMenuBtn" type="button" class="flex items-center justify-center w-12 h-12 rounded-xl bg-white text-gray-600 shadow-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-colors">
            <i class='bx bx-menu text-2xl'></i>
        </button>
    </div>

    <!-- Main Content -->
    <div class="lg:ml-72 min-h-screen">
        <!-- Header -->
        <div class="bg-primary-900 border-b border-gray-200 sticky top-0 z-30 shadow-sm">
            <div class="flex justify-between items-center px-4 lg:px-8 py-4">
                <div class="flex-1 ml-16 lg:ml-0">
                    <h2 class="text-xl lg:text-2xl font-bold text-white">ABU IT - User Access Request System</h2>
                </div>
                <div class="hidden md:flex items-center gap-6">
                    <div class="relative group">
                        <button class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center hover:bg-gray-200 transition-colors">
                            <i class='bx bx-user text-xl text-gray-600'></i>
                        </button>
                        <div class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-100 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all">
                            <div class="p-3 border-b border-gray-100">
                                <p class="text-sm font-medium text-gray-900">Admin Account</p>
                                <p class="text-xs text-gray-500 mt-0.5"><?php echo htmlspecialchars($_SESSION['admin_name'] ?? $_SESSION['username'] ?? 'Admin'); ?></p>
                            </div>
                            <a href="logout.php" class="flex items-center px-4 py-3 text-sm text-red-600 hover:bg-red-50 transition-colors">
                                <i class='bx bx-log-out mr-2'></i>
                                Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content Area -->
        <div class="p-4 lg:p-8">
            <!-- Main Navigation Cards -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Requests Card -->
                <a href="requests.php" class="group relative bg-white border border-gray-200 rounded-xl shadow-sm p-6 card-hover overflow-hidden">
                    <div class="relative z-10">
                        <div class="flex justify-between items-start mb-4">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-2">
                                    <h3 class="text-xl font-bold text-gray-800">Requests</h3>
                                    <?php if ($pendingRequests > 0): ?>
                                        <span class="bg-red-500 text-white text-xs rounded-full px-2 py-1">
                                            <?php echo $pendingRequests; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-gray-600 text-sm">Manage pending admin reviews assigned to you</p>
                            </div>
                            <div class="text-primary bg-primary-50 p-3 rounded-lg group-hover:scale-110 transition-transform">
                                <i class='bx bxs-message-square-detail text-2xl'></i>
                            </div>
                        </div>

                        <!-- Mini Requests Data -->
                        <?php if (!empty($recentRequests)): ?>
                            <div class="border-t border-gray-100 pt-3 mb-4">
                                <p class="text-xs font-medium text-gray-500 mb-2">Recent Requests</p>
                                <div class="space-y-2">
                                    <?php foreach (array_slice($recentRequests, 0, 2) as $request): ?>
                                        <div class="flex items-center text-sm">
                                            <span class="w-2 h-2 rounded-full mr-2 flex-shrink-0 <?php echo $request['status'] === 'pending' ? 'bg-yellow-400' : ($request['status'] === 'approved' ? 'bg-green-400' : 'bg-red-400'); ?>"></span>
                                            <span class="truncate flex-1"><?php echo htmlspecialchars($request['requestor_name']); ?></span>
                                            <span class="text-xs text-gray-500 ml-2 flex-shrink-0"><?php echo date('M d', strtotime($request['submission_date'])); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="flex items-center text-primary font-medium">
                            <span class="text-sm">View Details</span>
                            <i class='bx bx-right-arrow-alt ml-2 group-hover:translate-x-1 transition-transform'></i>
                        </div>
                    </div>
                    <div class="absolute inset-0 bg-gradient-to-r from-primary/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                </a>

                <!-- Approval History Card -->
                <a href="approval_history.php" class="group relative bg-white border border-gray-200 rounded-xl shadow-sm p-6 card-hover overflow-hidden">
                    <div class="relative z-10">
                        <div class="flex justify-between items-start mb-4">
                            <div class="flex-1">
                                <h3 class="text-xl font-bold text-gray-800 mb-2">Approval History</h3>
                                <p class="text-gray-600 text-sm">Review past approval decisions</p>
                            </div>
                            <div class="text-primary bg-primary-50 p-3 rounded-lg group-hover:scale-110 transition-transform">
                                <i class='bx bx-history text-2xl'></i>
                            </div>
                        </div>

                        <!-- Mini Approval History Data -->
                        <?php if (!empty($recentApprovals)): ?>
                            <div class="border-t border-gray-100 pt-3 mb-4">
                                <p class="text-xs font-medium text-gray-500 mb-2">Recent Activity</p>
                                <div class="space-y-2">
                                    <?php foreach (array_slice($recentApprovals, 0, 2) as $approval): ?>
                                        <div class="flex items-center text-sm">
                                            <span class="w-2 h-2 rounded-full mr-2 flex-shrink-0 <?php echo $approval['action'] === 'approved' ? 'bg-green-400' : 'bg-red-400'; ?>"></span>
                                            <span class="truncate flex-1"><?php echo htmlspecialchars($approval['requestor_name']); ?></span>
                                            <span class="text-xs text-gray-500 ml-2 flex-shrink-0"><?php echo date('M d', strtotime($approval['created_at'])); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="flex items-center text-primary font-medium">
                            <span class="text-sm">View Details</span>
                            <i class='bx bx-right-arrow-alt ml-2 group-hover:translate-x-1 transition-transform'></i>
                        </div>
                    </div>
                    <div class="absolute inset-0 bg-gradient-to-r from-primary/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                </a>
            </div>

            <!-- Quick Stats Section -->
            <div class="mb-8">
                <h3 class="text-lg font-semibold text-gray-800 mb-6">Quick Statistics</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <!-- Pending Requests -->
                    <div class="rounded-xl p-6 card-hover bg-gradient-to-r from-sky-500 to-blue-600 text-white shadow-md">
                        <div class="flex items-center">
                            <div class="p-3 rounded-lg flex-shrink-0 bg-white/20">
                                <i class='bx bx-time text-2xl'></i>
                            </div>
                            <div class="ml-4 min-w-0">
                                <p class="text-sm/none opacity-90 truncate">Pending Requests</p>
                                <h4 class="text-3xl font-extrabold tracking-tight"><?php echo $pendingRequests; ?></h4>
                            </div>
                        </div>
                    </div>

                    <!-- Approved Today -->
                    <div class="rounded-xl p-6 card-hover bg-gradient-to-r from-emerald-500 to-green-600 text-white shadow-md">
                        <div class="flex items-center">
                            <div class="p-3 rounded-lg flex-shrink-0 bg-white/20">
                                <i class='bx bx-check-circle text-2xl'></i>
                            </div>
                            <div class="ml-4 min-w-0">
                                <p class="text-sm/none opacity-90 truncate">Approved Today</p>
                                <h4 class="text-3xl font-extrabold tracking-tight"><?php echo $approvedToday; ?></h4>
                            </div>
                        </div>
                    </div>

                    <!-- Rejected Today -->
                    <div class="rounded-xl p-6 card-hover bg-gradient-to-r from-rose-500 to-red-600 text-white shadow-md">
                        <div class="flex items-center">
                            <div class="p-3 rounded-lg flex-shrink-0 bg-white/20">
                                <i class='bx bx-x-circle text-2xl'></i>
                            </div>
                            <div class="ml-4 min-w-0">
                                <p class="text-sm/none opacity-90 truncate">Rejected Today</p>
                                <h4 class="text-3xl font-extrabold tracking-tight"><?php echo $rejectedToday; ?></h4>
                            </div>
                        </div>
                    </div>

                    <!-- Total Requests -->
                    <div class="rounded-xl p-6 card-hover bg-gradient-to-r from-indigo-500 to-purple-600 text-white shadow-md">
                        <div class="flex items-center">
                            <div class="p-3 rounded-lg flex-shrink-0 bg-white/20">
                                <i class='bx bx-folder text-2xl'></i>
                            </div>
                            <div class="ml-4 min-w-0">
                                <p class="text-sm/none opacity-90 truncate">Total Requests</p>
                                <h4 class="text-3xl font-extrabold tracking-tight"><?php echo number_format($statsData['total']); ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Requests Section -->
            <div class="w-full">
                <!-- Recent Requests -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-gray-800">Recent Requests</h3>
                            <a href="requests.php" class="text-sm text-primary hover:text-primary-700 flex items-center transition-colors">
                                View All
                                <i class='bx bx-right-arrow-alt ml-1'></i>
                            </a>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Requestor</th>
                                    <th class="hidden sm:table-cell px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Business Unit</th>
                                    <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="hidden lg:table-cell px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (!empty($recentRequests)): ?>
                                    <?php foreach ($recentRequests as $request): ?>
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-4 lg:px-6 py-4 text-sm font-medium text-gray-900">
                                                <div class="truncate"><?php echo htmlspecialchars($request['requestor_name']); ?></div>
                                            </td>
                                            <td class="hidden sm:table-cell px-4 lg:px-6 py-4 text-sm text-gray-500">
                                                <div class="truncate"><?php echo htmlspecialchars($request['business_unit']); ?></div>
                                            </td>
                                            <td class="px-4 lg:px-6 py-4 text-sm text-gray-500">
                                                <div class="truncate"><?php echo htmlspecialchars($request['access_type'] ?? 'N/A'); ?></div>
                                            </td>
                                            <td class="px-4 lg:px-6 py-4">
                                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                                    <?php echo $request['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : ($request['status'] === 'approved' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'); ?>">
                                                    <?php echo formatStatus($request['status']); ?>
                                                </span>
                                            </td>
                                            <td class="hidden lg:table-cell px-4 lg:px-6 py-4 text-sm text-gray-500">
                                                <?php echo date('M d, Y', strtotime($request['submission_date'])); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="px-6 py-8 text-center">
                                            <div class="flex flex-col items-center">
                                                <i class='bx bx-inbox text-4xl text-gray-300 mb-2'></i>
                                                <p class="text-sm text-gray-500">No recent requests found</p>
                                            </div>
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

    <!-- Mobile Menu JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const sidebar = document.getElementById('sidebar');
            const mobileOverlay = document.getElementById('mobileOverlay');

            function toggleMobileMenu() {
                const isHidden = sidebar.classList.contains('mobile-nav-hidden');

                if (isHidden) {
                    sidebar.classList.remove('mobile-nav-hidden');
                    sidebar.classList.add('mobile-nav-visible');
                    mobileOverlay.classList.remove('invisible', 'opacity-0');
                    mobileOverlay.classList.add('visible', 'opacity-100');
                    document.body.style.overflow = 'hidden';
                } else {
                    sidebar.classList.remove('mobile-nav-visible');
                    sidebar.classList.add('mobile-nav-hidden');
                    mobileOverlay.classList.remove('visible', 'opacity-100');
                    mobileOverlay.classList.add('invisible', 'opacity-0');
                    document.body.style.overflow = '';
                }
            }

            // Mobile menu button click
            if (mobileMenuBtn) {
                mobileMenuBtn.addEventListener('click', toggleMobileMenu);
            }

            // Close menu when overlay is clicked
            if (mobileOverlay) {
                mobileOverlay.addEventListener('click', toggleMobileMenu);
            }

            // Close menu when escape key is pressed
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && sidebar.classList.contains('mobile-nav-visible')) {
                    toggleMobileMenu();
                }
            });

            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 1024) { // lg breakpoint
                    sidebar.classList.remove('mobile-nav-hidden', 'mobile-nav-visible');
                    mobileOverlay.classList.remove('visible', 'opacity-100');
                    mobileOverlay.classList.add('invisible', 'opacity-0');
                    document.body.style.overflow = '';
                } else {
                    if (!sidebar.classList.contains('mobile-nav-hidden') && !sidebar.classList.contains('mobile-nav-visible')) {
                        sidebar.classList.add('mobile-nav-hidden');
                    }
                }
            });

            // Add fade-in animation to main content
            const mainContent = document.querySelector('.lg\\:ml-72');
            if (mainContent) {
                mainContent.classList.add('fade-in');
            }
        });

        // Add smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Add loading states for navigation links
        document.querySelectorAll('nav a, .card-hover').forEach(link => {
            link.addEventListener('click', function(e) {
                // Only add loading state for actual navigation (not hash links)
                if (this.getAttribute('href') && !this.getAttribute('href').startsWith('#')) {
                    const icon = this.querySelector('i');
                    if (icon && !icon.classList.contains('bx-loader-alt')) {
                        const originalClass = icon.className;
                        icon.className = 'bx bx-loader-alt bx-spin text-xl';

                        // Restore original icon if page doesn't navigate (fallback)
                        setTimeout(() => {
                            icon.className = originalClass;
                        }, 5000);
                    }
                }
            });
        });
    </script>
</body>
<?php include '../footer.php'; ?>
</html>
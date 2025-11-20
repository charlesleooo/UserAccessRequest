<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config.php';

if (!isset($_SESSION['admin_id']) || ($_SESSION['role'] ?? '') !== 'technical_support') {
    header('Location: ../admin/login.php');
    exit();
}

$requestorId = $_SESSION['admin_id'];
$statusFilter = $_GET['status'] ?? 'all';
$dateFilter = $_GET['date'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';
$recordsPerPage = 10;
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$offset = ($currentPage - 1) * $recordsPerPage;

$baseQuery = "SELECT 
        source,
        request_id,
        access_request_number,
        status,
        created_at,
        admin_username,
        business_unit,
        department,
        access_type,
        system_type,
        justification,
        email,
        employee_id,
        requestor_name
      FROM (
        SELECT 
          'pending' AS source,
          id AS request_id,
          access_request_number,
          status,
          submission_date AS created_at,
          NULL AS admin_username,
          business_unit,
          department,
          'System Application' AS access_type,
          system_type,
          NULL AS justification,
          employee_email AS email,
          employee_id,
          requestor_name
        FROM uar.access_requests 
        WHERE employee_id = :employee_id
        UNION ALL
        SELECT 
          'history' AS source,
          ah.history_id AS request_id,
          ah.access_request_number,
          ah.action AS status,
          ah.created_at,
          (SELECT username FROM uar.admin_users WHERE id = ah.admin_id) AS admin_username,
          ah.business_unit,
          ah.department,
          ah.access_type,
          ah.system_type,
          ah.justification,
          ah.email,
          ah.employee_id,
          ah.requestor_name
        FROM uar.approval_history ah
        WHERE ah.employee_id = :employee_id2
          AND ah.action IN ('approved','rejected','cancelled')
      ) all_requests";

$rankedQuery = "SELECT * FROM (
        SELECT *, ROW_NUMBER() OVER (PARTITION BY access_request_number ORDER BY 
            CASE 
              WHEN status LIKE 'pending%' THEN 0
              WHEN status = 'approved' THEN 1
              WHEN status = 'rejected' THEN 2
              WHEN status = 'cancelled' THEN 3
              ELSE 4
            END, created_at DESC) AS rn
        FROM ($baseQuery) q
      ) ranked WHERE rn = 1";

$params = [':employee_id' => $requestorId, ':employee_id2' => $requestorId];
$whereConditions = [];

if ($statusFilter !== 'all') {
    if ($statusFilter === 'history') {
        $whereConditions[] = "source = 'history'";
    } elseif ($statusFilter === 'pending') {
        $whereConditions[] = "source = 'pending'";
    } else {
        $whereConditions[] = "status = :status";
        $params[':status'] = $statusFilter;
    }
}

if ($dateFilter !== 'all') {
    switch ($dateFilter) {
        case 'today':
            $whereConditions[] = "CAST(created_at AS DATE) = CAST(GETDATE() AS DATE)";
            break;
        case 'week':
            $whereConditions[] = "created_at >= DATEADD(WEEK,-1,CAST(GETDATE() AS DATE))";
            break;
        case 'month':
            $whereConditions[] = "created_at >= DATEADD(MONTH,-1,CAST(GETDATE() AS DATE))";
            break;
    }
}

if ($searchQuery !== '') {
    $whereConditions[] = "(access_request_number LIKE :search OR business_unit LIKE :search OR department LIKE :search OR access_type LIKE :search OR system_type LIKE :search)";
    $params[':search'] = "%$searchQuery%";
}

if ($whereConditions) {
    $rankedQuery = "SELECT * FROM ($rankedQuery) filtered WHERE " . implode(' AND ', $whereConditions);
}

$countQuery = "SELECT COUNT(*) AS total FROM ($rankedQuery) c";
$rankedQuery .= " ORDER BY 
    CASE 
      WHEN source = 'pending' OR LOWER(status) LIKE 'pending%' THEN 0
      WHEN status = 'approved' THEN 1
      WHEN status = 'rejected' THEN 2
      ELSE 3
    END, access_request_number DESC";

try {
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($params);
    $totalRecords = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    $totalPages = max(1, ceil($totalRecords / $recordsPerPage));
    if ($currentPage > $totalPages) {
        $currentPage = $totalPages;
        $offset = ($currentPage - 1) * $recordsPerPage;
    }
    $rankedQuery .= " OFFSET $offset ROWS FETCH NEXT $recordsPerPage ROWS ONLY";
    $stmt = $pdo->prepare($rankedQuery);
    $stmt->execute($params);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $pendingStmt = $pdo->prepare("SELECT COUNT(*) AS count FROM uar.access_requests WHERE employee_id = :eid AND status LIKE 'pending%' ");
    $pendingStmt->execute([':eid' => $requestorId]);
    $pending = (int)($pendingStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    $approvedStmt = $pdo->prepare("SELECT COUNT(*) AS count FROM uar.approval_history WHERE employee_id = :eid AND action='approved'");
    $approvedStmt->execute([':eid' => $requestorId]);
    $approved = (int)($approvedStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    $rejectedStmt = $pdo->prepare("SELECT COUNT(*) AS count FROM uar.approval_history WHERE employee_id = :eid AND action='rejected'");
    $rejectedStmt->execute([':eid' => $requestorId]);
    $rejected = (int)($rejectedStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    $cancelledStmt = $pdo->prepare("SELECT COUNT(*) AS count FROM uar.approval_history WHERE employee_id = :eid AND action='cancelled'");
    $cancelledStmt->execute([':eid' => $requestorId]);
    $cancelled = (int)($cancelledStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    $total = $pending + $approved + $rejected + $cancelled;
} catch (PDOException $e) {
    error_log('[TS Request History] Error: ' . $e->getMessage());
    $requests = [];
    $pending = $approved = $rejected = $cancelled = $total = 0;
    $totalRecords = 0;
    $totalPages = 1;
    $errorMessage = 'Database Error';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Request History - Technical Support</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet" />
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif
        }

        [x-cloak] {
            display: none !important
        }
    </style>
</head>

<body class="bg-gray-100" x-data="{ sidebarOpen: true }" x-init="sidebarOpen = window.innerWidth >= 1024; window.addEventListener('resize', () => sidebarOpen = window.innerWidth >= 1024);">
    <?php include 'sidebar.php'; ?>
    <div x-show="sidebarOpen" @click="sidebarOpen=false" class="fixed inset-0 bg-black bg-opacity-40 z-40 md:hidden"></div>
    <div class="transition-all duration-300" :class="sidebarOpen ? 'md:ml-72' : 'ml-0'">
        <div class="bg-gradient-to-r from-blue-700 to-blue-900 border-b border-blue-800 sticky top-0 z-10 shadow-lg">
            <div class="flex items-center px-4 md:px-8 py-4 gap-3">
                <button @click="sidebarOpen=!sidebarOpen" type="button" class="inline-flex items-center justify-center rounded-lg p-2 text-white hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-300 mr-3" aria-label="Toggle sidebar">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                        <path clip-rule="evenodd" fill-rule="evenodd" d="M2 4.75A.75.75 0 012.75 4h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 4.75zm0 10.5a.75.75 0 01.75-.75h7.5a.75.75 0 010 1.5h-7.5a.75.75 0 01-.75-.75zM2 10a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 10z"></path>
                    </svg>
                </button>
                <h2 class="text-2xl md:text-3xl font-bold text-white">Request History</h2>
            </div>
        </div>
        <div class="p-4 md:p-6" data-aos="fade-up" data-aos-duration="800">
            <?php if (isset($errorMessage)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-6" role="alert">
                    <p class="font-bold">Error Loading Request History</p>
                    <p class="text-sm"><?php echo htmlspecialchars($errorMessage); ?></p>
                </div>
            <?php endif; ?>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="rounded-xl p-6 flex items-center bg-gradient-to-br from-yellow-500 via-yellow-400 to-yellow-300">
                    <div>
                        <p class="text-sm text-white">Pending</p>
                        <p class="text-2xl font-bold text-white"><?php echo $pending; ?></p>
                    </div>
                </div>
                <div class="rounded-xl p-6 flex items-center bg-gradient-to-br from-blue-500 via-blue-400 to-blue-300">
                    <div>
                        <p class="text-sm text-white">Total Requests</p>
                        <p class="text-2xl font-bold text-white"><?php echo $total; ?></p>
                    </div>
                </div>
                <div class="rounded-xl p-6 flex items-center bg-gradient-to-br from-green-500 via-green-400 to-green-300">
                    <div>
                        <p class="text-sm text-white">Approved</p>
                        <p class="text-2xl font-bold text-white"><?php echo $approved; ?></p>
                    </div>
                </div>
                <div class="rounded-xl p-6 flex items-center bg-gradient-to-br from-red-500 via-red-400 to-red-300">
                    <div>
                        <p class="text-sm text-white">Rejected</p>
                        <p class="text-2xl font-bold text-white"><?php echo $rejected; ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-md border border-gray-200 p-6 mb-6">
                <div class="flex items-center justify-between p-4 mb-4 bg-gradient-to-r from-blue-700 to-blue-900 rounded-lg shadow-md border-b-4 border-blue-950">
                    <div class="flex items-center"><svg class="w-5 h-5 text-white mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L12 11.414V15a1 1 0 01-.293.707l-2 2A1 1 0 018 17v-5.586L3.293 6.707A1 1 0 013 6V3z" clip-rule="evenodd" />
                        </svg>
                        <h3 class="text-lg font-semibold text-white">Filter Options</h3>
                    </div>
                </div>
                <form id="filter-form" method="get" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div><label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label><select id="status" name="status" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg w-full p-2.5">
                            <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All</option>
                            <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            <option value="history" <?php echo $statusFilter === 'history' ? 'selected' : ''; ?>>History (Completed)</option>
                        </select></div>
                    <div><label for="date" class="block text-sm font-medium text-gray-700 mb-2">Date Range</label><select id="date" name="date" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg w-full p-2.5">
                            <option value="all" <?php echo $dateFilter === 'all' ? 'selected' : ''; ?>>All Time</option>
                            <option value="today" <?php echo $dateFilter === 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="week" <?php echo $dateFilter === 'week' ? 'selected' : ''; ?>>This Week</option>
                            <option value="month" <?php echo $dateFilter === 'month' ? 'selected' : ''; ?>>This Month</option>
                        </select></div>
                    <div><label for="search" class="block text-sm font-medium text-gray-700 mb-2">Search</label><input type="text" id="search" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Search requests..." class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg w-full p-2.5" /></div>
                    <div class="flex items-end"><button type="submit" class="w-full text-white bg-gradient-to-r from-blue-700 to-blue-900 hover:from-blue-800 hover:to-blue-950 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 inline-flex items-center justify-center transition"><svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L12 11.414V15a1 1 0 01-.293.707l-2 2A1 1 0 018 17v-5.586L3.293 6.707A1 1 0 013 6V3z" clip-rule="evenodd" />
                            </svg>Apply Filters</button></div>
                </form>
            </div>
            <div class="bg-white rounded-xl shadow-md border border-gray-200">
                <div class="border-b border-gray-200 px-6 py-4 bg-gray-50">
                    <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-3">
                        <div class="flex items-center"><svg class="w-5 h-5 text-blue-700 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0015.414 6L12 2.586A2 2 0 0010.586 2H6zm5 6a1 1 0 10-2 0v3.586l-1.293-1.293a1 1 0 10-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 11.586V8z" clip-rule="evenodd" />
                            </svg>
                            <h3 class="text-xl font-semibold text-gray-800">Request History</h3>
                        </div>
                        <div class="flex flex-wrap gap-2"><button onclick="filterRequests('all')" class="px-3 py-1.5 text-sm font-medium bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100">All</button><button onclick="filterRequests('pending')" class="px-3 py-1.5 text-sm font-medium text-gray-600 bg-gray-50 hover:bg-gray-100 rounded-lg">Pending</button><button onclick="filterRequests('approved')" class="px-3 py-1.5 text-sm font-medium text-gray-600 bg-gray-50 hover:bg-gray-100 rounded-lg">Approved</button><button onclick="filterRequests('rejected')" class="px-3 py-1.5 text-sm font-medium text-gray-600 bg-gray-50 hover:bg-gray-100 rounded-lg">Rejected</button></div>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="text-xs text-white uppercase bg-gradient-to-r from-blue-700 to-blue-900 border-b-2 border-blue-950">
                            <tr>
                                <th class="px-6 py-3 text-left font-semibold tracking-wider">UAR REF NO.</th>
                                <th class="px-6 py-3 text-left font-semibold tracking-wider">Date Requested</th>
                                <th class="px-6 py-3 text-left font-semibold tracking-wider">Days Since</th>
                                <th class="px-6 py-3 text-left font-semibold tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if ($requests): foreach ($requests as $request): $dateObj = new DateTime($request['created_at'] ?? 'now');
                                    $daysSince = (new DateTime())->diff($dateObj)->days;
                                    $status = strtolower($request['status']);
                                    $statusClass = 'bg-gray-100 text-gray-800';
                                    $displayStatus = ucfirst(str_replace('_', ' ', $status));
                                    switch ($status) {
                                        case 'pending_superior':
                                            $statusClass = 'bg-yellow-100 text-yellow-800';
                                            $displayStatus = 'Pending Superior Review';
                                            break;
                                        case 'pending_technical':
                                        case 'pending_technical_support':
                                            $statusClass = 'bg-yellow-100 text-yellow-800';
                                            $displayStatus = 'Pending Technical Review';
                                            break;
                                        case 'pending_process_owner':
                                            $statusClass = 'bg-yellow-100 text-yellow-800';
                                            $displayStatus = 'Pending Process Owner Review';
                                            break;
                                        case 'pending_admin':
                                            $statusClass = 'bg-yellow-100 text-yellow-800';
                                            $displayStatus = 'Pending Admin Review';
                                            break;
                                        case 'pending_testing':
                                        case 'pending_testing_setup':
                                        case 'pending_testing_review':
                                            $statusClass = 'bg-yellow-100 text-yellow-800';
                                            $displayStatus = 'Pending Testing';
                                            break;
                                        case 'pending_help_desk':
                                            $statusClass = 'bg-yellow-100 text-yellow-800';
                                            $displayStatus = 'Pending Help Desk Review';
                                            break;
                                        case 'approved':
                                            $statusClass = 'bg-green-100 text-green-800';
                                            $displayStatus = 'Approved';
                                            break;
                                        case 'rejected':
                                            $statusClass = 'bg-red-100 text-red-800';
                                            $displayStatus = 'Rejected';
                                            break;
                                        case 'cancelled':
                                            $statusClass = 'bg-gray-100 text-gray-800';
                                            $displayStatus = 'Cancelled';
                                            break;
                                    }
                                    $searchableText = strtolower($request['access_request_number'] . ' ' . ($request['business_unit'] ?? '') . ' ' . ($request['department'] ?? '') . ' ' . ($request['access_type'] ?? '') . ' ' . ($request['system_type'] ?? ''));
                                    $dateTimestamp = $dateObj->getTimestamp(); ?>
                                    <tr class="cursor-pointer hover:bg-gray-50 data-row" data-status="<?php echo htmlspecialchars($status); ?>" data-date-timestamp="<?php echo $dateTimestamp; ?>" data-search-text="<?php echo htmlspecialchars($searchableText); ?>" onclick="window.location.href='view_request.php?id=<?php echo $request['request_id']; ?>'">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($request['access_request_number']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $dateObj->format('M d, Y'); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $daysSince . ' day/s'; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap"><span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>"><?php echo $displayStatus; ?></span></td>
                                    </tr>
                                <?php endforeach;
                            else: ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                                        <div class="flex flex-col items-center"><i class="bx bx-folder-open text-5xl text-gray-300 mb-2"></i>
                                            <p class="text-lg font-medium">No request history found</p>
                                            <p class="text-sm mt-1">Try adjusting your filters or create new access requests</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($totalRecords > $recordsPerPage): $queryParams = $_GET; ?>
                    <div class="bg-gray-50 px-4 md:px-6 py-4 border-t border-gray-200 flex flex-col md:flex-row items-center justify-between gap-4">
                        <div class="text-sm text-gray-700">Showing <span class="font-medium"><?php echo $requests ? $offset + 1 : 0; ?></span> to <span class="font-medium"><?php echo min($offset + count($requests), $totalRecords); ?></span> of <span class="font-medium"><?php echo $totalRecords; ?></span> results</div>
                        <div class="flex items-center flex-wrap justify-center gap-2">
                            <a href="?<?php echo http_build_query(array_merge($queryParams, ['page' => max(1, $currentPage - 1)])); ?>" class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 <?php echo $currentPage <= 1 ? 'opacity-50 cursor-not-allowed pointer-events-none' : ''; ?>"><i class='bx bx-chevron-left'></i> Previous</a>
                            <div class="flex items-center space-x-1">
                                <?php $startPage = max(1, $currentPage - 2);
                                $endPage = min($totalPages, $currentPage + 2);
                                if ($startPage > 1): ?>
                                    <a href="?<?php echo http_build_query(array_merge($queryParams, ['page' => 1])); ?>" class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">1</a>
                                    <?php if ($startPage > 2): ?><span class="px-2 text-gray-500">...</span><?php endif; ?>
                                <?php endif;
                                for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <a href="?<?php echo http_build_query(array_merge($queryParams, ['page' => $i])); ?>" class="px-3 py-2 text-sm font-medium rounded-lg <?php echo $i == $currentPage ? 'bg-blue-600 text-white' : 'text-gray-700 bg-white border border-gray-300 hover:bg-gray-50'; ?>"><?php echo $i; ?></a>
                                    <?php endfor;
                                if ($endPage < $totalPages): if ($endPage < $totalPages - 1): ?><span class="px-2 text-gray-500">...</span><?php endif; ?>
                                    <a href="?<?php echo http_build_query(array_merge($queryParams, ['page' => $totalPages])); ?>" class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"><?php echo $totalPages; ?></a>
                                <?php endif; ?>
                            </div>
                            <a href="?<?php echo http_build_query(array_merge($queryParams, ['page' => min($totalPages, $currentPage + 1)])); ?>" class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 <?php echo $currentPage >= $totalPages ? 'opacity-50 cursor-not-allowed pointer-events-none' : ''; ?>">Next <i class='bx bx-chevron-right'></i></a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php include '../footer.php'; ?>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof AOS !== 'undefined') {
                AOS.init({
                    duration: 800,
                    easing: 'ease-in-out',
                    once: true,
                    offset: 100
                });
            } else {
                document.querySelectorAll('[data-aos]').forEach(el => {
                    el.style.opacity = 1;
                    el.style.transform = 'none';
                });
            }
        });

        function filterRequests(status) {
            const buttons = document.querySelectorAll('.flex.flex-wrap.gap-2 button');
            buttons.forEach(btn => {
                const t = btn.textContent.toLowerCase().trim();
                if (t === status || (status === 'all' && t === 'all')) {
                    btn.classList.add('bg-blue-50', 'text-blue-700');
                    btn.classList.remove('text-gray-600', 'bg-gray-50');
                } else {
                    btn.classList.remove('bg-blue-50', 'text-blue-700');
                    btn.classList.add('text-gray-600', 'bg-gray-50');
                }
            });
            const statusSelect = document.getElementById('status');
            if (statusSelect) statusSelect.value = status;
            applyFilters();
        }

        function applyFilters() {
            const searchValue = (document.getElementById('search')?.value || '').toLowerCase().trim();
            const statusValue = document.getElementById('status')?.value || 'all';
            const dateValue = document.getElementById('date')?.value || 'all';
            const rows = document.querySelectorAll('tbody tr.data-row');
            let visible = 0;
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const todayTs = Math.floor(today.getTime() / 1000);
            const weekAgoTs = Math.floor((new Date(today.getTime() - 7 * 86400000)).getTime() / 1000);
            const monthAgoTs = Math.floor((new Date(today.getTime() - 30 * 86400000)).getTime() / 1000);
            rows.forEach(row => {
                let show = true;
                const rowStatus = row.getAttribute('data-status') || '';
                const rowTs = parseInt(row.getAttribute('data-date-timestamp') || '0');
                const rowSearch = row.getAttribute('data-search-text') || '';
                if (statusValue !== 'all') {
                    if (statusValue === 'pending') {
                        show = show && rowStatus.startsWith('pending');
                    } else if (statusValue === 'history') {
                        show = show && !rowStatus.startsWith('pending');
                    } else {
                        show = show && rowStatus === statusValue;
                    }
                }
                if (dateValue !== 'all') {
                    if (dateValue === 'today') show = show && rowTs >= todayTs;
                    else if (dateValue === 'week') show = show && rowTs >= weekAgoTs;
                    else if (dateValue === 'month') show = show && rowTs >= monthAgoTs;
                }
                if (searchValue) show = show && rowSearch.includes(searchValue);
                row.style.display = show ? '' : 'none';
                if (show) visible++;
            });
            const pagination = document.querySelector('.bg-gray-50.px-4');
            if (pagination) {
                if (searchValue || statusValue !== 'all' || dateValue !== 'all') pagination.style.display = 'none';
                else pagination.style.display = '';
            }
        } ['status', 'date', 'search'].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener(id === 'search' ? 'input' : 'change', () => {
                    if (id === 'search') {
                        clearTimeout(window.__st);
                        window.__st = setTimeout(applyFilters, 300);
                    } else applyFilters();
                });
            }
        });
        applyFilters();
    </script>
</body>

</html>
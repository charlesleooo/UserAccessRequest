<?php
session_start();
require_once '../config.php';

// Authentication check
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'uar_admin') {
    header("Location: login.php");
    exit();
}

// Pagination
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
if (!in_array($perPage, [10, 20, 30, 50])) {
    $perPage = 10;
}

$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($currentPage < 1) {
    $currentPage = 1;
}

// Get all requests from both active and history tables
try {
    // Query to get all active requests (pending only)
    $activeQuery = "SELECT 
            ar.access_request_number,
            ar.requestor_name,
            ar.business_unit,
            ar.department,
            ar.submission_date,
            ar.status,
            ISNULL(ir.access_type, gr.access_type) as access_type,
            au.username as admin_username
        FROM uar.access_requests ar
        LEFT JOIN uar.individual_requests ir ON ar.access_request_number = ir.access_request_number
        LEFT JOIN uar.group_requests gr ON ar.access_request_number = gr.access_request_number
        LEFT JOIN uar.admin_users au ON ar.admin_id = au.id
        WHERE ar.status LIKE 'pending%'";

    // Query to get all approved/rejected requests from history
    $historyQuery = "SELECT 
            ah.access_request_number,
            ah.requestor_name,
            ah.business_unit,
            ah.department,
            ah.created_at as submission_date,
            ah.action as status,
            ah.access_type,
            au.username as admin_username
        FROM uar.approval_history ah
        LEFT JOIN uar.admin_users au ON ah.admin_id = au.id
        WHERE ah.action IN ('approved', 'rejected')";

    // Combine both queries with UNION (removes duplicates)
    $sql = "SELECT * FROM (
                $activeQuery
                UNION
                $historyQuery
            ) AS combined_requests
            ORDER BY access_request_number DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $allRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count
    $totalRequests = count($allRequests);

    // Calculate pagination
    $totalPages = ceil($totalRequests / $perPage);
    $offset = ($currentPage - 1) * $perPage;

    // Apply pagination
    $requests = array_slice($allRequests, $offset, $perPage);
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error fetching requests: " . $e->getMessage();
    error_log("Requests query error: " . $e->getMessage());
    $requests = [];
    $totalRequests = 0;
    $totalPages = 0;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Access Requests - UAR Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
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
                        <h2 class="text-2xl font-bold text-white">All Access Requests</h2>
                        <p class="text-indigo-100 text-sm mt-1">System-wide view of all requests</p>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="relative">
                            <input type="text"
                                id="searchInput"
                                placeholder="Search requests..."
                                class="pl-10 pr-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <i class='bx bx-search absolute left-3 top-2.5 text-gray-400'></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Area -->
            <div class="p-8">
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="mb-6 p-4 bg-green-50 border-l-4 border-green-500 rounded-lg">
                        <div class="flex items-center">
                            <i class='bx bx-check-circle text-green-500 text-xl mr-2'></i>
                            <p class="text-green-700"><?php echo htmlspecialchars($_SESSION['success_message']); ?></p>
                        </div>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 rounded-lg">
                        <div class="flex items-center">
                            <i class='bx bx-error text-red-500 text-xl mr-2'></i>
                            <p class="text-red-700"><?php echo htmlspecialchars($_SESSION['error_message']); ?></p>
                        </div>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <div class="border-b border-gray-100 px-6 py-4">
                        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                            <div class="flex items-center gap-4">
                                <h3 class="text-xl font-semibold text-gray-800">All Requests (<?php echo $totalRequests; ?>)</h3>
                                <div class="flex items-center gap-2">
                                    <label class="text-sm text-gray-600">Show:</label>
                                    <select id="perPageSelect" onchange="changePerPage(this.value)" class="px-3 py-1 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                        <option value="10" <?php echo $perPage == 10 ? 'selected' : ''; ?>>10</option>
                                        <option value="20" <?php echo $perPage == 20 ? 'selected' : ''; ?>>20</option>
                                        <option value="30" <?php echo $perPage == 30 ? 'selected' : ''; ?>>30</option>
                                        <option value="50" <?php echo $perPage == 50 ? 'selected' : ''; ?>>50</option>
                                    </select>
                                    <span class="text-sm text-gray-600">entries</span>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button onclick="filterRequests('all')" class="filter-btn px-3 py-1 text-sm bg-indigo-50 text-indigo-600 rounded">All</button>
                                <button onclick="filterRequests('pending_superior')" class="filter-btn px-3 py-1 text-sm text-gray-500 hover:bg-gray-50 rounded">Superior</button>
                                <button onclick="filterRequests('pending_technical')" class="filter-btn px-3 py-1 text-sm text-gray-500 hover:bg-gray-50 rounded">Technical</button>
                                <button onclick="filterRequests('pending_process_owner')" class="filter-btn px-3 py-1 text-sm text-gray-500 hover:bg-gray-50 rounded">Process Owner</button>
                                <button onclick="filterRequests('pending_admin')" class="filter-btn px-3 py-1 text-sm text-gray-500 hover:bg-gray-50 rounded">Admin</button>
                                <button onclick="filterRequests('approved')" class="filter-btn px-3 py-1 text-sm text-gray-500 hover:bg-gray-50 rounded">Approved</button>
                                <button onclick="filterRequests('rejected')" class="filter-btn px-3 py-1 text-sm text-gray-500 hover:bg-gray-50 rounded">Rejected</button>
                            </div>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200" id="requestsTable">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Request Number</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Requestor</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Business Unit</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Access Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Days Pending</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($requests as $request): ?>
                                    <tr class="hover:bg-gray-50 transition-colors request-row" data-status="<?php echo htmlspecialchars($request['status']); ?>">
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
                                            <?php echo htmlspecialchars($request['department']); ?>
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
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php
                                            $submission_date = new DateTime($request['submission_date']);
                                            $today = new DateTime();
                                            $interval = $submission_date->diff($today);
                                            echo $interval->days . ' day/s';
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Controls -->
                    <?php if ($totalPages > 1): ?>
                        <div class="border-t border-gray-100 px-6 py-4">
                            <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                                <!-- Showing info -->
                                <div class="text-sm text-gray-600">
                                    Showing <?php echo min($offset + 1, $totalRequests); ?> to <?php echo min($offset + $perPage, $totalRequests); ?> of <?php echo $totalRequests; ?> entries
                                </div>

                                <!-- Pagination buttons -->
                                <div class="flex items-center gap-2">
                                    <!-- Previous button -->
                                    <?php if ($currentPage > 1): ?>
                                        <a href="?page=<?php echo $currentPage - 1; ?>&per_page=<?php echo $perPage; ?>"
                                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                            <i class='bx bx-chevron-left'></i> Previous
                                        </a>
                                    <?php else: ?>
                                        <span class="px-4 py-2 text-sm font-medium text-gray-400 bg-gray-100 border border-gray-200 rounded-lg cursor-not-allowed">
                                            <i class='bx bx-chevron-left'></i> Previous
                                        </span>
                                    <?php endif; ?>

                                    <!-- Page numbers -->
                                    <div class="flex items-center gap-1">
                                        <?php
                                        $startPage = max(1, $currentPage - 2);
                                        $endPage = min($totalPages, $currentPage + 2);

                                        if ($startPage > 1): ?>
                                            <a href="?page=1&per_page=<?php echo $perPage; ?>"
                                                class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                                1
                                            </a>
                                            <?php if ($startPage > 2): ?>
                                                <span class="px-2 text-gray-500">...</span>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                            <?php if ($i == $currentPage): ?>
                                                <span class="px-3 py-2 text-sm font-medium text-white bg-indigo-600 border border-indigo-600 rounded-lg">
                                                    <?php echo $i; ?>
                                                </span>
                                            <?php else: ?>
                                                <a href="?page=<?php echo $i; ?>&per_page=<?php echo $perPage; ?>"
                                                    class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                                    <?php echo $i; ?>
                                                </a>
                                            <?php endif; ?>
                                        <?php endfor; ?>

                                        <?php if ($endPage < $totalPages): ?>
                                            <?php if ($endPage < $totalPages - 1): ?>
                                                <span class="px-2 text-gray-500">...</span>
                                            <?php endif; ?>
                                            <a href="?page=<?php echo $totalPages; ?>&per_page=<?php echo $perPage; ?>"
                                                class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                                <?php echo $totalPages; ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Next button -->
                                    <?php if ($currentPage < $totalPages): ?>
                                        <a href="?page=<?php echo $currentPage + 1; ?>&per_page=<?php echo $perPage; ?>"
                                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                            Next <i class='bx bx-chevron-right'></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="px-4 py-2 text-sm font-medium text-gray-400 bg-gray-100 border border-gray-200 rounded-lg cursor-not-allowed">
                                            Next <i class='bx bx-chevron-right'></i>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchValue = this.value.toLowerCase();
            const rows = document.querySelectorAll('.request-row');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchValue) ? '' : 'none';
            });
        });

        // Filter functionality
        function filterRequests(status) {
            const rows = document.querySelectorAll('.request-row');
            const buttons = document.querySelectorAll('.filter-btn');

            buttons.forEach(btn => {
                btn.className = 'filter-btn px-3 py-1 text-sm text-gray-500 hover:bg-gray-50 rounded';
            });

            event.target.className = 'filter-btn px-3 py-1 text-sm bg-indigo-50 text-indigo-600 rounded';

            rows.forEach(row => {
                if (status === 'all') {
                    row.style.display = '';
                } else {
                    row.style.display = row.dataset.status === status ? '' : 'none';
                }
            });
        }

        // Change per page
        function changePerPage(value) {
            const url = new URL(window.location.href);
            url.searchParams.set('per_page', value);
            url.searchParams.set('page', '1'); // Reset to page 1 when changing per page
            window.location.href = url.toString();
        }
    </script>
</body>

</html>
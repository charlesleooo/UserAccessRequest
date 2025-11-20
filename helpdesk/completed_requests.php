<?php
session_start();
require_once '../config.php';

// Check if help desk is logged in
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'help_desk') {
    header('Location: ../admin/login.php');
    exit();
}

// Check if the user needs to enter the encryption code
if (
    !isset($_SESSION['requests_verified']) || !$_SESSION['requests_verified'] ||
    (time() - $_SESSION['requests_verified_time'] > 1800)
) { // Expire after 30 minutes
    header('Location: requests_auth.php');
    exit();
}

// Get all completed requests (approved or rejected)
try {
    // Get from approval_history (most reliable source for completed requests)
    // Get only the latest entry per access_request_number to avoid duplicates
    // Join with individual_requests/group_requests to get date_needed and application_system
    $sql = "SELECT DISTINCT
                NULL as id,
                ah.access_request_number,
                ah.requestor_name,
                ah.employee_id,
                ISNULL(e.employee_email, ah.email) as employee_email,
                ah.business_unit,
                ah.department,
                ah.access_type as access_level,
                ah.system_type,
                (SELECT TOP 1 application_system FROM uar.individual_requests WHERE access_request_number = ah.access_request_number) as ir_app_system,
                (SELECT TOP 1 application_system FROM uar.group_requests WHERE access_request_number = ah.access_request_number) as gr_app_system,
                COALESCE(
                    (SELECT TOP 1 application_system FROM uar.individual_requests WHERE access_request_number = ah.access_request_number),
                    (SELECT TOP 1 application_system FROM uar.group_requests WHERE access_request_number = ah.access_request_number)
                ) as application_system,
                NULL as other_system_type,
                ah.created_at as submission_date,
                ah.action as status,
                CASE 
                    WHEN ah.action = 'approved' THEN 'Approved'
                    WHEN ah.action = 'rejected' THEN 'Rejected'
                    ELSE CAST(ah.action AS VARCHAR)
                END as status_display,
                CASE 
                    WHEN ah.action = 'approved' THEN 'approved'
                    WHEN ah.action = 'rejected' THEN 'rejected'
                    ELSE 'other'
                END as final_status,
                COALESCE(
                    (SELECT TOP 1 date_needed FROM uar.individual_requests WHERE access_request_number = ah.access_request_number),
                    (SELECT TOP 1 date_needed FROM uar.group_requests WHERE access_request_number = ah.access_request_number)
                ) as date_needed,
                ah.created_at as completion_date
            FROM uar.approval_history ah
            INNER JOIN (
                SELECT access_request_number, MAX(history_id) AS latest_id
                FROM uar.approval_history
                WHERE action IN ('approved', 'rejected')
                GROUP BY access_request_number
            ) latest ON latest.latest_id = ah.history_id
            LEFT JOIN uar.employees e ON ah.employee_id = e.employee_id
            WHERE ah.action IN ('approved', 'rejected')
            ORDER BY ah.created_at DESC";

    $stmt = $pdo->query($sql);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Debug: Log request count
    error_log("Completed requests from approval_history: " . count($requests));
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error fetching completed requests: " . $e->getMessage();
    error_log("Completed requests query error: " . $e->getMessage());
    $requests = [];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request History</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">

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
            <div class="bg-gradient-to-r from-blue-700 to-blue-900 border-b border-blue-800 sticky top-0 z-10 shadow-lg">
                <div class="px-4 md:px-8 py-4">
                    <h1 class="text-2xl md:text-3xl font-bold text-white">Review History</h1>
                </div>
            </div>

            <!-- Content -->
            <div class="p-4 md:p-8">
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="mb-4 bg-red-50 border-l-4 border-red-500 p-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class='bx bx-error text-xl text-red-500'></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-red-700">
                                    <?php echo htmlspecialchars($_SESSION['error_message']); ?>
                                    <?php unset($_SESSION['error_message']); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Filter Section -->
                <div class="mb-4 bg-white rounded-lg shadow p-4">
                    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                        <!-- Left: Standard filter form -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
                            <div class="flex flex-col gap-1">
                                <label for="statusSelect" class="text-sm text-gray-700">Status</label>
                                <select id="statusSelect" class="h-10 border border-gray-200 rounded-lg px-3 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20">
                                    <option value="all" selected>All</option>
                                    <option value="approved">Approved</option>
                                    <option value="rejected">Rejected</option>
                                </select>
                            </div>
                            <div class="flex flex-col gap-1">
                                <label for="dateFieldSelect" class="text-sm text-gray-700">Date Field</label>
                                <select id="dateFieldSelect" class="h-10 border border-gray-200 rounded-lg px-3 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20">
                                    <option value="completed" selected>Date Completed</option>
                                    <option value="requested">Date Requested</option>
                                    <option value="needed">Date Needed</option>
                                </select>
                            </div>
                            <div class="flex flex-col gap-1">
                                <label for="dateStart" class="text-sm text-gray-700">From</label>
                                <input type="date" id="dateStart" class="h-10 border border-gray-200 rounded-lg px-3 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20" />
                            </div>
                            <div class="flex flex-col gap-1">
                                <label for="dateEnd" class="text-sm text-gray-700">To</label>
                                <input type="date" id="dateEnd" class="h-10 border border-gray-200 rounded-lg px-3 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20" />
                            </div>
                            <div class="sm:col-span-2 lg:col-span-4 flex items-center gap-2">
                                <button type="button" id="applyFiltersBtn" class="inline-flex items-center h-10 px-4 rounded-lg text-sm bg-primary-600 text-white hover:bg-primary-700">
                                    Apply
                                </button>
                                <button type="button" id="resetFiltersBtn" class="inline-flex items-center h-10 px-4 rounded-lg text-sm bg-gray-100 text-gray-700 hover:bg-gray-200">
                                    Reset
                                </button>
                            </div>
                        </div>

                        <!-- Right: length + search -->
                        <div class="flex items-center gap-3 w-full md:w-auto md:justify-end">
                            <div class="flex items-center gap-2">
                                <label for="lengthSelectRequests" class="text-sm text-gray-700">Show</label>
                                <select id="lengthSelectRequests" class="h-10 border border-gray-200 rounded-lg px-3 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20">
                                    <option value="10">10</option>
                                    <option value="20">20</option>
                                    <option value="30">30</option>
                                    <option value="50">50</option>
                                </select>
                                <span class="text-sm text-gray-700">entries</span>
                            </div>
                            <div class="relative w-full md:w-72">
                                <input type="text"
                                    id="searchInputRequests"
                                    placeholder="Search requests..."
                                    class="pl-10 pr-4 h-10 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 w-full">
                                <i class='bx bx-search absolute left-3 top-2.5 text-gray-400'></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200" id="completedRequestsTable">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">UAR REF NO.</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Requestor</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Company</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Requested</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Needed</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Completed</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (!empty($requests)): ?>
                                    <?php foreach ($requests as $request): ?>
                                        <tr class="request-row hover:bg-gray-50 cursor-pointer <?php echo 'filter-' . $request['final_status']; ?>"
                                            data-filter="<?php echo 'filter-' . $request['final_status']; ?>"
                                            onclick="viewRequest('<?php echo htmlspecialchars($request['access_request_number'], ENT_QUOTES); ?>')">
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
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"
                                                data-order="<?php echo htmlspecialchars(date('Y-m-d', strtotime($request['submission_date']))); ?>"
                                                data-raw-date="<?php echo htmlspecialchars(date('Y-m-d', strtotime($request['submission_date']))); ?>">
                                                <?php echo date('M d, Y', strtotime($request['submission_date'])); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"
                                                data-order="<?php echo $request['date_needed'] ? htmlspecialchars(date('Y-m-d', strtotime($request['date_needed']))) : ''; ?>"
                                                data-raw-date="<?php echo $request['date_needed'] ? htmlspecialchars(date('Y-m-d', strtotime($request['date_needed']))) : ''; ?>">
                                                <?php echo $request['date_needed'] ? date('M d, Y', strtotime($request['date_needed'])) : 'N/A'; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"
                                                data-order="<?php echo $request['completion_date'] ? htmlspecialchars(date('Y-m-d', strtotime($request['completion_date']))) : ''; ?>"
                                                data-raw-date="<?php echo $request['completion_date'] ? htmlspecialchars(date('Y-m-d', strtotime($request['completion_date']))) : ''; ?>">
                                                <?php echo $request['completion_date'] ? date('M d, Y', strtotime($request['completion_date'])) : 'N/A'; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $request['final_status'] === 'approved' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                    <?php echo htmlspecialchars($request['status_display']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                                <button onclick="event.stopPropagation(); printRequest('<?php echo htmlspecialchars($request['access_request_number'], ENT_QUOTES); ?>')"
                                                    class="text-gray-600 hover:text-gray-900 inline-flex items-center">
                                                    <i class='bx bx-printer mr-1'></i>
                                                    Print
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="px-6 py-4 text-center text-gray-500">
                                            No completed requests found
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

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        // DataTables integration with custom filters
        let currentStatusFilter = 'all';

        // Custom global filter combining status + date range
        $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
            if (settings.nTable.getAttribute('id') !== 'completedRequestsTable') return true;

            const row = settings.aoData[dataIndex].nTr;
            const rowFilter = row.getAttribute('data-filter');

            // Status filter: pass when 'all' or matches
            const statusPass = (currentStatusFilter === 'all') || (rowFilter === 'filter-' + currentStatusFilter);
            if (!statusPass) return false;

            // Date range filter
            const field = document.getElementById('dateFieldSelect')?.value || 'completed';
            const startVal = document.getElementById('dateStart')?.value || '';
            const endVal = document.getElementById('dateEnd')?.value || '';

            if (!startVal && !endVal) return true; // no date filter applied

            // Map field to column index
            const colMap = {
                requested: 4,
                needed: 5,
                completed: 6
            };
            const colIdx = colMap[field] ?? 6;

            const cell = row.cells[colIdx];
            if (!cell) return true;
            const iso = cell.getAttribute('data-raw-date') || '';
            if (!iso) return false; // no date in row, exclude when filtering

            if (startVal && iso < startVal) return false;
            if (endVal && iso > endVal) return false;
            return true;
        });

        document.addEventListener('DOMContentLoaded', function() {
            const table = $('#completedRequestsTable').DataTable({
                pageLength: 10,
                lengthMenu: [10, 20, 30, 50],
                dom: 'rtip', // remove built-in search and length controls
                order: [
                    [6, 'desc']
                ], // Date Completed desc by default
                columnDefs: [{
                        targets: 8,
                        orderable: false
                    } // Actions column not orderable
                ],
                language: {
                    lengthMenu: 'Show _MENU_ entries per page',
                    info: 'Showing _START_ to _END_ of _TOTAL_ entries',
                    emptyTable: 'No completed requests found',
                    paginate: {
                        first: 'First',
                        last: 'Last',
                        next: 'Next',
                        previous: 'Previous'
                    },
                    search: 'Search in table:'
                }
            });

            // Hook external search input
            $('#searchInputRequests').on('keyup', function() {
                table.search($(this).val()).draw();
            });

            // Hook external length select
            const initialLen = table.page.len();
            $('#lengthSelectRequests').val(String(initialLen));
            $('#lengthSelectRequests').on('change', function() {
                const val = parseInt($(this).val(), 10);
                if (!isNaN(val)) {
                    table.page.len(val).draw();
                }
            });

            // Apply/Reset for standard filter UX
            $('#applyFiltersBtn').on('click', function() {
                currentStatusFilter = $('#statusSelect').val() || 'all';
                table.draw();
            });
            $('#resetFiltersBtn').on('click', function() {
                $('#statusSelect').val('all');
                $('#dateFieldSelect').val('completed');
                $('#dateStart').val('');
                $('#dateEnd').val('');
                currentStatusFilter = 'all';
                // Clear table search
                $('#searchInputRequests').val('');
                table.search('');
                table.draw();
            });

            // Enhance row click to work with DataTables pagination
            $('#completedRequestsTable tbody').on('click', 'tr', function(e) {
                // Ignore clicks on buttons/links inside the row
                if (e.target.closest('button, a, i')) return;
                const arn = this.querySelector('td')?.textContent?.trim();
                if (arn) viewRequest(arn);
            });

            // No legacy button-based status filters
        });

        // Global helper functions
        function viewRequest(accessRequestNumber) {
            window.location.href = 'view_request.php?access_request_number=' + encodeURIComponent(accessRequestNumber) + '&from_history=true';
        }

        function printRequest(accessRequestNumber) {
            const url = 'tcpdf_print_record.php?access_request_number=' + encodeURIComponent(accessRequestNumber);
            window.open(url, '_blank');
        }

        // filterRequests is redefined above to use DataTables
    </script>
</body>
<?php include '../footer.php'; ?>

</html>
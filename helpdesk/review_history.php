<?php
session_start();
require_once '../config.php';

// Check if help desk is logged in
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'help_desk') {
    header('Location: ../admin/login.php');
    exit();
}

$admin_id = $_SESSION['admin_id'];
$admin_username = $_SESSION['admin_username'] ?? '';

try {
    // First, get the admin_users.id from the database
    $adminQuery = $pdo->prepare("SELECT id FROM uar.admin_users WHERE username = :username OR username = :employee_id");
    $adminQuery->execute([
        'username' => $admin_username,
        'employee_id' => $admin_id
    ]);
    $adminRecord = $adminQuery->fetch(PDO::FETCH_ASSOC);
    $admin_users_id = $adminRecord ? $adminRecord['id'] : $admin_id;

    // Get requests reviewed by this help desk from both active requests and approval history
    $stmt = $pdo->prepare("
        SELECT * FROM (
            SELECT 
                ar.access_request_number,
                ar.requestor_name,
                ar.department,
                ar.business_unit,
                ar.access_level as access_type,
                ar.system_type,
                ar.help_desk_review_date as review_date,
                ar.help_desk_notes as review_notes,
                ar.status,
                ar.employee_id,
                ar.employee_email as email,
                CASE 
                    WHEN ar.status = 'rejected' THEN 'Rejected'
                    ELSE 'Approved/Forwarded'
                END as action,
                ROW_NUMBER() OVER (PARTITION BY ar.access_request_number ORDER BY ar.help_desk_review_date DESC) as rn
            FROM 
                uar.access_requests ar
            WHERE 
                ar.help_desk_id = :help_desk_id1 
                AND ar.help_desk_review_date IS NOT NULL
            
            UNION ALL
            
            SELECT 
                ah.access_request_number,
                ah.requestor_name,
                ah.department,
                ah.business_unit,
                ah.access_type,
                ah.system_type,
                ah.created_at as review_date,
                ah.help_desk_notes as review_notes,
                ah.action as status,
                ah.employee_id,
                ah.email,
                CASE 
                    WHEN ah.action = 'rejected' THEN 'Rejected'
                    ELSE 'Approved/Forwarded'
                END as action,
                ROW_NUMBER() OVER (PARTITION BY ah.access_request_number ORDER BY ah.created_at DESC) as rn
            FROM 
                uar.approval_history ah
            WHERE 
                ah.help_desk_id = :help_desk_id2
        ) combined
        WHERE rn = 1
        ORDER BY review_date DESC
    ");

    $stmt->execute([
        'help_desk_id1' => $admin_users_id,
        'help_desk_id2' => $admin_users_id
    ]);
    $reviewed_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error fetching review history: " . $e->getMessage();
    $reviewed_requests = [];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review History</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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
                    <h1 class="text-xl md:text-2xl font-bold text-white">Review History</h1>
                </div>
            </div>

            <!-- Content -->
            <div class="p-4 md:p-8">
                <div class="bg-white rounded-xl shadow overflow-hidden">
                    <!-- Filter Section -->
                    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <i class='bx bx-history text-2xl text-gray-600'></i>
                            <h2 class="text-lg font-semibold text-gray-800">Request History</h2>
                        </div>
                        <div class="flex items-center gap-2">
                            <button onclick="filterRequests('all')" 
                                    id="filter-all" 
                                    class="filter-btn active px-4 py-2 rounded-lg text-sm font-medium bg-blue-100 text-blue-700 hover:bg-blue-200 transition-colors">
                                All
                            </button>
                            <button onclick="filterRequests('pending')" 
                                    id="filter-pending" 
                                    class="filter-btn px-4 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-100 transition-colors">
                                Pending
                            </button>
                            <button onclick="filterRequests('approved')" 
                                    id="filter-approved" 
                                    class="filter-btn px-4 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-100 transition-colors">
                                Approved
                            </button>
                            <button onclick="filterRequests('rejected')" 
                                    id="filter-rejected" 
                                    class="filter-btn px-4 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-100 transition-colors">
                                Rejected
                            </button>
                        </div>
                    </div>

                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="bg-green-50 p-4 border-l-4 border-green-500">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class='bx bx-check-circle text-xl text-green-500'></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-green-700">
                                        <?php echo $_SESSION['success_message']; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <?php unset($_SESSION['success_message']); ?>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="bg-red-50 p-4 border-l-4 border-red-500">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class='bx bx-error text-xl text-red-500'></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-red-700">
                                        <?php echo $_SESSION['error_message']; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <?php unset($_SESSION['error_message']); ?>
                    <?php endif; ?>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">UAR REF NO.</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Requestor</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Business Unit Entity</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Review Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (!empty($reviewed_requests)): ?>
                                    <?php foreach ($reviewed_requests as $index => $request): ?>
                                        <?php 
                                        $actionFilter = strtolower($request['action']);
                                        $statusFilter = strtolower($request['status'] ?? '');
                                        $filterClass = '';
                                        
                                        if ($actionFilter === 'rejected' || $statusFilter === 'rejected') {
                                            $filterClass = 'filter-rejected';
                                        } elseif ($actionFilter === 'approved/forwarded' || $statusFilter === 'approved' || $statusFilter === 'approved') {
                                            $filterClass = 'filter-approved';
                                        } elseif (strpos($statusFilter, 'pending') !== false) {
                                            $filterClass = 'filter-pending';
                                        } else {
                                            $filterClass = 'filter-approved';
                                        }
                                        ?>
                                        <tr class="request-row hover:bg-gray-50 cursor-pointer <?php echo $filterClass; ?>" 
                                            data-filter="<?php echo $filterClass; ?>"
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
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo date('M d, Y', strtotime($request['review_date'])); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                <div class="flex items-center gap-3">
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                        <?php echo ($request['action'] === 'Rejected') ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?>">
                                                        <?php echo htmlspecialchars($request['action']); ?>
                                                    </span>
                                                    <button class="inline-flex items-center px-2 py-1 text-xs bg-white border border-gray-300 rounded hover:bg-gray-100"
                                                            title="Print"
                                                            onclick="event.stopPropagation(); printRequest('<?php echo htmlspecialchars($request['access_request_number'], ENT_QUOTES); ?>')">
                                                        <i class='bx bx-printer mr-1'></i> Print
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-12 text-center">
                                            <i class='bx bx-folder-open text-6xl text-gray-300'></i>
                                            <p class="mt-4 text-lg text-gray-500 font-medium">No review history found</p>
                                            <p class="mt-2 text-sm text-gray-400">Requests you review will appear here</p>
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
        function viewRequest(accessRequestNumber) {
            // Navigate to view_request.php with proper parameters
            window.location.href = 'view_request.php?access_request_number=' + encodeURIComponent(accessRequestNumber) + '&from_history=true';
        }

        function printRequest(accessRequestNumber) {
            const url = 'tcpdf_print_record.php?access_request_number=' + encodeURIComponent(accessRequestNumber);
            window.open(url, '_blank');
        }

        function filterRequests(filterType) {
            // Remove active class from all filter buttons
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active', 'bg-blue-100', 'text-blue-700');
                btn.classList.add('text-gray-600');
            });

            // Add active class to clicked filter button
            const clickedBtn = document.getElementById('filter-' + filterType);
            if (clickedBtn) {
                clickedBtn.classList.add('active', 'bg-blue-100', 'text-blue-700');
                clickedBtn.classList.remove('text-gray-600');
            }

            // Filter table rows
            const rows = document.querySelectorAll('.request-row');
            rows.forEach(row => {
                if (filterType === 'all') {
                    row.style.display = '';
                } else {
                    const rowFilter = row.getAttribute('data-filter');
                    if (rowFilter === 'filter-' + filterType) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                }
            });

            // Show/hide empty state
            const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none');
            const emptyRow = document.querySelector('tbody tr:not(.request-row)');
            if (emptyRow && visibleRows.length === 0 && filterType !== 'all') {
                if (!document.querySelector('.no-results-message')) {
                    const tbody = document.querySelector('tbody');
                    const newEmptyRow = document.createElement('tr');
                    newEmptyRow.className = 'no-results-message';
                    newEmptyRow.innerHTML = '<td colspan="6" class="px-6 py-12 text-center"><i class=\'bx bx-folder-open text-6xl text-gray-300\'></i><p class="mt-4 text-lg text-gray-500 font-medium">No ' + filterType + ' requests found</p></td>';
                    tbody.appendChild(newEmptyRow);
                }
            } else if (emptyRow && visibleRows.length > 0) {
                const noResultsMsg = document.querySelector('.no-results-message');
                if (noResultsMsg) {
                    noResultsMsg.remove();
                }
            }
        }
    </script>
</body>
<?php include '../footer.php'; ?>
</html>
<?php
session_start();
require_once '../config.php';

// Check if technical support is logged in
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'technical_support') {
    header('Location: ../admin/login.php');
    exit();
}

$admin_id = $_SESSION['admin_id'];
$admin_username = $_SESSION['admin_username'] ?? '';

try {
    // First, get the admin_users.id from the database
    // This is needed because the technical_id in approval_history matches admin_users.id, not the session admin_id
    $adminQuery = $pdo->prepare("SELECT id FROM uar.admin_users WHERE username = :username OR username = :employee_id");
    $adminQuery->execute([
        'username' => $admin_username,
        'employee_id' => $admin_id
    ]);
    $adminRecord = $adminQuery->fetch(PDO::FETCH_ASSOC);
    $technical_id = $adminRecord ? $adminRecord['id'] : $admin_id; // Fallback to session ID if not found
    
    // Debug: Log the technical_id being used
    error_log("Review History - Using technical_id: " . $technical_id . " for user: " . $admin_username);

    // Get requests reviewed by this technical support
    // First get from access_requests table
    $stmt1 = $pdo->prepare("
        SELECT 
            ar.access_request_number,
            ar.requestor_name,
            ar.department,
            ar.business_unit,
            ar.access_level as access_type,
            ar.system_type,
            ar.technical_review_date as review_date,
            ar.technical_notes as review_notes,
            ar.status,
            '' as justification,
            ar.employee_id,
            ar.employee_email as email,
            '' as role_access_type,
            '' as duration_type,
            '' as start_date,
            '' as end_date,
            CASE 
                WHEN ar.status = 'rejected' THEN 'Rejected'
                ELSE 'Approved/Forwarded'
            END as action
        FROM 
            uar.access_requests ar
        WHERE 
            ar.technical_id = :technical_id AND ar.technical_review_date IS NOT NULL
    ");
    
    $stmt1->execute(['technical_id' => $technical_id]);
    $access_requests = $stmt1->fetchAll(PDO::FETCH_ASSOC);
    
    // Then get from approval_history table
    $stmt2 = $pdo->prepare("
        SELECT 
            ah.access_request_number,
            ah.requestor_name,
            ah.department,
            ah.business_unit,
            ah.access_type,
            ah.system_type,
            ah.created_at as review_date,
            ah.technical_notes as review_notes,
            ah.action as status,
            ah.justification,
            ah.employee_id,
            ah.email,
            '' as role_access_type,
            ah.duration_type,
            ah.start_date,
            ah.end_date,
            CASE 
                WHEN ah.action = 'rejected' THEN 'Rejected'
                ELSE 'Approved/Forwarded'
            END as action
        FROM 
            uar.approval_history ah
        WHERE 
            ah.technical_id = :technical_id
    ");
    
    $stmt2->execute(['technical_id' => $technical_id]);
    $approval_requests = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    // Combine the results
    $reviewed_requests = array_merge($access_requests, $approval_requests);
    
    // Sort by review_date descending
    usort($reviewed_requests, function($a, $b) {
        $dateA = strtotime($a['review_date'] ?? '1970-01-01');
        $dateB = strtotime($b['review_date'] ?? '1970-01-01');
        return $dateB - $dateA;
    });

    // Debug: Log the number of results
    error_log("Review History - Found " . count($reviewed_requests) . " reviewed requests for technical_id: " . $technical_id);
    
} catch (PDOException $e) {
    error_log("Review History - Database error: " . $e->getMessage());
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
            <div class="bg-primary-900 border-b border-gray-200 sticky top-0 z-10">
                <div class="px-8 py-4">
                    <h1 class="text-2xl font-bold text-white">Review History</h1>
                </div>
            </div>

            <!-- Content -->
            <div class="p-8">
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

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Request No.</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Requestor</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Access Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Review Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (!empty($reviewed_requests)): ?>
                                    <?php foreach ($reviewed_requests as $index => $request): ?>
                                        <?php 
                                        $actionFilter = strtolower($request['action'] ?? '');
                                        $statusFilter = strtolower($request['status'] ?? '');
                                        $filterClass = '';
                                        
                                        if ($actionFilter === 'rejected' || $statusFilter === 'rejected') {
                                            $filterClass = 'filter-rejected';
                                        } elseif ($actionFilter === 'approved/forwarded' || $statusFilter === 'approved') {
                                            $filterClass = 'filter-approved';
                                        } elseif (strpos($statusFilter, 'pending') !== false) {
                                            $filterClass = 'filter-pending';
                                        } else {
                                            $filterClass = 'filter-approved';
                                        }
                                        ?>
                                        <tr class="request-row hover:bg-gray-50 cursor-pointer <?php echo $filterClass; ?>" 
                                            data-filter="<?php echo $filterClass; ?>"
                                            onclick="window.location='view_request.php?access_request_number=<?php echo urlencode($request['access_request_number'] ?? ''); ?>&from_history=true'">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($request['access_request_number'] ?? ''); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($request['requestor_name'] ?? ''); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($request['department'] ?? ''); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($request['access_type'] ?? ''); ?>
                                                <?php if (!empty($request['system_type'])): ?>
                                                    <span class="text-xs text-gray-400">(<?php echo htmlspecialchars($request['system_type'] ?? ''); ?>)</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo date('M d, Y H:i', strtotime($request['review_date'] ?? '1970-01-01')); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                    <?php echo ($request['action'] === 'Rejected') ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?>">
                                                    <?php echo htmlspecialchars($request['action'] ?? ''); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                                            No review history found
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
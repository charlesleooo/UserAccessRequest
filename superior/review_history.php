<?php
session_start();
require_once '../config.php';

// Check if superior is logged in
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'superior') {
    header('Location: ../admin/login.php');
    exit();
}

$admin_id = $_SESSION['admin_id'];
$admin_username = $_SESSION['admin_username'] ?? '';

// Pagination parameters
$recordsPerPage = 10;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$currentPage = max(1, $currentPage); // Ensure page is at least 1
$offset = ($currentPage - 1) * $recordsPerPage;

try {
    // First, get the admin_users.id from the database (same logic as helpdesk)
    $adminQuery = $pdo->prepare("SELECT id FROM uar.admin_users WHERE username = :username OR username = :employee_id");
    $adminQuery->execute([
        'username' => $admin_username,
        'employee_id' => $admin_id
    ]);
    $adminRecord = $adminQuery->fetch(PDO::FETCH_ASSOC);
    $admin_users_id = $adminRecord ? $adminRecord['id'] : $admin_id;

    // Base query for getting requests reviewed by this superior
    $baseQuery = "
        SELECT * FROM (
            SELECT 
                ar.access_request_number,
                ar.requestor_name,
                ar.department,
                ar.business_unit,
                ar.access_level as access_type,
                ar.system_type,
                ar.superior_review_date as review_date,
                ar.superior_notes as review_notes,
                ar.status,
                ar.employee_id,
                ar.employee_email as email,
                CASE 
                    WHEN ar.status = 'rejected' THEN 'Rejected'
                    ELSE 'Approved/Forwarded'
                END as action,
                ROW_NUMBER() OVER (PARTITION BY ar.access_request_number ORDER BY ar.superior_review_date DESC) as rn
            FROM 
                uar.access_requests ar
            WHERE 
                ar.superior_id = :superior_id1 
                AND ar.superior_review_date IS NOT NULL
            
            UNION ALL
            
            SELECT 
                ah.access_request_number,
                ah.requestor_name,
                ah.department,
                ah.business_unit,
                ah.access_type,
                ah.system_type,
                ah.created_at as review_date,
                ah.superior_notes as review_notes,
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
                ah.superior_id = :superior_id2
        ) combined
        WHERE rn = 1
    ";

    // First, get total count for pagination
    $countQuery = "SELECT COUNT(*) as total FROM ($baseQuery) as count_query";
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute([
        'superior_id1' => $admin_users_id,
        'superior_id2' => $admin_users_id
    ]);
    $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $totalPages = ceil($totalRecords / $recordsPerPage);
    
    // Ensure current page doesn't exceed total pages
    if ($currentPage > $totalPages && $totalPages > 0) {
        $currentPage = $totalPages;
        $offset = ($currentPage - 1) * $recordsPerPage;
    }
    
    // SQL Server requires literal integers for OFFSET and FETCH NEXT
    $offset = (int)$offset;
    $recordsPerPage = (int)$recordsPerPage;
    
    // Get requests reviewed by this superior with pagination
    $query = $baseQuery . " ORDER BY review_date DESC OFFSET $offset ROWS FETCH NEXT $recordsPerPage ROWS ONLY";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        'superior_id1' => $admin_users_id,
        'superior_id2' => $admin_users_id
    ]);
    $reviewed_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error fetching review history: " . $e->getMessage();
    $reviewed_requests = [];
    $totalRecords = 0;
    $totalPages = 0;
    $currentPage = 1;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Superior - Review History</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Boxicons -->
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <!-- Alpine.js for interactions -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.1/dist/cdn.min.js"></script>
    <!-- Flowbite CSS -->
    <link href="https://cdn.jsdelivr.net/npm/flowbite@2.5.1/dist/flowbite.min.css" rel="stylesheet" />
    <!-- Flowbite JS -->
    <script src="https://cdn.jsdelivr.net/npm/flowbite@2.5.1/dist/flowbite.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
                        <h1 class="<?php echo getComponentClass('header', 'title'); ?>">Review History</h1>
                    </div>
                    <?php renderPrivacyNotice(); ?>
                </div>
            </div>

            <!-- Content -->
            <div class="p-4 md:p-8">
                <div class="<?php echo getComponentClass('card'); ?>">
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
                            <?php 
                            $columns = ['UAR REF NO.', 'Requestor', 'Business Unit', 'Department', 'Review Date', 'Action'];
                            renderTableHeader($columns);
                            ?>
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
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                    <?php echo ($request['action'] === 'Rejected') ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?>">
                                                    <?php echo htmlspecialchars($request['action']); ?>
                                                </span>
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
                    
                    <!-- Pagination Controls -->
                    <?php if ($totalPages > 1): ?>
                    <div class="bg-gray-50 px-4 md:px-6 py-4 border-t border-gray-200 flex flex-col md:flex-row items-center justify-between gap-4">
                        <div class="text-sm text-gray-700">
                            Showing <span class="font-medium"><?php echo count($reviewed_requests) > 0 ? $offset + 1 : 0; ?></span> to 
                            <span class="font-medium"><?php echo min($offset + count($reviewed_requests), $totalRecords); ?></span> of 
                            <span class="font-medium"><?php echo $totalRecords; ?></span> results
                        </div>
                        <div class="flex items-center flex-wrap justify-center gap-2">
                            <?php
                            // Build URL with current filters
                            $queryParams = $_GET;
                            ?>
                            
                            <!-- Previous Button -->
                            <a href="?<?php echo http_build_query(array_merge($queryParams, ['page' => max(1, $currentPage - 1)])); ?>" 
                               class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 <?php echo $currentPage <= 1 ? 'opacity-50 cursor-not-allowed pointer-events-none' : ''; ?>">
                                <i class='bx bx-chevron-left'></i> Previous
                            </a>
                            
                            <!-- Page Numbers -->
                            <div class="flex items-center space-x-1">
                                <?php
                                $startPage = max(1, $currentPage - 2);
                                $endPage = min($totalPages, $currentPage + 2);
                                
                                // Show first page if not in range
                                if ($startPage > 1): ?>
                                    <a href="?<?php echo http_build_query(array_merge($queryParams, ['page' => 1])); ?>" 
                                       class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">1</a>
                                    <?php if ($startPage > 2): ?>
                                        <span class="px-2 text-gray-500">...</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <a href="?<?php echo http_build_query(array_merge($queryParams, ['page' => $i])); ?>" 
                                       class="px-3 py-2 text-sm font-medium rounded-lg <?php echo $i == $currentPage ? 'bg-blue-600 text-white' : 'text-gray-700 bg-white border border-gray-300 hover:bg-gray-50'; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <!-- Show last page if not in range -->
                                <?php if ($endPage < $totalPages): ?>
                                    <?php if ($endPage < $totalPages - 1): ?>
                                        <span class="px-2 text-gray-500">...</span>
                                    <?php endif; ?>
                                    <a href="?<?php echo http_build_query(array_merge($queryParams, ['page' => $totalPages])); ?>" 
                                       class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"><?php echo $totalPages; ?></a>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Next Button -->
                            <a href="?<?php echo http_build_query(array_merge($queryParams, ['page' => min($totalPages, $currentPage + 1)])); ?>" 
                               class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 <?php echo $currentPage >= $totalPages ? 'opacity-50 cursor-not-allowed pointer-events-none' : ''; ?>">
                                Next <i class='bx bx-chevron-right'></i>
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function viewRequest(accessRequestNumber) {
            // Navigate to view_request.php with proper parameters
            window.location.href = 'view_request.php?access_request_number=' + encodeURIComponent(accessRequestNumber) + '&from_history=true';
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
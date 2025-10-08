<?php
session_start();
require_once '../config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Get approval history
try {
    // Show only the latest history entry per access_request_number to avoid duplicates
    $sql = "SELECT h.*, a.username as admin_username, e.employee_name as admin_name
            FROM approval_history h
            INNER JOIN (
                SELECT access_request_number, MAX(history_id) AS latest_id
                FROM approval_history
                GROUP BY access_request_number
            ) latest ON latest.latest_id = h.history_id
            LEFT JOIN admin_users a ON h.admin_id = a.id
            LEFT JOIN employees e ON a.username = e.employee_id
            ORDER BY h.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error fetching history: " . $e->getMessage();
    $history = [];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approval History</title>

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
</head>

<body class="bg-gray-50">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Mobile menu button (for responsive design) -->
        <div class="lg:hidden fixed bottom-6 right-6 z-50">
            <button type="button" class="flex items-center justify-center w-14 h-14 rounded-full bg-primary-600 text-white shadow-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                <i class='bx bx-menu text-2xl'></i>
            </button>
        </div>

        <!-- Main Content -->
        <div class="flex-1 ml-72">
            <!-- Header -->
            <div class="bg-primary-900 border-b border-gray-200">
                <div class="flex justify-between items-center px-8 py-4">
                    <div>
                        <h2 class="text-2xl font-bold text-white">Approval History</h2>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="relative">
                            <input type="text"
                                id="searchInput"
                                placeholder="Search history..."
                                class="pl-10 pr-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20">
                            <i class='bx bx-search absolute left-3 top-2.5 text-gray-400'></i>
                        </div>
                        <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center">
                            <i class='bx bx-user text-xl text-gray-600'></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Area -->
            <div class="p-8">
                <div class="bg-white rounded-xl shadow-sm">
                    <div class="border-b border-gray-100 px-6 py-4">
                        <div class="flex justify-between items-center">
                            <h3 class="text-xl font-semibold text-gray-800">Access Request History</h3>
                            <div class="flex gap-2">
                                <button onclick="filterHistory('all')" class="px-3 py-1 text-sm bg-blue-50 text-primary rounded">All</button>
                                <button onclick="filterHistory('approved')" class="px-3 py-1 text-sm text-gray-500 hover:bg-gray-50 rounded">Approved</button>
                                <button onclick="filterHistory('rejected')" class="px-3 py-1 text-sm text-gray-500 hover:bg-gray-50 rounded">Rejected</button>
                            </div>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Request No.</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Requestor</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Business Unit</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Access Type</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Testing</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Review Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($history as $entry): ?>
                                    <tr class="hover:bg-gray-50 cursor-pointer" onclick="window.location.href='view_history.php?id=<?php echo $entry['history_id']; ?>'">
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($entry['access_request_number']); ?></div>
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($entry['requestor_name']); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($entry['email']); ?></div>
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($entry['business_unit']); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($entry['department']); ?></div>
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($entry['access_type']); ?></div>
                                            <?php if ($entry['system_type']): ?>
                                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($entry['system_type']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?php echo $entry['action'] === 'approved' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                <?php echo ucfirst($entry['action']); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <?php if (!empty($entry['testing_status'])): ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                <?php echo $entry['testing_status'] === 'success' ? 'bg-blue-100 text-blue-800' : ($entry['testing_status'] === 'failed' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800'); ?>">
                                                    <?php echo $entry['testing_status'] === 'success' ? 'Successful' : ($entry['testing_status'] === 'failed' ? 'Failed' : ($entry['testing_status'] === 'not_required' ? 'Not Required' : ucfirst($entry['testing_status']))); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-sm text-gray-500">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-500">
                                                <?php echo date('M d, Y', strtotime($entry['created_at'])); ?>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="flex space-x-2">
                                                <a href="tcpdf_print_record.php?id=<?php echo $entry['history_id']; ?>"
                                                    class="flex items-center px-3 py-1.5 bg-primary-50 text-primary-600 rounded-lg hover:bg-primary-100 transition-colors"
                                                    target="_blank"
                                                    onclick="event.stopPropagation();">
                                                    <i class='bx bx-printer text-lg'></i>
                                                    <span class="ml-1 text-sm">Print</span>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Function to filter history
        function filterHistory(type) {
            const rows = document.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const action = row.querySelector('td:nth-child(5)').textContent.trim().toLowerCase();
                if (type === 'all' || action === type) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchText = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchText) ? '' : 'none';
            });
        });
    </script>
</body>

</html>
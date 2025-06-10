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
    $sql = "SELECT h.*, a.username as admin_username, e.employee_name as admin_name 
            FROM approval_history h 
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
                        },
                        danger: {
                            DEFAULT: '#dc3545',
                            dark: '#c82333',
                        }
                    },
                    fontFamily: {
                        'sans': ['Inter', 'sans-serif'],
                    },
                }
            }
        }
    </script>
</head>

<body class="bg-gray-50">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <div class="fixed inset-y-0 left-0 z-50 w-72 bg-white shadow-lg transform transition-transform duration-300">
            <div class="flex flex-col h-full">
                <!-- Logo -->
                <div class="text-center">
                    <img src="../logo.png" alt="Alsons Agribusiness Logo" class="mt-1 w-60 h-auto mx-auto">
                </div><br>

                <!-- Navigation Menu -->
                <nav class="flex-1 pt-6 pb-4 px-4 space-y-1 overflow-y-auto">
                    <p class="px-4 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                        Main Menu
                    </p>
                    
                    <a href="dashboard.php" class="flex items-center px-4 py-3 text-gray-700 rounded-xl transition-all hover:bg-gray-50 hover:text-primary-600 group">
                        <span class="flex items-center justify-center w-9 h-9 bg-gray-100 text-gray-600 rounded-lg group-hover:bg-primary-50 group-hover:text-primary-600">
                            <i class='bx bxs-dashboard text-xl'></i>
                        </span>
                        <span class="ml-3">Dashboard</span>
                    </a>
                    
                    <a href="analytics.php" class="flex items-center px-4 py-3 text-gray-700 rounded-xl transition-all hover:bg-gray-50 hover:text-primary-600 group">
                        <span class="flex items-center justify-center w-9 h-9 bg-gray-100 text-gray-600 rounded-lg group-hover:bg-primary-50 group-hover:text-primary-600">
                            <i class='bx bx-line-chart text-xl'></i>
                        </span>
                        <span class="ml-3">Analytics</span>
                    </a>
                    
                    <a href="requests.php" class="flex items-center px-4 py-3 text-gray-700 rounded-xl transition-all hover:bg-gray-50 hover:text-primary-600 group">
                        <span class="flex items-center justify-center w-9 h-9 bg-gray-100 text-gray-600 rounded-lg group-hover:bg-primary-50 group-hover:text-primary-600">
                            <i class='bx bxs-message-square-detail text-xl'></i>
                        </span>
                        <span class="ml-3">Requests</span>
                    </a>
                    
                    <a href="#" class="flex items-center px-4 py-3 text-primary-600 bg-primary-50 rounded-xl transition-all hover:bg-primary-100 group">
                        <span class="flex items-center justify-center w-9 h-9 bg-primary-100 text-primary-600 rounded-lg group-hover:bg-primary-200">
                            <i class='bx bx-history text-xl'></i>
                        </span>
                        <span class="ml-3 font-medium">Approval History</span>
                    </a>

                    <!-- Add a divider -->
                    <div class="my-4 border-t border-gray-100"></div>
                    
                    <p class="px-4 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                        Account
                    </p>
                    
                    <a href="user_management.php" class="flex items-center px-4 py-3 text-gray-700 rounded-xl transition-all hover:bg-gray-50 hover:text-primary-600 group">
                        <span class="flex items-center justify-center w-9 h-9 bg-gray-100 text-gray-600 rounded-lg group-hover:bg-primary-50 group-hover:text-primary-600">
                            <i class='bx bx-user text-xl'></i>
                        </span>
                        <span class="ml-3">User Management</span>
                    </a>
                    
                    <a href="settings.php" class="flex items-center px-4 py-3 text-gray-700 rounded-xl transition-all hover:bg-gray-50 hover:text-primary-600 group">
                        <span class="flex items-center justify-center w-9 h-9 bg-gray-100 text-gray-600 rounded-lg group-hover:bg-primary-50 group-hover:text-primary-600">
                            <i class='bx bx-cog text-xl'></i>
                        </span>
                        <span class="ml-3">Settings</span>
                    </a>
                </nav>
                
                <!-- Logout Button -->
                <div class="p-4 border-t border-gray-100">
                    <a href="logout.php" class="flex items-center px-4 py-3 text-red-600 bg-red-50 rounded-xl transition-all hover:bg-red-100 group">
                        <span class="flex items-center justify-center w-9 h-9 bg-red-100 text-red-600 rounded-lg group-hover:bg-red-200">
                            <i class='bx bx-log-out text-xl group-hover:rotate-90 transition-transform duration-300'></i>
                        </span>
                        <span class="ml-3 font-medium">Logout</span>
                    </a>
                </div>

                <!-- User Profile -->
                <div class="px-4 py-4 border-t border-gray-100">
                    <div class="flex items-center space-x-3">
                        <div class="flex-shrink-0">
                            <div class="w-10 h-10 rounded-full bg-primary-100 flex items-center justify-center text-primary-600">
                                <i class='bx bxs-user text-xl'></i>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">
                                <?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?>
                            </p>
                            <p class="text-xs text-gray-500 truncate">
                                Administrator
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile menu button (for responsive design) -->
        <div class="lg:hidden fixed bottom-6 right-6 z-50">
            <button type="button" class="flex items-center justify-center w-14 h-14 rounded-full bg-primary-600 text-white shadow-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                <i class='bx bx-menu text-2xl'></i>
            </button>
        </div>

        <!-- Main Content -->
        <div class="flex-1 ml-72">
            <!-- Header -->
            <div class="bg-white border-b border-gray-200">
                <div class="flex justify-between items-center px-8 py-4">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">Approval History</h2>
                        <p class="text-gray-600 text-sm mt-1">
                            View all approved and rejected access requests
                        </p>
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
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reviewed By</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Review Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($history as $entry): ?>
                                <tr class="hover:bg-gray-50">
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
                                                <?php echo $entry['testing_status'] === 'success' ? 'bg-blue-100 text-blue-800' : 
                                                    ($entry['testing_status'] === 'failed' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800'); ?>">
                                                <?php echo $entry['testing_status'] === 'success' ? 'Successful' : 
                                                    ($entry['testing_status'] === 'failed' ? 'Failed' : 
                                                    ($entry['testing_status'] === 'not_required' ? 'Not Required' : ucfirst($entry['testing_status']))); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-sm text-gray-500">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($entry['admin_name']); ?></div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-500">
                                            <?php echo date('M d, Y H:i', strtotime($entry['created_at'])); ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="flex space-x-2">
                                            <button onclick="showDetailsModal(<?php echo $entry['history_id']; ?>)" 
                                                    class="flex items-center px-3 py-1.5 bg-gray-50 text-gray-700 rounded-lg hover:bg-gray-100 transition-colors">
                                                <i class='bx bx-info-circle text-lg'></i>
                                                <span class="ml-1 text-sm">View</span>
                                            </button>
                                            <a href="tcpdf_print_record.php?id=<?php echo $entry['history_id']; ?>" 
                                               class="flex items-center px-3 py-1.5 bg-primary-50 text-primary-600 rounded-lg hover:bg-primary-100 transition-colors"
                                               target="_blank">
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

    <!-- Details Modal -->
    <div id="detailsModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 hidden z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-xl w-[90%] max-w-7xl mx-auto shadow-xl overflow-hidden" id="modalContainer">
                <!-- Modal content will be populated by JavaScript -->
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

        // Function to show details modal
        function showDetailsModal(historyId) {
            document.getElementById('detailsModal').classList.remove('hidden');
            const modalContainer = document.getElementById('modalContainer');
            
            // Show loading state
            modalContainer.innerHTML = `
                <div class="flex justify-center items-center py-8">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                    <span class="ml-2">Loading details...</span>
                </div>
            `;
            
            // Fetch request details via AJAX
            fetch(`get_request_details.php?id=${historyId}&type=history`)
                .then(async response => {
                    if (!response.ok) {
                        const errorText = await response.text();
                        throw new Error(errorText || 'Failed to load request details');
                    }
                    return response.json();
                })
                .then(data => {
                    // Check if the response is in the expected format
                    if (!data || typeof data !== 'object') {
                        throw new Error('Invalid response data');
                    }
                    
                    // Handle both possible response formats (direct data or data in success.data)
                    if (data.success === false) {
                        throw new Error(data.message || 'Failed to load request details');
                    }
                    
                    // If we have a success property, extract the actual data
                    if (typeof data.success === 'boolean' && data.success === true) {
                        data = data.data;
                    }
                    
                    // Render the entire modal content including header
                    modalContainer.innerHTML = `
                        <!-- Modal Header -->
                        <div class="flex items-center px-6 py-4 border-b border-gray-200">
                            <div class="w-1/4">
                                <p class="text-sm font-medium text-gray-500">Request Number</p>
                                <p class="text-lg font-semibold text-gray-900">${data.access_request_number}</p>
                            </div>
                            <div class="flex-1 text-center">
                                <h3 class="text-xl font-semibold text-gray-800">Access Request Details</h3>
                            </div>
                            <div class="w-1/4 flex justify-end">
                                <button onclick="hideDetailsModal()" class="text-gray-500 hover:text-gray-700">
                                    <i class='bx bx-x text-2xl'></i>
                                </button>
                            </div>
                        </div>

                        <!-- Modal Content -->
                        <div class="p-6">
                            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                                <!-- Request Info -->
                                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                    <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                                        <i class='bx bx-info-circle text-primary-600 text-xl mr-2'></i>
                                        Request Information
                                    </h3>
                                    <div class="space-y-3">
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Request No:</span>
                                            <span class="font-medium text-gray-900">${data.access_request_number}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Action:</span>
                                            <div class="flex justify-center items-center ${
                                                data.action === 'approved' ? 'bg-green-50' : 'bg-red-50'
                                            } rounded-lg px-2 py-1">
                                                <span class="px-3 py-1 text-xs font-medium rounded-full ${
                                                    data.action === 'approved' 
                                                    ? 'bg-green-100 text-green-700' 
                                                    : 'bg-red-100 text-red-700'
                                                }">
                                                    ${data.action ? data.action.toUpperCase() : 'UNKNOWN'}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Review Date:</span>
                                            <span class="font-medium text-gray-900">
                                                ${data.created_at ? new Date(data.created_at).toLocaleString() : 'Unknown date'}
                                            </span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Reviewed By:</span>
                                            <span class="font-medium text-gray-900">${data.admin_name}</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Requestor Info -->
                                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                    <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                                        <i class='bx bx-user text-primary-600 text-xl mr-2'></i>
                                        Requestor Information
                                    </h3>
                                    <div class="space-y-3">
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Name:</span>
                                            <span class="font-medium text-gray-900">${data.requestor_name}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Business Unit:</span>
                                            <span class="font-medium text-gray-900">${data.business_unit}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Department:</span>
                                            <span class="font-medium text-gray-900">${data.department}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Email:</span>
                                            <span class="font-medium text-gray-900">${data.email}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Contact:</span>
                                            <span class="font-medium text-gray-900">${data.contact_number || 'Not provided'}</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Access Details -->
                                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                    <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                                        <i class='bx bx-lock-open text-primary-600 text-xl mr-2'></i>
                                        Access Details
                                    </h3>
                                    <div class="space-y-3">
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Access Type:</span>
                                            <span class="font-medium text-gray-900">${data.access_type}</span>
                                        </div>
                                        ${data.system_type ? `
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">System Type:</span>
                                            <span class="font-medium text-gray-900">${data.system_type}</span>
                                        </div>
                                        ` : ''}
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Duration Type:</span>
                                            <span class="font-medium text-gray-900">${data.duration_type ? data.duration_type.charAt(0).toUpperCase() + data.duration_type.slice(1) : 'Not specified'}</span>
                                        </div>
                                        ${data.duration_type === 'temporary' ? `
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Duration Period:</span>
                                            <span class="font-medium text-gray-900">${data.start_date ? new Date(data.start_date).toLocaleDateString() : 'Unknown'} - ${data.end_date ? new Date(data.end_date).toLocaleDateString() : 'Unknown'}</span>
                                        </div>
                                        ` : ''}
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Justification & Review -->
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                                <!-- Justification -->
                                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                    <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                                        <i class='bx bx-comment-detail text-primary-600 text-xl mr-2'></i>
                                        Justification
                                    </h3>
                                    <div id="justification-text" class="bg-gray-50 p-4 rounded-lg text-gray-700 break-words overflow-auto" style="min-height: 100px; max-height: 250px;">
                                        ${data.justification || 'No justification provided.'}
                                    </div>
                                </div>
                                
                                <!-- Review Notes -->
                                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                    <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                                        <i class='bx bx-message-square-detail text-primary-600 text-xl mr-2'></i>
                                        Review Notes
                                    </h3>
                                    <div id="review-notes-text" class="bg-gray-50 p-4 rounded-lg text-gray-700 break-words overflow-auto" style="min-height: 100px; max-height: 250px;">
                                        ${data.comments || 'No review notes provided.'}
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="flex justify-end gap-3 p-6 border-t border-gray-100">
                            <button onclick="hideDetailsModal()" 
                                    class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                                Close
                            </button>
                            <a href="tcpdf_print_record.php?id=${data.history_id}" 
                               class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors flex items-center" target="_blank">
                               <i class='bx bx-printer mr-2'></i> Print Request
                            </a>
                        </div>
                    `;
                    
                    // Set equal heights for justification and review notes after content is loaded
                    setTimeout(adjustTextHeights, 50);
                })
                .catch(error => {
                    console.error('Error:', error);
                    modalContainer.innerHTML = `
                        <div class="flex items-center px-6 py-4 border-b border-gray-200">
                            <div class="flex-1 text-center">
                                <h3 class="text-xl font-semibold text-gray-800">Error</h3>
                            </div>
                            <button onclick="hideDetailsModal()" class="text-gray-500 hover:text-gray-700">
                                <i class='bx bx-x text-2xl'></i>
                            </button>
                        </div>
                        <div class="p-6">
                            <div class="text-center">
                                <div class="text-red-600 mb-2">
                                    <i class='bx bx-error-circle text-3xl'></i>
                                </div>
                                <p class="text-red-600 font-medium">Error loading request details</p>
                                <p class="text-gray-500 text-sm mt-1">${error.message}</p>
                                <button onclick="hideDetailsModal()" 
                                        class="mt-4 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                                    Close
                                </button>
                            </div>
                        </div>
                    `;
                });
        }

        // Function to adjust text heights to be equal
        function adjustTextHeights() {
            const justDiv = document.getElementById('justification-text');
            const reviewDiv = document.getElementById('review-notes-text');
            
            if (justDiv && reviewDiv) {
                // Get scroll heights to determine content size
                const justHeight = justDiv.scrollHeight;
                const reviewHeight = reviewDiv.scrollHeight;
                
                // Use the larger height for both, but cap at 300px
                const maxHeight = Math.min(Math.max(justHeight, reviewHeight), 300);
                
                // Set a minimum height
                const height = Math.max(maxHeight, 100);
                
                // Apply the same height to both elements
                justDiv.style.height = `${height}px`;
                reviewDiv.style.height = `${height}px`;
            }
        }

        // Function to hide details modal
        function hideDetailsModal() {
            document.getElementById('detailsModal').classList.add('hidden');
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

        // Close modal when clicking outside
        document.getElementById('detailsModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideDetailsModal();
            }
        });
    </script>
</body>
</html>

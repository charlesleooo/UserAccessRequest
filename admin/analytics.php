<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config.php';
require_once 'analytics_functions.php';

// Authentication check
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Get filters from POST request
$filters = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if this is a reset action
    if (isset($_POST['reset_filters'])) {
        // Clear all filters and redirect to the same page to clear POST data
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    if (!empty($_POST['start_date'])) $filters['start_date'] = $_POST['start_date'];
    if (!empty($_POST['end_date'])) $filters['end_date'] = $_POST['end_date'];
    if (!empty($_POST['business_unit'])) $filters['business_unit'] = $_POST['business_unit'];
    if (!empty($_POST['department'])) $filters['department'] = $_POST['department'];
    if (!empty($_POST['system_type'])) $filters['system_type'] = $_POST['system_type'];
}

$analyticsData = getAnalyticsData($pdo, $filters);
$statsData = getDashboardStats($pdo, $filters);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UAR Analytics</title>

    <!-- External CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Tailwind Configuration -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#0084FF',
                        secondary: '#001A33',
                    }
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
                    
                    <a href="dashboard.php" class="flex items-center px-4 py-3 text-gray-700 rounded-xl transition-all hover:bg-gray-50 group">
                        <span class="flex items-center justify-center w-9 h-9 bg-gray-100 text-gray-600 rounded-lg group-hover:bg-gray-200">
                            <i class='bx bxs-dashboard text-xl'></i>
                        </span>
                        <span class="ml-3">Dashboard</span>
                    </a>
                    
                    <a href="#" class="flex items-center px-4 py-3 text-indigo-600 bg-indigo-50 rounded-xl transition-all hover:bg-indigo-100 group">
                        <span class="flex items-center justify-center w-9 h-9 bg-indigo-100 text-indigo-600 rounded-lg group-hover:bg-indigo-200">
                            <i class='bx bx-line-chart text-xl'></i>
                        </span>
                        <span class="ml-3 font-medium">Analytics</span>
                    </a>
                    
                    <a href="requests.php" class="flex items-center px-4 py-3 text-gray-700 rounded-xl transition-all hover:bg-gray-50 group">
                        <span class="flex items-center justify-center w-9 h-9 bg-gray-100 text-gray-600 rounded-lg group-hover:bg-gray-200">
                            <i class='bx bxs-message-square-detail text-xl'></i>
                        </span>
                        <span class="ml-3">Requests</span>
                    </a>
                    
                    <a href="approval_history.php" class="flex items-center px-4 py-3 text-gray-700 rounded-xl transition-all hover:bg-gray-50 group">
                        <span class="flex items-center justify-center w-9 h-9 bg-gray-100 text-gray-600 rounded-lg group-hover:bg-gray-200">
                            <i class='bx bx-history text-xl'></i>
                        </span>
                        <span class="ml-3">Approval History</span>
                    </a>

                    <!-- Add a divider -->
                    <div class="my-4 border-t border-gray-100"></div>
                    
                    <p class="px-4 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                        Account
                    </p>
                    
                    <a href="user_management.php" class="flex items-center px-4 py-3 text-gray-700 rounded-xl transition-all hover:bg-gray-50 group">
                        <span class="flex items-center justify-center w-9 h-9 bg-gray-100 text-gray-600 rounded-lg group-hover:bg-gray-200">
                            <i class='bx bx-user text-xl'></i>
                        </span>
                        <span class="ml-3">Employee Management</span>
                    </a>
                    
                    <a href="settings.php" class="flex items-center px-4 py-3 text-gray-700 rounded-xl transition-all hover:bg-gray-50 group">
                        <span class="flex items-center justify-center w-9 h-9 bg-gray-100 text-gray-600 rounded-lg group-hover:bg-gray-200">
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
                            <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600">
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
            <button type="button" class="flex items-center justify-center w-14 h-14 rounded-full bg-indigo-600 text-white shadow-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                <i class='bx bx-menu text-2xl'></i>
            </button>
        </div>

        <!-- Main Content -->
        <div class="flex-1 ml-72">
            <!-- Header -->
            <div class="bg-white border-b border-gray-200">
                <div class="flex justify-between items-center px-8 py-4">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">Analytics Dashboard</h2>
                        <p class="text-gray-600 text-sm mt-1">
                            Comprehensive analysis of access requests and approvals
                        </p>
                    </div>
                    <div class="flex items-center gap-4">
                        <form id="downloadForm" action="generate_report.php" method="POST" style="display: inline;">
                            <input type="hidden" name="start_date" value="<?php echo $_POST['start_date'] ?? ''; ?>">
                            <input type="hidden" name="end_date" value="<?php echo $_POST['end_date'] ?? ''; ?>">
                            <input type="hidden" name="business_unit" value="<?php echo $_POST['business_unit'] ?? ''; ?>">
                            <input type="hidden" name="department" value="<?php echo $_POST['department'] ?? ''; ?>">
                            <input type="hidden" name="system_type" value="<?php echo $_POST['system_type'] ?? ''; ?>">
                            <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
                                <i class='bx bx-download mr-2'></i>Download Report
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Content Area -->
            <div class="p-8">
                <!-- Filter Controls -->
                <div class="bg-white rounded-xl shadow-sm p-6 mb-8">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Filter Analytics</h3>
                    <form id="filterForm" method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">Date Range</label>
                            <div class="grid grid-cols-2 gap-2">
                                <input type="date" name="start_date" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm" value="<?php echo $_POST['start_date'] ?? ''; ?>">
                                <input type="date" name="end_date" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm" value="<?php echo $_POST['end_date'] ?? ''; ?>">
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">Business Unit</label>
                            <select name="business_unit" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm">
                                <option value="">All Business Units</option>
                                <?php foreach ($analyticsData['businessUnits'] as $unit): ?>
                                    <option value="<?php echo htmlspecialchars($unit); ?>" <?php echo (isset($_POST['business_unit']) && $_POST['business_unit'] === $unit) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($unit); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">Department</label>
                            <select name="department" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm">
                                <option value="">All Departments</option>
                                <?php foreach ($analyticsData['departments'] as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo (isset($_POST['department']) && $_POST['department'] === $dept) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">System Type</label>
                            <select name="system_type" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm">
                                <option value="">All System Types</option>
                                <?php foreach ($analyticsData['systemTypes'] as $type): ?>
                                    <option value="<?php echo htmlspecialchars($type); ?>" <?php echo (isset($_POST['system_type']) && $_POST['system_type'] === $type) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="md:col-span-3 flex justify-end space-x-4">
                            <button type="reset" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                                Reset Filters
                            </button>
                            <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
                                Apply Filters
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-gray-500 text-sm">Total Requests</h3>
                        </div>
                        <p class="text-2xl font-semibold text-amber-500"><?php echo number_format($statsData['total']); ?></p>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-gray-500 text-sm">Approved Requests</h3>
                        </div>
                        <p class="text-2xl font-semibold text-emerald-500"><?php echo number_format($statsData['approved']); ?></p>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-gray-500 text-sm">Approval Rate</h3>
                        </div>
                        <p class="text-2xl font-semibold text-blue-500"><?php echo $statsData['approval_rate']; ?>%</p>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-gray-500 text-sm">Decline Rate</h3>
                        </div>
                        <p class="text-2xl font-semibold text-red-500"><?php echo $statsData['decline_rate']; ?>%</p>
                    </div>
                </div>

                <!-- Analytics Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <!-- Access Type Distribution -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Access Type Distribution</h3>
                        <canvas id="accessTypeChart" height="300"></canvas>
                    </div>

                    <!-- Business Unit Analysis -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Business Unit Performance</h3>
                        <canvas id="businessUnitChart" height="300"></canvas>
                    </div>
                </div>

                <!-- Detailed Tables -->
                <div class="grid grid-cols-1 gap-6">
                    <!-- Department Performance Table -->
                    <div class="bg-white rounded-xl shadow-sm">
                        <div class="px-6 py-4 border-b border-gray-100">
                            <h3 class="text-lg font-semibold text-gray-800">Department Performance Analysis</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Department</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Requests</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Approved</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rejected</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Approval Rate</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($analyticsData['departmentAnalysis'] as $dept): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($dept['department']); ?></td>
                                        <td class="px-6 py-4 text-sm text-gray-900"><?php echo $dept['total_requests']; ?></td>
                                        <td class="px-6 py-4 text-sm text-emerald-600"><?php echo $dept['approved']; ?></td>
                                        <td class="px-6 py-4 text-sm text-red-600"><?php echo $dept['rejected']; ?></td>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            <?php echo round(($dept['approved'] / $dept['total_requests']) * 100, 1); ?>%
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
    </div>

    <!-- Charts Initialization -->
    <script>
        // Prepare data from PHP
        const accessTypeData = <?php echo json_encode($analyticsData['accessTypeDistribution']); ?>;
        const businessUnitData = <?php echo json_encode($analyticsData['businessUnitAnalysis']); ?>;

        // Simple color palette
        const colors = {
            blue: 'rgb(0, 132, 255)',   // Facebook Messenger Blue
            green: 'rgb(34, 197, 94)',   // Success green
            red: 'rgb(239, 68, 68)',     // Error red
            orange: 'rgb(249, 115, 22)', // Warning orange
            purple: 'rgb(139, 92, 246)', // Purple
            gray: 'rgb(107, 114, 128)'   // Gray
        };

        // Common chart options
        const commonOptions = {
            plugins: {
                tooltip: {
                    backgroundColor: 'rgb(255, 255, 255)',
                    titleColor: 'rgb(17, 24, 39)',
                    bodyColor: 'rgb(17, 24, 39)',
                    borderColor: 'rgb(229, 231, 235)',
                    borderWidth: 1,
                    padding: 12,
                    boxPadding: 6,
                    usePointStyle: true,
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            label += context.parsed.y || context.parsed || 0;
                            return label;
                        }
                    }
                },
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        usePointStyle: true,
                        font: {
                            size: 12
                        }
                    }
                }
            }
        };

        // Access Type Distribution Chart
        new Chart(document.getElementById('accessTypeChart').getContext('2d'), {
            type: 'pie',
            data: {
                labels: accessTypeData.map(item => item.access_type),
                datasets: [{
                    data: accessTypeData.map(item => item.count),
                    backgroundColor: [
                        colors.blue,
                        colors.green,
                        colors.orange,
                        colors.purple,
                        colors.gray
                    ]
                }]
            },
            options: {
                ...commonOptions,
                plugins: {
                    ...commonOptions.plugins,
                    tooltip: {
                        ...commonOptions.plugins.tooltip,
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const value = context.raw;
                                const percentage = ((value / total) * 100).toFixed(1);
                                return `${context.label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });

        // Business Unit Performance Chart
        new Chart(document.getElementById('businessUnitChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: businessUnitData.map(item => item.business_unit),
                datasets: [{
                    label: 'Approved',
                    data: businessUnitData.map(item => item.approved),
                    backgroundColor: colors.green,
                }, {
                    label: 'Rejected',
                    data: businessUnitData.map(item => item.rejected),
                    backgroundColor: colors.red,
                }]
            },
            options: {
                ...commonOptions,
                scales: {
                    x: { 
                        stacked: true,
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                size: 12
                            }
                        }
                    },
                    y: { 
                        stacked: true,
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        },
                        ticks: {
                            font: {
                                size: 12
                            }
                        }
                    }
                },
                plugins: {
                    ...commonOptions.plugins,
                    title: {
                        display: true,
                        text: 'Approved vs Rejected Requests by Business Unit',
                        font: {
                            size: 14
                        }
                    }
                }
            }
        });

        // Handle form reset
        document.querySelector('#filterForm button[type="reset"]').addEventListener('click', function(e) {
            e.preventDefault();
            const form = document.getElementById('filterForm');
            
            // Clear all input fields
            form.querySelectorAll('input[type="date"]').forEach(input => {
                input.value = '';
            });
            
            // Reset all select elements to their first option
            form.querySelectorAll('select').forEach(select => {
                select.selectedIndex = 0;
            });

            // Create a hidden input to indicate this is a reset action
            const resetInput = document.createElement('input');
            resetInput.type = 'hidden';
            resetInput.name = 'reset_filters';
            resetInput.value = '1';
            form.appendChild(resetInput);
            
            // Submit the form
            form.submit();
        });
    </script>
</body>
</html> 
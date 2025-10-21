<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config.php';
require_once '../admin/analytics_functions.php';

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
$isFiltersOpen = !empty($filters);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UAR Analytics</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Flowbite CSS -->
    <link href="https://cdn.jsdelivr.net/npm/flowbite@2.5.1/dist/flowbite.min.css" rel="stylesheet" />
    
    <!-- Boxicons -->
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
                        }
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
            <div class="bg-white border-b border-gray-200">
                <div class="flex justify-between items-center px-8 py-4">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">Analytics Dashboard</h2>
                        <p class="text-gray-600 text-sm mt-1">
                            Comprehensive analysis of access requests and approvals
                        </p>
                    </div>
                    <div class="flex items-center gap-4">
                        <form id="downloadForm" action="generate_report.php" method="POST" target="_blank" style="display: inline;">
                            <input type="hidden" name="start_date" value="<?php echo $_POST['start_date'] ?? ''; ?>">
                            <input type="hidden" name="end_date" value="<?php echo $_POST['end_date'] ?? ''; ?>">
                            <input type="hidden" name="business_unit" value="<?php echo $_POST['business_unit'] ?? ''; ?>">
                            <input type="hidden" name="department" value="<?php echo $_POST['department'] ?? ''; ?>">
                            <input type="hidden" name="system_type" value="<?php echo $_POST['system_type'] ?? ''; ?>">
                            <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
                                <i class='bx bx-file-blank mr-2'></i>Generate PDF Report
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Content Area -->
            <div class="p-8">
                <!-- Filters - Flowbite Accordion -->
                <div id="accordion-flush" data-accordion="collapse" data-active-classes="bg-white" data-inactive-classes="text-gray-500">
                    <h2 id="accordion-flush-heading-1">
                        <button type="button" class="flex items-center justify-between w-full p-5 font-medium rtl:text-right text-gray-900 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 gap-3 mb-6" data-accordion-target="#accordion-flush-body-1" aria-expanded="<?php echo $isFiltersOpen ? 'true' : 'false'; ?>" aria-controls="accordion-flush-body-1">
                            <span class="flex items-center gap-2">
                                <i class='bx bx-filter text-xl'></i>
                                <span class="text-lg">Filters & Options</span>
                            </span>
                            <svg data-accordion-icon class="w-3 h-3 rotate-180 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 10 6">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5 5 1 1 5"/>
                            </svg>
                        </button>
                    </h2>
                    <div id="accordion-flush-body-1" class="<?php echo $isFiltersOpen ? '' : 'hidden'; ?>" aria-labelledby="accordion-flush-heading-1">
                        <div class="p-6 bg-white border border-gray-200 rounded-xl mb-6">
                            <form id="filterForm" method="POST">
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
                                    <!-- Date Range -->
                                    <div>
                                        <label class="block mb-2 text-sm font-medium text-gray-900">
                                            <i class='bx bx-calendar mr-1'></i>Date Range
                                        </label>
                                        <div class="flex gap-2">
                                            <div date-rangepicker class="flex items-center gap-2 w-full">
                                                <input type="date" name="start_date" 
                                                       class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                                       placeholder="Start date"
                                                       value="<?php echo $_POST['start_date'] ?? ''; ?>">
                                                <span class="text-gray-500">to</span>
                                                <input type="date" name="end_date" 
                                                       class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                                       placeholder="End date"
                                                       value="<?php echo $_POST['end_date'] ?? ''; ?>">
                                            </div>
                            </div>
                        </div>

                                    <!-- Business Unit -->
                                    <div>
                                        <label for="business_unit" class="block mb-2 text-sm font-medium text-gray-900">
                                            <i class='bx bx-buildings mr-1'></i>Business Unit
                                        </label>
                                        <select id="business_unit" name="business_unit" 
                                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                                <option value="">All Business Units</option>
                                <?php foreach ($analyticsData['businessUnits'] as $unit): ?>
                                    <option value="<?php echo htmlspecialchars($unit); ?>" <?php echo (isset($_POST['business_unit']) && $_POST['business_unit'] === $unit) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($unit); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                                    <!-- Department -->
                                    <div>
                                        <label for="department" class="block mb-2 text-sm font-medium text-gray-900">
                                            <i class='bx bx-group mr-1'></i>Department
                                        </label>
                                        <select id="department" name="department" 
                                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                                <option value="">All Departments</option>
                                <?php foreach ($analyticsData['departments'] as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo (isset($_POST['department']) && $_POST['department'] === $dept) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                                    <!-- System Type -->
                                    <div>
                                        <label for="system_type" class="block mb-2 text-sm font-medium text-gray-900">
                                            <i class='bx bx-server mr-1'></i>System Type
                                        </label>
                                        <select id="system_type" name="system_type" 
                                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                                <option value="">All System Types</option>
                                <?php foreach ($analyticsData['systemTypes'] as $type): ?>
                                    <option value="<?php echo htmlspecialchars($type); ?>" <?php echo (isset($_POST['system_type']) && $_POST['system_type'] === $type) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex items-center justify-end gap-3">
                                    <button type="reset" 
                                            class="text-gray-900 bg-white border border-gray-300 focus:outline-none hover:bg-gray-100 focus:ring-4 focus:ring-gray-100 font-medium rounded-lg text-sm px-5 py-2.5 inline-flex items-center gap-2">
                                        <i class='bx bx-reset'></i>
                                Reset Filters
                            </button>
                                    <button type="submit" 
                                            class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 inline-flex items-center gap-2">
                                        <i class='bx bx-search-alt'></i>
                                Apply Filters
                            </button>
                        </div>
                    </form>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards - Flowbite Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- Total Requests Card -->
                    <div class="block p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-100 transition-all duration-200 hover:shadow-lg">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center justify-center w-14 h-14 rounded-full bg-gradient-to-br from-amber-400 to-amber-600 shadow-lg">
                                <i class='bx bx-file text-2xl text-white'></i>
                            </div>
                            <span class="bg-amber-100 text-amber-800 text-xs font-semibold px-3 py-1 rounded-full">Total</span>
                        </div>
                        <h5 class="mb-2 text-3xl font-bold tracking-tight text-gray-900"><?php echo number_format($statsData['total']); ?></h5>
                        <p class="font-normal text-gray-600">Total Requests</p>
                    </div>

                    <!-- Approved Requests Card -->
                    <div class="block p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-100 transition-all duration-200 hover:shadow-lg">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center justify-center w-14 h-14 rounded-full bg-gradient-to-br from-green-400 to-green-600 shadow-lg">
                                <i class='bx bx-check-circle text-2xl text-white'></i>
                            </div>
                            <span class="bg-green-100 text-green-800 text-xs font-semibold px-3 py-1 rounded-full">Approved</span>
                        </div>
                        <h5 class="mb-2 text-3xl font-bold tracking-tight text-gray-900"><?php echo number_format($statsData['approved']); ?></h5>
                        <p class="font-normal text-gray-600">Approved Requests</p>
                    </div>

                    <!-- Rejected Requests Card -->
                    <div class="block p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-100 transition-all duration-200 hover:shadow-lg">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center justify-center w-14 h-14 rounded-full bg-gradient-to-br from-red-400 to-red-600 shadow-lg">
                                <i class='bx bx-x-circle text-2xl text-white'></i>
                            </div>
                            <span class="bg-red-100 text-red-800 text-xs font-semibold px-3 py-1 rounded-full">Rejected</span>
                        </div>
                        <h5 class="mb-2 text-3xl font-bold tracking-tight text-gray-900"><?php echo number_format($statsData['rejected']); ?></h5>
                        <p class="font-normal text-gray-600">Rejected Requests</p>
                    </div>

                    <!-- Total Employees Card -->
                    <div class="block p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-100 transition-all duration-200 hover:shadow-lg">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center justify-center w-14 h-14 rounded-full bg-gradient-to-br from-blue-400 to-blue-600 shadow-lg">
                                <i class='bx bx-group text-2xl text-white'></i>
                            </div>
                            <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-3 py-1 rounded-full">Active</span>
                        </div>
                        <h5 class="mb-2 text-3xl font-bold tracking-tight text-gray-900"><?php echo number_format($statsData['total_employees']); ?></h5>
                        <p class="font-normal text-gray-600">Total Employees</p>
                    </div>
                </div>

                <!-- Analytics Grid - Flowbite Cards -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <!-- Daily Requests Chart -->
                    <div class="block p-6 bg-white border border-gray-200 rounded-lg shadow">
                        <div class="flex items-center justify-between mb-5">
                            <h5 class="text-xl font-bold leading-none text-gray-900">Requests per Day</h5>
                            <span class="text-sm font-medium text-blue-600 hover:underline cursor-pointer">
                                <i class='bx bx-line-chart'></i>
                            </span>
                        </div>
                        <div class="flow-root">
                            <canvas id="dailyRequestsChart" height="300"></canvas>
                        </div>
                    </div>

                    <!-- System Type Distribution Chart -->
                    <div class="block p-6 bg-white border border-gray-200 rounded-lg shadow">
                        <div class="flex items-center justify-between mb-5">
                            <h5 class="text-xl font-bold leading-none text-gray-900">System Type Distribution</h5>
                            <span class="text-sm font-medium text-blue-600 hover:underline cursor-pointer">
                                <i class='bx bx-pie-chart-alt-2'></i>
                            </span>
                        </div>
                        <div class="flow-root">
                            <canvas id="systemTypeChart" height="300"></canvas>
                        </div>
                    </div>

                    <!-- Business Unit Performance Chart -->
                    <div class="block p-6 bg-white border border-gray-200 rounded-lg shadow lg:col-span-2">
                        <div class="flex items-center justify-between mb-5">
                            <h5 class="text-xl font-bold leading-none text-gray-900">Business Unit Performance</h5>
                            <span class="text-sm font-medium text-blue-600 hover:underline cursor-pointer">
                                <i class='bx bx-bar-chart-alt-2'></i>
                            </span>
                        </div>
                        <div class="flow-root">
                            <canvas id="businessUnitChart" height="200"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Detailed Tables - Flowbite Table -->
                <div class="grid grid-cols-1 gap-6">
                    <!-- Department Performance Table -->
                    <div class="relative overflow-x-auto shadow-md sm:rounded-lg">
                        <div class="bg-white px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                            <div>
                                <h5 class="text-xl font-bold text-gray-900">Department Performance Analysis</h5>
                                <p class="mt-1 text-sm text-gray-500">Detailed breakdown of requests by department</p>
                            </div>
                            <button type="button" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-4 py-2 inline-flex items-center gap-2">
                                <i class='bx bx-download'></i>
                                Export
                            </button>
                        </div>
                        <table class="w-full text-sm text-left rtl:text-right text-gray-500">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3">Department</th>
                                    <th scope="col" class="px-6 py-3">Total Requests</th>
                                    <th scope="col" class="px-6 py-3">
                                        <div class="flex items-center">
                                            Approved
                                            <svg class="w-3 h-3 ms-1.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M8.574 11.024h6.852a2.075 2.075 0 0 0 1.847-1.086 1.9 1.9 0 0 0-.11-1.986L13.736 2.9a2.122 2.122 0 0 0-3.472 0L6.837 7.952a1.9 1.9 0 0 0-.11 1.986 2.074 2.074 0 0 0 1.847 1.086Zm6.852 1.952H8.574a2.072 2.072 0 0 0-1.847 1.087 1.9 1.9 0 0 0 .11 1.985l3.426 5.05a2.123 2.123 0 0 0 3.472 0l3.427-5.05a1.9 1.9 0 0 0 .11-1.985 2.074 2.074 0 0 0-1.846-1.087Z"/>
                                            </svg>
                                        </div>
                                    </th>
                                    <th scope="col" class="px-6 py-3">Rejected</th>
                                    <th scope="col" class="px-6 py-3">Approval Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($analyticsData['departmentAnalysis'] as $index => $dept): ?>
                                    <tr class="<?php echo $index % 2 == 0 ? 'bg-white' : 'bg-gray-50'; ?> border-b hover:bg-gray-100">
                                        <th scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap">
                                            <?php echo htmlspecialchars($dept['department']); ?>
                                        </th>
                                        <td class="px-6 py-4">
                                            <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded">
                                                <?php echo $dept['total_requests']; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">
                                                <?php echo $dept['approved']; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="bg-red-100 text-red-800 text-xs font-medium px-2.5 py-0.5 rounded">
                                                <?php echo $dept['rejected']; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center">
                                                <div class="w-full bg-gray-200 rounded-full h-2.5 mr-2">
                                                    <div class="bg-blue-600 h-2.5 rounded-full" style="width: <?php echo round(($dept['approved'] / $dept['total_requests']) * 100, 1); ?>%"></div>
                                                </div>
                                                <span class="text-sm font-medium text-gray-900">
                                                    <?php echo round(($dept['approved'] / $dept['total_requests']) * 100, 1); ?>%
                                                </span>
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

    <!-- Charts Initialization -->
    <script>
        // Prepare data from PHP
        const systemTypeData = <?php echo json_encode($analyticsData['systemTypeDistribution']); ?>;
        const businessUnitData = <?php echo json_encode($analyticsData['businessUnitAnalysis']); ?>;
        const dailyRequestsData = <?php echo json_encode($analyticsData['dailyRequests']); ?>;

        // Simple color palette
        const colors = {
            blue: 'rgb(0, 132, 255)', // Facebook Messenger Blue
            green: 'rgb(34, 197, 94)', // Success green
            red: 'rgb(239, 68, 68)', // Error red
            orange: 'rgb(249, 115, 22)', // Warning orange
            purple: 'rgb(139, 92, 246)', // Purple
            gray: 'rgb(107, 114, 128)' // Gray
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

        // Daily Requests Chart
        new Chart(document.getElementById('dailyRequestsChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: dailyRequestsData.map(item => item.request_date),
                datasets: [{
                    label: 'Requests',
                    data: dailyRequestsData.map(item => item.count),
                    borderColor: colors.blue,
                    backgroundColor: 'rgba(0,132,255,0.15)',
                    fill: true,
                    tension: 0.25,
                    pointRadius: 2
                }]
            },
            options: {
                ...commonOptions,
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true }
                }
            }
        });

        // System Type Distribution Chart - Doughnut style like dashboard
        const systemTypeColors = [
            'rgba(59, 130, 246, 0.9)',   // Blue
            'rgba(16, 185, 129, 0.9)',   // Green
            'rgba(249, 115, 22, 0.9)',   // Orange
            'rgba(139, 92, 246, 0.9)',   // Purple
            'rgba(236, 72, 153, 0.9)',   // Pink
            'rgba(14, 165, 233, 0.9)',   // Sky Blue
            'rgba(245, 158, 11, 0.9)',   // Amber
            'rgba(239, 68, 68, 0.9)',    // Red
            'rgba(168, 85, 247, 0.9)',   // Violet
            'rgba(34, 197, 94, 0.9)'     // Emerald
        ];
        
        new Chart(document.getElementById('systemTypeChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: systemTypeData.map(item => item.system_type),
                datasets: [{
                    data: systemTypeData.map(item => item.count),
                    backgroundColor: systemTypeColors,
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            padding: 15,
                            font: {
                                size: 12
                            },
                            generateLabels: function(chart) {
                                const data = chart.data;
                                if (data.labels.length && data.datasets.length) {
                                    return data.labels.map((label, i) => {
                                        const value = data.datasets[0].data[i];
                                        return {
                                            text: `${label} (${value})`,
                                            fillStyle: data.datasets[0].backgroundColor[i],
                                            hidden: false,
                                            index: i
                                        };
                                    });
                                }
                                return [];
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        cornerRadius: 8,
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return `${label}: ${value} (${percentage}%)`;
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

    <!-- Flowbite JS -->
    <script src="https://cdn.jsdelivr.net/npm/flowbite@2.5.1/dist/flowbite.min.js"></script>
</body>

</html>
<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['requestor_id'])) {
    header("Location: login.php");
    exit();
}

$requestorId = $_SESSION['requestor_id'];
$username = $_SESSION['username'] ?? 'User';

// Debug session data
error_log("Session data: " . json_encode($_SESSION));
error_log("Requestor ID: " . $requestorId);
error_log("Username: " . $username);

// Fetch requestor's complete information
try {
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE employee_id = ?");
    $stmt->execute([$requestorId]);
    $requestorInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($requestorInfo) {
        // Set requestor details
        $fullName = $requestorInfo['employee_name'] ?? $username;
        $businessUnit = $requestorInfo['company'] ?? ''; // Using company field as business unit
        $departmentName = $requestorInfo['department'] ?? '';
        
        // For debugging
        error_log("Retrieved user info: " . json_encode($requestorInfo));
    } else {
        // No employee record found with this ID
        error_log("No employee record found for ID: " . $requestorId);
        $fullName = $username;
        $businessUnit = '';
        $departmentName = '';
    }
} catch (PDOException $e) {
    error_log("Error fetching requestor info: " . $e->getMessage());
    $fullName = $username;
    $businessUnit = '';
    $departmentName = '';
}

$businessUnits = [
    'AAC' => 'AAC',
    'ALDEV' => 'ALDEV',
    'ARC' => 'ARC',
    'FHI' => 'FHI',
    'SACI' => 'SACI',
    'SAVI' => 'SAVI',
    'SCCI' => 'SCCI',
    'SFC' => 'SFC'
];

$systemApplications = [
    'Active Directory Access',
    'Canvassing',
    'CCTV Access',
    'Email Access',
    'ERP/NAV/SAP',
    'Firewall Access',
    'Fresh Chilled Receiving System',
    'HRIS',
    'Internet Access',
    'Legacy Inventory',
    'Legacy Ledger System',
    'Legacy Payroll',
    'Legacy Purchasing',
    'Legacy Vouchering',
    'Memorandum Receipt',
    'Offsite Storage Facility Access',
    'PC Access - Local',
    'PC Access - Network',
    'Piece Rate Payroll System',
    'Printer Access',
    'Quickbooks',
    'Server Access',
    'TNA Biometric Device Access',
    'USB/PC-port Access',
    'VPN Access',
    'Wi-Fi/Access Point Access',
    'ZankPOS'
];

try {
    $stmt = $pdo->prepare("SELECT 
        COUNT(*) as total,
        SUM(status = 'approved') as approved,
        SUM(status = 'rejected') as rejected,
        SUM(status = 'pending') as pending
        FROM access_requests
        WHERE requestor_id = ?");
    $stmt->execute([$requestorId]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    $total = $data['total'] ?? 0;
    $approved = $data['approved'] ?? 0;
    $rejected = $data['rejected'] ?? 0;
    $pending = $data['pending'] ?? 0;

    $approvalRate = $total > 0 ? round(($approved / $total) * 100, 1) : 0;
    $declineRate = $total > 0 ? round(($rejected / $total) * 100, 1) : 0;

    $stmt = $pdo->prepare("SELECT * FROM access_requests WHERE requestor_id = ? ORDER BY submission_date DESC LIMIT 5");
    $stmt->execute([$requestorId]);
    $recentRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $total = $approved = $rejected = $pending = 0;
    $approvalRate = $declineRate = 0;
    $recentRequests = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Request</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f4f7fc;
            margin: 0;
            padding: 0;
        }

        .form-container {
            background-color: #ffffff;
            border: 1px solid #dcdcdc;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
            padding: 30px;
            max-width: 800px;
            margin: 40px auto;
        }

        input, select, button {
            font-family: 'Poppins', sans-serif;
        }
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            DEFAULT: '#0084FF',
                            dark: '#006ACC',
                        },
                        danger: {
                            DEFAULT: '#dc3545',
                            dark: '#c82333',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-100" x-data="{ sidebarOpen: window.innerWidth >= 768 }" @resize.window="sidebarOpen = window.innerWidth >= 768">

<!-- Sidebar -->
<div 
    class="fixed inset-y-0 left-0 z-50 w-72 bg-white shadow-lg transform transition-transform duration-300 ease-in-out"
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
    aria-hidden="false"
>
    <div class="flex flex-col h-full">
        <div class="text-center mt-4">
            <img src="../logo.png" alt="Logo" class="w-48 mx-auto">
        </div>
        <nav class="flex-1 pt-6 px-4 space-y-1 overflow-y-auto">
            <a href="dashboard.php" class="flex items-center px-4 py-3 text-gray-700 rounded-xl transition hover:bg-gray-50 group">
                <span class="flex items-center justify-center w-9 h-9 bg-gray-100 text-gray-600 rounded-lg">
                    <i class='bx bxs-dashboard text-xl'></i>
                </span>
                <span class="ml-3">Dashboard</span>
            </a>
            <a href="create_request.php" class="flex items-center px-4 py-3 text-indigo-600 bg-indigo-50 rounded-xl transition hover:bg-indigo-100 group">
                <span class="flex items-center justify-center w-9 h-9 bg-indigo-100 text-indigo-600 rounded-lg">
                    <i class='bx bx-send text-xl'></i>
                </span>
                <span class="ml-3 font-medium">Create Request</span>
            </a>
            <a href="my_requests.php" class="flex items-center px-4 py-3 text-gray-700 rounded-xl transition hover:bg-gray-50 group">
                <span class="flex items-center justify-center w-9 h-9 bg-gray-100 text-gray-600 rounded-lg">
                    <i class='bx bx-list-ul text-xl'></i>
                </span>
                <span class="ml-3">Pending Requests</span>
            </a>
            <a href="request_history.php" class="flex items-center px-4 py-3 text-gray-700 rounded-xl transition hover:bg-gray-50 group">
                <span class="flex items-center justify-center w-9 h-9 bg-gray-100 text-gray-600 rounded-lg">
                    <i class='bx bx-history text-xl'></i>
                </span>
                <span class="ml-3">Request History</span>
            </a>
        </nav>

        <div class="p-4 border-t border-gray-100">
            <a href="logout.php" class="flex items-center px-4 py-3 text-red-600 bg-red-50 rounded-xl transition hover:bg-red-100 group">
                <span class="flex items-center justify-center w-9 h-9 bg-red-100 text-red-600 rounded-lg">
                    <i class='bx bx-log-out text-xl'></i>
                </span>
                <span class="ml-3 font-medium">Logout</span>
            </a>
        </div>
    </div>
</div>

<!-- Sidebar Overlay for mobile/tablet -->
<div 
    x-show="sidebarOpen" 
    @click="sidebarOpen = false" 
    class="fixed inset-0 bg-black bg-opacity-40 z-40 md:hidden" 
    x-transition:enter="transition-opacity ease-in-out duration-300" 
    x-transition:enter-start="opacity-0" 
    x-transition:enter-end="opacity-100" 
    x-transition:leave="transition-opacity ease-in-out duration-200" 
    x-transition:leave-start="opacity-100" 
    x-transition:leave-end="opacity-0" 
    aria-hidden="true">
</div>

<!-- Main Content -->
<div class="flex-1 transition-all duration-300" :class="sidebarOpen ? 'md:ml-72' : 'ml-0'">
    <!-- Header -->
    <div class="bg-blue-900 border-b border-gray-200 sticky top-0 z-10 shadow-sm">
        <div class="flex justify-between items-center px-8 py-4">
            <div class="flex items-center" style="margin-left: 0px;">
                <!-- Hamburger button for toggling sidebar -->
                <button 
                    @click="sidebarOpen = !sidebarOpen"
                    class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-white mr-4"
                    aria-label="Toggle sidebar"
                >
                    <i class='bx bx-menu text-2xl bg-white rounded-lg p-2'></i>
                </button>
                <div>
                    <h2 class="text-4xl font-bold text-white">Create New User Access Request (UAR)</h2>
                    <p class="text-white text-xl mt-1">Fill out the form below to submit a new access request</p>
                </div>
            </div>
            <div class="relative" x-data="{ privacyNoticeOpen: false }" @mouseover="privacyNoticeOpen = true" @mouseleave="privacyNoticeOpen = false">
                <button class="text-white hover:text-blue-200 focus:outline-none">
                    <i class='bx bx-info-circle text-2xl'></i>
                </button>
                <div x-cloak x-show="privacyNoticeOpen"
                     class="absolute right-0 mt-2 w-64 p-4 bg-white rounded-md shadow-lg text-gray-700 text-sm z-50"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 transform scale-95"
                     x-transition:enter-end="opacity-100 transform scale-100"
                     x-transition:leave="transition ease-in duration-75"
                     x-transition:leave-start="opacity-100 transform scale-100"
                     x-transition:leave-end="opacity-0 transform scale-95">
                    <p class="font-semibold mb-2">Data Privacy Notice</p>
                    <p>Your data is used solely for processing access requests and is handled according to our internal privacy policy.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="p-5">
        <form id="uarForm" class="w-full">
            <!-- Requestor Information -->
            <div class="bg-primary text-white py-3 px-5 rounded mb-5 text-center text-base font-bold">Requestor Information</div>
            <div class="mb-8 w-full">
                <table class="w-full border-collapse mb-6 shadow-sm table-fixed">
                    <tr>
                        <td class="border border-gray-200 p-4 w-1/12"><strong>Name</strong>
                        <td class="border border-gray-200 p-4 w-5/12"><input type="text" name="name" value="<?php echo htmlspecialchars($fullName); ?>" class="w-full p-3 border border-gray-300 rounded bg-gray-50 shadow-inner" readonly></td>
                        <td class="border border-gray-200 p-4 w-1/12"><strong><span class="after:content-['*'] after:text-red-500 after:ml-1">Date</span></strong></td>
                        <td class="border border-gray-200 p-4 w-5/12"><input type="text" name="date" value="<?php echo date('Y-m-d'); ?>" class="w-full p-3 border border-gray-300 rounded bg-gray-50 shadow-inner" readonly></td>
                    </tr>
                    <tr class="bg-gray-50">
                        <td class="border border-gray-200 p-4"><strong>Business Unit Entity</strong></td>
                        <td class="border border-gray-200 p-4">
                            <input type="text" name="business_unit" id="business_unit" value="<?php echo htmlspecialchars($businessUnit); ?>" class="w-full p-3 border border-gray-300 rounded bg-gray-50 shadow-inner" readonly>
                            <input type="hidden" name="business_unit_value" value="<?php echo htmlspecialchars($businessUnit); ?>">
                        </td>
                        <td class="border border-gray-200 p-4"><strong>Department</strong></td>
                        <td class="border border-gray-200 p-4">
                            <input type="text" name="department" id="department" value="<?php echo htmlspecialchars($departmentName); ?>" class="w-full p-3 border border-gray-300 rounded bg-gray-50 shadow-inner" readonly>
                            <input type="hidden" name="department_value" value="<?php echo htmlspecialchars($departmentName); ?>">
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Request Details -->
            <div class="bg-primary text-white py-3 px-5 rounded mb-5 text-center text-base font-bold">Request Details</div>
            
            <!-- Access Type Selector -->
            <div class="p-5 mb-6 bg-gray-50 rounded-md border-l-4 border-primary flex space-x-8">
                <label class="flex items-center cursor-pointer font-bold ">
                    <input type="radio" name="access_type" value="individual" checked class="mr-2 cursor-pointer"> Individual Access
                </label>
                <label class="flex items-center cursor-pointer font-bold">
                    <input type="radio" name="access_type" value="group" class="mr-2 cursor-pointer"> Group Access
                </label>
            </div>
            
            <!-- Individual Access -->
            <div id="individualAccess" class="mb-8">
                <h3 class="mb-4 text-primary text-base font-medium">I. For Individual Access</h3>
                <div class="p-4 mb-5 bg-blue-50 border-l-4 border-blue-500 rounded">
                    <i class="fas fa-info-circle"></i> Add additional access requests for the same user below.
                </div>
                <button type="button" class="bg-primary hover:bg-primary-dark text-white py-2.5 px-4 rounded mb-5 transition flex items-center" onclick="addRow('individualTable')">
                    <i class="fas fa-plus-circle mr-2"></i> Additional Request
                </button>
                <div class="w-full overflow-x-auto">
                    <table id="individualTable" class="w-full border-collapse mb-6 shadow-sm">
                        <thead>
                            <tr>
                                <th class="border border-gray-200 p-3 bg-blue-50 text-primary font-semibold w-[25%]">User Name</th>
                                <th class="border border-gray-200 p-3 bg-blue-50 text-primary font-semibold w-[17%]">Application/System</th>
                                <th class="border border-gray-200 p-3 bg-blue-50 text-primary font-semibold w-[8%]">Access Type</th>
                                <th class="border border-gray-200 p-3 bg-blue-50 text-primary font-semibold w-[11%]">Access Duration</th>
                                <th class="border border-gray-200 p-1 bg-blue-50 text-primary font-semibold w-[4%]">Start Date</th>
                                <th class="border border-gray-200 p-1 bg-blue-50 text-primary font-semibold w-[4%]">End Date</th>
                                <th class="border border-gray-200 p-1 bg-blue-50 text-primary font-semibold w-[4%]">Date Needed</th>
                                <th class="border border-gray-200 p-3 bg-blue-50 text-primary font-semibold w-[15.5%]">Justification</th>
                                <th class="border border-gray-200 p-3 bg-blue-50 text-primary font-semibold w-[5%] text-center">Delete</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="border border-gray-200 p-2">
                                    <input type="text" placeholder="User Name" name="ind_username" class="w-full p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition">
                                </td>
                                <td class="border border-gray-200 p-2">
                                    <select name="ind_application[]" class="w-full p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" required>
                                        <option value="">Select Application</option>
                                        <?php foreach ($systemApplications as $app): ?>
                                            <option value="<?php echo $app; ?>"><?php echo $app; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="ind_application_system[]" value="">
                                </td>
                                <td class="border border-gray-200 p-2">
                                    <select name="ind_access_type[]" class="w-full p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" required>
                                        <option value=""></option>
                                        <option value="full">Full</option>
                                        <option value="read">Read</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </td>
                                <td class="border border-gray-200 p-2">
                                    <select name="ind_duration_type[]" class="duration-select w-full p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" required onchange="toggleDateFields(this)">
                                        <option value=""></option>
                                        <option value="permanent">Permanent</option>
                                        <option value="temporary">Temporary</option>
                                    </select>
                                </td>
                                <td class="border border-gray-200 p-1">
                                    <input type="date" name="ind_start_date[]" class="start-date w-full p-1 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition bg-gray-50 opacity-70 cursor-not-allowed" disabled required>
                                </td>
                                <td class="border border-gray-200 p-1">
                                    <input type="date" name="ind_end_date[]" class="end-date w-full p-1 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition bg-gray-50 opacity-70 cursor-not-allowed" disabled required>
                                </td>
                                <td class="border border-gray-200 p-1">
                                    <input type="date" name="ind_date_needed[]" class="date-needed w-full p-1 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" required>
                                </td>
                                <td class="border border-gray-200 p-2">
                                    <input type="text" placeholder="Click to add justification" name="ind_justification[]" class="justification-input w-full p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition bg-gray-50 cursor-pointer" required>
                                </td>
                                <td class="border border-gray-200 p-2 text-center w-16">
                                    <button type="button" class="bg-danger hover:bg-danger-dark text-white p-2 rounded transition" onclick="deleteRow(this)">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Group Access -->
            <div id="groupAccess" class="mb-8 hidden">
                <h3 class="mb-4 text-primary text-base font-medium">II. For Group Access</h3>
                <div class="p-4 mb-5 bg-blue-50 border-l-4 border-blue-500 rounded">
                    <i class="fas fa-info-circle"></i> Add group access requests below. For multiple applications, add additional rows.
                </div>
                <button type="button" class="bg-primary hover:bg-primary-dark text-white py-2.5 px-4 rounded mb-5 transition flex items-center" onclick="addRow('groupTable')">
                    <i class="fas fa-plus-circle mr-2"></i> Additional Request
                </button>
                <div class="w-full overflow-x-auto">
                    <table id="groupTable" class="w-full border-collapse mb-6 shadow-sm">
                        <thead>
                            <tr>
                                <th class="border border-gray-200 p-3 bg-blue-50 text-primary font-semibold w-[18.5%]">Application/System</th>
                                <th class="border border-gray-200 p-3 bg-blue-50 text-primary font-semibold w-[32%] min-w-[200px]">User Name</th>
                                <th class="border border-gray-200 p-3 bg-blue-50 text-primary font-semibold w-[8%] min-w-[110px]">Access Type</th>
                                <th class="border border-gray-200 p-3 bg-blue-50 text-primary font-semibold w-[12%]">Access Duration</th>
                                <th class="border border-gray-200 p-1 bg-blue-50 text-primary font-semibold w-[3%]">Start Date</th>
                                <th class="border border-gray-200 p-1 bg-blue-50 text-primary font-semibold w-[3%]">End Date</th>
                                <th class="border border-gray-200 p-1 bg-blue-50 text-primary font-semibold w-[3%]">Date Needed</th>
                                <th class="border border-gray-200 p-3 bg-blue-50 text-primary font-semibold w-[15.5%]">Justification</th>
                                <th class="border border-gray-200 p-3 bg-blue-50 text-primary font-semibold w-[5%] text-center">Delete</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="application-row" data-app-id="1">
                                <td class="border border-gray-200 p-2">
                                    <div class="flex gap-2 items-center">
                                        <select name="grp_application[]" class="app-select flex-1 p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" required>
                                            <option value="">Select Application</option>
                                            <?php foreach ($systemApplications as $app): ?>
                                                <option value="<?php echo $app; ?>"><?php echo $app; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="hidden" name="grp_application_system[]" value="">
                                    </div>
                                </td>
                                <td class="border border-gray-200 p-2">
                                    <div class="flex items-center gap-2">
                                        <input type="text" placeholder="User Name" name="grp_username[]" class="w-full p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" required>
                                        <button type="button" class="bg-primary hover:bg-primary-dark text-white p-2 rounded transition flex-shrink-0" onclick="addUserRow(this)" title="Add User">
                                            <i class="fa fa-user-plus"></i>
                                        </button>
                                    </div>
                                </td>
                                <td class="border border-gray-200 p-2">
                                    <select name="grp_access_type[]" class="w-full p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" required>
                                        <option value=""></option>
                                        <option value="full">Full</option>
                                        <option value="read">Read</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </td>
                                <td class="border border-gray-200 p-2">
                                    <select name="grp_duration_type[]" class="duration-select w-full p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" required onchange="toggleDateFields(this)">
                                        <option value=""></option>
                                        <option value="permanent">Permanent</option>
                                        <option value="temporary">Temporary</option>
                                    </select>
                                </td>
                                <td class="border border-gray-200 p-1">
                                    <input type="date" name="grp_start_date[]" class="start-date w-full p-1 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition bg-gray-50 opacity-70 cursor-not-allowed" disabled required>
                                </td>
                                <td class="border border-gray-200 p-1">
                                    <input type="date" name="grp_end_date[]" class="end-date w-full p-1 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition bg-gray-50 opacity-70 cursor-not-allowed" disabled required>
                                </td>
                                <td class="border border-gray-200 p-1">
                                    <input type="date" name="grp_date_needed[]" class="date-needed w-full p-1 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" required>
                                </td>
                                <td class="border border-gray-200 p-2">
                                    <input type="text" placeholder="Click to add justification" name="grp_justification[]" class="justification-input w-full p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition bg-gray-50 cursor-pointer" required>
                                </td>
                                <td class="border border-gray-200 p-2 text-center w-16">
                                    <button type="button" class="bg-danger hover:bg-danger-dark text-white p-2 rounded transition" onclick="deleteRow(this)">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-5 text-center flex justify-center gap-5 flex-wrap">
                <button type="submit" class="bg-primary hover:bg-primary-dark text-white py-3.5 px-8 rounded transition text-base font-medium min-w-[200px]">
                    <i class="fas fa-paper-plane mr-2"></i> Submit Request
                </button>
                <button type="reset" class="bg-danger hover:bg-danger-dark text-white py-3.5 px-8 rounded transition text-base font-medium min-w-[200px]">
                    <i class="fas fa-undo mr-2"></i> Reset Form
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const businessUnitDepartments = {
        'AAC': [
            'AFFILIATES',
            'APP',
            'CATFISH GROW-OUT',
            'ENGINEERING',
            'FINANCE',
            'GENSAN PROCESSING PLANT (GPP)',
            'GROW OUT',
            'HUMAN RESOURCE & ADMIN',
            'INFORMATION TECHNOLOGY (IT)',
            'LAND BASED',
            'MANUFACTURING',
            'MARKETING',
            'MATERIALS MANAGEMENT',
            'OFFICE OF THE VP-OPERATIONS',
            'PPP-SLICING/OTHER PROCESSING',
            'REGIONAL SALES',
            'RPP',
            'SALES & MARKETING',
            'SEA CAGE',
            'SPECIAL IMPORTATION/TRADING',
            'TECHNICAL SERVICES',
            'TH - CLEARING',
            'TILAPIA HATCHERY (TH)',
            'VAP'
        ].sort(),
        'ALDEV': [
            'ALD Cattle',
            'ALD Banana-San Jose',
            'ALD Engineering',
            'ALD Operations Services',
            'ALD Technical Services',
            'ALD-PROD PLANNING'
        ].sort(),
        'ARC': [
            'ARC - NURSERY',
            'ARC Engineering',
            'ARC Growout',
            'Administrative services'
        ].sort(),
        'FHI': [
            'FIELDS',
            'SELLING & MARKETING DEPARTMENT',
            'OPERATIONS DEPARTMENT',
            'OTHER SPECIE DEPARTMENT'
        ].sort(),
        'SACI': [
            'ALDEVINCO-AGRI',
            'FHI',
            'ARC',
            'SCCI',
            'CLAFI',
            'ALSEC',
            'SAVI',
            'CONAL',
            'ABBA BLESS',
            'ALC',
            'SBSTG',
            'G3 AQUAVENTURES INC',
            'AAC',
            'VARIOUS AFFILIATES'
        ].sort(),
        'SAVI': [
            'SCCI',
            'ALSEC',
            'SUNFARMS',
            'AAC',
            'OPERATIONS SERVICES',
            'BANANA OPERATION',
            'BANANA LEAVES OPERATION',
            'AGRI-ENGINEERING',
            'G&A',
            'TSD Agri',
            'G&A - Distribution',
            'OOM',
            'Conal Corporation'
        ].sort(),
        'SCCI': [
            'SCC Banana-Lanton',
            'SCC Cattle',
            'SCC Engineering',
            'SCC Pineapple',
            'SCC Technical Services',
            'SCCI Operations Services'
        ].sort(),
        'SFC': [
            'AGRI-ENGINEERING',
            'AGRI-OPERATIONS SERVICES',
            'AGRI-PINEAPPLE OPERATIONS',
            'FIELD OVERHEAD'
        ].sort()
    };

    // Replace modal functionality with SweetAlert2
    let currentInput = null;

    function setupJustificationInputs() {
        document.querySelectorAll('.justification-input').forEach(input => {
            input.addEventListener('click', function(e) {
                currentInput = e.target;
                
                Swal.fire({
                    title: 'Reason for Access',
                    input: 'textarea',
                    inputLabel: 'Please provide detailed justification',
                    inputPlaceholder: 'Enter detailed reason for access here...',
                    inputValue: currentInput.value,
                    inputAttributes: {
                        'aria-label': 'Justification text area',
                        'rows': '8'
                    },
                    confirmButtonText: 'Save & Close',
                    confirmButtonColor: '#0084FF',
                    showCancelButton: true,
                    cancelButtonText: 'Cancel',
                    cancelButtonColor: '#6B7280',
                    customClass: {
                        input: 'text-sm font-normal p-4 min-h-[150px] resize-y'
                    }
                }).then((result) => {
                    if (result.isConfirmed && result.value) {
                        currentInput.value = result.value;
                    }
                });
            });
        });
    }

    // Initial setup
    setupJustificationInputs();

    // Handle access type selection
    document.querySelectorAll('input[name="access_type"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const switchingToIndividual = this.value === 'individual';
            
            // Simply show/hide appropriate sections
            if (switchingToIndividual) {
                document.getElementById('individualAccess').classList.remove('hidden');
                document.getElementById('groupAccess').classList.add('hidden');
            } else {
                document.getElementById('individualAccess').classList.add('hidden');
                document.getElementById('groupAccess').classList.remove('hidden');
            }
        });
    });

    // Function to toggle date fields based on duration type
    function toggleDateFields(select) {
        const row = select.closest('tr');
        const startDate = row.querySelector('.start-date');
        const endDate = row.querySelector('.end-date');
        const isTemporary = select.value === 'temporary';

        startDate.disabled = !isTemporary;
        endDate.disabled = !isTemporary;

        if (!isTemporary) {
            startDate.value = '';
            endDate.value = '';
        } else {
            // Set minimum date for start date as today
            const today = new Date().toISOString().split('T')[0];
            startDate.min = today;
            
            // Add event listeners for date validation
            startDate.addEventListener('change', function() {
                endDate.min = this.value;
                if (endDate.value && endDate.value < this.value) {
                    endDate.value = this.value;
                }
            });

            endDate.addEventListener('change', function() {
                if (this.value < startDate.value) {
                    this.value = startDate.value;
                }
            });
        }

        // Update required attribute
        startDate.required = isTemporary;
        endDate.required = isTemporary;
    }

    const today = new Date().toISOString().split('T')[0];
    document.querySelectorAll('.date-needed').forEach(input => {
        input.setAttribute('min', today);

        input.addEventListener('change', function() {
            if (this.value < today) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Invalid Date',
                    text: 'Date needed cannot be in the past.',
                    confirmButtonColor: '#0084FF'
                }).then(() => {
                    this.value = today;
                });
            }
        });
    });

    // Function to delete row
    function deleteRow(button) {
        const row = button.closest('tr');
        const table = row.closest('table');
        const appId = row.getAttribute('data-app-id');
        
        // Check if it's the last row in the table
        if (table.querySelectorAll('tbody tr').length <= 1) {
            Swal.fire({
                icon: 'info',
                title: 'Cannot Delete Row',
                text: 'Cannot delete the last row.',
                confirmButtonColor: '#0084FF'
            });
            return;
        }
        
        if (row.classList.contains('application-row')) {
            // If it's an application row, also delete all associated user rows
            const userRows = table.querySelectorAll(`.user-row[data-app-id="${appId}"]`);
            userRows.forEach(userRow => userRow.remove());
        }
        
        row.remove();
    }

    // Add event listeners to update application_system when application changes
    function setupApplicationSelects() {
        // For individual application selects
        document.querySelectorAll('select[name="ind_application[]"]').forEach(function(select, index) {
            select.addEventListener('change', function() {
                const hiddenInput = document.getElementsByName('ind_application_system[]')[index];
                hiddenInput.value = this.value;
            });
        });
        
        // For group application selects
        document.querySelectorAll('select[name="grp_application[]"]').forEach(function(select, index) {
            select.addEventListener('change', function() {
                const hiddenInput = document.getElementsByName('grp_application_system[]')[index];
                hiddenInput.value = this.value;
            });
        });
    }
    
    // Setup initially
    setupApplicationSelects();

    // Function to add new row for individual access
    function addRow(tableId) {
        const table = document.getElementById(tableId);
        
        if (tableId === 'individualTable') {
            const tbody = table.querySelector('tbody');
            const rows = tbody.querySelectorAll('tr');
            const lastRow = rows[rows.length - 1];
            
            // Check if the last row is complete
            const username = lastRow.querySelector('input[name="ind_username"]').value;
            const application = lastRow.querySelector('select[name="ind_application[]"]').value;
            const accessType = lastRow.querySelector('select[name="ind_access_type[]"]').value;
            const durationType = lastRow.querySelector('select[name="ind_duration_type[]"]').value;
            const dateNeeded = lastRow.querySelector('input[name="ind_date_needed[]"]').value;
            const justification = lastRow.querySelector('input[name="ind_justification[]"]').value;
            
            // Check if all required fields in the last row are filled
            if (!username || !application || !accessType || !durationType || !dateNeeded || !justification) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Complete Current Row',
                    text: 'Please complete all fields in the current row before adding a new row.',
                    confirmButtonColor: '#0084FF'
                });
                return;
            }
            
            const newRow = document.createElement('tr');
            newRow.innerHTML = `
                <td class="border border-gray-200 p-2">
                    <input type="hidden" name="ind_username_copy[]" value="${username}">
                    <div class="invisible">Username</div>
                </td>
                <td class="border border-gray-200 p-2">
                    <select name="ind_application[]" class="w-full p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" required>
                        <option value="">Select Application</option>
                        <?php foreach ($systemApplications as $app): ?>
                            <option value="<?php echo $app; ?>"><?php echo $app; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="ind_application_system[]" value="">
                </td>
                <td class="border border-gray-200 p-2">
                    <select name="ind_access_type[]" class="w-full p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" required>
                        <option value=""></option>
                        <option value="full">Full</option>
                        <option value="read">Read</option>
                        <option value="admin">Admin</option>
                    </select>
                </td>
                <td class="border border-gray-200 p-2">
                    <select name="ind_duration_type[]" class="duration-select w-full p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" required onchange="toggleDateFields(this)">
                        <option value=""></option>
                        <option value="permanent">Permanent</option>
                        <option value="temporary">Temporary</option>
                    </select>
                </td>
                <td class="border border-gray-200 p-1">
                    <input type="date" name="ind_start_date[]" class="start-date w-full p-1 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition bg-gray-50 opacity-70 cursor-not-allowed" disabled required>
                </td>
                <td class="border border-gray-200 p-1">
                    <input type="date" name="ind_end_date[]" class="end-date w-full p-1 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition bg-gray-50 opacity-70 cursor-not-allowed" disabled required>
                </td>
                <td class="border border-gray-200 p-1">
                    <input type="date" name="ind_date_needed[]" class="date-needed w-full p-1 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" required>
                </td>
                <td class="border border-gray-200 p-2">
                    <input type="text" placeholder="Click to add justification" name="ind_justification[]" class="justification-input w-full p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition bg-gray-50 cursor-pointer" required>
                </td>
                <td class="border border-gray-200 p-2 text-center w-16">
                    <button type="button" class="bg-danger hover:bg-danger-dark text-white p-2 rounded transition" onclick="deleteRow(this)">
                        <i class="fa fa-trash"></i>
                    </button>
                </td>
            `;
            
            tbody.appendChild(newRow);
            setupJustificationInputs();
            
            // Add event listener to the new duration select
            const durationSelect = newRow.querySelector('.duration-select');
            if (durationSelect) {
                durationSelect.addEventListener('change', function() {
                    toggleDateFields(this);
                });
            }
        } else {
            // Group table row addition
            const tbody = table.querySelector('tbody');
            const rows = tbody.querySelectorAll('tr');
            const lastRow = rows[rows.length - 1];
            
            // Check if the last row is complete
            const application = lastRow.querySelector('select[name="grp_application[]"]').value;
            const username = lastRow.querySelector('input[name="grp_username[]"]').value;
            const accessType = lastRow.querySelector('select[name="grp_access_type[]"]').value;
            const durationType = lastRow.querySelector('select[name="grp_duration_type[]"]').value;
            const dateNeeded = lastRow.querySelector('input[name="grp_date_needed[]"]').value;
            const justification = lastRow.querySelector('input[name="grp_justification[]"]').value;
            
            // Check if all required fields in the last row are filled
            if (!application || !username || !accessType || !durationType || !dateNeeded || !justification) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Complete Current Row',
                    text: 'Please complete all fields in the current row before adding a new row.',
                    confirmButtonColor: '#0084FF'
                });
                return;
            }
            
            const lastAppId = parseInt(tbody.lastElementChild?.getAttribute('data-app-id') || '0');
            const newAppId = lastAppId + 1;
            
            const newRow = document.createElement('tr');
            newRow.className = 'application-row';
            newRow.setAttribute('data-app-id', newAppId);
            
            newRow.innerHTML = `
                <td class="border border-gray-200 p-2">
                    <div class="flex gap-2 items-center">
                        <select name="grp_application[]" class="app-select flex-1 p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" required>
                            <option value="">Select Application</option>
                            <?php foreach ($systemApplications as $app): ?>
                                <option value="<?php echo $app; ?>"><?php echo $app; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="grp_application_system[]" value="">
                    </div>
                </td>
                <td class="border border-gray-200 p-2">
                    <div class="flex items-center gap-2">
                        <input type="text" placeholder="User Name" name="grp_username[]" class="w-full p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" required>
                        <button type="button" class="bg-primary hover:bg-primary-dark text-white p-2 rounded transition flex-shrink-0" onclick="addUserRow(this)" title="Add User">
                            <i class="fa fa-user-plus"></i>
                        </button>
                    </div>
                </td>
                <td class="border border-gray-200 p-2">
                    <select name="grp_access_type[]" class="w-full p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" required>
                        <option value=""></option>
                        <option value="full">Full</option>
                        <option value="read">Read</option>
                        <option value="admin">Admin</option>
                    </select>
                </td>
                <td class="border border-gray-200 p-2">
                    <select name="grp_duration_type[]" class="duration-select w-full p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" required onchange="toggleDateFields(this)">
                        <option value=""></option>
                        <option value="permanent">Permanent</option>
                        <option value="temporary">Temporary</option>
                    </select>
                </td>
                <td class="border border-gray-200 p-1">
                    <input type="date" name="grp_start_date[]" class="start-date w-full p-1 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition bg-gray-50 opacity-70 cursor-not-allowed" disabled required>
                </td>
                <td class="border border-gray-200 p-1">
                    <input type="date" name="grp_end_date[]" class="end-date w-full p-1 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition bg-gray-50 opacity-70 cursor-not-allowed" disabled required>
                </td>
                <td class="border border-gray-200 p-1">
                    <input type="date" name="grp_date_needed[]" class="date-needed w-full p-1 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" required>
                </td>
                <td class="border border-gray-200 p-2">
                    <input type="text" placeholder="Click to add justification" name="grp_justification[]" class="justification-input w-full p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition bg-gray-50 cursor-pointer" required>
                </td>
                <td class="border border-gray-200 p-2 text-center w-16">
                    <button type="button" class="bg-danger hover:bg-danger-dark text-white p-2 rounded transition" onclick="deleteRow(this)">
                        <i class="fa fa-trash"></i>
                    </button>
                </td>
            `;
            
            tbody.appendChild(newRow);
            setupJustificationInputs();
        }
        
        // After adding the row, set up the application selects again
        setupApplicationSelects();
    }

    // Function to add new user row under an application
    function addUserRow(button) {
        const appRow = button.closest('.application-row');
        const appId = appRow.getAttribute('data-app-id');
        const appSelect = appRow.querySelector('.app-select');
        const selectedApp = appSelect.value;
        const accessType = appRow.querySelector('select[name="grp_access_type[]"]').value;
        const durationType = appRow.querySelector('select[name="grp_duration_type[]"]').value;
        const dateNeeded = appRow.querySelector('input[name="grp_date_needed[]"]').value;
        const justification = appRow.querySelector('input[name="grp_justification[]"]').value;
        const username = appRow.querySelector('input[name="grp_username[]"]').value;
        
        // Validate all required fields
        const missingFields = [];
        
        if (!selectedApp) missingFields.push('Application');
        if (!username) missingFields.push('Username');
        if (!accessType) missingFields.push('Access Type');
        if (!durationType) missingFields.push('Duration Type');
        if (!dateNeeded) missingFields.push('Date Needed');
        if (!justification) missingFields.push('Justification');
        
        if (missingFields.length > 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Missing Information',
                html: `
                    <div class="text-left">
                        <p class="mb-2">Please complete the following fields before adding a new user:</p>
                        <ul class="list-disc list-inside">
                            ${missingFields.map(field => `<li>${field}</li>`).join('')}
                        </ul>
                    </div>
                `,
                confirmButtonColor: '#0084FF'
            });
            return;
        }
        
        // Validate date needed is not in the past
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const dateNeededObj = new Date(dateNeeded);
        
        if (dateNeededObj < today) {
            Swal.fire({
                icon: 'warning',
                title: 'Invalid Date',
                text: 'Date needed cannot be in the past.',
                confirmButtonColor: '#0084FF'
            });
            return;
        }
        
        // If temporary access, validate start and end dates
        if (durationType === 'temporary') {
            const startDate = appRow.querySelector('input[name="grp_start_date[]"]').value;
            const endDate = appRow.querySelector('input[name="grp_end_date[]"]').value;
            
            if (!startDate || !endDate) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Missing Dates',
                    text: 'Please set both start and end dates for temporary access.',
                    confirmButtonColor: '#0084FF'
                });
                return;
            }
            
            const startDateObj = new Date(startDate);
            const endDateObj = new Date(endDate);
            
            if (startDateObj > endDateObj) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Invalid Date Range',
                    text: 'Start date cannot be after end date.',
                    confirmButtonColor: '#0084FF'
                });
                return;
            }
        }
        
        const newRow = document.createElement('tr');
        newRow.className = 'user-row';
        newRow.setAttribute('data-app-id', appId);
        
        newRow.innerHTML = `
            <td class="border border-gray-200 p-2 bg-gray-50">
                ${selectedApp}
                <input type="hidden" name="grp_application[]" value="${selectedApp}">
                <input type="hidden" name="grp_application_system[]" value="${selectedApp}">
            </td>
            <td class="border border-gray-200 p-2">
                <input type="text" placeholder="User Name" name="grp_username[]" class="w-full p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" required>
            </td>
            <td class="border border-gray-200 p-2">
                <select name="grp_access_type[]" class="w-full p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" required>
                    <option value="">Select Access Type</option>
                    <option value="full">Full</option>
                    <option value="read">Read</option>
                    <option value="admin">Admin</option>
                </select>
            </td>
            <td class="border border-gray-200 p-2">
                <select name="grp_duration_type[]" class="duration-select w-full p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" required onchange="toggleDateFields(this)">
                    <option value="">Select Duration</option>
                    <option value="permanent">Permanent</option>
                    <option value="temporary">Temporary</option>
                </select>
            </td>
            <td class="border border-gray-200 p-1">
                <input type="date" name="grp_start_date[]" class="start-date w-full p-1 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition bg-gray-50 opacity-70 cursor-not-allowed" disabled required>
            </td>
            <td class="border border-gray-200 p-1">
                <input type="date" name="grp_end_date[]" class="end-date w-full p-1 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition bg-gray-50 opacity-70 cursor-not-allowed" disabled required>
            </td>
            <td class="border border-gray-200 p-1">
                <input type="date" name="grp_date_needed[]" class="date-needed w-full p-1 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" required>
            </td>
            <td class="border border-gray-200 p-2">
                <input type="text" placeholder="Click to add justification" name="grp_justification[]" class="justification-input w-full p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition bg-gray-50 cursor-pointer" required>
            </td>
            <td class="border border-gray-200 p-2 text-center w-16">
                <button type="button" class="bg-danger hover:bg-danger-dark text-white p-2 rounded transition" onclick="deleteRow(this)">
                    <i class="fa fa-trash"></i>
                </button>
            </td>
        `;
        
        appRow.after(newRow);
        setupJustificationInputs();

        // Add event listener to the new duration select
        const durationSelect = newRow.querySelector('.duration-select');
        if (durationSelect) {
            durationSelect.addEventListener('change', function() {
                toggleDateFields(this);
            });
        }
        
        // After adding the user row, set up the application selects again
        setupApplicationSelects();
    }

    // Business Unit and Department fields are now auto-filled with user information

    // Toggle required attribute based on access type selection
    $('input[name="access_type"]').change(function() {
        const accessType = $(this).val();
        
        if (accessType === 'individual') {
            // Remove required attribute from group fields
            $('#groupTable').find('[required]').prop('required', false);
            // Add required attribute to individual fields
            $('#individualTable').find('select, input[type="text"], input[type="date"]').not('.start-date, .end-date').prop('required', true);
        } else {
            // Remove required attribute from individual fields
            $('#individualTable').find('[required]').prop('required', false);
            // Add required attribute to group fields
            $('#groupTable').find('select, input[type="text"], input[type="date"]').not('.start-date, .end-date').prop('required', true);
        }
    });
    
    // Trigger change event on page load to set initial required attributes
    $('input[name="access_type"]:checked').trigger('change');

    // Form submission handler
    $('#uarForm').submit(function(e) {
        e.preventDefault();
        
        // Validate form
        let isValid = true;
        const formData = new FormData();
        
        // Get basic request info
        const name = $('input[name="name"]').val();
        const businessUnit = $('#business_unit').val();
        const department = $('#department').val();
        const accessType = $('input[name="access_type"]:checked').val();
        const date = $('input[name="date"]').val();
        
        // Add basic request data
        formData.append('requestor_name', name);
        formData.append('business_unit', businessUnit);
        formData.append('department', department);
        formData.append('request_date', date);
        formData.append('employee_id', <?php echo json_encode($requestorId); ?>);
        formData.append('email', <?php echo isset($_SESSION['email']) ? json_encode($_SESSION['email']) : '""'; ?>);
        
        // Prepare array to hold all access requests
        const userForms = [];
        
        if(accessType === 'individual') {
            // Process individual access requests
            const username = $('input[name="ind_username"]').val();
            
            // Get all rows from individual table
            $('#individualTable tbody tr').each(function(index) {
                const app = $(this).find('select[name="ind_application[]"]').val();
                const accessType = $(this).find('select[name="ind_access_type[]"]').val();
                const durationType = $(this).find('select[name="ind_duration_type[]"]').val();
                const startDate = $(this).find('input[name="ind_start_date[]"]').val();
                const endDate = $(this).find('input[name="ind_end_date[]"]').val();
                const dateNeeded = $(this).find('input[name="ind_date_needed[]"]').val();
                const justification = $(this).find('input[name="ind_justification[]"]').val();
                const applicationSystem = $(this).find('input[name="ind_application_system[]"]').val();
                
                // For individual access, make sure username is also provided
                if(app && accessType && durationType && dateNeeded && justification && username) {
                    userForms.push({
                        access_type: 'individual',
                        system_type: app,
                        application_system: applicationSystem,
                        role_access_type: accessType,
                        duration_type: durationType,
                        start_date: startDate,
                        end_date: endDate,
                        date_needed: dateNeeded,
                        justification: justification,
                        usernames: [username]
                    });
                } else {
                    isValid = false;
                }
            });
        } else {
            // Process group access requests
            $('#groupTable tbody tr').each(function(index) {
                const app = $(this).find('select[name="grp_application[]"]').val() || 
                           $(this).find('input[name="grp_application[]"]').val();
                           
                const username = $(this).find('input[name="grp_username[]"]').val();
                const accessType = $(this).find('select[name="grp_access_type[]"]').val();
                const durationType = $(this).find('select[name="grp_duration_type[]"]').val();
                const startDate = $(this).find('input[name="grp_start_date[]"]').val();
                const endDate = $(this).find('input[name="grp_end_date[]"]').val();
                const dateNeeded = $(this).find('input[name="grp_date_needed[]"]').val();
                const justification = $(this).find('input[name="grp_justification[]"]').val();
                const applicationSystem = $(this).find('input[name="grp_application_system[]"]').val();
                
                if(app && username && accessType && durationType && dateNeeded && justification) {
                    // Find if there's already an entry for this application
                    const existingAppIndex = userForms.findIndex(form => form.system_type === app);
                    
                    if(existingAppIndex !== -1) {
                        // Add username to existing application
                        userForms[existingAppIndex].usernames.push(username);
                    } else {
                        // Create new entry for this application
                        userForms.push({
                            access_type: 'group',
                            system_type: app,
                            application_system: applicationSystem,
                            role_access_type: accessType,
                            duration_type: durationType,
                            start_date: startDate,
                            end_date: endDate,
                            date_needed: dateNeeded,
                            justification: justification,
                            usernames: [username]
                        });
                    }
                } else {
                    isValid = false;
                }
            });
        }
        
        // Check if we have at least one valid request
        if(userForms.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Missing Information',
                text: 'Please add at least one valid access request.',
                confirmButtonColor: '#0084FF'
            });
            return;
        }
        
        // Check if form is valid
        if(!isValid) {
            Swal.fire({
                icon: 'warning',
                title: 'Incomplete Form',
                text: 'Please fill out all required fields.',
                confirmButtonColor: '#0084FF'
            });
            return;
        }
        
        // Validate basic form fields
        if(!name || !businessUnit || !department || !date) {
            Swal.fire({
                icon: 'warning',
                title: 'Incomplete Form',
                text: 'Please fill out all required fields in the Requestor Information section.',
                confirmButtonColor: '#0084FF'
            });
            return;
        }
        
        // Add user forms to formData
        formData.append('user_forms', JSON.stringify(userForms));
        
        // Debug: Log the data being sent
        console.log('Form data:');
        for (let pair of formData.entries()) {
            console.log(pair[0] + ': ' + pair[1]);
        }
        
        // Show loading indicator
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.html('<i class="fas fa-spinner fa-spin mr-2"></i> Submitting...');
        submitBtn.prop('disabled', true);
        
        // Submit form with explicit content type
        $.ajax({
            url: 'submit.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                console.log('Response received:', response);
                submitBtn.html(originalText);
                submitBtn.prop('disabled', false);
                
                if(response.success) {
                    // Extract request number if available
                    let requestNumber = '';
                    if (response.message && response.message.includes('Request number is')) {
                        const match = response.message.match(/Request number is (\d+-\d+)/);
                        if (match && match[1]) {
                            requestNumber = match[1];
                        }
                    }

                    Swal.fire({
                        icon: 'success',
                        title: 'Request Submitted Successfully!',
                        html: `
                            <div class="ml-3">
                                              <p class="text-sm text-blue-800 font-medium">Request Number: <span class="font-bold">${requestNumber}</span></p>
                                          </div>
                        `,
                        confirmButtonColor: '#0084FF',
                        confirmButtonText: 'Close',
                        allowOutsideClick: false
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Go to my requests page
                            window.location.href = 'dashboard.php';
                        } else {
                            // Reset form for a new submission
                            $('#uarForm').trigger('reset');
                            // Refresh the page to reset all fields
                            window.location.reload();
                        }
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message || 'An error occurred while submitting the form.',
                        confirmButtonColor: '#0084FF'
                    });
                }
            },
            error: function(xhr, status, error) {
                console.log('AJAX error:', status, error);
                console.log('Response:', xhr.responseText);
                submitBtn.html(originalText);
                submitBtn.prop('disabled', false);
                
                try {
                    // Try to parse the error response
                    const response = JSON.parse(xhr.responseText);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message || 'An error occurred while submitting the form. Please try again.',
                        confirmButtonColor: '#0084FF'
                    });
                } catch (e) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'An error occurred while submitting the form. Please try again.',
                        confirmButtonColor: '#0084FF'
                    });
                }
            }
        });
    });

    // Handle form reset
    $('#uarForm').on('reset', function(e) {
        // Prevent the default reset behavior
        e.preventDefault();
        
        // Store the current access type selection
        const currentAccessType = $('input[name="access_type"]:checked').val();
        
        // Reset all form elements except radio buttons
        this.querySelectorAll('input:not([type="radio"]), select, textarea').forEach(element => {
            if (element.type === 'text' || element.type === 'date' || element.tagName === 'SELECT' || element.tagName === 'TEXTAREA') {
                element.value = '';
            }
        });

            // Reset business unit and department
            $('#business_unit').val('').trigger('change');
            $('#department').val('').prop('disabled', true);

            // Reset all date fields to disabled state
        $('.start-date, .end-date').prop('disabled', true);

            // Reset all duration selects
            $('.duration-select').val('');

            // Remove extra rows from tables, leaving only one row in each
            const individualTable = document.getElementById('individualTable');
            const groupTable = document.getElementById('groupTable');

            while (individualTable.querySelectorAll('tbody tr').length > 1) {
                individualTable.querySelector('tbody tr:last-child').remove();
            }
            while (groupTable.querySelectorAll('tbody tr').length > 1) {
                groupTable.querySelector('tbody tr:last-child').remove();
            }

        // Reset the first row in individual table
        const firstIndRow = individualTable.querySelector('tbody tr');
        if (firstIndRow) {
            firstIndRow.querySelectorAll('input:not([type="radio"]), select').forEach(input => {
                if (input.type === 'text' || input.type === 'date') {
                    input.value = '';
                } else if (input.tagName === 'SELECT') {
                    input.selectedIndex = 0;
                }
            });
        }

        // Reset the first row in group table
        const firstGrpRow = groupTable.querySelector('tbody tr');
        if (firstGrpRow) {
            firstGrpRow.querySelectorAll('input:not([type="radio"]), select').forEach(input => {
                if (input.type === 'text' || input.type === 'date') {
                    input.value = '';
                } else if (input.tagName === 'SELECT') {
                    input.selectedIndex = 0;
                }
            });
        }

        // Reset justification fields
        document.querySelectorAll('.justification-input').forEach(input => {
            input.value = '';
        });
    });
</script>
<?php include 'footer.php'; ?>

</body>
</html>

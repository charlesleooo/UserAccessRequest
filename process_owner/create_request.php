<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config.php';

// Ensure a Process Owner user is logged in
if (!isset($_SESSION['admin_id']) || ($_SESSION['role'] ?? '') !== 'process_owner') {
    header('Location: ../admin/login.php');
    exit();
}

// Mark requests section verified to skip code prompt for this page
$_SESSION['requests_verified'] = true;
$_SESSION['requests_verified_time'] = time();

// Use the admin's employee_id as the requestor id
$requestorId = $_SESSION['admin_id'];
$username = $_SESSION['admin_username'] ?? 'User';

// Fetch requestor's information
try {
    $stmt = $pdo->prepare("SELECT * FROM uar.employees WHERE employee_id = ?");
    $stmt->execute([$requestorId]);
    $requestorInfo = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($requestorInfo) {
        $fullName = $requestorInfo['employee_name'] ?? $username;
        $company = $requestorInfo['company'] ?? '';
        $departmentName = $requestorInfo['department'] ?? '';
    } else {
        $fullName = $username;
        $company = '';
        $departmentName = '';
    }
} catch (PDOException $e) {
    error_log("Process Owner Create Request - Error fetching requestor info: " . $e->getMessage());
    $fullName = $username;
    $company = '';
    $departmentName = '';
}

$systemApplications = [
    'Active Directory Access',
    'Canvassing',
    'CCTV Access',
    'Email Access',
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
    'NAV',
    'Offsite Storage Facility Access',
    'PC Access - Local',
    'PC Access - Network',
    'Piece Rate Payroll System',
    'Printer Access',
    'Quickbooks',
    'SAP',
    'Server Access',
    'TNA Biometric Device Access',
    'USB/PC-port Access',
    'VPN Access',
    'Wi-Fi/Access Point Access',
    'ZankPOS'
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Create Request - UAR System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/flowbite@2.5.1/dist/flowbite.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {}
            }
        }
    </script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .swal2-container {
            z-index: 100000 !important;
        }
    </style>
</head>

<body class="bg-gray-100" x-data="{ sidebarOpen: true }" x-init="sidebarOpen = window.innerWidth >= 1024; window.addEventListener('resize', () => { sidebarOpen = window.innerWidth >= 1024; });">

    <?php include 'sidebar.php'; ?>

    <div x-show="sidebarOpen" @click="sidebarOpen = false" class="fixed inset-0 bg-black bg-opacity-40 z-40 md:hidden"></div>

    <div class="transition-all duration-300" :class="sidebarOpen ? 'md:ml-72' : 'ml-0'">
        <div class="bg-gradient-to-r from-blue-700 to-blue-900 border-b border-blue-800 sticky top-0 z-10 shadow-lg">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center px-4 md:px-8 py-4 gap-3">
                <div class="flex items-center">
                    <button @click="sidebarOpen = !sidebarOpen" type="button" class="inline-flex items-center justify-center rounded-lg p-2 text-white hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-300 mr-3 md:mr-4 transition-all" aria-label="Toggle sidebar">
                        <svg class="w-6 h-6" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                            <path clip-rule="evenodd" fill-rule="evenodd" d="M2 4.75A.75.75 0 012.75 4h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 4.75zm0 10.5a.75.75 0 01.75-.75h7.5a.75.75 0 010 1.5h-7.5a.75.75 0 01-.75-.75zM2 10a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 10z"></path>
                        </svg>
                    </button>
                    <h2 class="text-2xl md:text-3xl lg:text-4xl font-bold text-white">Create New User Access Request (UAR)</h2>
                </div>
            </div>
        </div>

        <div class="p-5" data-aos="fade-up" data-aos-duration="800">
            <form id="uarForm" class="w-full">
                <div class="flex items-center justify-between p-4 mb-6 bg-gradient-to-r from-blue-700 to-blue-900 rounded-lg shadow-md border-b-4 border-blue-950">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-white mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                        </svg>
                        <h3 class="text-lg font-semibold text-white">Requestor Information</h3>
                    </div>
                    <div class="h-6 w-px bg-white opacity-30"></div>
                </div>

                <div class="mb-8 w-full">
                    <div class="relative overflow-x-auto shadow-md rounded-lg border border-gray-200">
                        <table class="w-full text-sm text-left text-gray-700 table-fixed">
                            <tbody>
                                <tr class="bg-white border-b hover:bg-gray-50">
                                    <td class="px-6 py-4 font-semibold text-gray-900 bg-gray-50 w-1/6">Name</td>
                                    <td class="px-6 py-4 w-1/3">
                                        <input type="text" name="name" value="<?php echo htmlspecialchars($fullName); ?>" class="bg-gray-100 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 cursor-not-allowed" readonly>
                                    </td>
                                    <td class="px-6 py-4 font-semibold text-gray-900 bg-gray-50 w-1/6">Date</td>
                                    <td class="px-6 py-4 w-1/3">
                                        <input type="text" name="date" value="<?php echo date('Y-m-d'); ?>" class="bg-gray-100 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 cursor-not-allowed" readonly>
                                    </td>
                                </tr>
                                <tr class="bg-white border-b hover:bg-gray-50">
                                    <td class="px-6 py-4 font-semibold text-gray-900 bg-gray-50">Company</td>
                                    <td class="px-6 py-4">
                                        <input type="text" name="company" id="company" value="<?php echo htmlspecialchars($company); ?>" class="bg-gray-100 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 cursor-not-allowed" readonly>
                                        <input type="hidden" name="company_value" value="<?php echo htmlspecialchars($company); ?>">
                                    </td>
                                    <td class="px-6 py-4 font-semibold text-gray-900 bg-gray-50">Department</td>
                                    <td class="px-6 py-4">
                                        <input type="text" name="department" id="department" value="<?php echo htmlspecialchars($departmentName); ?>" class="bg-gray-100 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 cursor-not-allowed" readonly>
                                        <input type="hidden" name="department_value" value="<?php echo htmlspecialchars($departmentName); ?>">
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex items-center gap-4 p-4 mb-6 bg-gradient-to-r from-blue-700 to-blue-900 rounded-lg shadow-md border-b-4 border-blue-950">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-white mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path>
                            <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"></path>
                        </svg>
                        <h3 class="text-lg font-semibold text-white">Request Details</h3>
                    </div>

                    <div class="flex gap-2 items-center">
                        <label class="relative flex items-center px-3 py-1.5 bg-white border-2 border-gray-200 rounded cursor-pointer hover:border-blue-500 transition-all has-[:checked]:border-blue-600 has-[:checked]:ring-1 has-[:checked]:ring-blue-300 has-[:checked]:bg-blue-50">
                            <input type="radio" name="access_type" value="individual" checked class="sr-only peer">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-blue-600 peer-checked:text-blue-700" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                                </svg>
                                <span class="text-xs font-semibold text-gray-900">Individual</span>
                            </div>
                        </label>
                        <label class="relative flex items-center px-3 py-1.5 bg-white border-2 border-gray-200 rounded cursor-pointer hover:border-blue-500 transition-all has-[:checked]:border-blue-600 has-[:checked]:ring-1 has-[:checked]:ring-blue-300 has-[:checked]:bg-blue-50">
                            <input type="radio" name="access_type" value="group" class="sr-only peer">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-blue-600 peer-checked:text-blue-700" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z"></path>
                                </svg>
                                <span class="text-xs font-semibold text-gray-900">Group</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Individual Table -->
                <div id="individualAccess" class="mb-0">
                    <button type="button" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 mb-5 inline-flex items-center transition-all shadow-sm hover:shadow-md" onclick="addRow('individualTable')">
                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd"></path>
                        </svg>
                        Additional Request
                    </button>
                    <div class="relative overflow-x-auto shadow-md rounded-lg border border-gray-200 flowbite-table">
                        <table id="individualTable" class="w-full text-sm text-left text-gray-700">
                            <thead class="text-xs text-white uppercase bg-gradient-to-r from-blue-700 to-blue-900 border-b-2 border-blue-950">
                                <tr>
                                    <th class="px-4 py-3 font-semibold w-[25%]">User Name <span class="text-red-600">*</span></th>
                                    <th class="px-4 py-3 font-semibold w-[17%]">Application/System <span class="text-red-600">*</span></th>
                                    <th class="px-4 py-3 font-semibold w-[8%]">Access Type <span class="text-red-600">*</span></th>
                                    <th class="px-4 py-3 font-semibold w-[11%]">Access Duration <span class="text-red-600">*</span></th>
                                    <th class="px-2 py-3 font-semibold w-[4%] ind-start-date-header hidden">Start Date <span class="text-red-600">*</span></th>
                                    <th class="px-2 py-3 font-semibold w-[4%] ind-end-date-header hidden">End Date <span class="text-red-600">*</span></th>
                                    <th class="px-2 py-3 font-semibold w-[4%]">Date Needed <span class="text-red-600">*</span></th>
                                    <th class="px-4 py-3 font-semibold w-[15.5%]">Justification <span class="text-red-600">*</span></th>
                                    <th class="px-4 py-3 font-semibold w-[5%] text-center">Delete</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-4 py-3">
                                        <input type="text" placeholder="User Name" name="ind_user_names" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 transition-all">
                                    </td>
                                    <td class="px-4 py-3">
                                        <select name="ind_application[]" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 transition-all" required>
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
                                    <td class="border border-gray-200 p-1 hidden">
                                        <input type="date" name="ind_start_date[]" class="start-date w-full p-1 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" disabled>
                                    </td>
                                    <td class="border border-gray-200 p-1 hidden">
                                        <input type="date" name="ind_end_date[]" class="end-date w-full p-1 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" disabled>
                                    </td>
                                    <td class="border border-gray-200 p-1">
                                        <input type="date" name="ind_date_needed[]" class="date-needed w-full p-1 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" required>
                                    </td>
                                    <td class="border border-gray-200 p-2">
                                        <input type="text" placeholder="Click to add justification" name="ind_justification[]" class="justification-input w-full p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition bg-gray-50 cursor-pointer" required>
                                    </td>
                                    <td class="border border-gray-200 p-2 text-center w-16">
                                        <button type="button" class="bg-red-600 hover:bg-red-700 text-white p-2 rounded transition" onclick="deleteRow(this)">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Group Table -->
                <div id="groupAccess" class="mb-0 hidden">
                    <button type="button" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 mb-5 inline-flex items-center transition-all shadow-sm hover:shadow-md" onclick="addRow('groupTable')">
                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd"></path>
                        </svg>
                        Additional Request
                    </button>
                    <div class="relative overflow-x-auto shadow-md rounded-lg border border-gray-200 flowbite-table">
                        <table id="groupTable" class="w-full text-sm text-left text-gray-700">
                            <thead class="text-xs text-white uppercase bg-gradient-to-r from-blue-700 to-blue-900 border-b-2 border-blue-950">
                                <tr>
                                    <th class="px-4 py-3 font-semibold w-[18.5%]">Application/System <span class="text-red-600">*</span></th>
                                    <th class="px-4 py-3 font-semibold w-[32%] min-w-[200px]">User Name <span class="text-red-600">*</span></th>
                                    <th class="px-4 py-3 font-semibold w-[8%] min-w-[110px]">Access Type <span class="text-red-600">*</span></th>
                                    <th class="px-4 py-3 font-semibold w-[12%]">Access Duration <span class="text-red-600">*</span></th>
                                    <th class="px-2 py-3 font-semibold w-[3%] grp-start-date-header hidden">Start Date <span class="text-red-600">*</span></th>
                                    <th class="px-2 py-3 font-semibold w-[3%] grp-end-date-header hidden">End Date <span class="text-red-600">*</span></th>
                                    <th class="px-2 py-3 font-semibold w-[3%]">Date Needed <span class="text-red-600">*</span></th>
                                    <th class="px-4 py-3 font-semibold w-[15.5%]">Justification <span class="text-red-600">*</span></th>
                                    <th class="px-4 py-3 font-semibold w-[5%] text-center">Delete</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr class="application-row hover:bg-gray-50 transition-colors" data-app-id="1">
                                    <td class="px-4 py-3">
                                        <div class="flex gap-2 items-center">
                                            <select name="grp_application[]" class="app-select flex-1 bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 transition-all" required>
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
                                            <input type="text" placeholder="User Name" name="grp_user_names[]" class="w-full p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" required>
                                            <button type="button" class="bg-blue-600 hover:bg-blue-700 text-white p-2 rounded transition flex-shrink-0" onclick="addUserRow(this)" title="Add User">
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
                                    <td class="border border-gray-200 p-1 hidden">
                                        <input type="date" name="grp_start_date[]" class="start-date w-full p-1 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" disabled>
                                    </td>
                                    <td class="border border-gray-200 p-1 hidden">
                                        <input type="date" name="grp_end_date[]" class="end-date w-full p-1 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" disabled>
                                    </td>
                                    <td class="border border-gray-200 p-1">
                                        <input type="date" name="grp_date_needed[]" class="date-needed w-full p-1 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" required>
                                    </td>
                                    <td class="border border-gray-200 p-2">
                                        <input type="text" placeholder="Click to add justification" name="grp_justification[]" class="justification-input w-full p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition bg-gray-50 cursor-pointer" required>
                                    </td>
                                    <td class="border border-gray-200 p-2 text-center w-16">
                                        <button type="button" class="bg-red-600 hover:bg-red-700 text-white p-2 rounded transition" onclick="deleteRow(this)">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-8 flex justify-center gap-4 flex-wrap">
                    <button type="submit" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-base px-8 py-3.5 inline-flex items-center justify-center min-w-[200px] shadow-md hover:shadow-lg transition-all">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z"></path>
                        </svg>
                        Submit Request
                    </button>
                    <button type="reset" class="text-white bg-gray-600 hover:bg-gray-700 focus:ring-4 focus:outline-none focus:ring-gray-300 font-medium rounded-lg text-base px-8 py-3.5 inline-flex items-center justify-center min-w-[200px] shadow-md hover:shadow-lg transition-all">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"></path>
                        </svg>
                        Reset Form
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Date controls and justification modal
        function toggleDateFields(select) {
            const row = select.closest('tr');
            const table = select.closest('table');
            const isIndividualTable = table.id === 'individualTable';
            const startDateCell = row.querySelector('.start-date').closest('td');
            const endDateCell = row.querySelector('.end-date').closest('td');
            const startDate = row.querySelector('.start-date');
            const endDate = row.querySelector('.end-date');
            const isTemporary = select.value === 'temporary';

            if (isTemporary) {
                startDateCell.classList.remove('hidden');
                endDateCell.classList.remove('hidden');
                startDate.disabled = false;
                endDate.disabled = false;
                startDate.required = true;
                endDate.required = true;
                const today = new Date().toISOString().split('T')[0];
                startDate.min = today;
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
                if (isIndividualTable) {
                    document.querySelectorAll('.ind-start-date-header').forEach(h => h.classList.remove('hidden'));
                    document.querySelectorAll('.ind-end-date-header').forEach(h => h.classList.remove('hidden'));
                } else {
                    document.querySelectorAll('.grp-start-date-header').forEach(h => h.classList.remove('hidden'));
                    document.querySelectorAll('.grp-end-date-header').forEach(h => h.classList.remove('hidden'));
                }
            } else {
                startDateCell.classList.add('hidden');
                endDateCell.classList.add('hidden');
                startDate.disabled = true;
                endDate.disabled = true;
                startDate.required = false;
                endDate.required = false;
                startDate.value = '';
                endDate.value = '';
                const allRows = table.querySelectorAll('tbody tr');
                let hasTemporary = false;
                allRows.forEach(r => {
                    const ds = r.querySelector('.duration-select');
                    if (ds && ds.value === 'temporary') hasTemporary = true;
                });
                if (!hasTemporary) {
                    if (isIndividualTable) {
                        document.querySelectorAll('.ind-start-date-header').forEach(h => h.classList.add('hidden'));
                        document.querySelectorAll('.ind-end-date-header').forEach(h => h.classList.add('hidden'));
                    } else {
                        document.querySelectorAll('.grp-start-date-header').forEach(h => h.classList.add('hidden'));
                        document.querySelectorAll('.grp-end-date-header').forEach(h => h.classList.add('hidden'));
                    }
                }
            }
        }

        function addRow(tableId) {
            const table = document.getElementById(tableId);
            if (tableId === 'individualTable') {
                const tbody = table.querySelector('tbody');
                const rows = tbody.querySelectorAll('tr');
                const lastRow = rows[rows.length - 1];
                const firstRowUsername = lastRow.querySelector('input[name="ind_user_names"]');
                const copyUsername = lastRow.querySelector('input[name="ind_username_copy[]"]');
                const username = firstRowUsername && firstRowUsername.offsetParent !== null ? firstRowUsername.value : (copyUsername ? copyUsername.value : '');
                const application = lastRow.querySelector('select[name="ind_application[]"]').value;
                const accessType = lastRow.querySelector('select[name="ind_access_type[]"]').value;
                const durationType = lastRow.querySelector('select[name="ind_duration_type[]"]').value;
                const startDate = lastRow.querySelector('input[name="ind_start_date[]"]').value;
                const endDate = lastRow.querySelector('input[name="ind_end_date[]"]').value;
                const dateNeeded = lastRow.querySelector('input[name="ind_date_needed[]"]').value;
                const justification = lastRow.querySelector('input[name="ind_justification[]"]').value;
                if (!username || !application || !accessType || !durationType || !dateNeeded || !justification) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Complete Current Row',
                        text: 'Please complete all fields in the current row before adding a new row.',
                        confirmButtonColor: '#0084FF'
                    });
                    return;
                }
                if (durationType === 'temporary' && (!startDate || !endDate)) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Missing Dates',
                        text: 'Please set both start and end dates for temporary access.',
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
                        <select name="ind_application[]" class="w-full p-2 border border-gray-300 rounded" required>
                            <option value="">Select Application</option>
                            <?php foreach ($systemApplications as $app): ?>
                                <option value="<?php echo $app; ?>"><?php echo $app; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="ind_application_system[]" value="">
                    </td>
                    <td class="border border-gray-200 p-2">
                        <select name="ind_access_type[]" class="w-full p-2 border border-gray-300 rounded" required>
                            <option value=""></option>
                            <option value="full">Full</option>
                            <option value="read">Read</option>
                            <option value="admin">Admin</option>
                        </select>
                    </td>
                    <td class="border border-gray-200 p-2">
                        <select name="ind_duration_type[]" class="duration-select w-full p-2 border border-gray-300 rounded" required onchange="toggleDateFields(this)">
                            <option value=""></option>
                            <option value="permanent">Permanent</option>
                            <option value="temporary">Temporary</option>
                        </select>
                    </td>
                    <td class="border border-gray-200 p-1 hidden">
                        <input type="date" name="ind_start_date[]" class="start-date w-full p-1 border border-gray-300 rounded" disabled>
                    </td>
                    <td class="border border-gray-200 p-1 hidden">
                        <input type="date" name="ind_end_date[]" class="end-date w-full p-1 border border-gray-300 rounded" disabled>
                    </td>
                    <td class="border border-gray-200 p-1">
                        <input type="date" name="ind_date_needed[]" class="date-needed w-full p-1 border border-gray-300 rounded" required>
                    </td>
                    <td class="border border-gray-200 p-2">
                        <input type="text" placeholder="Click to add justification" name="ind_justification[]" class="justification-input w-full p-2 border border-gray-300 rounded bg-gray-50 cursor-pointer" required>
                    </td>
                    <td class="border border-gray-200 p-2 text-center w-16">
                        <button type="button" class="bg-red-600 hover:bg-red-700 text-white p-2 rounded transition" onclick="deleteRow(this)">
                            <i class="fa fa-trash"></i>
                        </button>
                    </td>`;
                tbody.appendChild(newRow);
                setupJustificationInputs();
                const durationSelect = newRow.querySelector('.duration-select');
                if (durationSelect) durationSelect.addEventListener('change', function() {
                    toggleDateFields(this);
                });
            } else {
                const tbody = table.querySelector('tbody');

                // Find the last application-row specifically (not user-row)
                const applicationRows = tbody.querySelectorAll('tr.application-row');
                const lastAppRow = applicationRows[applicationRows.length - 1];

                const application = lastAppRow.querySelector('select[name="grp_application[]"]').value;
                const username = lastAppRow.querySelector('input[name="grp_user_names[]"]').value;
                const accessType = lastAppRow.querySelector('select[name="grp_access_type[]"]').value;
                const durationType = lastAppRow.querySelector('select[name="grp_duration_type[]"]').value;
                const dateNeeded = lastAppRow.querySelector('input[name="grp_date_needed[]"]').value;
                const justification = lastAppRow.querySelector('input[name="grp_justification[]"]').value;
                if (!application || !username || !accessType || !durationType || !dateNeeded || !justification) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Complete Current Row',
                        text: 'Please complete all fields in the current application row before adding a new application.',
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
                            <select name="grp_application[]" class="app-select flex-1 p-2 border border-gray-300 rounded" required>
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
                            <input type="text" placeholder="User Name" name="grp_user_names[]" class="w-full p-2 border border-gray-300 rounded" required>
                            <button type="button" class="bg-blue-600 hover:bg-blue-700 text-white p-2 rounded transition flex-shrink-0" onclick="addUserRow(this)" title="Add User">
                                <i class="fa fa-user-plus"></i>
                            </button>
                        </div>
                    </td>
                    <td class="border border-gray-200 p-2">
                        <select name="grp_access_type[]" class="w-full p-2 border border-gray-300 rounded" required>
                            <option value=""></option>
                            <option value="full">Full</option>
                            <option value="read">Read</option>
                            <option value="admin">Admin</option>
                        </select>
                    </td>
                    <td class="border border-gray-200 p-2">
                        <select name="grp_duration_type[]" class="duration-select w-full p-2 border border-gray-300 rounded" required onchange="toggleDateFields(this)">
                            <option value=""></option>
                            <option value="permanent">Permanent</option>
                            <option value="temporary">Temporary</option>
                        </select>
                    </td>
                    <td class="border border-gray-200 p-1 hidden">
                        <input type="date" name="grp_start_date[]" class="start-date w-full p-1 border border-gray-300 rounded" disabled>
                    </td>
                    <td class="border border-gray-200 p-1 hidden">
                        <input type="date" name="grp_end_date[]" class="end-date w-full p-1 border border-gray-300 rounded" disabled>
                    </td>
                    <td class="border border-gray-200 p-1">
                        <input type="date" name="grp_date_needed[]" class="date-needed w-full p-1 border border-gray-300 rounded" required>
                    </td>
                    <td class="border border-gray-200 p-2">
                        <input type="text" placeholder="Click to add justification" name="grp_justification[]" class="justification-input w-full p-2 border border-gray-300 rounded bg-gray-50 cursor-pointer" required>
                    </td>
                    <td class="border border-gray-200 p-2 text-center w-16">
                        <button type="button" class="bg-red-600 hover:bg-red-700 text-white p-2 rounded transition" onclick="deleteRow(this)">
                            <i class="fa fa-trash"></i>
                        </button>
                    </td>`;
                tbody.appendChild(newRow);
                setupJustificationInputs();
                const durationSelect = newRow.querySelector('.duration-select');
                if (durationSelect) durationSelect.addEventListener('change', function() {
                    toggleDateFields(this);
                });
            }
            setupApplicationSelects();
        }

        function addUserRow(button) {
            const appRow = button.closest('.application-row');
            const appId = appRow.getAttribute('data-app-id');
            const appSelect = appRow.querySelector('.app-select');
            const selectedApp = appSelect.value;
            const accessType = appRow.querySelector('select[name="grp_access_type[]"]').value;
            const durationType = appRow.querySelector('select[name="grp_duration_type[]"]').value;
            const dateNeeded = appRow.querySelector('input[name="grp_date_needed[]"]').value;
            const justification = appRow.querySelector('input[name="grp_justification[]"]').value;
            const username = appRow.querySelector('input[name="grp_user_names[]"]').value;
            const missing = [];
            if (!selectedApp) missing.push('Application');
            if (!username) missing.push('Username');
            if (!accessType) missing.push('Access Type');
            if (!durationType) missing.push('Duration Type');
            if (!dateNeeded) missing.push('Date Needed');
            if (!justification) missing.push('Justification');
            if (missing.length) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Missing Information',
                    text: 'Please complete: ' + missing.join(', '),
                    confirmButtonColor: '#0084FF'
                });
                return;
            }
            if (durationType === 'temporary') {
                const startDate = appRow.querySelector('input[name="grp_start_date[]"]').value;
                const endDate = appRow.querySelector('input[name="grp_end_date[]"]').value;
                if (!startDate || !endDate) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Missing Dates',
                        text: 'Please set start and end dates for temporary access.',
                        confirmButtonColor: '#0084FF'
                    });
                    return;
                }
                if (new Date(startDate) > new Date(endDate)) {
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
                <td class="border border-gray-200 p-2 bg-gray-50">${selectedApp}
                    <input type="hidden" name="grp_application[]" value="${selectedApp}">
                    <input type="hidden" name="grp_application_system[]" value="${selectedApp}">
                </td>
                <td class="border border-gray-200 p-2"><input type="text" placeholder="User Name" name="grp_user_names[]" class="w-full p-2 border border-gray-300 rounded" required></td>
                <td class="border border-gray-200 p-2">
                    <select name="grp_access_type[]" class="w-full p-2 border border-gray-300 rounded" required>
                        <option value="">Select Access Type</option>
                        <option value="full">Full</option>
                        <option value="read">Read</option>
                        <option value="admin">Admin</option>
                    </select>
                </td>
                <td class="border border-gray-200 p-2">
                    <select name="grp_duration_type[]" class="duration-select w-full p-2 border border-gray-300 rounded" required onchange="toggleDateFields(this)">
                        <option value="">Select Duration</option>
                        <option value="permanent">Permanent</option>
                        <option value="temporary">Temporary</option>
                    </select>
                </td>
                <td class="border border-gray-200 p-1 hidden"><input type="date" name="grp_start_date[]" class="start-date w-full p-1 border border-gray-300 rounded" disabled></td>
                <td class="border border-gray-200 p-1 hidden"><input type="date" name="grp_end_date[]" class="end-date w-full p-1 border border-gray-300 rounded" disabled></td>
                <td class="border border-gray-200 p-1"><input type="date" name="grp_date_needed[]" class="date-needed w-full p-1 border border-gray-300 rounded" required></td>
                <td class="border border-gray-200 p-2"><input type="text" placeholder="Click to add justification" name="grp_justification[]" class="justification-input w-full p-2 border border-gray-300 rounded bg-gray-50 cursor-pointer" required></td>
                <td class="border border-gray-200 p-2 text-center w-16"><button type="button" class="bg-red-600 hover:bg-red-700 text-white p-2 rounded transition" onclick="deleteRow(this)"><i class="fa fa-trash"></i></button></td>`;
            appRow.after(newRow);
            setupJustificationInputs();
            const durationSelect = newRow.querySelector('.duration-select');
            if (durationSelect) durationSelect.addEventListener('change', function() {
                toggleDateFields(this);
            });
            setupApplicationSelects();
        }

        function setupApplicationSelects() {
            document.querySelectorAll('select[name="ind_application[]"]').forEach(function(select, index) {
                select.addEventListener('change', function() {
                    const hidden = document.getElementsByName('ind_application_system[]')[index];
                    if (hidden) hidden.value = this.value;
                });
            });
            document.querySelectorAll('select[name="grp_application[]"]').forEach(function(select, index) {
                select.addEventListener('change', function() {
                    const hidden = document.getElementsByName('grp_application_system[]')[index];
                    if (hidden) hidden.value = this.value;
                });
            });
        }

        function setupJustificationInputs() {
            document.querySelectorAll('.justification-input').forEach(input => {
                input.addEventListener('click', function(e) {
                    const currentInput = e.target;
                    Swal.fire({
                        title: 'Reason for Access',
                        input: 'textarea',
                        inputLabel: 'Please provide detailed justification',
                        inputPlaceholder: 'Enter details...',
                        inputValue: currentInput.value,
                        showCancelButton: true,
                        confirmButtonText: 'Save & Close',
                        confirmButtonColor: '#0084FF'
                    }).then(res => {
                        if (res.isConfirmed && res.value) currentInput.value = res.value;
                    });
                });
            });
        }
        setupJustificationInputs();
        setupApplicationSelects();

        // Delete row function
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

        $(function() {
            // Handle access type required attributes
            $('input[name="access_type"]').change(function() {
                const type = $(this).val();
                if (type === 'individual') {
                    $('#groupTable').find('[required]').prop('required', false);
                    $('#individualTable').find('select, input[type="text"], input[type="date"]').not('.start-date, .end-date').prop('required', true);
                    $('#individualAccess').removeClass('hidden');
                    $('#groupAccess').addClass('hidden');
                } else {
                    $('#individualTable').find('[required]').prop('required', false);
                    $('#groupTable').find('select, input[type="text"], input[type="date"]').not('.start-date, .end-date').prop('required', true);
                    $('#individualAccess').addClass('hidden');
                    $('#groupAccess').removeClass('hidden');
                }
            }).trigger('change');

            // Date-needed min = today
            const today = new Date().toISOString().split('T')[0];
            $(document).on('focus', '.date-needed', function() {
                $(this).attr('min', today);
            });

            // Submit form
            $('#uarForm').on('submit', function(e) {
                e.preventDefault();
                const formData = new FormData();
                const name = $('input[name="name"]').val();
                const company = $('#company').val();
                const department = $('#department').val();
                const accessType = $('input[name="access_type"]:checked').val();
                const date = $('input[name="date"]').val();
                formData.append('requestor_name', name);
                formData.append('company', company);
                formData.append('department', department);
                formData.append('request_date', date);
                formData.append('employee_id', <?php echo json_encode($requestorId); ?>);
                formData.append('employee_email', <?php echo json_encode($_SESSION['employee_email'] ?? ($requestorInfo['employee_email'] ?? '')); ?>);
                const userForms = [];
                if (accessType === 'individual') {
                    $('#individualTable tbody tr').each(function(index) {
                        const username = index === 0 ? $('input[name="ind_user_names"]').val() : $(this).find('input[name="ind_username_copy[]"]').val();
                        const app = $(this).find('select[name="ind_application[]"]').val();
                        const rtype = $(this).find('select[name="ind_access_type[]"]').val();
                        const dtype = $(this).find('select[name="ind_duration_type[]"]').val();
                        const sdate = $(this).find('input[name="ind_start_date[]"]').val();
                        const edate = $(this).find('input[name="ind_end_date[]"]').val();
                        const dneed = $(this).find('input[name="ind_date_needed[]"]').val();
                        const just = $(this).find('input[name="ind_justification[]"]').val();
                        const appSys = $(this).find('input[name="ind_application_system[]"]').val();
                        if (app && rtype && dtype && dneed && just && username) {
                            userForms.push({
                                access_type: 'individual',
                                system_type: app,
                                application_system: appSys,
                                role_access_type: rtype,
                                duration_type: dtype,
                                start_date: sdate,
                                end_date: edate,
                                date_needed: dneed,
                                justification: just,
                                user_names: [username]
                            });
                        }
                    });
                } else {
                    $('#groupTable tbody tr').each(function() {
                        const app = $(this).find('select[name="grp_application[]"]').val() || $(this).find('input[name="grp_application[]"]').val();
                        const username = $(this).find('input[name="grp_user_names[]"]').val();
                        const rtype = $(this).find('select[name="grp_access_type[]"]').val();
                        const dtype = $(this).find('select[name="grp_duration_type[]"]').val();
                        const sdate = $(this).find('input[name="grp_start_date[]"]').val();
                        const edate = $(this).find('input[name="grp_end_date[]"]').val();
                        const dneed = $(this).find('input[name="grp_date_needed[]"]').val();
                        const just = $(this).find('input[name="grp_justification[]"]').val();
                        const appSys = $(this).find('input[name="grp_application_system[]"]').val();
                        if (app && username && rtype && dtype && dneed && just) {
                            const idx = userForms.findIndex(f => f.system_type === app);
                            if (idx !== -1) userForms[idx].user_names.push(username);
                            else userForms.push({
                                access_type: 'group',
                                system_type: app,
                                application_system: appSys,
                                role_access_type: rtype,
                                duration_type: dtype,
                                start_date: sdate,
                                end_date: edate,
                                date_needed: dneed,
                                justification: just,
                                user_names: [username]
                            });
                        }
                    });
                }
                if (userForms.length === 0) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Missing Information',
                        text: 'Please add at least one valid access request.',
                        confirmButtonColor: '#0084FF'
                    });
                    return;
                }
                if (!name || !company || !department || !date) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Incomplete Form',
                        text: 'Please complete Requestor Information.',
                        confirmButtonColor: '#0084FF'
                    });
                    return;
                }
                formData.append('user_forms', JSON.stringify(userForms));
                const submitBtn = $(this).find('button[type="submit"]');
                const originalText = submitBtn.html();
                submitBtn.html('<i class="fas fa-spinner fa-spin mr-2"></i> Submitting...');
                submitBtn.prop('disabled', true);
                $.ajax({
                        url: 'submit.php',
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        dataType: 'json'
                    })
                    .done(function(response) {
                        submitBtn.html(originalText);
                        submitBtn.prop('disabled', false);
                        if (response.success) {
                            let reqNo = '';
                            if (response.message && response.message.includes('Request number is')) {
                                const m = response.message.match(/Request number is (\d+-\d+)/);
                                if (m && m[1]) reqNo = m[1];
                            }
                            Swal.fire({
                                    icon: 'success',
                                    title: 'Request Submitted Successfully!',
                                    html: `<div class="ml-3"><p class="text-sm text-blue-800 font-medium">Request Number: <span class="font-bold">${reqNo}</span></p></div>`,
                                    confirmButtonColor: '#0084FF'
                                })
                                .then(() => {
                                    window.location.href = 'dashboard.php';
                                });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.message || 'An error occurred while submitting the form.',
                                confirmButtonColor: '#0084FF'
                            });
                        }
                    })
                    .fail(function(xhr) {
                        submitBtn.html(originalText);
                        submitBtn.prop('disabled', false);
                        let msg = 'An error occurred while submitting the form.';
                        try {
                            const r = JSON.parse(xhr.responseText);
                            if (r.message) msg = r.message;
                        } catch (e) {}
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: msg,
                            confirmButtonColor: '#0084FF'
                        });
                    });
            });

            // Reset handler
            $('#uarForm').on('reset', function(e) {
                e.preventDefault();
                const origName = $('input[name="name"]').val();
                const origDate = $('input[name="date"]').val();
                const origComp = $('#company').val();
                const origDept = $('#department').val();
                this.querySelectorAll('input:not([type="radio"]):not([readonly]), select:not([readonly]), textarea:not([readonly])').forEach(el => {
                    if (el.type === 'text' || el.type === 'date' || el.tagName === 'SELECT' || el.tagName === 'TEXTAREA') el.value = '';
                });
                $('input[name="name"]').val(origName);
                $('input[name="date"]').val(origDate);
                $('#company').val(origComp);
                $('#department').val(origDept);
                $('.start-date').closest('td').addClass('hidden');
                $('.end-date').closest('td').addClass('hidden');
                $('.start-date, .end-date').prop('disabled', true).prop('required', false);
                $('.ind-start-date-header, .ind-end-date-header, .grp-start-date-header, .grp-end-date-header').addClass('hidden');
                $('.duration-select').val('');
                const it = document.getElementById('individualTable');
                const gt = document.getElementById('groupTable');
                while (it.querySelectorAll('tbody tr').length > 1) it.querySelector('tbody tr:last-child').remove();
                while (gt.querySelectorAll('tbody tr').length > 1) gt.querySelector('tbody tr:last-child').remove();
                document.querySelectorAll('.justification-input').forEach(i => i.value = '');
            });
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/flowbite@2.5.1/dist/flowbite.min.js"></script>
    <script>
        // Ensure AOS reveals elements with data-aos on this page
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof AOS !== 'undefined') {
                AOS.init({
                    duration: 800,
                    easing: 'ease-in-out',
                    once: true,
                    offset: 100
                });
            } else {
                // Fallback: if AOS failed to load, make sure content is visible
                document.querySelectorAll('[data-aos]').forEach(function(el) {
                    el.style.opacity = 1;
                    el.style.transform = 'none';
                });
            }
        });
    </script>
    <?php include '../footer.php'; ?>
</body>

</html>
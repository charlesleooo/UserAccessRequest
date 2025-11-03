<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['requestor_id'])) {
    header("Location: login.php");
    exit();
}

// Set verification flag directly
$_SESSION['requests_verified'] = true;
$_SESSION['requests_verified_time'] = time();

$requestorId = $_SESSION['requestor_id'];
$username = $_SESSION['username'] ?? 'User';

// Debug session data
error_log("Session data: " . json_encode($_SESSION));
error_log("Requestor ID: " . $requestorId);
error_log("Username: " . $username);

// Fetch requestor's complete information
try {
    $stmt = $pdo->prepare("SELECT * FROM uar.employees WHERE employee_id = ?");
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
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
        FROM uar.access_requests
        WHERE requestor_id = ?");
    $stmt->execute([$requestorId]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    $total = $data['total'] ?? 0;
    $approved = $data['approved'] ?? 0;
    $rejected = $data['rejected'] ?? 0;
    $pending = $data['pending'] ?? 0;

    $approvalRate = $total > 0 ? round(($approved / $total) * 100, 1) : 0;
    $declineRate = $total > 0 ? round(($rejected / $total) * 100, 1) : 0;

    $stmt = $pdo->prepare("SELECT TOP 5 * FROM uar.access_requests WHERE requestor_id = ? ORDER BY submission_date DESC");
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
    <title>Create Request - UAR System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Flowbite CSS -->
    <link href="https://cdn.jsdelivr.net/npm/flowbite@2.5.1/dist/flowbite.min.css" rel="stylesheet" />
    
    <!-- Boxicons -->
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Animate on Scroll -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f9fafb;
            margin: 0;
            padding: 0;
        }

        input,
        select,
        button {
            font-family: 'Inter', sans-serif;
        }
        
        /* Flowbite Table Enhancements */
        .flowbite-table input,
        .flowbite-table select {
            transition: all 0.2s ease-in-out;
        }
        
        .flowbite-table input:focus,
        .flowbite-table select:focus {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        /* Ensure SweetAlert is always on top and not clipped */
        .swal2-container {
            z-index: 100000 !important;
        }

        /* Force visible SweetAlert buttons (prevent transparent/white-on-white styles) */
        .swal2-styled.swal2-confirm {
            background-color: #0084FF !important;
            color: #ffffff !important;
            border: none !important;
        }
        .swal2-styled.swal2-confirm:hover,
        .swal2-styled.swal2-confirm:focus {
            background-color: #1d4ed8 !important;
            color: #ffffff !important;
        }
        .swal2-styled.swal2-cancel {
            background-color: #6B7280 !important;
            color: #ffffff !important;
            border: none !important;
        }
        .swal2-styled.swal2-cancel:hover,
        .swal2-styled.swal2-cancel:focus {
            background-color: #4b5563 !important;
            color: #ffffff !important;
        }
    </style>
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
                            DEFAULT: '#3b82f6',
                            dark: '#1d4ed8',
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

<body class="bg-gray-100" x-data="{ sidebarOpen: true }" x-init="$store.app = { sidebarOpen: true }">

    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

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
    <div class="transition-all duration-300" :class="sidebarOpen ? 'md:ml-72' : 'ml-0'">
        <!-- Header - Flowbite Style -->
        <div class="bg-gradient-to-r from-blue-700 to-blue-900 border-b border-blue-800 sticky top-0 z-10 shadow-lg">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center px-4 md:px-8 py-4 gap-3">
                <div class="flex items-center" data-aos="fade-right" data-aos-duration="800">
                    <!-- Hamburger button for toggling sidebar -->
                    <button
                        @click="sidebarOpen = !sidebarOpen"
                        type="button"
                        class="inline-flex items-center justify-center rounded-lg p-2 text-white hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-300 mr-3 md:mr-4 transition-all"
                        aria-label="Toggle sidebar">
                        <svg class="w-6 h-6" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                            <path clip-rule="evenodd" fill-rule="evenodd" d="M2 4.75A.75.75 0 012.75 4h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 4.75zm0 10.5a.75.75 0 01.75-.75h7.5a.75.75 0 010 1.5h-7.5a.75.75 0 01-.75-.75zM2 10a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 10z"></path>
                        </svg>
                    </button>
                    <div>
                        <h2 class="text-2xl md:text-3xl lg:text-4xl font-bold text-white">Create New User Access Request (UAR)</h2>
                    </div>
                </div>
                <div class="relative" x-data="{ privacyNoticeOpen: false }" @mouseover="privacyNoticeOpen = true" @mouseleave="privacyNoticeOpen = false">
                    <button type="button" class="inline-flex items-center p-2 text-white bg-blue-800 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-300 transition-all">
                        <svg class="w-5 h-5" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="sr-only">Privacy info</span>
                    </button>
                    <div x-cloak x-show="privacyNoticeOpen"
                        class="absolute right-0 mt-2 w-72 p-4 bg-white rounded-lg shadow-xl text-gray-700 text-sm z-50 border border-gray-200"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 transform scale-95"
                        x-transition:enter-end="opacity-100 transform scale-100"
                        x-transition:leave="transition ease-in duration-75"
                        x-transition:leave-start="opacity-100 transform scale-100"
                        x-transition:leave-end="opacity-0 transform scale-95">
                        <div class="flex items-start mb-2">
                            <svg class="w-5 h-5 text-blue-600 mr-2 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                            </svg>
                            <p class="font-semibold text-gray-900">Data Privacy Notice</p>
                        </div>
                        <p class="ml-7 text-gray-600">Your data is used solely for processing access requests and is handled according to our internal privacy policy.</p>
                    </div>
                </div>
            </div>
        </div>


        <div class="p-5" data-aos="fade-up" data-aos-duration="800">
            <form id="uarForm" class="w-full">
                <!-- Requestor Information -->
                <div class="flex items-center justify-between p-4 mb-6 bg-gradient-to-r from-blue-700 to-blue-900 rounded-lg shadow-md border-b-4 border-blue-950">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-white mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                        </svg>
                        <h3 class="text-lg font-semibold text-white">Requestor Information</h3>
                    </div>
                    <div class="h-6 w-px bg-white opacity-30"></div>
                </div>
                <!-- Flowbite Info Table -->
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
                                    <td class="px-6 py-4 font-semibold text-gray-900 bg-gray-50">Business Unit Entity</td>
                                    <td class="px-6 py-4">
                                        <input type="text" name="business_unit" id="business_unit" value="<?php echo htmlspecialchars($businessUnit); ?>" class="bg-gray-100 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 cursor-not-allowed" readonly>
                                        <input type="hidden" name="business_unit_value" value="<?php echo htmlspecialchars($businessUnit); ?>">
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

                <!-- Request Details -->
                <div class="flex items-center gap-4 p-4 mb-6 bg-gradient-to-r from-blue-700 to-blue-900 rounded-lg shadow-md border-b-4 border-blue-950">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-white mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path>
                            <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"></path>
                        </svg>
                        <h3 class="text-lg font-semibold text-white">Request Details</h3>
                    </div>
                    
                    <!-- Access Type Selector - Compact inline buttons -->
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

                <!-- Individual Access -->
                <div id="individualAccess" class="mb-0">
                    <div class="flex items-center gap-4 mb-5">
                
                    </div>
                    <!-- Flowbite Button -->
                    <button type="button" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 mb-5 inline-flex items-center transition-all shadow-sm hover:shadow-md" onclick="addRow('individualTable')">
                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd"></path>
                        </svg>
                        Additional Request
                    </button>
                    <!-- Flowbite Table -->
                    <div class="relative overflow-x-auto shadow-md rounded-lg border border-gray-200 flowbite-table">
                        <table id="individualTable" class="w-full text-sm text-left text-gray-700">
                            <thead class="text-xs text-white uppercase bg-gradient-to-r from-blue-700 to-blue-900 border-b-2 border-blue-950">
                                <tr>
                                    <th scope="col" class="px-4 py-3 font-semibold w-[25%]">User Name <span class="text-red-600">*</span></th>
                                    <th scope="col" class="px-4 py-3 font-semibold w-[17%]">Application/System <span class="text-red-600">*</span></th>
                                    <th scope="col" class="px-4 py-3 font-semibold w-[8%]">Access Type <span class="text-red-600">*</span></th>
                                    <th scope="col" class="px-4 py-3 font-semibold w-[11%]">Access Duration <span class="text-red-600">*</span></th>
                                    <th scope="col" class="px-2 py-3 font-semibold w-[4%]">Start Date <span class="text-red-600">*</span></th>
                                    <th scope="col" class="px-2 py-3 font-semibold w-[4%]">End Date <span class="text-red-600">*</span></th>
                                    <th scope="col" class="px-2 py-3 font-semibold w-[4%]">Date Needed <span class="text-red-600">*</span></th>
                                    <th scope="col" class="px-4 py-3 font-semibold w-[15.5%]">Justification <span class="text-red-600">*</span></th>
                                    <th scope="col" class="px-4 py-3 font-semibold w-[5%] text-center">Delete</th>
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
                <div id="groupAccess" class="mb-0 hidden">
                    <div class="flex items-center gap-4 mb-5">
                        </div>
                    <!-- Flowbite Button -->
                    <button type="button" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 mb-5 inline-flex items-center transition-all shadow-sm hover:shadow-md" onclick="addRow('groupTable')">
                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd"></path>
                        </svg>
                        Additional Request
                    </button>
                    <!-- Flowbite Table -->
                    <div class="relative overflow-x-auto shadow-md rounded-lg border border-gray-200 flowbite-table">
                        <table id="groupTable" class="w-full text-sm text-left text-gray-700">
                            <thead class="text-xs text-white uppercase bg-gradient-to-r from-blue-700 to-blue-900 border-b-2 border-blue-950">
                                <tr>
                                    <th scope="col" class="px-4 py-3 font-semibold w-[18.5%]">Application/System <span class="text-red-600">*</span></th>
                                    <th scope="col" class="px-4 py-3 font-semibold w-[32%] min-w-[200px]">User Name <span class="text-red-600">*</span></th>
                                    <th scope="col" class="px-4 py-3 font-semibold w-[8%] min-w-[110px]">Access Type <span class="text-red-600">*</span></th>
                                    <th scope="col" class="px-4 py-3 font-semibold w-[12%]">Access Duration <span class="text-red-600">*</span></th>
                                    <th scope="col" class="px-2 py-3 font-semibold w-[3%]">Start Date <span class="text-red-600">*</span></th>
                                    <th scope="col" class="px-2 py-3 font-semibold w-[3%]">End Date <span class="text-red-600">*</span></th>
                                    <th scope="col" class="px-2 py-3 font-semibold w-[3%]">Date Needed <span class="text-red-600">*</span></th>
                                    <th scope="col" class="px-4 py-3 font-semibold w-[15.5%]">Justification <span class="text-red-600">*</span></th>
                                    <th scope="col" class="px-4 py-3 font-semibold w-[5%] text-center">Delete</th>
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

                <!-- Flowbite Action Buttons -->
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
                            heightAuto: false,
                            buttonsStyling: true,
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
                // For first row, check ind_user_names, for subsequent rows check ind_username_copy[]
                let username;
                const firstRowUsername = lastRow.querySelector('input[name="ind_user_names"]');
                const copyUsername = lastRow.querySelector('input[name="ind_username_copy[]"]');
                
                if (firstRowUsername && firstRowUsername.offsetParent !== null) {
                    // First row - visible username input
                    username = firstRowUsername.value;
                } else if (copyUsername) {
                    // Subsequent rows - hidden username copy
                    username = copyUsername.value;
                } else {
                    // Fallback - any visible text input in first cell
                    const firstCell = lastRow.querySelector('td:first-child');
                    const visibleInput = firstCell ? firstCell.querySelector('input[type="text"]:not([type="hidden"])') : null;
                    username = visibleInput ? visibleInput.value : (copyUsername ? copyUsername.value : '');
                }

                const application = lastRow.querySelector('select[name="ind_application[]"]').value;
                const accessType = lastRow.querySelector('select[name="ind_access_type[]"]').value;
                const durationType = lastRow.querySelector('select[name="ind_duration_type[]"]').value;
                const startDate = lastRow.querySelector('input[name="ind_start_date[]"]').value;
                const endDate = lastRow.querySelector('input[name="ind_end_date[]"]').value;
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

                // If temporary access, validate start and end dates
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
                const username = lastRow.querySelector('input[name="grp_user_names[]"]').value;
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
                        <input type="text" placeholder="User Name" name="grp_user_names[]" class="w-full p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" required>
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
            const username = appRow.querySelector('input[name="grp_user_names[]"]').value;

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
                <input type="text" placeholder="User Name" name="grp_user_names[]" class="w-full p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" required>
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
            formData.append('employee_email', <?php echo json_encode($_SESSION['employee_email'] ?? ($requestorInfo['employee_email'] ?? '')); ?>);

            // Prepare array to hold all access requests
            const userForms = [];

            if (accessType === 'individual') {
                // Process individual access requests
                // Get all rows from individual table
                $('#individualTable tbody tr').each(function(index) {
                    const username = index === 0 ? $('input[name="ind_user_names"]').val() : $(this).find('input[name="ind_username_copy[]"]').val();
                    const app = $(this).find('select[name="ind_application[]"]').val();
                    const accessType = $(this).find('select[name="ind_access_type[]"]').val();
                    const durationType = $(this).find('select[name="ind_duration_type[]"]').val();
                    const startDate = $(this).find('input[name="ind_start_date[]"]').val();
                    const endDate = $(this).find('input[name="ind_end_date[]"]').val();
                    const dateNeeded = $(this).find('input[name="ind_date_needed[]"]').val();
                    const justification = $(this).find('input[name="ind_justification[]"]').val();
                    const applicationSystem = $(this).find('input[name="ind_application_system[]"]').val();

                    // For individual access, make sure username is also provided
                    if (app && accessType && durationType && dateNeeded && justification && username) {
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
                            user_names: [username]
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

                    const username = $(this).find('input[name="grp_user_names[]"]').val();
                    const accessType = $(this).find('select[name="grp_access_type[]"]').val();
                    const durationType = $(this).find('select[name="grp_duration_type[]"]').val();
                    const startDate = $(this).find('input[name="grp_start_date[]"]').val();
                    const endDate = $(this).find('input[name="grp_end_date[]"]').val();
                    const dateNeeded = $(this).find('input[name="grp_date_needed[]"]').val();
                    const justification = $(this).find('input[name="grp_justification[]"]').val();
                    const applicationSystem = $(this).find('input[name="grp_application_system[]"]').val();

                    if (app && username && accessType && durationType && dateNeeded && justification) {
                        // Find if there's already an entry for this application
                        const existingAppIndex = userForms.findIndex(form => form.system_type === app);

                        if (existingAppIndex !== -1) {
                            // Add username to existing application
                            userForms[existingAppIndex].user_names.push(username);
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
                                user_names: [username]
                            });
                        }
                    } else {
                        isValid = false;
                    }
                });
            }

            // Check if we have at least one valid request
            if (userForms.length === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Missing Information',
                    text: 'Please add at least one valid access request.',
                    confirmButtonColor: '#0084FF'
                });
                return;
            }

            // Check if form is valid
            if (!isValid) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Incomplete Form',
                    text: 'Please fill out all required fields.',
                    confirmButtonColor: '#0084FF'
                });
                return;
            }

            // Validate basic form fields
            if (!name || !businessUnit || !department || !date) {
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

            // Debug userForms specifically
            console.log('User forms data:');
            console.log(JSON.stringify(userForms, null, 2));

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

                    if (response.success) {
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
                            heightAuto: false,
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

            // Get the original values of requestor info fields that should never be cleared
            const originalName = $('input[name="name"]').val();
            const originalDate = $('input[name="date"]').val();
            const originalBusinessUnit = $('input[name="business_unit"]').val();
            const originalDepartment = $('input[name="department"]').val();

            // Reset all form elements except radio buttons and readonly fields
            this.querySelectorAll('input:not([type="radio"]):not([readonly]), select:not([readonly]), textarea:not([readonly])').forEach(element => {
                if (element.type === 'text' || element.type === 'date' || element.tagName === 'SELECT' || element.tagName === 'TEXTAREA') {
                    element.value = '';
                }
            });

            // Restore the requestor information fields to their original values
            $('input[name="name"]').val(originalName);
            $('input[name="date"]').val(originalDate);
            $('input[name="business_unit"]').val(originalBusinessUnit);
            $('input[name="department"]').val(originalDepartment);

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

        // Set sidebar state based on screen size
        function checkScreenSize() {
            if (window.innerWidth < 768) {
                Alpine.store('app').sidebarOpen = false;
            } else {
                Alpine.store('app').sidebarOpen = true;
            }
        }

        // Check on resize and on load
        window.addEventListener('resize', checkScreenSize);
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(checkScreenSize, 50);
            
            // Initialize AOS
            AOS.init({
                duration: 800,
                easing: 'ease-in-out',
                once: true,
                offset: 100
            });
        });

        // Initialize Alpine store for sidebar state if Alpine.js is loaded
        $(document).ready(function() {
            if (typeof Alpine !== 'undefined') {
                if (!Alpine.store) {
                    Alpine.store('app', {
                        sidebarOpen: true
                    });
                } else {
                    Alpine.store('app', {
                        sidebarOpen: true
                    });
                }
            }
        });
    </script>
    
    <!-- Flowbite JS -->
    <script src="https://cdn.jsdelivr.net/npm/flowbite@2.5.1/dist/flowbite.min.js"></script>
    
    <?php include '../footer.php'; ?>

</body>

</html>
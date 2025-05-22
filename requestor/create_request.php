<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['requestor_id'])) {
    header("Location: login.php");
    exit();
}

$requestorId = $_SESSION['requestor_id'];
$username = $_SESSION['username'] ?? 'User';
$employeeId = $_SESSION['employee_id'] ?? $requestorId;

// Fetch requestor information from database
$requestorInfo = [];
try {
    $stmt = $pdo->prepare("SELECT employee_name as full_name, employee_email as email, employee_id, company as business_unit, department FROM employees WHERE employee_id = ?");
    $stmt->execute([$employeeId]);
    $requestorInfo = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Silent error handling
    error_log("Error fetching requestor info: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Access Request</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Boxicons -->
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Alpine.js for interactions -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <!-- Animate on Scroll -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
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
                    boxShadow: {
                        'soft': '0 2px 15px -3px rgba(0, 0, 0, 0.07), 0 10px 20px -2px rgba(0, 0, 0, 0.04)',
                        'card': '0 0 25px 0 rgba(0,0,0,0.04)',
                        'input': '0 1px 2px 0 rgba(0, 0, 0, 0.05)'
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-in-out',
                        'slide-up': 'slideUp 0.5s ease-in-out'
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' }
                        },
                        slideUp: {
                            '0%': { transform: 'translateY(20px)', opacity: '0' },
                            '100%': { transform: 'translateY(0)', opacity: '1' }
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body {
            background-image: url('bg2.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            font-family: 'Inter', sans-serif;
        }
        
        .form-card {
            backdrop-filter: blur(5px);
            background-color: rgba(255, 255, 255, 0.95);
            border-radius: 0.75rem;
            transition: all 0.3s ease;
        }
        
        .input-field {
            transition: all 0.2s ease;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            box-shadow: var(--tw-shadow-input);
            width: 100%;
        }
        
        .input-field:focus {
            outline: none;
            border-color: #0ea5e9;
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.2);
        }
        
        .radio-card {
            transition: all 0.2s ease;
            cursor: pointer;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 0.75rem;
            display: flex;
            align-items: center;
        }
        
        .radio-card:hover {
            background-color: #f8fafc;
            border-color: #cbd5e1;
        }
        
        .custom-radio:checked + span {
            color: #0ea5e9;
            font-weight: 500;
        }
        
        .custom-radio:checked ~ .radio-card {
            border-color: #0ea5e9;
            background-color: #f0f9ff;
        }
        
        .checkbox-card {
            transition: all 0.2s ease;
            cursor: pointer;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 0.75rem;
            display: flex;
            align-items: center;
        }
        
        .checkbox-card:hover {
            background-color: #f8fafc;
            border-color: #cbd5e1;
        }
        
        .custom-checkbox:checked + span {
            color: #0ea5e9;
            font-weight: 500;
        }
        
        .checkbox-card.checked {
            border-color: #0ea5e9;
            background-color: #f0f9ff;
        }

        .logo {
            max-width: 220px;
            height: auto;
        }
        
        @media (max-width: 768px) {
            .logo {
                max-width: 180px;
            }
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #a1a1a1;
        }

        /* Animated Card Transition */
        .card-transition {
            transition: all 0.3s ease;
        }
        
        .card-transition:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        /* Progress Bar */
        .progress-container {
            width: 100%;
            height: 4px;
            background-color: #e2e8f0;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 999;
        }
        
        .progress-bar {
            height: 4px;
            background: linear-gradient(90deg, #0ea5e9, #38bdf8);
            width: 0%;
            transition: width 0.3s ease;
        }

        [x-cloak] {
            display: none !important;
        }
    </style>
</head>
<body class="bg-gray-50 font-sans" x-data="formHandler()">
<!-- Progress bar -->
<div class="progress-container">
    <div class="progress-bar" id="progressBar"></div>
</div>

<!-- Sidebar -->
<div class="fixed inset-y-0 left-0 z-50 w-72 bg-white shadow-card transform transition-transform duration-300 overflow-hidden" 
    x-data="{
        open: true,
        init() {
            if (typeof Alpine !== 'undefined' && Alpine.store) {
                this.open = Alpine.store('sidebar').open;
                this.$watch('open', val => {
                    Alpine.store('sidebar').open = val;
                });
            }
        }
    }" 
    :class="{'translate-x-0': open, '-translate-x-full': !open}">
    <div class="flex flex-col h-full">
        <div class="text-center p-5 flex items-center justify-center border-b border-gray-100">
            <img src="../logo.png" alt="Logo" class="w-40 mx-auto">
        </div>
        <nav class="flex-1 pt-4 px-3 space-y-1 overflow-y-auto">
            <a href="dashboard.php" class="flex items-center p-3 text-gray-700 rounded-xl transition-all duration-200 hover:bg-gray-50 hover:text-primary-600 group">
                <span class="flex items-center justify-center w-10 h-10 bg-gray-100 text-gray-600 rounded-xl mr-3 group-hover:bg-primary-50 group-hover:text-primary-600 transition-all duration-200">
                    <i class='bx bxs-dashboard text-xl'></i>
                </span>
                <span class="font-medium">Dashboard</span>
            </a>
            <a href="create_request.php" class="flex items-center p-3 text-primary-600 bg-primary-50 rounded-xl transition-all duration-200 group">
                <span class="flex items-center justify-center w-10 h-10 bg-primary-100 text-primary-600 rounded-xl mr-3">
                    <i class='bx bx-send text-xl'></i>
                </span>
                <span class="font-medium">Create Request</span>
            </a>
            <a href="my_requests.php" class="flex items-center p-3 text-gray-700 rounded-xl transition-all duration-200 hover:bg-gray-50 hover:text-primary-600 group">
                <span class="flex items-center justify-center w-10 h-10 bg-gray-100 text-gray-600 rounded-xl mr-3 group-hover:bg-primary-50 group-hover:text-primary-600 transition-all duration-200">
                    <i class='bx bx-list-ul text-xl'></i>
                </span>
                <span class="font-medium">My Requests</span>
            </a>
            <a href="request_history.php" class="flex items-center p-3 text-gray-700 rounded-xl transition-all duration-200 hover:bg-gray-50 hover:text-primary-600 group">
                <span class="flex items-center justify-center w-10 h-10 bg-gray-100 text-gray-600 rounded-xl mr-3 group-hover:bg-primary-50 group-hover:text-primary-600 transition-all duration-200">
                    <i class='bx bx-history text-xl'></i>
                </span>
                <span class="font-medium">Request History</span>
            </a>
        </nav>

        <div class="p-3 mt-auto">
            <a href="logout.php" class="flex items-center p-3 text-red-600 bg-red-50 rounded-xl transition-all duration-200 hover:bg-red-100 group">
                <span class="flex items-center justify-center w-10 h-10 bg-red-100 text-red-600 rounded-xl mr-3">
                    <i class='bx bx-log-out text-xl'></i>
                </span>
                <span class="font-medium">Logout</span>
            </a>
        </div>

        <div class="px-4 py-4 border-t border-gray-100">
            <div class="flex items-center space-x-3">
                <div class="flex items-center justify-center w-10 h-10 rounded-full bg-primary-100 text-primary-600">
                    <i class='bx bxs-user text-xl'></i>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($username); ?></p>
                    <p class="text-xs text-gray-500">Requestor</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Mobile menu toggle removed -->

<!-- Main Content -->
<div x-data="{}" :class="{'ml-0 md:ml-72': $store.sidebar.open, 'ml-0': !$store.sidebar.open}" class="transition-all duration-300">
    <!-- Header -->
    <div class="bg-white border-b border-gray-200 sticky top-0 z-10 shadow-sm">
        <div class="flex justify-between items-center px-6 py-4">
            <div data-aos="fade-right" data-aos-duration="800" class="flex items-center">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">Create Access Request</h2>
                    <p class="text-gray-600 text-lg mt-1">Fill in the details below to submit a new access request</p>
                </div>
            </div>
            <div data-aos="fade-left" data-aos-duration="800" class="hidden md:block">
                <div class="flex items-center space-x-2 text-sm bg-primary-50 text-primary-700 px-4 py-2 rounded-lg">
                    <i class='bx bx-time-five'></i>
                    <span id="current_time"></span>
                </div>
            </div>
        </div>
    </div>

    <div class="p-6">
        <div class="max-w-7xl mx-auto bg-white rounded-2xl shadow-card overflow-hidden" data-aos="fade-up" data-aos-duration="1000">
            <div class="bg-gradient-to-r from-primary-600 to-primary-500 text-white py-6 px-8 border-b relative overflow-hidden">
                <!-- Animated background shapes -->
                <div class="absolute top-0 right-0 w-32 h-32 bg-white opacity-10 rounded-full -mr-10 -mt-10"></div>
                <div class="absolute bottom-0 left-0 w-24 h-24 bg-white opacity-10 rounded-full -ml-12 -mb-12"></div>
            </div>
            
            <div class="p-6">
                <div class="text-sm text-gray-500 mb-6 flex items-center" data-aos="fade-up" data-aos-duration="800">
                    <i class='bx bx-info-circle mr-2'></i>
                    <span>Please fill in all required fields marked with <span class="text-red-500">*</span></span>
                </div>
                
                <form action="submit.php" method="POST" id="accessRequestForm" class="space-y-8">
                    <input type="hidden" name="requestor_id" value="<?php echo htmlspecialchars($requestorId); ?>">
                    
                    <!-- Requestor Information -->
                    <div class="bg-white p-6 rounded-xl shadow-card border border-gray-100 transition-all duration-300 hover:shadow-lg" data-aos="fade-up" data-aos-duration="800">
                        <h2 class="text-xl font-semibold text-gray-800 mb-5 pb-2 border-b border-gray-200 flex items-center">
                            <i class='bx bx-user-circle text-primary-500 text-2xl mr-2'></i>
                            Requestor Information
                        </h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="form-group">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Requestor Name <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">
                                        <i class='bx bx-user'></i>
                                    </span>
                                    <input type="text" name="requestor_name" value="<?php echo htmlspecialchars($requestorInfo['full_name'] ?? ''); ?>"
                                        class="input-field pl-10 bg-gray-50 text-gray-700" readonly>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Date</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">
                                        <i class='bx bx-calendar'></i>
                                    </span>
                                    <input type="text" name="request_date" id="current_date" readonly
                                        class="input-field pl-10 bg-gray-50 text-gray-700">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Email Address <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">
                                        <i class='bx bx-envelope'></i>
                                    </span>
                                    <input type="email" name="email" value="<?php echo htmlspecialchars($requestorInfo['email'] ?? ''); ?>"
                                        class="input-field pl-10 bg-gray-50 text-gray-700" readonly>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Employee ID <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">
                                        <i class='bx bx-id-card'></i>
                                    </span>
                                    <input type="text" name="employee_id" value="<?php echo htmlspecialchars($requestorInfo['employee_id'] ?? ''); ?>"
                                        class="input-field pl-10 bg-gray-50 text-gray-700" readonly>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Business Unit <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">
                                        <i class='bx bx-buildings'></i>
                                    </span>
                                    <input type="text" name="business_unit" value="<?php echo htmlspecialchars($requestorInfo['business_unit'] ?? ''); ?>"
                                        class="input-field pl-10 bg-gray-50 text-gray-700" readonly>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Department <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">
                                        <i class='bx bx-briefcase'></i>
                                    </span>
                                    <input type="text" name="department" value="<?php echo htmlspecialchars($requestorInfo['department'] ?? ''); ?>"
                                        class="input-field pl-10 bg-gray-50 text-gray-700" readonly>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Access Types -->
                    <div class="bg-white p-6 rounded-xl shadow-card border border-gray-100 transition-all duration-300 hover:shadow-lg" data-aos="fade-up" data-aos-duration="800">
                        <h2 class="text-xl font-semibold text-gray-800 mb-5 pb-2 border-b border-gray-200 flex items-center">
                            <i class='bx bx-lock-open text-primary-500 text-2xl mr-2'></i>
                            Access Type <span class="text-red-500">*</span>
                        </h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                            <label class="radio-card" :class="{'border-primary-500 bg-primary-50': accessTypeValue === 'System Application'}" x-data>
                                <input type="radio" name="access_type" value="System Application" required class="custom-radio hidden"
                                    @change="accessTypeValue = 'System Application'; toggleSystemApplicationSection()">
                                <div class="w-10 h-10 mr-3 rounded-lg bg-gray-100 flex items-center justify-center text-gray-600"
                                    :class="{'bg-primary-100 text-primary-600': accessTypeValue === 'System Application'}">
                                    <i class='bx bx-window-alt text-xl'></i>
                                </div>
                                <span class="text-gray-700" :class="{'text-primary-600 font-medium': accessTypeValue === 'System Application'}">
                                    System Application
                                </span>
                            </label>
                            
                            <label class="radio-card" :class="{'border-primary-500 bg-primary-50': accessTypeValue === 'PC Access - Network'}" x-data>
                                <input type="radio" name="access_type" value="PC Access - Network" class="custom-radio hidden"
                                    x-on:change="accessTypeValue = 'PC Access - Network'; toggleSystemApplicationSection()">
                                <div class="w-10 h-10 mr-3 rounded-lg bg-gray-100 flex items-center justify-center text-gray-600"
                                    :class="{'bg-primary-100 text-primary-600': accessTypeValue === 'PC Access - Network'}">
                                    <i class='bx bx-desktop text-xl'></i>
                                </div>
                                <span class="text-gray-700" :class="{'text-primary-600 font-medium': accessTypeValue === 'PC Access - Network'}">
                                    PC Access - Network
                                </span>
                            </label>
                            
                            <label class="radio-card" :class="{'border-primary-500 bg-primary-50': accessTypeValue === 'Email Access'}" x-data>
                                <input type="radio" name="access_type" value="Email Access" class="custom-radio hidden"
                                    x-on:change="accessTypeValue = 'Email Access'; toggleSystemApplicationSection()">
                                <div class="w-10 h-10 mr-3 rounded-lg bg-gray-100 flex items-center justify-center text-gray-600"
                                    :class="{'bg-primary-100 text-primary-600': accessTypeValue === 'Email Access'}">
                                    <i class='bx bx-envelope text-xl'></i>
                                </div>
                                <span class="text-gray-700" :class="{'text-primary-600 font-medium': accessTypeValue === 'Email Access'}">
                                    Email Access
                                </span>
                            </label>
                            
                            <label class="radio-card" :class="{'border-primary-500 bg-primary-50': accessTypeValue === 'Server Access'}" x-data>
                                <input type="radio" name="access_type" value="Server Access" class="custom-radio hidden"
                                    x-on:change="accessTypeValue = 'Server Access'; toggleSystemApplicationSection()">
                                <div class="w-10 h-10 mr-3 rounded-lg bg-gray-100 flex items-center justify-center text-gray-600"
                                    :class="{'bg-primary-100 text-primary-600': accessTypeValue === 'Server Access'}">
                                    <i class='bx bx-server text-xl'></i>
                                </div>
                                <span class="text-gray-700" :class="{'text-primary-600 font-medium': accessTypeValue === 'Server Access'}">
                                    Server Access
                                </span>
                            </label>
                            
                            <label class="radio-card" :class="{'border-primary-500 bg-primary-50': accessTypeValue === 'Internet Access'}" x-data>
                                <input type="radio" name="access_type" value="Internet Access" class="custom-radio hidden"
                                    x-on:change="accessTypeValue = 'Internet Access'; toggleSystemApplicationSection()">
                                <div class="w-10 h-10 mr-3 rounded-lg bg-gray-100 flex items-center justify-center text-gray-600"
                                    :class="{'bg-primary-100 text-primary-600': accessTypeValue === 'Internet Access'}">
                                    <i class='bx bx-globe text-xl'></i>
                                </div>
                                <span class="text-gray-700" :class="{'text-primary-600 font-medium': accessTypeValue === 'Internet Access'}">
                                    Internet Access
                                </span>
                            </label>
                            
                            <label class="radio-card" :class="{'border-primary-500 bg-primary-50': accessTypeValue === 'Printer Access'}" x-data>
                                <input type="radio" name="access_type" value="Printer Access" class="custom-radio hidden"
                                    x-on:change="accessTypeValue = 'Printer Access'; toggleSystemApplicationSection()">
                                <div class="w-10 h-10 mr-3 rounded-lg bg-gray-100 flex items-center justify-center text-gray-600"
                                    :class="{'bg-primary-100 text-primary-600': accessTypeValue === 'Printer Access'}">
                                    <i class='bx bx-printer text-xl'></i>
                                </div>
                                <span class="text-gray-700" :class="{'text-primary-600 font-medium': accessTypeValue === 'Printer Access'}">
                                    Printer Access
                                </span>
                            </label>
                            
                            <label class="radio-card" :class="{'border-primary-500 bg-primary-50': accessTypeValue === 'Active Directory Access (MS ENTRA ID)'}" x-data>
                                <input type="radio" name="access_type" value="Active Directory Access (MS ENTRA ID)" class="custom-radio hidden"
                                    x-on:change="accessTypeValue = 'Active Directory Access (MS ENTRA ID)'; toggleSystemApplicationSection()">
                                <div class="w-10 h-10 mr-3 rounded-lg bg-gray-100 flex items-center justify-center text-gray-600"
                                    :class="{'bg-primary-100 text-primary-600': accessTypeValue === 'Active Directory Access (MS ENTRA ID)'}">
                                    <i class='bx bx-folder-open text-xl'></i>
                                </div>
                                <span class="text-gray-700" :class="{'text-primary-600 font-medium': accessTypeValue === 'Active Directory Access (MS ENTRA ID)'}">
                                    Active Directory Access
                                </span>
                            </label>
                            
                            <label class="radio-card" :class="{'border-primary-500 bg-primary-50': accessTypeValue === 'Firewall Access'}" x-data>
                                <input type="radio" name="access_type" value="Firewall Access" class="custom-radio hidden"
                                    x-on:change="accessTypeValue = 'Firewall Access'; toggleSystemApplicationSection()">
                                <div class="w-10 h-10 mr-3 rounded-lg bg-gray-100 flex items-center justify-center text-gray-600"
                                    :class="{'bg-primary-100 text-primary-600': accessTypeValue === 'Firewall Access'}">
                                    <i class='bx bx-shield-quarter text-xl'></i>
                                </div>
                                <span class="text-gray-700" :class="{'text-primary-600 font-medium': accessTypeValue === 'Firewall Access'}">
                                    Firewall Access
                                </span>
                            </label>
                            
                            <!-- Additional items -->
                            <label class="radio-card" :class="{'border-primary-500 bg-primary-50': accessTypeValue === 'Wi-Fi/Access Point Access'}" x-data>
                                <input type="radio" name="access_type" value="Wi-Fi/Access Point Access" class="custom-radio hidden"
                                    x-on:change="accessTypeValue = 'Wi-Fi/Access Point Access'; toggleSystemApplicationSection()">
                                <div class="w-10 h-10 mr-3 rounded-lg bg-gray-100 flex items-center justify-center text-gray-600"
                                    :class="{'bg-primary-100 text-primary-600': accessTypeValue === 'Wi-Fi/Access Point Access'}">
                                    <i class='bx bx-wifi text-xl'></i>
                                </div>
                                <span class="text-gray-700" :class="{'text-primary-600 font-medium': accessTypeValue === 'Wi-Fi/Access Point Access'}">
                                    Wi-Fi/Access Point Access
                                </span>
                            </label>
                            
                            <!-- More access type options with similar pattern -->
                            <label class="radio-card" :class="{'border-primary-500 bg-primary-50': accessTypeValue === 'TNA Biometric Device Access'}" x-data>
                                <input type="radio" name="access_type" value="TNA Biometric Device Access" class="custom-radio hidden"
                                    x-on:change="accessTypeValue = 'TNA Biometric Device Access'; toggleSystemApplicationSection()">
                                <div class="w-10 h-10 mr-3 rounded-lg bg-gray-100 flex items-center justify-center text-gray-600"
                                    :class="{'bg-primary-100 text-primary-600': accessTypeValue === 'TNA Biometric Device Access'}">
                                    <i class='bx bx-fingerprint text-xl'></i>
                                </div>
                                <span class="text-gray-700" :class="{'text-primary-600 font-medium': accessTypeValue === 'TNA Biometric Device Access'}">
                                    TNA Biometric Device Access
                                </span>
                            </label>
                            
                            <label class="radio-card" :class="{'border-primary-500 bg-primary-50': accessTypeValue === 'USB/PC-port Access'}" x-data>
                                <input type="radio" name="access_type" value="USB/PC-port Access" class="custom-radio hidden"
                                    x-on:change="accessTypeValue = 'USB/PC-port Access'; toggleSystemApplicationSection()">
                                <div class="w-10 h-10 mr-3 rounded-lg bg-gray-100 flex items-center justify-center text-gray-600"
                                    :class="{'bg-primary-100 text-primary-600': accessTypeValue === 'USB/PC-port Access'}">
                                    <i class='bx bx-usb text-xl'></i>
                                </div>
                                <span class="text-gray-700" :class="{'text-primary-600 font-medium': accessTypeValue === 'USB/PC-port Access'}">
                                    USB/PC-port Access
                                </span>
                            </label>
                            
                            <label class="radio-card" :class="{'border-primary-500 bg-primary-50': accessTypeValue === 'CCTV Access'}" x-data>
                                <input type="radio" name="access_type" value="CCTV Access" class="custom-radio hidden"
                                    x-on:change="accessTypeValue = 'CCTV Access'; toggleSystemApplicationSection()">
                                <div class="w-10 h-10 mr-3 rounded-lg bg-gray-100 flex items-center justify-center text-gray-600"
                                    :class="{'bg-primary-100 text-primary-600': accessTypeValue === 'CCTV Access'}">
                                    <i class='bx bx-cctv text-xl'></i>
                                </div>
                                <span class="text-gray-700" :class="{'text-primary-600 font-medium': accessTypeValue === 'CCTV Access'}">
                                    CCTV Access
                                </span>
                            </label>
                        </div>
                    </div>

                    <!-- System/Application Type -->
                    <div id="systemApplicationSection" class="hidden bg-white p-6 rounded-xl shadow-card border border-gray-100 transition-all duration-300 hover:shadow-lg transform" 
                        data-aos="fade-up" data-aos-duration="800">
                        <h2 class="text-xl font-semibold text-gray-800 mb-5 pb-2 border-b border-gray-200 flex items-center">
                            <i class='bx bx-grid-alt text-primary-500 text-2xl mr-2'></i>
                            System/Application Type
                        </h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                            <label class="checkbox-card">
                                <input type="checkbox" name="system_type[]" value="Canvasing System" class="hidden checkbox-system">
                                <div class="w-10 h-10 mr-3 rounded-lg bg-gray-100 flex items-center justify-center text-gray-600 checkbox-icon">
                                    <i class='bx bx-circle text-xl'></i>
                                </div>
                                <span class="text-gray-700">Canvasing System</span>
                            </label>
                            
                            <label class="checkbox-card">
                                <input type="checkbox" name="system_type[]" value="ERP/NAV" class="hidden checkbox-system">
                                <div class="w-10 h-10 mr-3 rounded-lg bg-gray-100 flex items-center justify-center text-gray-600 checkbox-icon">
                                    <i class='bx bx-circle text-xl'></i>
                                </div>
                                <span class="text-gray-700">ERP/NAV</span>
                            </label>
                            
                            <label class="checkbox-card">
                                <input type="checkbox" name="system_type[]" value="Legacy Payroll" class="hidden checkbox-system">
                                <div class="w-10 h-10 mr-3 rounded-lg bg-gray-100 flex items-center justify-center text-gray-600 checkbox-icon">
                                    <i class='bx bx-circle text-xl'></i>
                                </div>
                                <span class="text-gray-700">Legacy Payroll</span>
                            </label>
                            
                            <label class="checkbox-card">
                                <input type="checkbox" name="system_type[]" value="HRIS" class="hidden checkbox-system">
                                <div class="w-10 h-10 mr-3 rounded-lg bg-gray-100 flex items-center justify-center text-gray-600 checkbox-icon">
                                    <i class='bx bx-circle text-xl'></i>
                                </div>
                                <span class="text-gray-700">HRIS</span>
                            </label>
                            
                            <label class="checkbox-card">
                                <input type="checkbox" name="system_type[]" value="Legacy Purchasing" class="hidden checkbox-system">
                                <div class="w-10 h-10 mr-3 rounded-lg bg-gray-100 flex items-center justify-center text-gray-600 checkbox-icon">
                                    <i class='bx bx-circle text-xl'></i>
                                </div>
                                <span class="text-gray-700">Legacy Purchasing</span>
                            </label>
                            
                            <label class="checkbox-card">
                                <input type="checkbox" name="system_type[]" value="Piece Rate Payroll System" class="hidden checkbox-system">
                                <div class="w-10 h-10 mr-3 rounded-lg bg-gray-100 flex items-center justify-center text-gray-600 checkbox-icon">
                                    <i class='bx bx-circle text-xl'></i>
                                </div>
                                <span class="text-gray-700">Piece Rate Payroll System</span>
                            </label>
                            
                            <label class="checkbox-card">
                                <input type="checkbox" name="system_type[]" value="Legacy Inventory" class="hidden checkbox-system">
                                <div class="w-10 h-10 mr-3 rounded-lg bg-gray-100 flex items-center justify-center text-gray-600 checkbox-icon">
                                    <i class='bx bx-circle text-xl'></i>
                                </div>
                                <span class="text-gray-700">Legacy Inventory</span>
                            </label>
                            
                            <label class="checkbox-card">
                                <input type="checkbox" name="system_type[]" value="Fresh Chilled Receiving System" class="hidden checkbox-system">
                                <div class="w-10 h-10 mr-3 rounded-lg bg-gray-100 flex items-center justify-center text-gray-600 checkbox-icon">
                                    <i class='bx bx-circle text-xl'></i>
                                </div>
                                <span class="text-gray-700">Fresh Chilled Receiving System</span>
                            </label>
                            
                            <label class="checkbox-card">
                                <input type="checkbox" name="system_type[]" value="Legacy Vouchering" class="hidden checkbox-system">
                                <div class="w-10 h-10 mr-3 rounded-lg bg-gray-100 flex items-center justify-center text-gray-600 checkbox-icon">
                                    <i class='bx bx-circle text-xl'></i>
                                </div>
                                <span class="text-gray-700">Legacy Vouchering</span>
                            </label>
                            
                            <label class="checkbox-card">
                                <input type="checkbox" name="system_type[]" value="Quickbooks" class="hidden checkbox-system">
                                <div class="w-10 h-10 mr-3 rounded-lg bg-gray-100 flex items-center justify-center text-gray-600 checkbox-icon">
                                    <i class='bx bx-circle text-xl'></i>
                                </div>
                                <span class="text-gray-700">Quickbooks</span>
                            </label>
                            
                            <label class="checkbox-card">
                                <input type="checkbox" name="system_type[]" value="Legacy Ledger System" class="hidden checkbox-system">
                                <div class="w-10 h-10 mr-3 rounded-lg bg-gray-100 flex items-center justify-center text-gray-600 checkbox-icon">
                                    <i class='bx bx-circle text-xl'></i>
                                </div>
                                <span class="text-gray-700">Legacy Ledger System</span>
                            </label>
                            
                            <label class="checkbox-card">
                                <input type="checkbox" name="system_type[]" value="Memorandum Receipt" class="hidden checkbox-system">
                                <div class="w-10 h-10 mr-3 rounded-lg bg-gray-100 flex items-center justify-center text-gray-600 checkbox-icon">
                                    <i class='bx bx-circle text-xl'></i>
                                </div>
                                <span class="text-gray-700">Memorandum Receipt</span>
                            </label>
                            
                            <label class="checkbox-card">
                                <input type="checkbox" name="system_type[]" value="ZankPOS" class="hidden checkbox-system">
                                <div class="w-10 h-10 mr-3 rounded-lg bg-gray-100 flex items-center justify-center text-gray-600 checkbox-icon">
                                    <i class='bx bx-circle text-xl'></i>
                                </div>
                                <span class="text-gray-700">ZankPOS</span>
                            </label>
                            
                            <div class="col-span-1 sm:col-span-2 lg:col-span-3 xl:col-span-4 flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                                <div class="w-10 h-10 mr-3 rounded-lg bg-gray-100 flex items-center justify-center text-gray-600">
                                    <i class='bx bx-plus-circle text-xl'></i>
                                </div>
                                <input type="checkbox" name="system_type[]" value="other" id="otherSystemType" class="hidden">
                                <span class="text-gray-700 mr-4">Other (specify):</span>
                                <input type="text" name="other_system_type" id="otherSystemTypeText"
                                    class="flex-1 input-field" disabled>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Role Access Type -->
                    <div id="roleAccessSection" class="hidden bg-white p-6 rounded-xl shadow-card border border-gray-100 transition-all duration-300 hover:shadow-lg"
                        x-show="showRoleAccessSection"
                        x-transition:enter="transition ease-out duration-300"
                        x-transition:enter-start="opacity-0 transform -translate-y-4"
                        x-transition:enter-end="opacity-100 transform translate-y-0"
                        x-transition:leave="transition ease-in duration-300"
                        x-transition:leave-start="opacity-100 transform translate-y-0"
                        x-transition:leave-end="opacity-0 transform -translate-y-4"
                        data-aos="fade-up" data-aos-duration="800">
                        <h2 class="text-xl font-semibold text-gray-800 mb-5 pb-2 border-b border-gray-200 flex items-center">
                            <i class='bx bx-user-check text-primary-500 text-2xl mr-2'></i>
                            Role Access Type (If applicable)
                        </h2>
                        <div>
                            <div class="relative">
                                <textarea name="role_access_type" rows="4" 
                                    class="input-field pl-10 resize-none" 
                                    placeholder="Enter role access type details"
                                    x-model="roleAccessType"></textarea>
                                <span class="absolute top-3 left-3 text-gray-500">
                                    <i class='bx bx-edit-alt'></i>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Access Duration -->
                    <div class="bg-white p-6 rounded-xl shadow-card border border-gray-100 transition-all duration-300 hover:shadow-lg" data-aos="fade-up" data-aos-duration="800">
                        <h2 class="text-xl font-semibold text-gray-800 mb-5 pb-2 border-b border-gray-200 flex items-center">
                            <i class='bx bx-time text-primary-500 text-2xl mr-2'></i>
                            Access Duration <span class="text-red-500">*</span>
                        </h2>
                        <div class="space-y-4">
                            <label class="flex items-center p-4 border border-gray-200 rounded-xl hover:bg-gray-50 transition cursor-pointer"
                                :class="{'border-primary-500 bg-primary-50': durationType === 'permanent'}">
                                <input type="radio" name="duration_type" value="permanent" required class="hidden" x-model="durationType">
                                <div class="w-10 h-10 mr-3 rounded-lg bg-gray-100 flex items-center justify-center text-gray-600"
                                    :class="{'bg-primary-100 text-primary-600': durationType === 'permanent'}">
                                    <i class='bx bx-check-circle text-xl' x-show="durationType === 'permanent'"></i>
                                    <i class='bx bx-circle text-xl' x-show="durationType !== 'permanent'"></i>
                                </div>
                                <span class="text-gray-700 text-lg" :class="{'text-primary-600 font-medium': durationType === 'permanent'}">Permanent</span>
                            </label>
                            
                            <div class="p-4 border border-gray-200 rounded-xl"
                                :class="{'border-primary-500 bg-primary-50': durationType === 'temporary'}">
                                <div class="flex flex-wrap items-center gap-4">
                                    <label class="flex items-center">
                                        <input type="radio" name="duration_type" value="temporary" class="hidden" x-model="durationType">
                                        <div class="w-10 h-10 mr-3 rounded-lg bg-gray-100 flex items-center justify-center text-gray-600"
                                            :class="{'bg-primary-100 text-primary-600': durationType === 'temporary'}">
                                            <i class='bx bx-check-circle text-xl' x-show="durationType === 'temporary'"></i>
                                            <i class='bx bx-circle text-xl' x-show="durationType !== 'temporary'"></i>
                                        </div>
                                        <span class="text-gray-700 text-lg" :class="{'text-primary-600 font-medium': durationType === 'temporary'}">Temporary</span>
                                    </label>
                                    
                                    <div class="flex-1 flex flex-wrap items-center gap-3 mt-2 md:mt-0" x-show="durationType === 'temporary'"
                                        x-transition:enter="transition ease-out duration-300"
                                        x-transition:enter-start="opacity-0"
                                        x-transition:enter-end="opacity-100">
                                        <div class="relative">
                                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">
                                                <i class='bx bx-calendar'></i>
                                            </span>
                                            <input type="date" name="start_date" 
                                                class="input-field pl-10" 
                                                :disabled="durationType !== 'temporary'"
                                                :required="durationType === 'temporary'"
                                                x-model="startDate">
                                        </div>
                                        <span class="text-gray-700 text-lg mx-2">to</span>
                                        <div class="relative">
                                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">
                                                <i class='bx bx-calendar'></i>
                                            </span>
                                            <input type="date" name="end_date" 
                                                class="input-field pl-10" 
                                                :disabled="durationType !== 'temporary'"
                                                :required="durationType === 'temporary'"
                                                x-model="endDate">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Justification -->
                    <div class="bg-white p-6 rounded-xl shadow-card border border-gray-100 transition-all duration-300 hover:shadow-lg" data-aos="fade-up" data-aos-duration="800">
                        <h2 class="text-xl font-semibold text-gray-800 mb-5 pb-2 border-b border-gray-200 flex items-center">
                            <i class='bx bx-comment-detail text-primary-500 text-2xl mr-2'></i>
                            Justification for Access Request <span class="text-red-500">*</span>
                        </h2>
                        <div class="relative">
                            <textarea name="justification" 
                                placeholder="Please provide a detailed reason for this access request" 
                                required rows="4" 
                                class="input-field pl-10 resize-none"
                                x-model="justification"></textarea>
                            <span class="absolute top-3 left-3 text-gray-500">
                                <i class='bx bx-edit'></i>
                            </span>
                            <div class="text-right text-xs text-gray-500 mt-2" x-text="`${justification.length} characters`"></div>
                        </div>
                    </div>

                    <div class="sticky bottom-0 bg-white p-4 border-t border-gray-200 rounded-b-lg shadow-lg flex justify-end space-x-4 items-center z-30" 
                        x-transition:enter="transition ease-out duration-300"
                        x-transition:enter-start="opacity-0 transform translate-y-4"
                        x-transition:enter-end="opacity-100 transform translate-y-0">
                        <div class="text-sm text-gray-500 mr-auto">
                            <span class="text-red-500">*</span> Required fields
                        </div>
                        <button type="reset" @click="resetForm"
                            class="px-6 py-3 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 transition duration-200 text-lg font-medium shadow-sm flex items-center">
                            <i class='bx bx-refresh mr-2'></i> Reset
                        </button>
                        <button type="submit" 
                            class="px-6 py-3 bg-primary-600 text-white rounded-xl hover:bg-primary-700 transition duration-200 text-lg font-medium shadow-sm flex items-center">
                            <i class='bx bx-paper-plane mr-2'></i> Submit Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div id="successModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white p-8 rounded-xl shadow-xl max-w-md w-full mx-4 transform transition-all">
        <div class="text-center">
            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-6">
                <svg class="h-8 w-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            <h3 class="text-2xl font-bold text-gray-900 mb-3">Request Submitted!</h3>
            <p class="text-gray-600 mb-6" id="modalMessage"></p>
            <button type="button" onclick="closeModal()" 
                class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-6 py-3 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition duration-200">
                Close
            </button>
        </div>
    </div>
</div>

<!---------------------------------------------------------------------SCRIPT------------------------------------------------------------------------------------------------------------------------------------------------------------------------------>
<script>
    function formHandler() {
        return {
            accessTypeValue: '',
            showSystemApplicationSection: false,
            showRoleAccessSection: false,
            systemTypes: [
                { value: 'Canvasing System', label: 'Canvasing System', checked: false },
                { value: 'ERP/NAV', label: 'ERP/NAV', checked: false },
                { value: 'Legacy Payroll', label: 'Legacy Payroll', checked: false },
                { value: 'HRIS', label: 'HRIS', checked: false },
                { value: 'Legacy Purchasing', label: 'Legacy Purchasing', checked: false },
                { value: 'Piece Rate Payroll System', label: 'Piece Rate Payroll System', checked: false },
                { value: 'Legacy Inventory', label: 'Legacy Inventory', checked: false },
                { value: 'Fresh Chilled Receiving System', label: 'Fresh Chilled Receiving System', checked: false },
                { value: 'Legacy Vouchering', label: 'Legacy Vouchering', checked: false },
                { value: 'Quickbooks', label: 'Quickbooks', checked: false },
                { value: 'Legacy Ledger System', label: 'Legacy Ledger System', checked: false },
                { value: 'Memorandum Receipt', label: 'Memorandum Receipt', checked: false },
                { value: 'ZankPOS', label: 'ZankPOS', checked: false }
            ],
            otherSystemSelected: false,
            otherSystemType: '',
            durationType: '',
            startDate: '',
            endDate: '',
            roleAccessType: '',
            justification: '',
            
            init() {
                this.setupProgressBar();
                this.setupDateTime();
                
                // Initialize Alpine store for sidebar state if Alpine.js is loaded
                if (typeof Alpine !== 'undefined') {
                    if (!Alpine.store) {
                        // If Alpine.store is not available yet, wait for Alpine to initialize
                        document.addEventListener('alpine:init', () => {
                            Alpine.store('sidebar', {
                                open: true
                            });
                        });
                    } else {
                        // If Alpine.store is already available
                        Alpine.store('sidebar', {
                            open: true
                        });
                    }
                }
                
                // Initialize AOS
                AOS.init({
                    once: true,
                    duration: 800,
                    offset: 100
                });
                
                // Set current date
                const today = new Date();
                const options = { year: 'numeric', month: 'long', day: 'numeric' };
                document.getElementById('current_date').value = today.toLocaleDateString('en-US', options);
                
                // Add direct event listener for System Application radio button
                const systemApplicationRadio = document.querySelector('input[name="access_type"][value="System Application"]');
                if (systemApplicationRadio) {
                    systemApplicationRadio.addEventListener('click', () => {
                        const systemApplicationSection = document.getElementById('systemApplicationSection');
                        if (systemApplicationSection) {
                            systemApplicationSection.classList.remove('hidden');
                        }
                    });
                }
            },
            
            setupProgressBar() {
                window.onscroll = function() {
                    const winScroll = document.body.scrollTop || document.documentElement.scrollTop;
                    const height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
                    const scrolled = (winScroll / height) * 100;
                    document.getElementById("progressBar").style.width = scrolled + "%";
                };
            },
            
            setupDateTime() {
                const updateTime = () => {
                    const now = new Date();
                    const timeElement = document.getElementById('current_time');
                    if (timeElement) {
                        timeElement.textContent = now.toLocaleTimeString('en-US', { 
                            hour: '2-digit', 
                            minute: '2-digit',
                            hour12: true 
                        });
                    }
                };
                
                updateTime();
                setInterval(updateTime, 1000);
            },
            
            toggleSystemApplicationSection() {
                // Hide all first
                this.showSystemApplicationSection = false;
                this.showRoleAccessSection = false;
                
                if (this.accessTypeValue === 'System Application') {
                    this.showSystemApplicationSection = true;
                    // Make the section visible using JavaScript as a backup
                    const systemApplicationSection = document.getElementById('systemApplicationSection');
                    if (systemApplicationSection) {
                        systemApplicationSection.classList.remove('hidden');
                    }
                } else if (this.accessTypeValue === 'role_access') {
                    this.showRoleAccessSection = true;
                }
                
                console.log('Access Type:', this.accessTypeValue);
                console.log('Show System Application Section:', this.showSystemApplicationSection);
            },
            
            handleSystemTypeChange(system) {
                // Any additional logic when system type changes
            },
            
            resetForm() {
                this.accessTypeValue = '';
                this.showSystemApplicationSection = false;
                this.showRoleAccessSection = false;
                this.systemTypes.forEach(system => system.checked = false);
                this.otherSystemSelected = false;
                this.otherSystemType = '';
                this.durationType = '';
                this.startDate = '';
                this.endDate = '';
                this.roleAccessType = '';
                this.justification = '';
                
                // Reset form
                document.getElementById('accessRequestForm').reset();
                
                // Hide sections
                const systemApplicationSection = document.getElementById('systemApplicationSection');
                if (systemApplicationSection) {
                    systemApplicationSection.classList.add('hidden');
                }
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Direct vanilla JS implementation to handle System Application section visibility
        const accessTypeRadios = document.querySelectorAll('input[name="access_type"]');
        const systemApplicationSection = document.getElementById('systemApplicationSection');
        
        accessTypeRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                // Hide section by default
                systemApplicationSection.classList.add('hidden');
                
                // Show system application section if relevant radio is selected
                if (this.value === 'System Application' && this.checked) {
                    systemApplicationSection.classList.remove('hidden');
                }
            });
        });
        
        // Handle checkbox styling
        const checkboxSystems = document.querySelectorAll('.checkbox-system');
        checkboxSystems.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const parent = this.closest('.checkbox-card');
                const icon = parent.querySelector('.checkbox-icon i');
                
                if (this.checked) {
                    parent.classList.add('border-primary-500', 'bg-primary-50');
                    icon.classList.remove('bx-circle');
                    icon.classList.add('bx-check-circle', 'text-primary-600');
                    parent.querySelector('.checkbox-icon').classList.add('bg-primary-100', 'text-primary-600');
                    parent.querySelector('span').classList.add('text-primary-600', 'font-medium');
                } else {
                    parent.classList.remove('border-primary-500', 'bg-primary-50');
                    icon.classList.add('bx-circle');
                    icon.classList.remove('bx-check-circle', 'text-primary-600');
                    parent.querySelector('.checkbox-icon').classList.remove('bg-primary-100', 'text-primary-600');
                    parent.querySelector('span').classList.remove('text-primary-600', 'font-medium');
                }
            });
        });
        
        // Handle "Other" system type
        const otherSystemType = document.getElementById('otherSystemType');
        const otherSystemTypeText = document.getElementById('otherSystemTypeText');
        
        if (otherSystemType && otherSystemTypeText) {
            otherSystemType.addEventListener('change', function() {
                otherSystemTypeText.disabled = !this.checked;
                
                const parent = this.closest('div');
                const icon = parent.querySelector('i');
                
                if (this.checked) {
                    parent.classList.add('border-primary-500', 'bg-primary-50');
                    icon.parentElement.classList.add('bg-primary-100', 'text-primary-600');
                    parent.querySelector('span').classList.add('text-primary-600', 'font-medium');
                } else {
                    parent.classList.remove('border-primary-500', 'bg-primary-50');
                    icon.parentElement.classList.remove('bg-primary-100', 'text-primary-600');
                    parent.querySelector('span').classList.remove('text-primary-600', 'font-medium');
                    otherSystemTypeText.value = '';
                }
            });
        }
        
        // Form submission handling
        const form = document.getElementById('accessRequestForm');
        
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Basic validation
            const accessTypes = form.querySelectorAll('input[name="access_type"]:checked');
            if (accessTypes.length === 0) {
                Swal.fire({
                    icon: 'error',
                    title: 'Validation Error',
                    text: 'Please select an Access Type',
                    confirmButtonColor: '#0ea5e9'
                });
                return;
            }

            // Validate system application specific fields
            if (accessTypes[0].value === 'System Application') {
                // Validate system types
                const systemTypes = form.querySelectorAll('input[name="system_type[]"]:checked');
                if (systemTypes.length === 0) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Validation Error',
                        text: 'Please select at least one System/Application Type',
                        confirmButtonColor: '#0ea5e9'
                    });
                    return;
                }

                // Validate access duration
                const durationTypeSelected = form.querySelector('input[name="duration_type"]:checked');
                if (!durationTypeSelected) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Validation Error',
                        text: 'Please select an Access Duration',
                        confirmButtonColor: '#0ea5e9'
                    });
                    return;
                }

                if (durationTypeSelected.value === 'temporary') {
                    const startDate = form.querySelector('input[name="start_date"]');
                    const endDate = form.querySelector('input[name="end_date"]');
                    if (!startDate.value || !endDate.value) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Validation Error',
                            text: 'Please enter both start and end dates',
                            confirmButtonColor: '#0ea5e9'
                        });
                        return;
                    }
                }
            }

            // Show loading animation
            Swal.fire({
                title: 'Submitting Request',
                html: '<div class="flex flex-col items-center"><div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-primary-500 mb-4"></div><p>Please wait while we process your request...</p></div>',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false
            });

            // Submit form using fetch
            const formData = new FormData(this);
            fetch('submit.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Close loading animation
                Swal.close();
                
                if (data.success) {
                    // Show success message
                    Swal.fire({
                        icon: 'success',
                        title: 'Request Submitted!',
                        text: data.message || 'Your access request has been successfully submitted.',
                        confirmButtonColor: '#0ea5e9',
                        showClass: {
                            popup: 'animate__animated animate__fadeInUp'
                        }
                    }).then(() => {
                        // Reset form after closing the success dialog
                        form.reset();
                        
                        // Reset Alpine.js data
                        const alpine = document.querySelector('[x-data]').__x;
                        if (alpine) {
                            alpine.$data.resetForm();
                        }
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Submission Error',
                        text: data.message || 'An error occurred while submitting the form.',
                        confirmButtonColor: '#0ea5e9'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Submission Error',
                    text: 'An error occurred while submitting the form.',
                    confirmButtonColor: '#0ea5e9'
                });
            });
        });
    });
</script>
</body>
</html>
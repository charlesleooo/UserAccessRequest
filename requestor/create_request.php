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
<div class="fixed inset-y-0 left-0 z-50 w-72 bg-white shadow-card transform transition-transform duration-300 overflow-hidden" x-data="{open: true}">
    <div class="flex flex-col h-full">
        <div class="text-center p-5 flex items-center justify-center border-b border-gray-100">
            <img src="../logo.png" alt="Logo" class="w-48 mx-auto transition-all duration-300 hover:scale-105">
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

<!-- Mobile menu toggle -->
<div class="fixed top-4 left-4 z-50 md:hidden">
    <button type="button" class="p-2 bg-white rounded-lg shadow-md text-gray-700" @click="open = !open">
        <i class='bx bx-menu text-2xl'></i>
    </button>
</div>

<!-- Main Content -->
<div class="ml-0 md:ml-72 transition-all duration-300">
    <!-- Header -->
    <div class="bg-white border-b border-gray-200 sticky top-0 z-10 shadow-sm">
        <div class="flex justify-between items-center px-6 py-4">
            <div data-aos="fade-right" data-aos-duration="800">
                <h2 class="text-2xl font-bold text-gray-800">Create Access Request</h2>
                <p class="text-gray-600 text-lg mt-1">Fill in the details below to submit a new access request</p>
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

                    <!-- Multiple User Forms Section -->
                    <div class="bg-white p-6 rounded-xl shadow-card border border-gray-100 transition-all duration-300 hover:shadow-lg" data-aos="fade-up" data-aos-duration="800">
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-xl font-semibold text-gray-800 flex items-center">
                                <i class='bx bx-user-plus text-primary-500 text-2xl mr-2'></i>
                                User Access Forms
                            </h2>
                        </div>

                        <!-- User Forms Container -->
                        <div class="space-y-6">
                            <template x-for="(form, index) in userForms" :key="index">
                                <div class="border border-gray-200 rounded-xl p-6 bg-gray-50" 
                                     x-transition:enter="transition ease-out duration-300"
                                     x-transition:enter-start="opacity-0 transform -translate-y-4"
                                     x-transition:enter-end="opacity-100 transform translate-y-0">
                                    <div class="flex justify-between items-center mb-4">
                                        <h3 class="text-lg font-medium text-gray-800">User Form #<span x-text="index + 1"></span></h3>
                                        <div class="flex items-center space-x-2">
                                            <button type="button" 
                                                    @click="addUserForm"
                                                    class="px-3 py-1.5 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors flex items-center text-sm">
                                                <i class='bx bx-plus mr-1'></i>
                                                Add Another User
                                            </button>
                                            <button type="button" 
                                                    @click="removeUserForm(index)"
                                                    class="p-2 text-red-600 hover:text-red-700 transition-colors"
                                                    x-show="userForms.length > 1">
                                                <i class='bx bx-trash text-xl'></i>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Username Field -->
                                    <div class="mb-4">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Username <span class="text-red-500">*</span>
                                        </label>
                                        <div class="space-y-4">
                                            <template x-for="(username, usernameIndex) in form.usernames" :key="usernameIndex">
                                                <div class="flex items-center gap-4">
                                                    <div class="flex-1 relative">
                                                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">
                                                            <i class='bx bx-user'></i>
                                                        </span>
                                                        <input type="text" 
                                                               :name="'user_forms['+index+'][usernames][]'"
                                                               x-model="form.usernames[usernameIndex]"
                                                               class="input-field pl-10"
                                                               :placeholder="'Username ' + (usernameIndex + 1)"
                                                               required>
                                                    </div>
                                                    <button type="button" 
                                                            @click="removeUsername(index, usernameIndex)"
                                                            class="p-2 text-red-600 hover:text-red-700 transition-colors"
                                                            x-show="form.usernames.length > 1">
                                                        <i class='bx bx-trash text-xl'></i>
                                                    </button>
                                                </div>
                                            </template>
                                            <button type="button" 
                                                    @click="addUsername(index)"
                                                    class="mt-2 px-4 py-2 bg-primary-50 text-primary-600 rounded-lg hover:bg-primary-100 transition-colors flex items-center">
                                                <i class='bx bx-plus mr-2'></i>
                                                Add Another Username
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Access Type Selection -->
                                    <div class="mb-4">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Access Type <span class="text-red-500">*</span>
                                        </label>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                                            <template x-for="accessType in accessTypes" :key="accessType.value">
                                                <label class="radio-card" :class="{'border-primary-500 bg-primary-50': form.accessType === accessType.value}">
                                                    <input type="radio" 
                                                           :name="'user_forms['+index+'][access_type]'" 
                                                           :value="accessType.value"
                                                           x-model="form.accessType"
                                                           class="custom-radio hidden"
                                                           required>
                                                    <div class="w-10 h-10 mr-3 rounded-lg bg-gray-100 flex items-center justify-center text-gray-600"
                                                         :class="{'bg-primary-100 text-primary-600': form.accessType === accessType.value}">
                                                        <i :class="accessType.icon + ' text-xl'"></i>
                                                    </div>
                                                    <span class="text-gray-700" :class="{'text-primary-600 font-medium': form.accessType === accessType.value}">
                                                        <span x-text="accessType.label"></span>
                                                    </span>
                                                </label>
                                            </template>
                                        </div>
                                    </div>

                                    <!-- Access Level Selection (for System Application) -->
                                    <div x-show="form.accessType === 'System Application'" 
                                         x-transition:enter="transition ease-out duration-300"
                                         x-transition:enter-start="opacity-0"
                                         x-transition:enter-end="opacity-100"
                                         class="mb-4">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Access Level <span class="text-red-500">*</span>
                                        </label>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                                            <template x-for="level in accessLevels" :key="level.value">
                                                <label class="radio-card" :class="{'border-primary-500 bg-primary-50': form.accessLevel === level.value}">
                                                    <input type="radio" 
                                                           :name="'user_forms['+index+'][access_level]'" 
                                                           :value="level.value"
                                                           x-model="form.accessLevel"
                                                           class="custom-radio hidden"
                                                           :required="form.accessType === 'System Application'">
                                                    <div class="w-10 h-10 mr-3 rounded-lg bg-gray-100 flex items-center justify-center text-gray-600"
                                                         :class="{'bg-primary-100 text-primary-600': form.accessLevel === level.value}">
                                                        <i :class="level.icon + ' text-xl'"></i>
                                                    </div>
                                                    <span class="text-gray-700" :class="{'text-primary-600 font-medium': form.accessLevel === level.value}">
                                                        <span x-text="level.label"></span>
                                                    </span>
                                                </label>
                                            </template>
                                        </div>
                                    </div>

                                    <!-- System Application Type (if applicable) -->
                                    <div x-show="form.accessType === 'System Application'" 
                                         x-transition:enter="transition ease-out duration-300"
                                         x-transition:enter-start="opacity-0"
                                         x-transition:enter-end="opacity-100"
                                         class="mb-4">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            System/Application Type
                                        </label>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                                            <template x-for="systemType in systemTypes" :key="systemType.value">
                                                <label class="checkbox-card"
                                                       :class="{
                                                           'border-primary-500 bg-primary-50': form.selectedSystems.includes(systemType.value),
                                                           'bg-white border-gray-200': !form.selectedSystems.includes(systemType.value)
                                                       }">
                                                    <input type="checkbox"
                                                           :name="'user_forms['+index+'][system_type][]'"
                                                           :value="systemType.value"
                                                           x-model="form.selectedSystems"
                                                           class="custom-checkbox hidden">
                                                    <div class="w-10 h-10 mr-3 rounded-lg flex items-center justify-center text-gray-600 checkbox-icon"
                                                         :class="{
                                                             'bg-primary-100 text-primary-600': form.selectedSystems.includes(systemType.value),
                                                             'bg-gray-100 text-gray-600': !form.selectedSystems.includes(systemType.value)
                                                         }">
                                                        <i :class="form.selectedSystems.includes(systemType.value) ? 'bx bx-check-circle text-xl' : 'bx bx-circle text-xl'"></i>
                                                    </div>
                                                    <span class="text-gray-700"
                                                          :class="{
                                                              'text-primary-600 font-medium': form.selectedSystems.includes(systemType.value)
                                                          }"
                                                          x-text="systemType.label"></span>
                                                </label>
                                            </template>
                                        </div>
                                    </div>

                                    <!-- Access Duration -->
                                    <div class="mb-4">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Access Duration <span class="text-red-500">*</span>
                                        </label>
                                        <div class="space-y-4">
                                            <label class="flex items-center p-4 border border-gray-200 rounded-xl hover:bg-gray-50 transition cursor-pointer"
                                                   :class="{'border-primary-500 bg-primary-50': form.durationType === 'permanent'}">
                                                <input type="radio" 
                                                       :name="'user_forms['+index+'][duration_type]'" 
                                                       value="permanent" 
                                                       x-model="form.durationType"
                                                       class="hidden" 
                                                       required>
                                                <div class="w-10 h-10 mr-3 rounded-lg bg-gray-100 flex items-center justify-center text-gray-600"
                                                     :class="{'bg-primary-100 text-primary-600': form.durationType === 'permanent'}">
                                                    <i class='bx bx-check-circle text-xl' x-show="form.durationType === 'permanent'"></i>
                                                    <i class='bx bx-circle text-xl' x-show="form.durationType !== 'permanent'"></i>
                                                </div>
                                                <span class="text-gray-700 text-lg" :class="{'text-primary-600 font-medium': form.durationType === 'permanent'}">Permanent</span>
                                            </label>
                                            
                                            <div class="p-4 border border-gray-200 rounded-xl"
                                                 :class="{'border-primary-500 bg-primary-50': form.durationType === 'temporary'}">
                                                <div class="flex flex-wrap items-center gap-4">
                                                    <label class="flex items-center">
                                                        <input type="radio" 
                                                               :name="'user_forms['+index+'][duration_type]'" 
                                                               value="temporary" 
                                                               x-model="form.durationType"
                                                               class="hidden">
                                                        <div class="w-10 h-10 mr-3 rounded-lg bg-gray-100 flex items-center justify-center text-gray-600"
                                                             :class="{'bg-primary-100 text-primary-600': form.durationType === 'temporary'}">
                                                            <i class='bx bx-check-circle text-xl' x-show="form.durationType === 'temporary'"></i>
                                                            <i class='bx bx-circle text-xl' x-show="form.durationType !== 'temporary'"></i>
                                                        </div>
                                                        <span class="text-gray-700 text-lg" :class="{'text-primary-600 font-medium': form.durationType === 'temporary'}">Temporary</span>
                                                    </label>
                                                    
                                                    <div class="flex-1 flex flex-wrap items-center gap-3 mt-2 md:mt-0" 
                                                         x-show="form.durationType === 'temporary'"
                                                         x-transition:enter="transition ease-out duration-300"
                                                         x-transition:enter-start="opacity-0"
                                                         x-transition:enter-end="opacity-100">
                                                        <div class="relative">
                                                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">
                                                                <i class='bx bx-calendar'></i>
                                                            </span>
                                                            <input type="date" 
                                                                   :name="'user_forms['+index+'][start_date]'"
                                                                   x-model="form.startDate"
                                                                   :min="minDate"
                                                                   class="input-field pl-10"
                                                                   :required="form.durationType === 'temporary'"
                                                                   @change="validateDates(index)">
                                                        </div>
                                                        <span class="text-gray-700 text-lg mx-2">to</span>
                                                        <div class="relative">
                                                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">
                                                                <i class='bx bx-calendar'></i>
                                                            </span>
                                                            <input type="date" 
                                                                   :name="'user_forms['+index+'][end_date]'"
                                                                   x-model="form.endDate"
                                                                   :min="form.startDate || minDate"
                                                                   class="input-field pl-10"
                                                                   :required="form.durationType === 'temporary'"
                                                                   @change="validateDates(index)">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div x-show="form.dateError" class="mt-2 text-sm text-red-600" x-text="form.dateError"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Justification -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Justification <span class="text-red-500">*</span>
                                        </label>
                                        <div class="relative">
                                            <textarea :name="'user_forms['+index+'][justification]'"
                                                      x-model="form.justification"
                                                      placeholder="Please provide a detailed reason for this access request"
                                                      required
                                                      rows="3"
                                                      class="input-field pl-10 resize-none"></textarea>
                                            <span class="absolute top-3 left-3 text-gray-500">
                                                <i class='bx bx-edit'></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </template>
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
            isGroupAccess: false,
            groupUsernames: [],
            userForms: [
                {
                    usernames: [''],
                    accessType: '',
                    accessLevel: '',
                    selectedSystems: [],
                    durationType: '',
                    startDate: '',
                    endDate: '',
                    justification: ''
                }
            ],
            
            init() {
                this.setupProgressBar();
                this.setupDateTime();
                
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
                
                // Set minimum date for date inputs
                this.minDate = today.toISOString().split('T')[0];
                
                // Initialize with one empty user form
                this.userForms = [{
                    usernames: [''],
                    accessType: '',
                    accessLevel: '',
                    selectedSystems: [],
                    durationType: '',
                    startDate: '',
                    endDate: '',
                    justification: ''
                }];

                // Define access types with their icons
                this.accessTypes = [
                    { value: 'System Application', label: 'System Application', icon: 'bx bx-window-alt' },
                    { value: 'PC Access - Network', label: 'PC Access - Network', icon: 'bx bx-desktop' },
                    { value: 'Email Access', label: 'Email Access', icon: 'bx bx-envelope' },
                    { value: 'Server Access', label: 'Server Access', icon: 'bx bx-server' },
                    { value: 'Internet Access', label: 'Internet Access', icon: 'bx bx-globe' },
                    { value: 'Printer Access', label: 'Printer Access', icon: 'bx bx-printer' },
                    { value: 'Active Directory Access (MS ENTRA ID)', label: 'Active Directory Access', icon: 'bx bx-folder-open' },
                    { value: 'Firewall Access', label: 'Firewall Access', icon: 'bx bx-shield-quarter' },
                    { value: 'Wi-Fi/Access Point Access', label: 'Wi-Fi/Access Point Access', icon: 'bx bx-wifi' },
                    { value: 'TNA Biometric Device Access', label: 'TNA Biometric Device Access', icon: 'bx bx-fingerprint' },
                    { value: 'USB/PC-port Access', label: 'USB/PC-port Access', icon: 'bx bx-usb' },
                    { value: 'CCTV Access', label: 'CCTV Access', icon: 'bx bx-cctv' }
                ];

                // Define access levels with their icons
                this.accessLevels = [
                    { value: 'read', label: 'Read Only', icon: 'bx bx-book-reader' },
                    { value: 'full', label: 'Full Access', icon: 'bx bx-edit' },
                    { value: 'admin', label: 'Administrator', icon: 'bx bx-cog' },
                ];

                // Define system types
                this.systemTypes = [
                    { value: 'Canvasing System', label: 'Canvasing System' },
                    { value: 'ERP/NAV', label: 'ERP/NAV' },
                    { value: 'Legacy Payroll', label: 'Legacy Payroll' },
                    { value: 'HRIS', label: 'HRIS' },
                    { value: 'Legacy Purchasing', label: 'Legacy Purchasing' },
                    { value: 'Piece Rate Payroll System', label: 'Piece Rate Payroll System' },
                    { value: 'Legacy Inventory', label: 'Legacy Inventory' },
                    { value: 'Fresh Chilled Receiving System', label: 'Fresh Chilled Receiving System' },
                    { value: 'Legacy Vouchering', label: 'Legacy Vouchering' },
                    { value: 'Quickbooks', label: 'Quickbooks' },
                    { value: 'Legacy Ledger System', label: 'Legacy Ledger System' },
                    { value: 'Memorandum Receipt', label: 'Memorandum Receipt' },
                    { value: 'ZankPOS', label: 'ZankPOS' }
                ];
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
                this.userForms = [{
                    usernames: [''],
                    accessType: '',
                    accessLevel: '',
                    selectedSystems: [],
                    durationType: '',
                    startDate: '',
                    endDate: '',
                    justification: ''
                }];
                
                // Reset form
                document.getElementById('accessRequestForm').reset();
                
                // Hide sections
                const systemApplicationSection = document.getElementById('systemApplicationSection');
                if (systemApplicationSection) {
                    systemApplicationSection.classList.add('hidden');
                }
            },
            addUsername(formIndex) {
                this.userForms[formIndex].usernames.push('');
            },
            removeUsername(formIndex, usernameIndex) {
                this.userForms[formIndex].usernames.splice(usernameIndex, 1);
            },
            addUserForm() {
                this.userForms.push({
                    usernames: [''],
                    accessType: '',
                    accessLevel: '',
                    selectedSystems: [],
                    durationType: '',
                    startDate: '',
                    endDate: '',
                    justification: ''
                });
            },
            removeUserForm(index) {
                this.userForms.splice(index, 1);
            },
            validateDates(index) {
                const form = this.userForms[index];
                if (form.durationType === 'temporary') {
                    const startDate = new Date(form.startDate);
                    const endDate = new Date(form.endDate);
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);

                    // Clear previous error
                    form.dateError = '';

                    // Validate start date
                    if (startDate < today) {
                        form.dateError = 'Start date cannot be in the past';
                        form.startDate = '';
                        return;
                    }

                    // Validate end date
                    if (endDate < startDate) {
                        form.dateError = 'End date must be after start date';
                        form.endDate = '';
                        return;
                    }

                    // Calculate difference in days
                    const diffTime = Math.abs(endDate - startDate);
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

                    // Validate maximum duration (e.g., 90 days)
                    if (diffDays > 90) {
                        form.dateError = 'Temporary access cannot exceed 90 days';
                        form.endDate = '';
                        return;
                    }
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

            // Validate each user form
            const userFormContainers = form.querySelectorAll('[x-for="(form, index) in userForms"] > div');
            for (let index = 0; index < userFormContainers.length; index++) {
                const container = userFormContainers[index];
                // Validate all usernames
                const usernameInputs = container.querySelectorAll(`input[name^="user_forms[${index}][usernames]"]`);
                let hasEmptyUsername = false;
                usernameInputs.forEach(input => {
                    if (!input.value.trim()) {
                        hasEmptyUsername = true;
                    }
                });
                if (hasEmptyUsername) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Validation Error',
                        text: `Please fill in all username fields for User Form #${index + 1}`,
                        confirmButtonColor: '#0ea5e9'
                    });
                    return;
                }
                // Validate access type
                const accessType = container.querySelector(`input[name="user_forms[${index}][access_type]"]:checked`);
                if (!accessType) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Validation Error',
                        text: `Please select an access type for User Form #${index + 1}`,
                        confirmButtonColor: '#0ea5e9'
                    });
                    return;
                }
                // If System Application, validate access level and system type
                if (accessType.value === 'System Application') {
                    const accessLevel = container.querySelector(`input[name="user_forms[${index}][access_level]"]:checked`);
                    if (!accessLevel) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Validation Error',
                            text: `Please select an access level for User Form #${index + 1}`,
                            confirmButtonColor: '#0ea5e9'
                        });
                        return;
                    }
                    const systemTypes = container.querySelectorAll(`input[name="user_forms[${index}][system_type][]"]:checked`);
                    if (systemTypes.length === 0) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Validation Error',
                            text: `Please select at least one system/application type for User Form #${index + 1}`,
                            confirmButtonColor: '#0ea5e9'
                        });
                        return;
                    }
                }
                // Validate duration type
                const durationType = container.querySelector(`input[name="user_forms[${index}][duration_type]"]:checked`);
                if (!durationType) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Validation Error',
                        text: `Please select a duration type for User Form #${index + 1}`,
                        confirmButtonColor: '#0ea5e9'
                    });
                    return;
                }
                // If temporary, validate dates
                if (durationType.value === 'temporary') {
                    const startDate = container.querySelector(`input[name="user_forms[${index}][start_date]"]`);
                    const endDate = container.querySelector(`input[name="user_forms[${index}][end_date]"]`);
                    if (!startDate.value || !endDate.value) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Validation Error',
                            text: `Please enter both start and end dates for User Form #${index + 1}`,
                            confirmButtonColor: '#0ea5e9'
                        });
                        return;
                    }
                    // Additional date logic (already handled by Alpine, but double check)
                    if (new Date(startDate.value) > new Date(endDate.value)) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Validation Error',
                            text: `End date must be after start date for User Form #${index + 1}`,
                            confirmButtonColor: '#0ea5e9'
                        });
                        return;
                    }
                }
                // Validate justification
                const justification = container.querySelector(`textarea[name="user_forms[${index}][justification]"]`);
                if (!justification || !justification.value.trim()) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Validation Error',
                        text: `Please provide a justification for User Form #${index + 1}`,
                        confirmButtonColor: '#0ea5e9'
                    });
                    return;
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

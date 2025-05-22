<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['requestor_id'])) {
    header("Location: login.php");
    exit();
}

$requestorId = $_SESSION['requestor_id'];
$username = $_SESSION['username'] ?? 'User';

// Check if request ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: my_requests.php");
    exit();
}

$requestId = intval($_GET['id']);

// Fetch the request details
try {
    $query = "SELECT ar.*, e.employee_name as requestor_name 
              FROM access_requests ar
              LEFT JOIN employees e ON ar.employee_id = e.employee_id
              WHERE ar.id = :request_id AND ar.employee_id = :employee_id";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':request_id' => $requestId,
        ':employee_id' => $requestorId
    ]);
    
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        // Request not found or doesn't belong to the user
        header("Location: my_requests.php");
        exit();
    }
    
} catch (PDOException $e) {
    error_log("Error fetching request details: " . $e->getMessage());
    header("Location: my_requests.php?error=db");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Request</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Boxicons -->
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
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
                        }
                    },
                    fontFamily: {
                        'sans': ['Inter', 'sans-serif'],
                    },
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
        
        /* Status Badge Styles */
        .status-badge {
            @apply px-3 py-1.5 text-xs font-bold rounded-full shadow-md inline-block;
        }
        .status-pending {
            @apply bg-yellow-500 text-white border-2 border-yellow-600;
        }
        .status-approved {
            @apply bg-green-600 text-white border-2 border-green-700;
        }
        .status-rejected {
            @apply bg-red-600 text-white border-2 border-red-700;
        }
    </style>
</head>
<body class="bg-gray-50 font-sans">

<!-- Sidebar -->
<div class="fixed inset-y-0 left-0 z-50 w-72 bg-white shadow-card transform transition-transform duration-300 overflow-hidden" x-data="{open: true}">
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
            <a href="create_request.php" class="flex items-center p-3 text-gray-700 rounded-xl transition-all duration-200 hover:bg-gray-50 hover:text-primary-600 group">
                <span class="flex items-center justify-center w-10 h-10 bg-gray-100 text-gray-600 rounded-xl mr-3 group-hover:bg-primary-50 group-hover:text-primary-600 transition-all duration-200">
                    <i class='bx bx-send text-xl'></i>
                </span>
                <span class="font-medium">Create Request</span>
            </a>
            <a href="my_requests.php" class="flex items-center p-3 text-primary-600 bg-primary-50 rounded-xl transition-all duration-200 group">
                <span class="flex items-center justify-center w-10 h-10 bg-primary-100 text-primary-600 rounded-xl mr-3">
                    <i class='bx bx-list-ul text-xl'></i>
                </span>
                <span class="font-medium">My Requests</span>
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
<div class="ml-0 md:ml-72 transition-all duration-300">
    <!-- Header -->
    <div class="bg-white border-b border-gray-200 sticky top-0 z-10 shadow-sm">
        <div class="flex justify-between items-center px-6 py-4">
            <div data-aos="fade-right" data-aos-duration="800">
                <h2 class="text-2xl font-bold text-gray-800">View Request Details</h2>
                <p class="text-gray-600 text-lg mt-1">Request #<?php echo htmlspecialchars($request['access_request_number'] ?? 'N/A'); ?></p>
            </div>
            <div data-aos="fade-left" data-aos-duration="800" class="flex space-x-2">
                <a href="my_requests.php" class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                    <i class='bx bx-arrow-back mr-2'></i> Back to Requests
                </a>
            </div>
        </div>
    </div>

    <div class="p-6">
        <!-- Request Details -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <!-- Request Info -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6" data-aos="fade-up" data-aos-duration="800">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                    <i class='bx bx-info-circle text-primary-500 text-xl mr-2'></i>
                    Request Information
                </h3>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Request No:</span>
                        <span class="font-medium text-gray-900"><?php echo htmlspecialchars($request['access_request_number'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Status:</span>
                        <?php 
                        $statusClass = '';
                        $status = strtolower($request['status'] ?? 'pending');
                        
                        if ($status === 'pending') {
                            $statusClass = 'status-pending';
                            $bgClass = 'bg-yellow-100';
                        } elseif ($status === 'approved') {
                            $statusClass = 'status-approved';
                            $bgClass = 'bg-green-100';
                        } elseif ($status === 'rejected') {
                            $statusClass = 'status-rejected';
                            $bgClass = 'bg-red-100';
                        }
                        ?>
                        <div class="flex justify-center items-center w-full">
                            <span class="inline-block px-4 py-1 rounded-full bg-blue-100 text-blue-900 font-semibold text-base text-center">
                                <?php 
                                $displayStatus = ucwords(str_replace('_', ' ', $status));
                                echo htmlspecialchars($displayStatus);
                                ?>
                            </span>
                        </div>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Submitted:</span>
                        <span class="font-medium text-gray-900">
                            <?php 
                            $date = new DateTime($request['submission_date'] ?? 'now');
                            echo $date->format('M d, Y h:i A'); 
                            ?>
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Duration:</span>
                        <span class="font-medium text-gray-900">
                            <?php 
                            if ($request['duration_type'] === 'permanent') {
                                echo 'Permanent';
                            } else {
                                $startDate = new DateTime($request['start_date']);
                                $endDate = new DateTime($request['end_date']);
                                echo $startDate->format('M d, Y') . ' - ' . $endDate->format('M d, Y');
                            }
                            ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Requestor Info -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6" data-aos="fade-up" data-aos-duration="800" data-aos-delay="100">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                    <i class='bx bx-user text-primary-500 text-xl mr-2'></i>
                    Requestor Information
                </h3>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Name:</span>
                        <span class="font-medium text-gray-900"><?php echo htmlspecialchars($request['requestor_name'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Business Unit:</span>
                        <span class="font-medium text-gray-900"><?php echo htmlspecialchars($request['business_unit'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Department:</span>
                        <span class="font-medium text-gray-900"><?php echo htmlspecialchars($request['department'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Email:</span>
                        <span class="font-medium text-gray-900"><?php echo htmlspecialchars($request['email'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Employee ID:</span>
                        <span class="font-medium text-gray-900"><?php echo htmlspecialchars($request['employee_id'] ?? 'N/A'); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Access Details -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6" data-aos="fade-up" data-aos-duration="800" data-aos-delay="200">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                    <i class='bx bx-lock-open text-primary-500 text-xl mr-2'></i>
                    Access Details
                </h3>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Access Type:</span>
                        <span class="font-medium text-gray-900"><?php echo htmlspecialchars($request['access_type'] ?? 'N/A'); ?></span>
                    </div>
                    <?php if (!empty($request['system_type'])): ?>
                    <div class="flex justify-between">
                        <span class="text-gray-600">System Type:</span>
                        <span class="font-medium text-gray-900"><?php echo htmlspecialchars($request['system_type'] ?? 'N/A'); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($request['other_system_type'])): ?>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Other System:</span>
                        <span class="font-medium text-gray-900"><?php echo htmlspecialchars($request['other_system_type'] ?? 'N/A'); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($request['role_access_type'])): ?>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Role Access Type:</span>
                        <span class="font-medium text-gray-900"><?php echo htmlspecialchars($request['role_access_type'] ?? 'N/A'); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Justification -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6" data-aos="fade-up" data-aos-duration="800" data-aos-delay="300">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                <i class='bx bx-comment-detail text-primary-500 text-xl mr-2'></i>
                Justification
            </h3>
            <div class="bg-gray-50 p-4 rounded-lg text-gray-700">
                <?php echo nl2br(htmlspecialchars($request['justification'] ?? 'No justification provided.')); ?>
            </div>
        </div>
        
        <?php if (!empty($request['review_notes'])): ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6" data-aos="fade-up" data-aos-duration="800" data-aos-delay="350">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                <i class='bx bx-message-square-detail text-primary-500 text-xl mr-2'></i>
                Administrator Feedback
            </h3>
            <div class="bg-gray-50 p-4 rounded-lg text-gray-700">
                <?php echo nl2br(htmlspecialchars($request['review_notes'])); ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Actions -->
        <div class="flex justify-between items-center" data-aos="fade-up" data-aos-duration="800" data-aos-delay="500">
            <div class="flex space-x-2">
                <a href="my_requests.php" class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                    <i class='bx bx-arrow-back mr-2'></i> Back to Requests
                </a>
            </div>
            <?php if ($request['status'] === 'pending'): ?>
            <button id="cancelRequestBtn" class="inline-flex items-center px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                <i class='bx bx-x mr-2'></i> Cancel Request
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add approval timeline -->
    <div class="mt-8 bg-white shadow overflow-hidden sm:rounded-lg">
        <div class="px-4 py-5 sm:px-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900">Approval Timeline</h3>
        </div>
        <div class="border-t border-gray-200">
            <div class="px-4 py-5 sm:p-6">
                <div class="flow-root">
                    <ul role="list" class="-mb-8">
                        <!-- Superior Review -->
                        <li>
                            <div class="relative pb-8">
                                <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                                <div class="relative flex space-x-3">
                                    <div>
                                        <span class="h-8 w-8 rounded-full <?php echo $request['superior_id'] ? 'bg-green-500' : ($request['status'] === 'pending_superior' ? 'bg-yellow-500' : 'bg-gray-400'); ?> flex items-center justify-center ring-8 ring-white">
                                            <i class='bx bx-user text-white'></i>
                                        </span>
                                    </div>
                                    <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                        <div>
                                            <p class="text-sm text-gray-500">Superior Review</p>
                                            <?php if ($request['superior_id']): ?>
                                            <p class="text-sm text-gray-600 mt-1"><?php echo nl2br(htmlspecialchars($request['superior_notes'])); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-right text-sm whitespace-nowrap text-gray-500">
                                            <?php if ($request['superior_review_date']): ?>
                                            <time datetime="<?php echo $request['superior_review_date']; ?>"><?php echo date('M d, Y', strtotime($request['superior_review_date'])); ?></time>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>
                        <!-- Technical Review -->
                        <li>
                            <div class="relative pb-8">
                                <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                                <div class="relative flex space-x-3">
                                    <div>
                                        <span class="h-8 w-8 rounded-full <?php echo $request['technical_id'] ? 'bg-green-500' : ($request['status'] === 'pending_technical' ? 'bg-yellow-500' : 'bg-gray-400'); ?> flex items-center justify-center ring-8 ring-white">
                                            <i class='bx bx-wrench text-white'></i>
                                        </span>
                                    </div>
                                    <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                        <div>
                                            <p class="text-sm text-gray-500">Technical Review</p>
                                            <?php if ($request['technical_id']): ?>
                                            <p class="text-sm text-gray-600 mt-1"><?php echo nl2br(htmlspecialchars($request['technical_notes'])); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-right text-sm whitespace-nowrap text-gray-500">
                                            <?php if ($request['technical_review_date']): ?>
                                            <time datetime="<?php echo $request['technical_review_date']; ?>"><?php echo date('M d, Y', strtotime($request['technical_review_date'])); ?></time>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>
                        <!-- Process Owner Review -->
                        <li>
                            <div class="relative pb-8">
                                <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                                <div class="relative flex space-x-3">
                                    <div>
                                        <span class="h-8 w-8 rounded-full <?php echo $request['process_owner_id'] ? 'bg-green-500' : ($request['status'] === 'pending_process_owner' ? 'bg-yellow-500' : 'bg-gray-400'); ?> flex items-center justify-center ring-8 ring-white">
                                            <i class='bx bx-briefcase text-white'></i>
                                        </span>
                                    </div>
                                    <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                        <div>
                                            <p class="text-sm text-gray-500">Process Owner Review</p>
                                            <?php if ($request['process_owner_id']): ?>
                                            <p class="text-sm text-gray-600 mt-1"><?php echo nl2br(htmlspecialchars($request['process_owner_notes'])); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-right text-sm whitespace-nowrap text-gray-500">
                                            <?php if ($request['process_owner_review_date']): ?>
                                            <time datetime="<?php echo $request['process_owner_review_date']; ?>"><?php echo date('M d, Y', strtotime($request['process_owner_review_date'])); ?></time>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>
                        <!-- Admin Review -->
                        <li>
                            <div class="relative">
                                <div class="relative flex space-x-3">
                                    <div>
                                        <span class="h-8 w-8 rounded-full <?php echo $request['admin_id'] ? 'bg-green-500' : ($request['status'] === 'pending_admin' ? 'bg-yellow-500' : 'bg-gray-400'); ?> flex items-center justify-center ring-8 ring-white">
                                            <i class='bx bx-shield text-white'></i>
                                        </span>
                                    </div>
                                    <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                        <div>
                                            <p class="text-sm text-gray-500">Admin Review</p>
                                            <?php if ($request['admin_id']): ?>
                                            <p class="text-sm text-gray-600 mt-1"><?php echo nl2br(htmlspecialchars($request['admin_notes'])); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-right text-sm whitespace-nowrap text-gray-500">
                                            <?php if ($request['admin_review_date']): ?>
                                            <time datetime="<?php echo $request['admin_review_date']; ?>"><?php echo date('M d, Y', strtotime($request['admin_review_date'])); ?></time>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Add testing status section if applicable -->
    <?php if ($request['status'] === 'pending_testing'): ?>
    <div class="mt-8 bg-white shadow overflow-hidden sm:rounded-lg">
        <div class="px-4 py-5 sm:px-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900">Testing Status</h3>
        </div>
        <div class="border-t border-gray-200">
            <div class="px-4 py-5 sm:p-6">
                <?php if ($request['testing_status'] === 'pending'): ?>
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Please test your access and update the status:</p>
                    </div>
                    <div class="flex space-x-3">
                        <button onclick="updateTestingStatus(<?php echo $request['id']; ?>, 'success')" 
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                            Testing Success
                        </button>
                        <button onclick="updateTestingStatus(<?php echo $request['id']; ?>, 'failed')" 
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                            Testing Failed
                        </button>
                    </div>
                </div>
                <?php else: ?>
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <?php if ($request['testing_status'] === 'success'): ?>
                        <i class='bx bx-check text-2xl text-green-500'></i>
                        <?php else: ?>
                        <i class='bx bx-x text-2xl text-red-500'></i>
                        <?php endif; ?>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-gray-900">
                            Testing <?php echo ucfirst($request['testing_status']); ?>
                        </h3>
                        <?php if ($request['testing_notes']): ?>
                        <div class="mt-2 text-sm text-gray-500">
                            <p><?php echo nl2br(htmlspecialchars($request['testing_notes'])); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- SweetAlert2 CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize AOS animation library
        AOS.init();
        
        // Setup cancel request button
        const cancelBtn = document.getElementById('cancelRequestBtn');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function() {
                Swal.fire({
                    title: 'Cancel Request',
                    text: 'Are you sure you want to cancel this request?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, cancel it!',
                    cancelButtonText: 'No, keep it'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Redirect to cancel_request.php with the request ID
                        window.location.href = 'cancel_request.php?id=<?php echo $requestId; ?>';
                    }
                });
            });
        }
    });

    function updateTestingStatus(requestId, status) {
        Swal.fire({
            title: 'Update Testing Status',
            text: 'Please provide any notes about the testing process:',
            input: 'textarea',
            showCancelButton: true,
            confirmButtonText: 'Submit',
            showLoaderOnConfirm: true,
            preConfirm: (notes) => {
                return fetch('testing_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `request_id=${requestId}&testing_status=${status}&testing_notes=${encodeURIComponent(notes)}`,
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(response.statusText);
                    }
                    return response.json();
                })
                .catch(error => {
                    Swal.showValidationMessage(`Request failed: ${error}`);
                });
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Success!',
                    text: 'Testing status has been updated.',
                    icon: 'success'
                }).then(() => {
                    window.location.reload();
                });
            }
        });
    }
</script>
</body>
</html> 
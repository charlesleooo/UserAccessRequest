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
    // First check if it's an individual or group request
    $checkQuery = "SELECT COUNT(*) as count FROM individual_requests WHERE access_request_number = (
                    SELECT access_request_number FROM access_requests WHERE id = :request_id
                  )";
    $stmt = $pdo->prepare($checkQuery);
    $stmt->execute([':request_id' => $requestId]);
    $isIndividual = $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
    
    $requestTable = $isIndividual ? 'individual_requests' : 'group_requests';
    
    // First get the main request details
    $mainQuery = "SELECT ar.*, e.employee_name as requestor_name
                 FROM access_requests ar
                 LEFT JOIN employees e ON ar.employee_id = e.employee_id
                 WHERE ar.id = :request_id AND ar.employee_id = :employee_id";
    
    $stmt = $pdo->prepare($mainQuery);
    $stmt->execute([
        ':request_id' => $requestId,
        ':employee_id' => $requestorId
    ]);
    
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        header("Location: my_requests.php");
        exit();
    }
    
    // Then get all the individual entries for this request
    $detailsQuery = "SELECT r.username, r.application_system, r.access_type as role_access_type,
                            r.access_duration as duration_type, r.start_date, r.end_date,
                            r.date_needed, r.justification
                     FROM {$requestTable} r
                     WHERE r.access_request_number = :access_request_number
                     ORDER BY r.username";
    
    $stmt = $pdo->prepare($detailsQuery);
    $stmt->execute([
        ':access_request_number' => $request['access_request_number']
    ]);
    
    $requestDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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

<!-- Main Content -->
<div class="w-full transition-all duration-300">
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

    <!-- Requestor Information -->
    <div class="p-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6" data-aos="fade-up" data-aos-duration="800">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-1 border-b border-gray-100 flex items-center">
                <i class='bx bx-user text-primary-500 text-xl mr-2'></i>
                Requestor Information
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-gray-50 p-4 rounded-lg">
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Name:</span>
                            <span class="font-medium text-gray-900"><?php echo htmlspecialchars($request['requestor_name'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Employee ID:</span>
                            <span class="font-medium text-gray-900"><?php echo htmlspecialchars($request['employee_id'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Email:</span>
                            <span class="font-medium text-gray-900"><?php echo htmlspecialchars($request['employee_email'] ?? 'N/A'); ?></span>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Business Unit:</span>
                            <span class="font-medium text-gray-900"><?php echo htmlspecialchars($request['business_unit'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Department:</span>
                            <span class="font-medium text-gray-900"><?php echo htmlspecialchars($request['department'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Request Date:</span>
                            <span class="font-medium text-gray-900">
                                <?php 
                                if (!empty($request['request_date'])) {
                                    $requestDate = new DateTime($request['request_date']);
                                    echo $requestDate->format('M d, Y');
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </span>
                        </div>
                    </div>
            </div>
        </div>
        </div>
        </div>

    <div class="p-6">
        <!-- Access Details -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6" data-aos="fade-up" data-aos-duration="800">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                <i class='bx bx-lock-open text-primary-500 text-xl mr-2'></i>
                Access Details
            </h3>
            <div class="space-y-4">
                <?php foreach ($requestDetails as $index => $detail): ?>
                <div class="bg-white p-4 rounded-lg mb-4 shadow-sm">
                    <h4 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100">
                        User Details #<?php echo $index + 1; ?>
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h4 class="text-sm font-medium text-gray-600 mb-2">Basic Information</h4>
                            <div class="space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">User Name:</span>
                                    <span class="font-medium text-gray-900">
                                        <?php echo htmlspecialchars($detail['username'] ?? 'N/A'); ?>
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Access Type:</span>
                                    <span class="font-medium text-gray-900"><?php echo htmlspecialchars($detail['role_access_type'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">System Type:</span>
                                    <span class="font-medium text-gray-900"><?php echo htmlspecialchars($request['system_type'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Application System:</span>
                                    <span class="font-medium text-gray-900"><?php echo htmlspecialchars($detail['application_system'] ?? 'N/A'); ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h4 class="text-sm font-medium text-gray-600 mb-2">Access Duration</h4>
                            <div class="space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Duration Type:</span>
                                    <span class="font-medium text-gray-900">
                                        <?php 
                                        if (isset($detail['duration_type'])) {
                                            echo $detail['duration_type'] === 'permanent' ? 'Permanent' : 'Temporary';
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </span>
                                </div>
                                <?php if (isset($detail['duration_type']) && $detail['duration_type'] !== 'permanent'): ?>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Start Date:</span>
                                    <span class="font-medium text-gray-900">
                                        <?php 
                                        if (!empty($detail['start_date'])) {
                                            $startDate = new DateTime($detail['start_date']);
                                            echo $startDate->format('M d, Y');
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">End Date:</span>
                                    <span class="font-medium text-gray-900">
                                        <?php 
                                        if (!empty($detail['end_date'])) {
                                            $endDate = new DateTime($detail['end_date']);
                                            echo $endDate->format('M d, Y');
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Date Needed:</span>
                                    <span class="font-medium text-gray-900">
                                        <?php 
                                        if (!empty($detail['date_needed'])) {
                                            $dateNeeded = new DateTime($detail['date_needed']);
                                            echo $dateNeeded->format('M d, Y');
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-lg mt-4">
                        <h4 class="text-sm font-medium text-gray-600 mb-2">Justification</h4>
                        <div class="text-gray-700">
                            <?php echo nl2br(htmlspecialchars($detail['justification'] ?? 'No justification provided.')); ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
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
        <div class="flex justify-end items-center" data-aos="fade-up" data-aos-duration="800" data-aos-delay="500">

            <?php 
            $status = strtolower($request['status']);
            $adminReviewDate = $request['admin_review_date'] ?? null;
            
            if ($status === 'pending' || ($status !== 'approved' && $status !== 'rejected' && !$adminReviewDate)): 
            ?>
            <button onclick="cancelRequest(<?php echo $request['id']; ?>)"
                    class="inline-flex items-center px-4 py-2 bg-red-600 text-white rounded-lg mr-5 hover:bg-red-700 transition-colors">
                <i class='bx bx-x mr-2'></i> Cancel Request
            </button>
            <?php endif; ?>
        </div>
    </div>    <!-- Add approval timeline -->
    <div class="p-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6" data-aos="fade-up" data-aos-duration="800">
            <h3 class="text-lg font-semibold text-gray-800 mb-6 pb-2 border-b border-gray-100 flex items-center">
                <i class='bx bx-history text-primary-500 text-xl mr-2'></i>
                Approval Timeline
            </h3>            
            
            <div class="relative">
                <!-- Vertical Line - will end at the last item -->
                <div class="absolute left-5 top-0 h-[calc(98%-4rem)] w-0.5 bg-gray-200"></div>
                
                <div class="space-y-8">
                    <!-- Superior Review -->
                    <div class="relative flex items-start group">
                        <div class="absolute left-0 w-10 h-10 flex items-center justify-center z-10">
                            <div class="w-10 h-10 rounded-full <?php echo $request['superior_review_date'] ? 'bg-green-500' : 'bg-gray-300'; ?> flex items-center justify-center shadow-sm transform transition-transform group-hover:scale-110">
                                <i class='bx bxs-user-check text-xl text-white'></i>
                            </div>
                        </div>
                        <div class="ml-16 bg-white rounded-lg border border-gray-100 shadow-sm hover:shadow-md transition-shadow p-4 w-full">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-2">
                                <h4 class="text-base font-semibold text-gray-900">Superior Review</h4>
                                <?php if ($request['superior_review_date']): ?>
                                    <span class="text-sm text-gray-600 font-medium bg-gray-50 px-3 py-1 rounded-full">
                                        <?php echo date('M j, Y h:i A', strtotime($request['superior_review_date'])); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="mt-2 bg-gray-50 rounded-lg p-3 text-sm text-gray-600">
                                <?php 
                                if (!empty($request['superior_notes'])) {
                                    echo nl2br(htmlspecialchars($request['superior_notes']));
                                } else {
                                    echo '<span class="text-gray-500 italic">Pending review</span>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>

                    <!-- Technical Review -->
                    <div class="relative flex items-start group">
                        <div class="absolute left-0 w-10 h-10 flex items-center justify-center z-10">
                            <div class="w-10 h-10 rounded-full <?php echo $request['technical_review_date'] ? 'bg-green-500' : 'bg-gray-300'; ?> flex items-center justify-center shadow-sm transform transition-transform group-hover:scale-110">
                                <i class='bx bx-code-alt text-xl text-white'></i>
                            </div>
                        </div>
                        <div class="ml-16 bg-white rounded-lg border border-gray-100 shadow-sm hover:shadow-md transition-shadow p-4 w-full">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-2">
                                <h4 class="text-base font-semibold text-gray-900">Technical Support Review</h4>
                                <?php if ($request['technical_review_date']): ?>
                                    <span class="text-sm text-gray-600 font-medium bg-gray-50 px-3 py-1 rounded-full">
                                        <?php echo date('M j, Y h:i A', strtotime($request['technical_review_date'])); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="mt-2 bg-gray-50 rounded-lg p-3 text-sm text-gray-600">
                                <?php 
                                if (!empty($request['technical_notes'])) {
                                    echo nl2br(htmlspecialchars($request['technical_notes']));
                                } else {
                                    echo '<span class="text-gray-500 italic">Awaiting technical review</span>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>

                    <!-- Process Owner Review -->
                    <div class="relative flex items-start group">
                        <div class="absolute left-0 w-10 h-10 flex items-center justify-center z-10">
                            <div class="w-10 h-10 rounded-full <?php echo $request['process_owner_review_date'] ? 'bg-green-500' : 'bg-gray-300'; ?> flex items-center justify-center shadow-sm transform transition-transform group-hover:scale-110">
                                <i class='bx bx-user-voice text-xl text-white'></i>
                            </div>
                        </div>
                        <div class="ml-16 bg-white rounded-lg border border-gray-100 shadow-sm hover:shadow-md transition-shadow p-4 w-full">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-2">
                                <h4 class="text-base font-semibold text-gray-900">Process Owner Review</h4>
                                <?php if ($request['process_owner_review_date']): ?>
                                    <span class="text-sm text-gray-600 font-medium bg-gray-50 px-3 py-1 rounded-full">
                                        <?php echo date('M j, Y h:i A', strtotime($request['process_owner_review_date'])); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="mt-2 bg-gray-50 rounded-lg p-3 text-sm text-gray-600">
                                <?php 
                                if (!empty($request['process_owner_notes'])) {
                                    echo nl2br(htmlspecialchars($request['process_owner_notes']));
                                } else {
                                    echo '<span class="text-gray-500 italic">Awaiting process owner review</span>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>

                    <!-- Admin Review -->
                    <div class="relative flex items-start group">
                        <div class="absolute left-0 w-10 h-10 flex items-center justify-center z-10">
                            <div class="w-10 h-10 rounded-full <?php echo $request['admin_review_date'] ? 'bg-green-500' : 'bg-gray-300'; ?> flex items-center justify-center shadow-sm transform transition-transform group-hover:scale-110">
                                <i class='bx bx-shield-quarter text-xl text-white'></i>
                            </div>
                        </div>
                        <div class="ml-16 bg-white rounded-lg border border-gray-100 shadow-sm hover:shadow-md transition-shadow p-4 w-full">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-2">
                                <h4 class="text-base font-semibold text-gray-900">Admin Review</h4>
                                <?php if ($request['admin_review_date']): ?>
                                    <span class="text-sm text-gray-600 font-medium bg-gray-50 px-3 py-1 rounded-full">
                                        <?php echo date('M j, Y h:i A', strtotime($request['admin_review_date'])); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="mt-2 bg-gray-50 rounded-lg p-3 text-sm text-gray-600">
                                <?php 
                                if (!empty($request['admin_notes'])) {
                                    echo nl2br(htmlspecialchars($request['admin_notes']));
                                } else {
                                    echo '<span class="text-gray-500 italic">Awaiting admin review</span>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>    <!-- Add testing status section if applicable -->
    <?php if ($request['status'] === 'pending_testing'): ?>
    <div class="p-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6" data-aos="fade-up" data-aos-duration="800">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                <i class='bx bx-test-tube text-messenger-primary text-xl mr-2'></i>
                Testing Status
            </h3>
            <div class="p-4">
                <?php if ($request['testing_status'] === 'pending'): ?>
                <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                    <div>
                        <p class="text-gray-700 font-medium mb-2">System Access Testing Required</p>
                        <p class="text-sm text-gray-600">
                            <?php 
                            $testing_reason = $request['access_type'] === 'System Application' ? 
                                'System Application access' : 
                                'Admin role access';
                            echo "Testing is required for your {$testing_reason}. Please test your system access and update the status below."; 
                            ?>
                        </p>
                    </div>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <button onclick="updateTestingStatus(<?php echo $request['id']; ?>, 'success')" 
                                class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg bg-messenger-primary text-white hover:bg-messenger-dark focus:outline-none focus:ring-2 focus:ring-messenger-light focus:ring-offset-2 transition-colors duration-200">
                            <i class='bx bx-check-circle mr-2 text-xl'></i>
                            Testing Success
                        </button>
                        <button onclick="updateTestingStatus(<?php echo $request['id']; ?>, 'failed')" 
                                class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg bg-red-500 text-white hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-400 focus:ring-offset-2 transition-colors duration-200">
                            <i class='bx bx-x-circle mr-2 text-xl'></i>
                            Testing Failed
                        </button>
                    </div>
                </div>                <?php else: ?>
                <div class="flex items-start space-x-4">
                    <div class="flex-shrink-0">
                        <div class="flex items-center justify-center w-12 h-12 rounded-full <?php echo $request['testing_status'] === 'success' ? 'bg-green-100' : 'bg-red-100'; ?>">
                            <?php if ($request['testing_status'] === 'success'): ?>
                            <i class='bx bx-check text-3xl text-green-500'></i>
                            <?php else: ?>
                            <i class='bx bx-x text-3xl text-red-500'></i>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-lg font-medium <?php echo $request['testing_status'] === 'success' ? 'text-green-700' : 'text-red-700'; ?>">
                            Testing <?php echo ucfirst($request['testing_status']); ?>
                        </h3>
                        <?php if ($request['testing_notes']): ?>
                        <div class="mt-2 p-4 bg-gray-50 rounded-lg">
                            <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($request['testing_notes'])); ?></p>
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
        

        
        // Show success/error messages
        <?php if (isset($_GET['success']) && $_GET['success'] === 'cancelled'): ?>
        Swal.fire({
            title: 'Success!',
            text: 'Your request has been cancelled successfully.',
            icon: 'success',
            timer: 3000,
            showConfirmButton: false
        }).then(() => {
            window.location.href = 'my_requests.php';
        });
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
        Swal.fire({
            title: 'Error!',
            text: <?php 
                $errorMsg = 'An error occurred. Please try again.';
                if ($_GET['error'] === 'not_found') {
                    $errorMsg = 'Request not found.';
                } elseif ($_GET['error'] === 'cannot_cancel') {
                    $errorMsg = 'This request cannot be cancelled.';
                } elseif ($_GET['error'] === 'invalid_request') {
                    $errorMsg = 'Invalid request.';
                }
                echo json_encode($errorMsg);
            ?>,
            icon: 'error'
        });
        <?php endif; ?>
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

    function cancelRequest(requestId) {
        Swal.fire({
            title: 'Cancel Request',
            html: `
                <div class="mb-4">
                    <label for="reason" class="block text-sm font-medium text-gray-700 mb-2 text-left">
                        Please provide a reason for cancellation
                    </label>
                    <textarea id="reason" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        rows="4" placeholder="Enter your reason for cancellation..."></textarea>
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, cancel it!',
            cancelButtonText: 'No, keep it',
            preConfirm: () => {
                const reason = document.getElementById('reason').value;
                if (!reason) {
                    Swal.showValidationMessage('Please provide a cancellation reason');
                    return false;
                }
                return reason;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading modal
                Swal.fire({
                    title: 'Processing Cancellation',
                    html: `
                        <div class="text-center">
                            <div class="mb-4">
                                <i class="bx bx-loader-alt bx-spin text-4xl text-primary-600"></i>
                            </div>
                            <p class="text-gray-600">Please wait while your request is being cancelled...</p>
                        </div>
                    `,
                    allowOutsideClick: false,
                    showConfirmButton: false
                });

                // Create a form and submit it
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'cancel_request.php?id=' + requestId;
                
                const reasonInput = document.createElement('input');
                reasonInput.type = 'hidden';
                reasonInput.name = 'reason';
                reasonInput.value = result.value;
                
                form.appendChild(reasonInput);
                document.body.appendChild(form);
                form.submit();
            }
        });
    }
</script>
</body>
</html>

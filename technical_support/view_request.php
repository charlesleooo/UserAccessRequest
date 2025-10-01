<?php
session_start();
require_once '../config.php';

// Check if technical support is logged in
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'technical_support') {
    header('Location: ../admin/login.php');
    exit();
}

// Check if the user needs to enter the encryption code
if (
    !isset($_SESSION['requests_verified']) || !$_SESSION['requests_verified'] ||
    (time() - $_SESSION['requests_verified_time'] > 1800)
) { // Expire after 30 minutes
    header('Location: requests_auth.php');
    exit();
}

// Check if request ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: requests.php");
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
                 WHERE ar.id = :request_id";

    $stmt = $pdo->prepare($mainQuery);
    $stmt->execute([
        ':request_id' => $requestId
    ]);

    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        header("Location: requests.php");
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
} catch (PDOException $e) {
    error_log("Error fetching request details: " . $e->getMessage());
    header("Location: requests.php?error=db");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Request Details</title>
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
            background-image: url('../requestor/bg2.jpg');
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
    <div class="fixed inset-y-0 left-0 z-50 w-72 bg-white shadow-lg">
        <div class="flex flex-col h-full">
            <!-- Logo -->
            <div class="text-center">
                <img src="../logo.png" alt="Company Logo" class="mt-1 w-60 h-auto mx-auto">
            </div>

            <!-- Navigation Menu -->
            <nav class="flex-1 pt-6 pb-4 px-4 space-y-1 overflow-y-auto">
                <p class="px-4 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                    Main Menu
                </p>

                <a href="dashboard.php" class="flex items-center px-4 py-3 text-gray-700 rounded-xl hover:bg-gray-50">
                    <span class="flex items-center justify-center w-9 h-9 bg-gray-100 text-gray-600 rounded-lg">
                        <i class='bx bxs-dashboard text-xl'></i>
                    </span>
                    <span class="ml-3">Dashboard</span>
                </a>

                <a href="requests.php" class="flex items-center px-4 py-3 text-primary-600 bg-primary-50 rounded-xl">
                    <span class="flex items-center justify-center w-9 h-9 bg-primary-100 text-primary-600 rounded-lg">
                        <i class='bx bxs-message-square-detail text-xl'></i>
                    </span>
                    <span class="ml-3 font-medium">Requests</span>
                </a>

                <a href="review_history.php" class="flex items-center px-4 py-3 text-gray-700 rounded-xl hover:bg-gray-50">
                    <span class="flex items-center justify-center w-9 h-9 bg-gray-100 text-gray-600 rounded-lg">
                        <i class='bx bx-history text-xl'></i>
                    </span>
                    <span class="ml-3">Review History</span>
                </a>

                <a href="testing_setup.php" class="flex items-center px-4 py-3 text-gray-700 rounded-xl hover:bg-gray-50">
                    <span class="flex items-center justify-center w-9 h-9 bg-gray-100 text-gray-600 rounded-lg">
                        <i class='bx bx-test-tube text-xl'></i>
                    </span>
                    <span class="ml-3">Testing Setup</span>
                </a>

                <a href="settings.php" class="flex items-center px-4 py-3 text-gray-700 rounded-xl hover:bg-gray-50">
                    <span class="flex items-center justify-center w-9 h-9 bg-gray-100 text-gray-600 rounded-lg">
                        <i class='bx bx-cog text-xl'></i>
                    </span>
                    <span class="ml-3">Settings</span>
                </a>
            </nav>

            <!-- Logout Button -->
            <div class="p-4 border-t border-gray-100">
                <a href="../admin/logout.php" class="flex items-center px-4 py-3 text-red-600 bg-red-50 rounded-xl hover:bg-red-100">
                    <span class="flex items-center justify-center w-9 h-9 bg-red-100 text-red-600 rounded-lg">
                        <i class='bx bx-log-out text-xl'></i>
                    </span>
                    <span class="ml-3 font-medium">Logout</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="flex-1 ml-72 transition-all duration-300">
        <!-- Header -->
        <div class="bg-blue-900 border-b border-gray-200 sticky top-0 z-10 shadow-sm">
            <div class="flex justify-between items-center px-6 py-4">
                <div data-aos="fade-right" data-aos-duration="800">
                    <h2 class="text-4xl font-bold text-white">View Request Details</h2>
                    <p class="text-white text-lg mt-1">Request #<?php echo htmlspecialchars($request['access_request_number'] ?? 'N/A'); ?></p>
                </div>
                <div data-aos="fade-left" data-aos-duration="800" class="flex space-x-2">
                    <a href="requests.php" class="inline-flex items-center px-4 py-2 bg-white text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                        <i class='bx bx-arrow-back mr-2'></i> Back to Requests
                    </a>
                    <?php if ($request['status'] === 'pending_technical'): ?>
                        <button onclick="scrollToReviewSection('reviewForm')" class="px-4 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition-colors">
                            <i class='bx bx-edit mr-2'></i> Add Comments
                        </button>
                        <button onclick="handleRequest('decline')" class="px-4 py-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition-colors">
                            <i class='bx bx-x-circle mr-2'></i> Not Feasible
                        </button>
                        <button onclick="handleRequest('approve')" class="px-4 py-2 bg-green-50 text-green-600 rounded-lg hover:bg-green-100 transition-colors">
                            <i class='bx bx-check-circle mr-2'></i> Approve
                        </button>
                    <?php endif; ?>
                    <?php if ($request['status'] === 'pending_testing_review'): ?>
                        <button onclick="scrollToReviewSection('testingForm')" class="px-4 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition-colors">
                            <i class='bx bx-edit mr-2'></i> Add Testing Instructions
                        </button>
                        <button onclick="handleRequest('approve')" class="px-4 py-2 bg-green-50 text-green-600 rounded-lg hover:bg-green-100 transition-colors">
                            <i class='bx bx-refresh mr-2'></i> Send for Retest
                        </button>
                    <?php endif; ?>
                    <?php if ($request['status'] === 'pending_testing_setup'): ?>
                        <button onclick="scrollToReviewSection('testingForm')" class="px-4 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition-colors">
                            <i class='bx bx-edit mr-2'></i> Add Instructions
                        </button>
                        <button onclick="handleTesting()" class="px-4 py-2 bg-green-50 text-green-600 rounded-lg hover:bg-green-100 transition-colors">
                            <i class='bx bx-send mr-2'></i> Send to Requestor
                        </button>
                    <?php endif; ?>
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

            <!-- Access Details -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mt-6" data-aos="fade-up" data-aos-duration="800">
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

            <!-- Superior's Comments -->
            <?php if (!empty($request['superior_notes'])): ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mt-6" data-aos="fade-up" data-aos-duration="800" data-aos-delay="350">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                        <i class='bx bx-message-square-detail text-primary-500 text-xl mr-2'></i>
                        Superior's Comments
                    </h3>
                    <div class="bg-gray-50 p-4 rounded-lg text-gray-700">
                        <?php echo nl2br(htmlspecialchars($request['superior_notes'])); ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Help Desk Comments -->
            <?php if (!empty($request['help_desk_notes'])): ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mt-6" data-aos="fade-up" data-aos-duration="800" data-aos-delay="350">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                        <i class='bx bx-message-square-detail text-primary-500 text-xl mr-2'></i>
                        Help Desk Comments
                    </h3>
                    <div class="bg-gray-50 p-4 rounded-lg text-gray-700">
                        <?php echo nl2br(htmlspecialchars($request['help_desk_notes'])); ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Process Owner Comments -->
            <?php if (!empty($request['process_owner_notes'])): ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mt-6" data-aos="fade-up" data-aos-duration="800" data-aos-delay="350">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                        <i class='bx bx-message-square-detail text-primary-500 text-xl mr-2'></i>
                        Process Owner Comments
                    </h3>
                    <div class="bg-gray-50 p-4 rounded-lg text-gray-700">
                        <?php echo nl2br(htmlspecialchars($request['process_owner_notes'])); ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Actions -->
            <?php if ($request['status'] === 'pending_technical'): ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mt-6" data-aos="fade-up" data-aos-duration="800">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                        <i class='bx bx-check-circle text-primary-500 text-xl mr-2'></i>
                        Technical Review
                    </h3>
                    <div class="p-4">
                        <form id="reviewForm">
                            <input type="hidden" name="request_id" value="<?php echo $requestId; ?>">
                            <div class="mb-4">
                                <label for="review_notes" class="block text-sm font-medium text-gray-700 mb-2">Technical Review Notes</label>
                                <textarea id="review_notes" name="review_notes" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500" placeholder="Enter your technical review notes..."></textarea>
                            </div>
                            <div class="flex justify-end space-x-4">
                                <button type="button" onclick="handleRequest('decline')" class="px-4 py-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition-colors">
                                    <i class='bx bx-x-circle mr-2'></i> Not Feasible
                                </button>
                                <button type="button" onclick="handleRequest('approve')" class="px-4 py-2 bg-green-50 text-green-600 rounded-lg hover:bg-green-100 transition-colors">
                                    <i class='bx bx-check-circle mr-2'></i> Approve
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Testing Setup -->
            <?php if ($request['status'] === 'pending_testing_setup' || $request['status'] === 'pending_testing_review'): ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mt-6" data-aos="fade-up" data-aos-duration="800">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                        <i class='bx bx-test-tube text-primary-500 text-xl mr-2'></i>
                        <?php echo $request['status'] === 'pending_testing_review' ? 'Testing Failed - Review and Retest' : 'Testing Setup'; ?>
                    </h3>
                    
                    <?php if ($request['status'] === 'pending_testing_review' && !empty($request['testing_notes'])): ?>
                    <div class="mb-6 bg-red-50 rounded-lg p-4 border border-red-100">
                        <div class="flex items-start">
                            <div class="flex-shrink-0 pt-0.5">
                                <i class='bx bx-error-circle text-red-600 text-xl'></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800">Previous Testing Failed</h3>
                                <div class="mt-2 text-sm text-red-700">
                                    <strong>User's Testing Notes:</strong>
                                    <div class="mt-1 bg-white p-3 rounded border">
                                        <?php echo nl2br(htmlspecialchars($request['testing_notes'])); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="p-4">
                        <form id="testingForm">
                            <input type="hidden" name="request_id" value="<?php echo $requestId; ?>">
                            <div class="mb-4">
                                <label for="testing_instructions" class="block text-sm font-medium text-gray-700 mb-2">Testing Instructions</label>
                                <textarea id="testing_instructions" name="testing_instructions" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500" placeholder="Enter detailed instructions for the requestor to test the access..."></textarea>
                            </div>
                            <div class="flex justify-end">
                                <button type="button" onclick="handleTesting()" class="px-4 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition-colors">
                                    <i class='bx bx-send mr-2'></i> Send to Requestor
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>


    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize AOS animation library
            AOS.init();
        });

        function scrollToReviewSection(formId) {
            // Find the review section and scroll to it
            const reviewSection = document.getElementById(formId);
            if (reviewSection) {
                reviewSection.scrollIntoView({
                    behavior: 'smooth'
                });
                // Focus on the textarea
                const textareaId = formId === 'testingForm' ? 'testing_instructions' : 'review_notes';
                document.getElementById(textareaId).focus();
            }
        }

        function handleRequest(action) {
            const notes = document.getElementById('review_notes').value;
            const requestId = <?php echo $requestId; ?>;

            if (!notes) {
                Swal.fire({
                    title: 'Review Notes Required',
                    text: 'Please provide technical review notes before submitting your decision.',
                    icon: 'warning',
                    confirmButtonColor: '#0ea5e9'
                });
                scrollToReviewSection('reviewForm');
                return;
            }

            Swal.fire({
                title: action === 'approve' ? 'Approve Request?' : 'Mark as Not Feasible?',
                text: action === 'approve' ? 'This will indicate that the request is technically feasible.' : 'This will mark the request as technically not feasible and notify the requestor.',
                icon: action === 'approve' ? 'question' : 'warning',
                showCancelButton: true,
                confirmButtonColor: action === 'approve' ? '#10B981' : '#EF4444',
                cancelButtonColor: '#6B7280',
                confirmButtonText: action === 'approve' ? 'Yes, Approve' : 'Yes, Not Feasible',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading state
                    Swal.fire({
                        title: 'Processing...',
                        html: 'Please wait while we process your request.',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Submit the form via AJAX
                    fetch('../admin/process_request.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `request_id=${requestId}&action=${action}&review_notes=${encodeURIComponent(notes)}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    title: 'Success!',
                                    text: data.message,
                                    icon: 'success',
                                    confirmButtonColor: '#0ea5e9'
                                }).then(() => {
                                    window.location.href = 'requests.php';
                                });
                            } else {
                                Swal.fire({
                                    title: 'Error',
                                    text: data.message || 'An error occurred while processing your request.',
                                    icon: 'error',
                                    confirmButtonColor: '#0ea5e9'
                                });
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            Swal.fire({
                                title: 'Error',
                                text: 'An error occurred while processing your request.',
                                icon: 'error',
                                confirmButtonColor: '#0ea5e9'
                            });
                        });
                }
            });
        }

        function handleTesting() {
            const instructions = document.getElementById('testing_instructions').value;
            const requestId = <?php echo $requestId; ?>;

            if (!instructions) {
                Swal.fire({
                    title: 'Testing Instructions Required',
                    text: 'Please provide testing instructions before submitting.',
                    icon: 'warning',
                    confirmButtonColor: '#0ea5e9'
                });
                scrollToReviewSection('testingForm');
                return;
            }

            Swal.fire({
                title: 'Send Testing Instructions?',
                text: 'This will send the testing instructions to the requestor.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#6B7280',
                confirmButtonText: 'Yes, Send',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading state
                    Swal.fire({
                        title: 'Processing...',
                        html: 'Please wait while we process your request.',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Submit the form via AJAX
                    fetch('../admin/process_request.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `request_id=${requestId}&action=approve&review_notes=${encodeURIComponent(instructions)}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    title: 'Success!',
                                    text: data.message || 'Testing instructions have been sent to the requestor.',
                                    icon: 'success',
                                    confirmButtonColor: '#0ea5e9'
                                }).then(() => {
                                    window.location.href = 'requests.php';
                                });
                            } else {
                                Swal.fire({
                                    title: 'Error',
                                    text: data.message || 'An error occurred while processing your request.',
                                    icon: 'error',
                                    confirmButtonColor: '#0ea5e9'
                                });
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            Swal.fire({
                                title: 'Error',
                                text: 'An error occurred while processing your request.',
                                icon: 'error',
                                confirmButtonColor: '#0ea5e9'
                            });
                        });
                }
            });
        }
    </script>
</body>

</html>
<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['requestor_id'])) {
    header("Location: login.php");
    exit();
}

$requestorId = $_SESSION['requestor_id'];
$username = $_SESSION['username'] ?? 'User';

// Check if history ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: request_history.php");
    exit();
}

$historyId = intval($_GET['id']);

// Fetch the request history details
try {
    $query = "SELECT ah.*, admin.username as admin_username 
              FROM approval_history ah
              LEFT JOIN admin_users admin ON ah.admin_id = admin.id
              WHERE ah.history_id = :history_id AND ah.requestor_name = :requestor_name";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':history_id' => $historyId,
        ':requestor_name' => $username
    ]);
    
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        // Request not found or doesn't belong to the user
        header("Location: request_history.php");
        exit();
    }
    
} catch (PDOException $e) {
    error_log("Error fetching request history details: " . $e->getMessage());
    header("Location: request_history.php?error=db");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Request History</title>
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
                <h2 class="text-2xl font-bold text-gray-800">Request History Details</h2>
                <p class="text-gray-600 text-lg mt-1">Request #<?php echo htmlspecialchars($request['access_request_number'] ?? 'N/A'); ?></p>
            </div>
            <div data-aos="fade-left" data-aos-duration="800" class="flex space-x-2">
                <a href="request_history.php" class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                    <i class='bx bx-arrow-back mr-2'></i> Back to History
                </a>
            </div>
        </div>
    </div>

    <div class="p-6">
        <!-- Request Details -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
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
                        $status = strtolower($request['action'] ?? '');
                        
                        if ($status === 'approved') {
                            $statusClass = 'status-approved';
                            $bgClass = 'bg-green-100';
                        } elseif ($status === 'rejected') {
                            $statusClass = 'status-rejected';
                            $bgClass = 'bg-red-100';
                        }
                        ?>
                        <div class="flex justify-center items-center <?php echo $bgClass; ?> rounded-lg px-2 py-1">
                            <span class="status-badge <?php echo $statusClass; ?>">
                                <?php echo ucfirst($status); ?>
                            </span>
                        </div>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Processed:</span>
                        <span class="font-medium text-gray-900">
                            <?php 
                            $date = new DateTime($request['created_at'] ?? 'now');
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
                        <span class="text-gray-600">Email:</span>
                        <span class="font-medium text-gray-900"><?php echo htmlspecialchars($request['email'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Business Unit:</span>
                        <span class="font-medium text-gray-900"><?php echo htmlspecialchars($request['business_unit'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Department:</span>
                        <span class="font-medium text-gray-900"><?php echo htmlspecialchars($request['department'] ?? 'N/A'); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Admin Info -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6" data-aos="fade-up" data-aos-duration="800" data-aos-delay="200">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                    <i class='bx bx-shield-quarter text-primary-500 text-xl mr-2'></i>
                    Approval Information
                </h3>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Reviewed By:</span>
                        <span class="font-medium text-gray-900"><?php echo htmlspecialchars($request['admin_username'] ?? 'System'); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Decision:</span>
                        <span class="font-medium text-gray-900"><?php echo ucfirst($request['action'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Date:</span>
                        <span class="font-medium text-gray-900">
                            <?php 
                            $date = new DateTime($request['created_at'] ?? 'now');
                            echo $date->format('M d, Y'); 
                            ?>
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Time:</span>
                        <span class="font-medium text-gray-900">
                            <?php 
                            $date = new DateTime($request['created_at'] ?? 'now');
                            echo $date->format('h:i A'); 
                            ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Access Request Details -->
        <div class="grid grid-cols-1 md:grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Access Type Details -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6" data-aos="fade-up" data-aos-duration="800" data-aos-delay="300">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                    <i class='bx bx-key text-primary-500 text-xl mr-2'></i>
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
                        <span class="font-medium text-gray-900"><?php echo htmlspecialchars($request['other_system_type']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($request['role_access_type'])): ?>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Role/Access:</span>
                        <span class="font-medium text-gray-900"><?php echo htmlspecialchars($request['role_access_type']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Duration:</span>
                        <span class="font-medium text-gray-900">
                            <?php 
                            if ($request['duration_type'] === 'permanent') {
                                echo 'Permanent';
                            } else {
                                echo 'Temporary';
                            }
                            ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Comments -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6" data-aos="fade-up" data-aos-duration="800" data-aos-delay="400">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                    <i class='bx bx-message-detail text-primary-500 text-xl mr-2'></i>
                    Additional Information
                </h3>
                
                <div class="mb-4">
                    <h4 class="text-sm font-medium text-gray-700 mb-2">Justification:</h4>
                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-200 text-gray-800">
                        <?php echo nl2br(htmlspecialchars($request['justification'] ?? 'No justification provided')); ?>
                    </div>
                </div>
                
                <?php if (!empty($request['comments'])): ?>
                <div>
                    <h4 class="text-sm font-medium text-gray-700 mb-2">Reviewer Comments:</h4>
                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-200 text-gray-800">
                        <?php echo nl2br(htmlspecialchars($request['comments'])); ?>
                    </div>
                </div>
                <?php else: ?>
                <div>
                    <h4 class="text-sm font-medium text-gray-700 mb-2">Reviewer Comments:</h4>
                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-200 text-gray-500 italic">
                        No comments provided
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="flex justify-end space-x-4 mt-6" data-aos="fade-up" data-aos-duration="800" data-aos-delay="500">
            <a href="request_history.php" class="px-6 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                Back to History
            </a>
            <a href="tcpdf_print_record.php?id=<?php echo $historyId; ?>" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                <i class='bx bx-file-pdf mr-2'></i> Download PDF
            </a>
        </div>
    </div>
</div>



<script>
    document.addEventListener('DOMContentLoaded', function() {
        AOS.init();
        

    });
</script>
</body>
</html> 

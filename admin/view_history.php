<?php
session_start();
require_once '../config.php';

// Ensure admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Validate history ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: approval_history.php');
    exit();
}

$historyId = intval($_GET['id']);

// Fetch history record details with all reviewer information and original request details
try {
    $sql = "SELECT h.*, 
            -- Admin Info
            a.username AS admin_username, 
            ea.employee_name AS admin_name,
            -- Superior Info
            (SELECT e.employee_name FROM employees e 
             JOIN admin_users au ON e.employee_id = au.username 
             WHERE au.id = h.superior_id) as superior_reviewer_name,
            -- Technical Support Info
            (SELECT e.employee_name FROM employees e 
             JOIN admin_users au ON e.employee_id = au.username 
             WHERE au.id = h.technical_id) as technical_reviewer_name,
            -- Process Owner Info
            (SELECT e.employee_name FROM employees e 
             JOIN admin_users au ON e.employee_id = au.username 
             WHERE au.id = h.process_owner_id) as process_owner_reviewer_name,
            -- Help Desk Info
            (SELECT e.employee_name FROM employees e 
             JOIN admin_users au ON e.employee_id = au.username 
             WHERE au.id = h.help_desk_id) as help_desk_reviewer_name,
            -- Original Request Details
            COALESCE(ir.date_needed, gr.date_needed) as date_needed,
            COALESCE(ir.justification, gr.justification) as justification,
            COALESCE(ir.start_date, gr.start_date) as start_date,
            COALESCE(ir.end_date, gr.end_date) as end_date,
            COALESCE(ir.access_duration, gr.access_duration) as duration_type,
            COALESCE(ir.application_system, gr.application_system) as application_system
            FROM approval_history h
            LEFT JOIN admin_users a ON h.admin_id = a.id
            LEFT JOIN employees ea ON a.username = ea.employee_id
            LEFT JOIN individual_requests ir ON h.access_request_number = ir.access_request_number
            LEFT JOIN group_requests gr ON h.access_request_number = gr.access_request_number
            WHERE h.history_id = :history_id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':history_id' => $historyId]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        header('Location: approval_history.php');
        exit();
    }
} catch (PDOException $e) {
    error_log('Error fetching history record: ' . $e->getMessage());
    header('Location: approval_history.php?error=db');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View History Details</title>
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
                            950: '#082f49'
                        }
                    },
                }
            }
        }
    </script>
    <style>
        body {
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50 font-sans">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 ml-72 transition-all duration-300">
            <!-- Header -->
            <div class="bg-blue-900 border-b border-gray-200 sticky top-0 z-10 shadow-sm">
                <div class="flex justify-between items-center px-6 py-4">
                    <div data-aos="fade-right" data-aos-duration="800">
                        <h2 class="text-4xl font-bold text-white">View History Details</h2>
                        <p class="text-white text-lg mt-1">Request #<?php echo htmlspecialchars($record['access_request_number']); ?></p>
                    </div>
                    <div data-aos="fade-left" data-aos-duration="800" class="flex space-x-2">
                        <a href="approval_history.php" class="inline-flex items-center px-4 py-2 bg-white text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                            <i class='bx bx-arrow-back mr-2'></i> Back to History
                        </a>
                        <a href="tcpdf_print_record.php?id=<?php echo $record['history_id']; ?>" target="_blank" class="inline-flex items-center px-4 py-2 bg-white text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                            <i class='bx bx-printer mr-2'></i> Print
                        </a>
                    </div>
                </div>
            </div>

            <!-- Requestor Information -->
            <div class="p-6">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6" data-aos="fade-up" data-aos-duration="800">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-1 border-b border-gray-100 flex items-center">
                        <i class='bx bx-info-circle text-primary-500 text-xl mr-2'></i>
                        Overview
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <p class="text-sm text-gray-500">UAR Ref No.</p>
                            <p class="font-medium text-gray-900"><?php echo htmlspecialchars($record['access_request_number']); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Action</p>
                            <span class="px-3 py-1 text-xs font-semibold rounded-full <?php echo $record['action'] === 'approved' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                <?php echo strtoupper(htmlspecialchars($record['action'])); ?>
                            </span>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Review Date</p>
                            <p class="font-medium text-gray-900"><?php echo date('M d, Y H:i', strtotime($record['created_at'])); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Reviewed By</p>
                            <p class="font-medium text-gray-900"><?php echo htmlspecialchars($record['admin_name'] ?? 'Administrator'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-6 pt-0">
                <!-- Requestor Information -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6" data-aos="fade-up" data-aos-duration="800">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                        <i class='bx bx-user text-primary-500 text-xl mr-2'></i>
                        Requestor Information
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <div class="space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Name:</span>
                                    <span class="font-medium text-gray-900"><?php echo htmlspecialchars($record['requestor_name']); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Business Unit:</span>
                                    <span class="font-medium text-gray-900"><?php echo htmlspecialchars($record['business_unit']); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Department:</span>
                                    <span class="font-medium text-gray-900"><?php echo htmlspecialchars($record['department']); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <div class="space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Email:</span>
                                    <span class="font-medium text-gray-900"><?php echo htmlspecialchars($record['email'] ?? '-'); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Contact:</span>
                                    <span class="font-medium text-gray-900"><?php echo htmlspecialchars($record['contact_number'] ?? 'Not provided'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Access Details -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6" data-aos="fade-up" data-aos-duration="800">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                        <i class='bx bx-lock-open text-primary-500 text-xl mr-2'></i>
                        Access Details
                    </h3>
                    <div class="space-y-4">
                        <div class="bg-white p-4 rounded-lg mb-4 shadow-sm">
                            <h4 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100">
                                User Details #1
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h4 class="text-sm font-medium text-gray-600 mb-2">Basic Information</h4>
                                    <div class="space-y-3">
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">User Name:</span>
                                            <span class="font-medium text-gray-900">
                                                <?php echo htmlspecialchars($record['requestor_name'] ?? 'N/A'); ?>
                                            </span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Access Type:</span>
                                            <span class="font-medium text-gray-900"><?php echo htmlspecialchars($record['access_type'] ?? 'N/A'); ?></span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">System Type:</span>
                                            <span class="font-medium text-gray-900"><?php echo htmlspecialchars($record['system_type'] ?? 'N/A'); ?></span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Application System:</span>
                                            <span class="font-medium text-gray-900"><?php echo htmlspecialchars($record['application_system'] ?? 'N/A'); ?></span>
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
                                                if (isset($record['duration_type'])) {
                                                    echo $record['duration_type'] === 'permanent' ? 'Permanent' : 'Temporary';
                                                } else {
                                                    echo 'N/A';
                                                }
                                                ?>
                                            </span>
                                        </div>
                                        <?php if (isset($record['duration_type']) && $record['duration_type'] !== 'permanent'): ?>
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">Start Date:</span>
                                                <span class="font-medium text-gray-900">
                                                    <?php
                                                    if (!empty($record['start_date'])) {
                                                        $startDate = new DateTime($record['start_date']);
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
                                                    if (!empty($record['end_date'])) {
                                                        $endDate = new DateTime($record['end_date']);
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
                                                if (!empty($record['date_needed'])) {
                                                    $dateNeeded = new DateTime($record['date_needed']);
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
                                    <?php echo nl2br(htmlspecialchars($record['justification'] ?? 'No justification provided.')); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Approval Timeline Section -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6" data-aos="fade-up" data-aos-duration="800">
                    <h3 class="text-lg font-semibold text-gray-800 mb-6 pb-2 border-b border-gray-100 flex items-center">
                        <i class='bx bx-history text-primary-500 text-xl mr-2'></i>
                        Approval Timeline
                    </h3>            
                    
                    <div class="relative">
                        <!-- Vertical Line -->
                        <div class="absolute left-5 top-0 h-[calc(98%-4rem)] w-0.5 bg-gray-200"></div>
                        
                        <div class="space-y-8">
                            <!-- Superior Review -->
                            <?php if (!empty($record['superior_id']) || !empty($record['superior_notes'])): ?>
                            <div class="relative flex items-start group">
                                <div class="absolute left-0 w-10 h-10 flex items-center justify-center z-10">
                                    <div class="w-10 h-10 rounded-full bg-green-500 flex items-center justify-center shadow-sm transform transition-transform group-hover:scale-110">
                                        <i class='bx bxs-user-check text-xl text-white'></i>
                                    </div>
                                </div>
                                <div class="ml-16 bg-white rounded-lg border border-gray-100 shadow-sm hover:shadow-md transition-shadow p-4 w-full">
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-2">
                                        <h4 class="text-base font-semibold text-gray-900">
                                            Superior Review
                                            <?php if (!empty($record['superior_reviewer_name'])): ?>
                                                <span class="text-sm font-normal text-gray-600">
                                                    (<?php echo htmlspecialchars($record['superior_reviewer_name']); ?>)
                                                </span>
                                            <?php endif; ?>
                                        </h4>
                                        <?php if (!empty($record['superior_review_date'])): ?>
                                            <span class="text-sm text-gray-600 font-medium bg-gray-50 px-3 py-1 rounded-full">
                                                <?php echo date('M j, Y h:i A', strtotime($record['superior_review_date'])); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mt-2 bg-gray-50 rounded-lg p-3 text-sm text-gray-600">
                                        <?php echo !empty($record['superior_notes']) ? nl2br(htmlspecialchars($record['superior_notes'])) : '<span class="text-gray-500 italic">No notes provided</span>'; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Help Desk Review -->
                            <?php if (!empty($record['help_desk_id']) || !empty($record['help_desk_notes'])): ?>
                            <div class="relative flex items-start group">
                                <div class="absolute left-0 w-10 h-10 flex items-center justify-center z-10">
                                    <div class="w-10 h-10 rounded-full bg-green-500 flex items-center justify-center shadow-sm transform transition-transform group-hover:scale-110">
                                        <i class='bx bx-support text-xl text-white'></i>
                                    </div>
                                </div>
                                <div class="ml-16 bg-white rounded-lg border border-gray-100 shadow-sm hover:shadow-md transition-shadow p-4 w-full">
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-2">
                                        <h4 class="text-base font-semibold text-gray-900">
                                            Help Desk Review
                                            <?php if (!empty($record['help_desk_reviewer_name'])): ?>
                                                <span class="text-sm font-normal text-gray-600">
                                                    (<?php echo htmlspecialchars($record['help_desk_reviewer_name']); ?>)
                                                </span>
                                            <?php endif; ?>
                                        </h4>
                                        <?php if (!empty($record['help_desk_review_date'])): ?>
                                            <span class="text-sm text-gray-600 font-medium bg-gray-50 px-3 py-1 rounded-full">
                                                <?php echo date('M j, Y h:i A', strtotime($record['help_desk_review_date'])); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mt-2 bg-gray-50 rounded-lg p-3 text-sm text-gray-600">
                                        <?php echo !empty($record['help_desk_notes']) ? nl2br(htmlspecialchars($record['help_desk_notes'])) : '<span class="text-gray-500 italic">No notes provided</span>'; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Technical Review -->
                            <?php if (!empty($record['technical_id']) || !empty($record['technical_notes'])): ?>
                            <div class="relative flex items-start group">
                                <div class="absolute left-0 w-10 h-10 flex items-center justify-center z-10">
                                    <div class="w-10 h-10 rounded-full bg-green-500 flex items-center justify-center shadow-sm transform transition-transform group-hover:scale-110">
                                        <i class='bx bx-code-alt text-xl text-white'></i>
                                    </div>
                                </div>
                                <div class="ml-16 bg-white rounded-lg border border-gray-100 shadow-sm hover:shadow-md transition-shadow p-4 w-full">
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-2">
                                        <h4 class="text-base font-semibold text-gray-900">
                                            Technical Support Review
                                            <?php if (!empty($record['technical_reviewer_name'])): ?>
                                                <span class="text-sm font-normal text-gray-600">
                                                    (<?php echo htmlspecialchars($record['technical_reviewer_name']); ?>)
                                                </span>
                                            <?php endif; ?>
                                        </h4>
                                        <?php if (!empty($record['technical_review_date'])): ?>
                                            <span class="text-sm text-gray-600 font-medium bg-gray-50 px-3 py-1 rounded-full">
                                                <?php echo date('M j, Y h:i A', strtotime($record['technical_review_date'])); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mt-2 bg-gray-50 rounded-lg p-3 text-sm text-gray-600">
                                        <?php echo !empty($record['technical_notes']) ? nl2br(htmlspecialchars($record['technical_notes'])) : '<span class="text-gray-500 italic">No notes provided</span>'; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Process Owner Review -->
                            <?php if (!empty($record['process_owner_id']) || !empty($record['process_owner_notes'])): ?>
                            <div class="relative flex items-start group">
                                <div class="absolute left-0 w-10 h-10 flex items-center justify-center z-10">
                                    <div class="w-10 h-10 rounded-full bg-green-500 flex items-center justify-center shadow-sm transform transition-transform group-hover:scale-110">
                                        <i class='bx bx-user-voice text-xl text-white'></i>
                                    </div>
                                </div>
                                <div class="ml-16 bg-white rounded-lg border border-gray-100 shadow-sm hover:shadow-md transition-shadow p-4 w-full">
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-2">
                                        <h4 class="text-base font-semibold text-gray-900">
                                            Process Owner Review
                                            <?php if (!empty($record['process_owner_reviewer_name'])): ?>
                                                <span class="text-sm font-normal text-gray-600">
                                                    (<?php echo htmlspecialchars($record['process_owner_reviewer_name']); ?>)
                                                </span>
                                            <?php endif; ?>
                                        </h4>
                                        <?php if (!empty($record['process_owner_review_date'])): ?>
                                            <span class="text-sm text-gray-600 font-medium bg-gray-50 px-3 py-1 rounded-full">
                                                <?php echo date('M j, Y h:i A', strtotime($record['process_owner_review_date'])); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mt-2 bg-gray-50 rounded-lg p-3 text-sm text-gray-600">
                                        <?php echo !empty($record['process_owner_notes']) ? nl2br(htmlspecialchars($record['process_owner_notes'])) : '<span class="text-gray-500 italic">No notes provided</span>'; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Admin Review -->
                            <?php if (!empty($record['admin_id']) || !empty($record['comments'])): ?>
                            <div class="relative flex items-start group">
                                <div class="absolute left-0 w-10 h-10 flex items-center justify-center z-10">
                                    <div class="w-10 h-10 rounded-full bg-green-500 flex items-center justify-center shadow-sm transform transition-transform group-hover:scale-110">
                                        <i class='bx bx-shield-quarter text-xl text-white'></i>
                                    </div>
                                </div>
                                <div class="ml-16 bg-white rounded-lg border border-gray-100 shadow-sm hover:shadow-md transition-shadow p-4 w-full">
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-2">
                                        <h4 class="text-base font-semibold text-gray-900">
                                            Admin Review
                                            <?php if (!empty($record['admin_name'])): ?>
                                                <span class="text-sm font-normal text-gray-600">
                                                    (<?php echo htmlspecialchars($record['admin_name']); ?>)
                                                </span>
                                            <?php endif; ?>
                                        </h4>
                                        <?php if (!empty($record['created_at'])): ?>
                                            <span class="text-sm text-gray-600 font-medium bg-gray-50 px-3 py-1 rounded-full">
                                                <?php echo date('M j, Y h:i A', strtotime($record['created_at'])); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mt-2 bg-gray-50 rounded-lg p-3 text-sm text-gray-600">
                                        <?php echo !empty($record['comments']) ? nl2br(htmlspecialchars($record['comments'])) : '<span class="text-gray-500 italic">No notes provided</span>'; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Review Notes -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6" data-aos="fade-up" data-aos-duration="800">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                        <i class='bx bx-message-square-detail text-primary-500 text-xl mr-2'></i>
                        Review Notes
                    </h3>
                    <div class="bg-gray-50 p-4 rounded-lg text-gray-700 break-words overflow-auto min-h-[100px] max-h-[300px]">
                        <?php echo nl2br(htmlspecialchars(!empty($record['comments']) ? $record['comments'] : 'No review notes provided.')); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize AOS animation library
            AOS.init();
        });
    </script>
</body>
</html>



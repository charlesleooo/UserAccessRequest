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
    header("Location: request_history.php");
    exit();
}

$requestId = intval($_GET['id']);

// Fetch the request details
try {
    // Determine if this is a pending request or completed request
    $isPending = true;
    $request = null;
    $requestDetails = [];
    $approvalDetails = null;

    // First try to find it in pending requests
    $pendingQuery = "SELECT ar.*, e.employee_name as requestor_name
                     FROM uar.access_requests ar
                     LEFT JOIN uar.employees e ON ar.employee_id = e.employee_id
                     WHERE ar.id = :request_id AND ar.employee_id = :employee_id";

    $stmt = $pdo->prepare($pendingQuery);
    $stmt->execute([
        ':request_id' => $requestId,
        ':employee_id' => $requestorId
    ]);

    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($request) {
        // It's a pending request
        $isPending = true;

        // Check if it's an individual or group request
        $checkQuery = "SELECT COUNT(*) as count FROM uar.individual_requests WHERE access_request_number = :access_request_number";
        $stmt = $pdo->prepare($checkQuery);
        $stmt->execute([':access_request_number' => $request['access_request_number']]);
        $isIndividual = $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;

        $requestTable = $isIndividual ? 'uar.individual_requests' : 'uar.group_requests';

        // Get request details
        $detailsQuery = "SELECT r.username, r.application_system, r.access_type as role_access_type,
                                r.access_duration as duration_type, r.start_date, r.end_date,
                                r.date_needed, r.justification
                         FROM {$requestTable} r
                         WHERE r.access_request_number = :access_request_number
                         ORDER BY r.username";

        $stmt = $pdo->prepare($detailsQuery);
        $stmt->execute([':access_request_number' => $request['access_request_number']]);
        $requestDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Try to find it in approval history
        $historyQuery = "SELECT ah.*, au.username as admin_username
                         FROM uar.approval_history ah
                         LEFT JOIN uar.admin_users au ON ah.admin_id = au.id
                         WHERE ah.history_id = :request_id";

        $stmt = $pdo->prepare($historyQuery);
        $stmt->execute([':request_id' => $requestId]);
        $approvalDetails = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($approvalDetails) {
            // Check if this request belongs to the current user
            if ($approvalDetails['employee_id'] !== $requestorId) {
                header("Location: request_history.php");
                exit();
            }

            // It's a completed request
            $isPending = false;
            $request = $approvalDetails; // Use approval history data

            // For completed requests, the approval_history already contains most of the data we need
            // We just need to get the request details from individual or group requests
            $checkQuery = "SELECT COUNT(*) as count FROM uar.individual_requests WHERE access_request_number = :access_request_number";
            $stmt = $pdo->prepare($checkQuery);
            $stmt->execute([':access_request_number' => $approvalDetails['access_request_number']]);
            $isIndividual = $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;

            $requestTable = $isIndividual ? 'uar.individual_requests' : 'uar.group_requests';

            // Get request details
            $detailsQuery = "SELECT r.username, r.application_system, r.access_type as role_access_type,
                                    r.access_duration as duration_type, r.start_date, r.end_date,
                                    r.date_needed, r.justification
                             FROM {$requestTable} r
                             WHERE r.access_request_number = :access_request_number
                             ORDER BY r.username";

            $stmt = $pdo->prepare($detailsQuery);
            $stmt->execute([':access_request_number' => $approvalDetails['access_request_number']]);
            $requestDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Request not found
            header("Location: request_history.php");
            exit();
        }
    }

    // Build rejection details only (Approval Flow removed)
    $approvalFlow = [];
    $rejectionInfo = null;
    $accessRequestNumber = $request['access_request_number'] ?? null;
    if ($accessRequestNumber) {
        // Always try to get live review dates and reviewer ids for consistent flow
        $liveStmt = $pdo->prepare("SELECT TOP 1 superior_id, superior_review_date, superior_notes,
                                          help_desk_id, help_desk_review_date, help_desk_notes,
                                          technical_id, technical_review_date, technical_notes,
                                          process_owner_id, process_owner_review_date, process_owner_notes,
                                          admin_id, admin_review_date, admin_notes,
                                          status
                                   FROM uar.access_requests WHERE access_request_number = :arn");
        $liveStmt->execute([':arn' => $accessRequestNumber]);
        $live = $liveStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // Determine current status for pending
        $statusVal = $isPending ? strtolower($live['status'] ?? ($request['status'] ?? '')) : strtolower($request['action'] ?? '');

        // Rejection details if rejected
        if ($statusVal === 'rejected') {
            try {
                $rejStmt = $pdo->prepare("SELECT TOP 1 * FROM uar.approval_history WHERE access_request_number = :arn AND action = 'rejected' ORDER BY created_at DESC");
                $rejStmt->execute([':arn' => $accessRequestNumber]);
                if ($rej = $rejStmt->fetch(PDO::FETCH_ASSOC)) {
                    $actorId = $rej['admin_id'] ?? $rej['technical_id'] ?? $rej['process_owner_id'] ?? $rej['help_desk_id'] ?? $rej['superior_id'] ?? null;
                    $actor = null;
                    if ($actorId) {
                        $actorStmt = $pdo->prepare("SELECT e.employee_name, e.role FROM uar.admin_users a INNER JOIN uar.employees e ON a.username = e.employee_id WHERE a.id = :id");
                        $actorStmt->execute([':id' => $actorId]);
                        $actor = $actorStmt->fetch(PDO::FETCH_ASSOC);
                    }
                    $rejectionInfo = [
                        'name' => $actor['employee_name'] ?? 'Unknown Reviewer',
                        'role' => $actor['role'] ?? null,
                        'notes' => $rej['comments'] ?? ($rej['help_desk_notes'] ?? $rej['admin_notes'] ?? $rej['technical_notes'] ?? $rej['process_owner_notes'] ?? $rej['superior_notes'] ?? ''),
                        'date' => $rej['created_at'] ?? null,
                        'actor_id' => $actorId,
                    ];
                }
            } catch (PDOException $e) { /* ignore */
            }
        }

        // Approval Flow computation intentionally removed
    }
} catch (PDOException $e) {
    error_log("Error fetching request details: " . $e->getMessage());
    header("Location: request_history.php?error=db");
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

        /* Hide scrollbars helper */
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }

        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
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

        .status-cancelled {
            @apply bg-gray-600 text-white border-2 border-gray-700;
        }
    </style>
</head>

<body class="bg-gray-50 font-sans">

    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="flex-1 ml-72 transition-all duration-300">
        <!-- Header -->
        <div class="bg-white border-b border-gray-200 sticky top-0 z-10 shadow-sm">
            <div class="flex justify-between items-center px-6 py-4">
                <div data-aos="fade-right" data-aos-duration="800">
                    <h2 class="text-2xl font-bold text-gray-800">View Request Details</h2>
                    <div class="flex items-center gap-3 mt-1">
                        <p class="text-gray-600 text-lg">Request #<?php echo htmlspecialchars($request['access_request_number'] ?? 'N/A'); ?></p>
                        <?php
                        // Determine status and display appropriate badge
                        $currentStatus = $isPending ? strtolower($request['status']) : strtolower($request['action'] ?? 'unknown');
                        $statusClass = '';
                        $statusText = '';

                        switch ($currentStatus) {
                            case 'pending_superior':
                                $statusClass = 'status-pending';
                                $statusText = 'Pending Superior Review';
                                break;
                            case 'pending_technical':
                            case 'pending_technical_support':
                                $statusClass = 'status-pending';
                                $statusText = 'Pending Technical Review';
                                break;
                            case 'pending_process_owner':
                                $statusClass = 'status-pending';
                                $statusText = 'Pending Process Owner Review';
                                break;
                            case 'pending_admin':
                                $statusClass = 'status-pending';
                                $statusText = 'Pending Admin Review';
                                break;
                            case 'pending_testing':
                            case 'pending_testing_setup':
                            case 'pending_testing_review':
                                $statusClass = 'status-pending';
                                $statusText = 'Pending Testing';
                                break;
                            case 'pending_help_desk':
                                $statusClass = 'status-pending';
                                $statusText = 'Pending Help Desk Review';
                                break;
                            case 'pending':
                                $statusClass = 'status-pending';
                                $statusText = 'Pending';
                                break;
                            case 'approved':
                                $statusClass = 'status-approved';
                                $statusText = 'Approved';
                                break;
                            case 'rejected':
                                $statusClass = 'status-rejected';
                                $statusText = 'Rejected';
                                break;
                            case 'cancelled':
                                $statusClass = 'status-cancelled';
                                $statusText = 'Cancelled';
                                break;
                            default:
                                $statusClass = 'status-pending';
                                $statusText = ucfirst(str_replace('_', ' ', $currentStatus));
                        }
                        ?>
                        <span class="status-badge <?php echo $statusClass; ?>">
                            <?php echo $statusText; ?>
                        </span>
                    </div>
                </div>
                <div data-aos="fade-left" data-aos-duration="800" class="flex space-x-2">
                    <a href="request_history.php" class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                        <i class='bx bx-arrow-back mr-2'></i> Back to Requests
                    </a>
                </div>
            </div>
        </div>

        <!-- Rejection Details -->
        <div class="p-6">
            <?php if (!empty($rejectionInfo)): ?>
                <div class="bg-white rounded-xl shadow-sm border border-red-200 p-6 mb-6" data-aos="fade-up" data-aos-duration="800">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                        <i class='bx bx-block text-red-600 text-xl mr-2'></i>
                        Rejection Details
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-red-50 p-4 rounded-lg">
                            <div class="space-y-2">
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-700">Rejected By:</span>
                                    <span class="font-medium text-red-700"><?php echo htmlspecialchars($rejectionInfo['name']); ?></span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-700">Rejected On:</span>
                                    <span class="font-medium text-gray-900"><?php echo !empty($rejectionInfo['date']) ? date('M d, Y H:i', strtotime($rejectionInfo['date'])) : 'N/A'; ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="bg-red-50 p-4 rounded-lg">
                            <span class="text-gray-700 block mb-2">Rejection Notes:</span>
                            <div class="text-gray-800"><?php echo $rejectionInfo['notes'] !== '' ? nl2br(htmlspecialchars($rejectionInfo['notes'])) : '<span class="text-gray-500">No notes provided.</span>'; ?></div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
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
                                <span class="font-medium text-gray-900"><?php echo htmlspecialchars($request['employee_email'] ?? $request['email'] ?? 'N/A'); ?></span>
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
                                    $dateField = $request['request_date'] ?? $request['created_at'] ?? $request['submission_date'];
                                    if (!empty($dateField)) {
                                        $requestDate = new DateTime($dateField);
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
                    <?php
                    // Debug information (remove this in production)
                    if (isset($_GET['debug'])) {
                        echo "<div class='bg-yellow-100 p-4 rounded-lg mb-4'>";
                        echo "<h4 class='font-bold'>Debug Info:</h4>";
                        echo "<p><strong>Request ID:</strong> " . $requestId . "</p>";
                        echo "<p><strong>Is Pending:</strong> " . ($isPending ? 'Yes' : 'No') . "</p>";
                        echo "<p><strong>Access Request Number:</strong> " . ($request['access_request_number'] ?? 'N/A') . "</p>";
                        echo "<p><strong>Request Details Count:</strong> " . count($requestDetails) . "</p>";
                        echo "<p><strong>Requestor ID:</strong> " . $requestorId . "</p>";
                        echo "<p><strong>Employee ID from Request:</strong> " . ($request['employee_id'] ?? 'N/A') . "</p>";
                        if (!empty($requestDetails)) {
                            echo "<p><strong>First Request Detail:</strong> " . json_encode($requestDetails[0]) . "</p>";
                        }
                        echo "</div>";
                    }
                    ?>
                    <?php if (!empty($requestDetails)): ?>
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
                    <?php else: ?>
                        <div class="bg-gray-50 p-6 rounded-lg text-center">
                            <i class='bx bx-info-circle text-gray-400 text-4xl mb-3'></i>
                            <p class="text-gray-600">No access details found for this request.</p>
                        </div>
                    <?php endif; ?>
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

            <!-- Approval Details (for completed requests) -->
            <?php if (!$isPending && $approvalDetails): ?>
                <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class='bx bx-info-circle text-blue-400 text-xl'></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-blue-800">
                                Request <?php echo ucfirst(strtolower($approvalDetails['action'])); ?>
                            </h3>
                            <div class="mt-2 text-sm text-blue-700">
                                <?php if ($approvalDetails['admin_username']): ?>
                                    <p><strong>Processed by:</strong> <?php echo htmlspecialchars($approvalDetails['admin_username']); ?></p>
                                <?php endif; ?>
                                <?php if ($approvalDetails['created_at']): ?>
                                    <p><strong>Processed on:</strong> <?php
                                                                        $processedDate = new DateTime($approvalDetails['created_at']);
                                                                        echo $processedDate->format('M d, Y \a\t g:i A');
                                                                        ?></p>
                                <?php endif; ?>
                                <?php if ($approvalDetails['justification']): ?>
                                    <p><strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($approvalDetails['justification'])); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Actions -->
            <div class="flex justify-end items-center" data-aos="fade-up" data-aos-duration="800" data-aos-delay="500">
                <?php
                // Show different actions based on request status
                if ($isPending) {
                    // For pending requests, show cancel button
                    $currentStatus = strtolower($request['status']);
                    if (
                        $currentStatus === 'pending' ||
                        strpos($currentStatus, 'pending') === 0
                    ) {
                ?>
                        <button onclick="cancelRequest(<?php echo $request['id']; ?>)"
                            class="inline-flex items-center px-4 py-2 bg-red-600 text-white rounded-lg mr-5 hover:bg-red-700 transition-colors">
                            <i class='bx bx-x mr-2'></i> Cancel Request
                        </button>
                    <?php
                    }
                } else {
                    // For completed requests, show status-specific information
                    $action = strtolower($request['action'] ?? 'unknown');
                    if ($action === 'approved') {
                    ?>
                        <div class="inline-flex items-center px-4 py-2 bg-green-100 text-green-800 rounded-lg">
                            <i class='bx bx-check-circle mr-2'></i> Request Approved
                        </div>
                    <?php
                    } elseif ($action === 'rejected') {
                    ?>
                        <div class="inline-flex items-center px-4 py-2 bg-red-100 text-red-800 rounded-lg">
                            <i class='bx bx-x-circle mr-2'></i> Request Rejected
                        </div>
                    <?php
                    } elseif ($action === 'cancelled') {
                    ?>
                        <div class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-800 rounded-lg">
                            <i class='bx bx-x mr-2'></i> Request Cancelled
                        </div>
                <?php
                    }
                }
                ?>
            </div>
        </div>
        <!-- Approval Timeline removed per latest requirements -->
        <!-- Add testing status section if applicable -->
        <?php if (!empty($request['status']) && $request['status'] === 'pending_testing'): ?>
            <div class="p-6">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6" data-aos="fade-up" data-aos-duration="800">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                        <i class='bx bx-test-tube text-messenger-primary text-xl mr-2'></i>
                        Testing Status
                    </h3>
                    <div class="p-4">
                        <?php if (!empty($request['testing_status']) && $request['testing_status'] === 'pending'): ?>
                            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                                <div>
                                    <p class="text-gray-700 font-medium mb-2">System Access Testing Required</p>
                                    <p class="text-sm text-gray-600">
                                        <?php
                                        $testing_reason = (!empty($request['access_type']) && $request['access_type'] === 'System Application') ?
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
                            </div> <?php else: ?>
                            <div class="flex items-start space-x-4">
                                <div class="flex-shrink-0">
                                    <div class="flex items-center justify-center w-12 h-12 rounded-full <?php echo (!empty($request['testing_status']) && $request['testing_status'] === 'success') ? 'bg-green-100' : 'bg-red-100'; ?>">
                                        <?php if (!empty($request['testing_status']) && $request['testing_status'] === 'success'): ?>
                                            <i class='bx bx-check text-3xl text-green-500'></i>
                                        <?php else: ?>
                                            <i class='bx bx-x text-3xl text-red-500'></i>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="flex-1">
                                    <h3 class="text-lg font-medium <?php echo (!empty($request['testing_status']) && $request['testing_status'] === 'success') ? 'text-green-700' : 'text-red-700'; ?>">
                                        Testing <?php echo ucfirst($request['testing_status'] ?? 'Unknown'); ?>
                                    </h3>
                                    <?php if (!empty($request['testing_notes'])): ?>
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
                    window.location.href = 'request_history.php';
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

        // Horizontal scroll controls for Approval Flow
        function scrollFlow(direction) {
            const scroller = document.getElementById('approvalFlowScroller');
            if (!scroller) return;
            const delta = Math.max(240, Math.floor(scroller.clientWidth * 0.8));
            scroller.scrollBy({
                left: direction * delta,
                behavior: 'smooth'
            });
        }

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
<?php include '../footer.php'; ?>

</html>
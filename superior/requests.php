<?php
session_start();
require_once '../config.php';

// Check if superior is logged in
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'superior') {
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

// Get all requests pending superior review - grouped by access_request_number
try {
    $sql = "SELECT ar.*, 
            CASE 
                WHEN ar.status = 'pending_superior' THEN 'Pending Your Review'
                WHEN ar.status = 'pending_help_desk' THEN 'Pending Help Desk Review'
                WHEN ar.status = 'pending_technical' THEN 'Pending Technical Review'
                WHEN ar.status = 'pending_process_owner' THEN 'Pending Process Owner Review'
                WHEN ar.status = 'pending_admin' THEN 'Pending Admin Review'
                WHEN ar.status = 'approved' THEN 'Approved'
                WHEN ar.status = 'rejected' THEN 'Rejected'
                ELSE ar.status
            END as status_display
            FROM access_requests ar 
            WHERE ar.status = 'pending_superior'
            ORDER BY ar.submission_date DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error fetching requests: " . $e->getMessage();
    $requests = [];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Superior - Pending Requests</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Tailwind Configuration -->
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
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-gray-100">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 ml-72">
            <!-- Header -->
            <div class="bg-blue-900 border-b border-gray-200 sticky top-0 z-10">
                <div class="px-8 py-4">
                    <h1 class="text-4xl font-bold text-white">Pending Requests</h1>
                </div>
            </div>

            <!-- Content -->
            <div class="p-8">
                <div class="bg-white rounded-xl shadow overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">UAR REF NO.</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Requestor</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Requested</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Needed</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Days Pending</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>

                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (!empty($requests)): ?>
                                    <?php foreach ($requests as $request): ?>
                                        <tr class="hover:bg-gray-50 cursor-pointer" onclick="window.location.href='view_request.php?id=<?php echo $request['id']; ?>'">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($request['access_request_number']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($request['requestor_name']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php
                                                try {
                                                    echo !empty($request['submission_date']) ? date('M d, Y', strtotime($request['submission_date'])) : 'N/A';
                                                } catch (Exception $e) {
                                                    echo 'N/A';
                                                }
                                                ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" data-label="Date Needed">
                                                <?php
                                                try {
                                                    echo !empty($request['request_date']) ? date('M d, Y', strtotime($request['request_date'])) : 'N/A';
                                                } catch (Exception $e) {
                                                    echo 'N/A';
                                                }
                                                ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php
                                                try {
                                                    if (!empty($request['submission_date'])) {
                                                        $submission_date = new DateTime($request['submission_date']);
                                                        $today = new DateTime();
                                                        $interval = $submission_date->diff($today);
                                                        echo $interval->days . ' day/s';
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                } catch (Exception $e) {
                                                    echo 'N/A';
                                                }
                                                ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                    <?php echo htmlspecialchars($request['status_display']); ?>
                                                </span>
                                            </td>

                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                                            No pending requests found
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>
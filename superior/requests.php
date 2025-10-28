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
            FROM uar.access_requests ar 
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
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Boxicons -->
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <!-- Alpine.js for interactions -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.1/dist/cdn.min.js"></script>
    <!-- Flowbite CSS -->
    <link href="https://cdn.jsdelivr.net/npm/flowbite@2.5.1/dist/flowbite.min.css" rel="stylesheet" />
    <!-- Flowbite JS -->
    <script src="https://cdn.jsdelivr.net/npm/flowbite@2.5.1/dist/flowbite.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Include Design System -->
    <?php require_once '../includes/design_system.php'; ?>

    <!-- Tailwind Configuration -->
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
                        }
                    },
                    fontFamily: {
                        'sans': ['Inter', 'system-ui', 'sans-serif'],
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-gray-100" x-data="{ sidebarOpen: true }">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 transition-all duration-300" :class="sidebarOpen ? 'md:ml-72' : 'ml-0'">
            <!-- Header -->
            <div class="<?php echo getComponentClass('header'); ?>">
                <div class="<?php echo getComponentClass('header', 'container'); ?>">
                    <div class="flex items-center">
                        <?php renderHamburgerButton(); ?>
                        <h1 class="<?php echo getComponentClass('header', 'title'); ?>">Pending Requests</h1>
                    </div>
                    <?php renderPrivacyNotice(); ?>
                </div>
            </div>

            <!-- Content -->
            <div class="p-4 md:p-8">
                <div class="<?php echo getComponentClass('card'); ?>">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <?php 
                            $columns = ['UAR REF NO.', 'Requestor', 'Date Requested', 'Date Needed', 'Days Pending', 'Status'];
                            renderTableHeader($columns);
                            ?>
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
<?php include '../footer.php'; ?>
</html>
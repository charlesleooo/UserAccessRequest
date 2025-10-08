<?php
session_start();
require_once '../config.php';

// Check if help desk is logged in
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'help_desk') {
    header('Location: ../admin/login.php');
    exit();
}

$admin_id = $_SESSION['admin_id'];
$admin_username = $_SESSION['admin_username'] ?? '';

try {
    // First, get the admin_users.id from the database
    // This is needed because the help_desk_id in approval_history matches admin_users.id, not the session admin_id
    $adminQuery = $pdo->prepare("SELECT id FROM admin_users WHERE username = :username OR username = :employee_id");
    $adminQuery->execute([
        'username' => $admin_username,
        'employee_id' => $admin_id
    ]);
    $adminRecord = $adminQuery->fetch(PDO::FETCH_ASSOC);
    $admin_users_id = $adminRecord ? $adminRecord['id'] : $admin_id; // Fallback to session ID if not found

    // Get requests reviewed by this help desk
    // Use ROW_NUMBER() to get only the most recent entry per access_request_number
    $stmt = $pdo->prepare("
        SELECT 
            access_request_number,
            requestor_name,
            department,
            business_unit,
            access_type,
            system_type,
            review_date,
            review_notes,
            status,
            justification,
            employee_id,
            email,
            role_access_type,
            duration_type,
            start_date,
            end_date,
            action
        FROM (
            SELECT 
                ar.access_request_number,
                ar.requestor_name,
                ar.department,
                ar.business_unit,
                ar.access_level as access_type,
                ar.system_type,
                ar.help_desk_review_date as review_date,
                ar.help_desk_notes as review_notes,
                ar.status,
                '' as justification,
                ar.employee_id,
                ar.employee_email as email,
                '' as role_access_type,
                '' as duration_type,
                '' as start_date,
                '' as end_date,
                CASE 
                    WHEN ar.status = 'rejected' AND ar.help_desk_id = :help_desk_id THEN 'Rejected'
                    ELSE 'Approved/Forwarded'
                END as action,
                ROW_NUMBER() OVER (PARTITION BY ar.access_request_number ORDER BY ar.help_desk_review_date DESC) as rn
            FROM 
                access_requests ar
            WHERE 
                ar.help_desk_id = :help_desk_id AND ar.help_desk_review_date IS NOT NULL
            UNION ALL
            SELECT 
                ah.access_request_number,
                ah.requestor_name,
                ah.department,
                ah.business_unit,
                ah.access_type,
                ah.system_type,
                ah.created_at as review_date,
                ah.help_desk_notes as review_notes,
                ah.action as status,
                ah.justification,
                ah.employee_id,
                ah.email,
                '',
                ah.duration_type,
                ah.start_date,
                ah.end_date,
                CASE 
                    WHEN ah.action = 'rejected' AND ah.help_desk_id = :help_desk_id THEN 'Rejected'
                    ELSE 'Approved/Forwarded'
                END as action,
                ROW_NUMBER() OVER (PARTITION BY ah.access_request_number ORDER BY ah.created_at DESC) as rn
            FROM 
                approval_history ah
            WHERE 
                ah.help_desk_id = :help_desk_id
        ) combined
        WHERE rn = 1
        GROUP BY access_request_number
        ORDER BY 
            review_date DESC
    ");

    // Execute with the admin_users.id from the database
    $stmt->execute(['help_desk_id' => $admin_users_id]);
    $reviewed_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error fetching review history: " . $e->getMessage();
    $reviewed_requests = [];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help Desk - Review History</title>
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
            <div class="bg-white border-b border-gray-200 sticky top-0 z-10">
                <div class="px-8 py-4">
                    <h1 class="text-2xl font-bold text-gray-800">Review History</h1>
                    <p class="text-gray-600 mt-1">Historical record of all requests you have reviewed</p>
                </div>
            </div>

            <!-- Content -->
            <div class="p-8">
                <div class="bg-white rounded-xl shadow overflow-hidden">
                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="bg-green-50 p-4 border-l-4 border-green-500">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class='bx bx-check-circle text-xl text-green-500'></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-green-700">
                                        <?php echo $_SESSION['success_message']; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <?php unset($_SESSION['success_message']); ?>
                    <?php endif; ?>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">UAR REF NO.</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Requestor</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Business Unit</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Review Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (!empty($reviewed_requests)): ?>
                                    <?php foreach ($reviewed_requests as $index => $request): ?>
                                        <tr class="hover:bg-gray-50 cursor-pointer" 
                                            onclick="window.location='view_request.php?access_request_number=<?php echo urlencode($request['access_request_number']); ?>&from_history=true'">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($request['access_request_number']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($request['requestor_name']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($request['business_unit']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($request['department']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo date('M d, Y', strtotime($request['review_date'])); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                    <?php echo ($request['action'] === 'Rejected') ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?>">
                                                    <?php echo htmlspecialchars($request['action']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">
                                            No review history found
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
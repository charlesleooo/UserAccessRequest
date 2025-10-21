<?php
session_start();
require_once '../config.php';

// Check if superior is logged in
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'superior') {
    header('Location: ../admin/login.php');
    exit();
}

$admin_id = $_SESSION['admin_id'];
$admin_username = $_SESSION['admin_username'] ?? '';

try {
    // First, get the admin_users.id from the database (same logic as helpdesk)
    $adminQuery = $pdo->prepare("SELECT id FROM uar.admin_users WHERE username = :username OR username = :employee_id");
    $adminQuery->execute([
        'username' => $admin_username,
        'employee_id' => $admin_id
    ]);
    $adminRecord = $adminQuery->fetch(PDO::FETCH_ASSOC);
    $admin_users_id = $adminRecord ? $adminRecord['id'] : $admin_id;

    // Get requests reviewed by this superior from both active requests and approval history
    $stmt = $pdo->prepare("
        SELECT * FROM (
            SELECT 
                ar.access_request_number,
                ar.requestor_name,
                ar.department,
                ar.business_unit,
                ar.access_level as access_type,
                ar.system_type,
                ar.superior_review_date as review_date,
                ar.superior_notes as review_notes,
                ar.status,
                ar.employee_id,
                ar.employee_email as email,
                CASE 
                    WHEN ar.status = 'rejected' THEN 'Rejected'
                    ELSE 'Approved/Forwarded'
                END as action,
                ROW_NUMBER() OVER (PARTITION BY ar.access_request_number ORDER BY ar.superior_review_date DESC) as rn
            FROM 
                uar.access_requests ar
            WHERE 
                ar.superior_id = :superior_id1 
                AND ar.superior_review_date IS NOT NULL
            
            UNION ALL
            
            SELECT 
                ah.access_request_number,
                ah.requestor_name,
                ah.department,
                ah.business_unit,
                ah.access_type,
                ah.system_type,
                ah.created_at as review_date,
                ah.superior_notes as review_notes,
                ah.action as status,
                ah.employee_id,
                ah.email,
                CASE 
                    WHEN ah.action = 'rejected' THEN 'Rejected'
                    ELSE 'Approved/Forwarded'
                END as action,
                ROW_NUMBER() OVER (PARTITION BY ah.access_request_number ORDER BY ah.created_at DESC) as rn
            FROM 
                uar.approval_history ah
            WHERE 
                ah.superior_id = :superior_id2
        ) combined
        WHERE rn = 1
        ORDER BY review_date DESC
    ");

    $stmt->execute([
        'superior_id1' => $admin_users_id,
        'superior_id2' => $admin_users_id
    ]);
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
    <title>Superior - Review History</title>
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
                    <h1 class="text-4xl font-bold text-white">Review History</h1>
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
                                            onclick="viewRequest('<?php echo htmlspecialchars($request['access_request_number'], ENT_QUOTES); ?>')">
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
                                        <td colspan="6" class="px-6 py-12 text-center">
                                            <i class='bx bx-folder-open text-6xl text-gray-300'></i>
                                            <p class="mt-4 text-lg text-gray-500 font-medium">No review history found</p>
                                            <p class="mt-2 text-sm text-gray-400">Requests you review will appear here</p>
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

    <script>
        function viewRequest(accessRequestNumber) {
            // Navigate to view_request.php with proper parameters
            window.location.href = 'view_request.php?access_request_number=' + encodeURIComponent(accessRequestNumber) + '&from_history=true';
        }
    </script>
</body>

</html>
<?php
session_start();
require_once '../config.php';

// Check if process owner is logged in
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'process_owner') {
    header('Location: ../admin/login.php');
    exit();
}

$admin_id = $_SESSION['admin_id'];
$admin_username = $_SESSION['admin_username'] ?? '';

try {
    // First, get the admin_users.id from the database
    // This is needed because the process_owner_id in approval_history matches admin_users.id, not the session admin_id
    $adminQuery = $pdo->prepare("SELECT id FROM admin_users WHERE username = :username OR username = :employee_id");
    $adminQuery->execute([
        'username' => $admin_username,
        'employee_id' => $admin_id
    ]);
    $adminRecord = $adminQuery->fetch(PDO::FETCH_ASSOC);
    $process_owner_id = $adminRecord ? $adminRecord['id'] : $admin_id; // Fallback to session ID if not found

    // Get requests reviewed by this process owner
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
                ar.process_owner_review_date as review_date,
                ar.process_owner_notes as review_notes,
                ar.status,
                '' as justification,
                ar.employee_id,
                ar.employee_email as email,
                '' as role_access_type,
                '' as duration_type,
                '' as start_date,
                '' as end_date,
                CASE 
                    WHEN ar.status = 'rejected' AND ar.process_owner_id = :process_owner_id THEN 'Rejected'
                    ELSE 'Approved/Forwarded'
                END as action,
                ROW_NUMBER() OVER (PARTITION BY ar.access_request_number ORDER BY ar.process_owner_review_date DESC) as rn
            FROM 
                access_requests ar
            WHERE 
                ar.process_owner_id = :process_owner_id AND ar.process_owner_review_date IS NOT NULL
            UNION
            SELECT 
                ah.access_request_number,
                ah.requestor_name,
                ah.department,
                ah.business_unit,
                ah.access_type,
                ah.system_type,
                ah.created_at as review_date,
                ah.process_owner_notes as review_notes,
                ah.action as status,
                ah.justification,
                ah.employee_id,
                ah.email,
                '',
                ah.duration_type,
                ah.start_date,
                ah.end_date,
                CASE 
                    WHEN ah.action = 'rejected' AND ah.process_owner_id = :process_owner_id THEN 'Rejected'
                    ELSE 'Approved/Forwarded'
                END as action,
                ROW_NUMBER() OVER (PARTITION BY ah.access_request_number ORDER BY ah.created_at DESC) as rn
            FROM 
                approval_history ah
            WHERE 
                ah.process_owner_id = :process_owner_id
        ) combined
        WHERE rn = 1
        ORDER BY 
            review_date DESC
    ");

    $stmt->execute(['process_owner_id' => $process_owner_id]);
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
    <title>Process Owner - Review History</title>
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
            <div class="bg-primary-900 border-b border-gray-200 sticky top-0 z-10">
                <div class="px-8 py-4">
                    <h1 class="text-2xl font-bold text-white">Review History</h1>
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
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Request No.</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Requestor</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Access Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Review Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (!empty($reviewed_requests)): ?>
                                    <?php foreach ($reviewed_requests as $index => $request): ?>
                                        <tr class="hover:bg-gray-50 cursor-pointer view-btn"
                                            data-request='<?php echo json_encode($request); ?>'
                                            data-index="<?php echo $index; ?>">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($request['access_request_number']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($request['requestor_name']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($request['department']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($request['access_type']); ?>
                                                <?php if (!empty($request['system_type'])): ?>
                                                    <span class="text-xs text-gray-400">(<?php echo htmlspecialchars($request['system_type']); ?>)</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo date('M d, Y H:i', strtotime($request['review_date'])); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                    <?php echo ($request['action'] === 'Rejected') ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?>">
                                                    <?php echo htmlspecialchars($request['action']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 max-w-xs truncate">
                                                <?php echo htmlspecialchars($request['review_notes']); ?>
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

    <!-- View Request Modal -->
    <div id="viewRequestModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-xl w-[96%] max-w-7xl mx-auto shadow-xl flex flex-col max-h-[90vh]">
                <div class="flex items-center px-6 py-4 border-b border-gray-200 flex-shrink-0">
                    <div class="w-1/4">
                        <p class="text-sm font-medium text-gray-500">Request Number</p>
                        <p id="modal-request-number" class="text-lg font-semibold text-gray-900"></p>
                    </div>
                    <div class="flex-1 text-center">
                        <h3 class="text-xl font-semibold text-gray-800">Access Request Details</h3>
                    </div>
                    <div class="w-1/4 flex justify-end">
                        <button id="closeModal" class="text-gray-500 hover:text-gray-700">
                            <i class='bx bx-x text-2xl'></i>
                        </button>
                    </div>
                </div>
                <div id="modalContent" class="p-6 overflow-y-auto">
                    <!-- Content populated by JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Get all view buttons
            const viewButtons = document.querySelectorAll('.view-btn');
            const modal = document.getElementById('viewRequestModal');
            const closeModalBtn = document.getElementById('closeModal');

            // Add click event to view buttons
            viewButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const requestData = JSON.parse(this.getAttribute('data-request'));

                    // Populate modal header with request number
                    document.getElementById('modal-request-number').textContent = requestData.access_request_number;

                    // Format duration details
                    let durationText = requestData.duration_type || 'N/A';
                    if (requestData.duration_type === 'temporary' && requestData.start_date && requestData.end_date) {
                        durationText = `${new Date(requestData.start_date).toLocaleDateString()} - ${new Date(requestData.end_date).toLocaleDateString()}`;
                    } else if (requestData.duration_type === 'permanent') {
                        durationText = 'Permanent';
                    }

                    // Set appropriate status color
                    const statusColor = requestData.action === 'Rejected' ? 'text-red-600' : 'text-green-600';

                    // Build the modal content with the same grid layout as admin side
                    document.getElementById('modalContent').innerHTML = `
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            <!-- Request Overview -->
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                                    <i class='bx bx-info-circle text-primary-600 text-xl mr-2'></i>
                                    Request Overview
                                </h3>
                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Request Number:</span>
                                        <span class="font-medium text-gray-900">${requestData.access_request_number}</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-600">Status:</span>
                                        <span class="font-medium ${statusColor}">${requestData.action}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Review Date:</span>
                                        <span class="font-medium text-gray-900">${new Date(requestData.review_date).toLocaleString()}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Duration:</span>
                                        <span class="font-medium text-gray-900">${durationText}</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Requestor Info -->
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                                    <i class='bx bx-user text-primary-600 text-xl mr-2'></i>
                                    Requestor Information
                                </h3>
                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Name:</span>
                                        <span class="font-medium text-gray-900">${requestData.requestor_name}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Business Unit:</span>
                                        <span class="font-medium text-gray-900">${requestData.business_unit}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Department:</span>
                                        <span class="font-medium text-gray-900">${requestData.department}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Email:</span>
                                        <span class="font-medium text-gray-900">${requestData.email || 'N/A'}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Employee ID:</span>
                                        <span class="font-medium text-gray-900">${requestData.employee_id || 'N/A'}</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Access Details -->
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                                    <i class='bx bx-lock-open text-primary-600 text-xl mr-2'></i>
                                    Access Details
                                </h3>
                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Access Type:</span>
                                        <span class="font-medium text-gray-900">${requestData.access_type}</span>
                                    </div>
                                    ${requestData.system_type ? `
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">System Type:</span>
                                        <span class="font-medium text-gray-900">${requestData.system_type}</span>
                                    </div>
                                    ` : ''}
                                    ${requestData.role_access_type ? `
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Role Access Type:</span>
                                        <span class="font-medium text-gray-900">${requestData.role_access_type}</span>
                                    </div>
                                    ` : ''}
                                </div>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
                            <!-- Justification -->
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                                    <i class='bx bx-comment-detail text-primary-600 text-xl mr-2'></i>
                                    Justification
                                </h3>
                                <div class="bg-gray-50 p-4 rounded-lg text-gray-700">
                                    ${requestData.justification || 'No justification provided.'}
                                </div>
                            </div>
                            
                            <!-- Your Review -->
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                                    <i class='bx bx-check-shield text-primary-600 text-xl mr-2'></i>
                                    Your Review
                                </h3>
                                <div class="space-y-3">
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-600">Action:</span>
                                        <span class="font-medium ${statusColor}">${requestData.action}</span>
                                    </div>
                                    <div>
                                        <span class="text-gray-600 block mb-2">Review Notes:</span>
                                        <div class="bg-gray-50 p-4 rounded-lg text-gray-700">
                                            ${requestData.review_notes || 'No notes provided.'}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;

                    // Show modal
                    modal.classList.remove('hidden');
                });
            });

            // Close modal when clicking the close button
            closeModalBtn.addEventListener('click', function() {
                modal.classList.add('hidden');
            });

            // Close modal when clicking outside the content
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.classList.add('hidden');
                }
            });
        });
    </script>
</body>

</html>
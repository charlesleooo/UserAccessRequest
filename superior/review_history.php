<?php
session_start();
require_once '../config.php';

// Check if superior is logged in
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'superior') {
    header('Location: ../admin/login.php');
    exit();
}

$superior_id = $_SESSION['admin_id'];

try {
    // Get requests reviewed by this superior
    $stmt = $pdo->prepare("
        SELECT 
            ar.access_request_number,
            ar.requestor_name,
            ar.department,
            ar.business_unit,
            ar.access_type,
            ar.system_type,
            ar.superior_review_date as review_date,
            ar.superior_notes as review_notes,
            ar.status,
            ar.justification,
            ar.employee_id,
            ar.email,
            ar.role_access_type,
            ar.duration_type,
            ar.start_date,
            ar.end_date,
            CASE 
                WHEN ar.status = 'rejected' AND ar.superior_id = :superior_id THEN 'Rejected'
                ELSE 'Approved/Forwarded'
            END as action
        FROM 
            access_requests ar
        WHERE 
            ar.superior_id = :superior_id AND ar.superior_review_date IS NOT NULL
        UNION
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
            ah.justification,
            ah.employee_id,
            ah.email,
            '',
            ah.duration_type,
            ah.start_date,
            ah.end_date,
            CASE 
                WHEN ah.action = 'rejected' AND ah.superior_id = :superior_id THEN 'Rejected'
                ELSE 'Approved/Forwarded'
            END as action
        FROM 
            approval_history ah
        WHERE 
            ah.superior_id = :superior_id
        ORDER BY 
            review_date DESC
    ");
    
    $stmt->execute(['superior_id' => $superior_id]);
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
                    
                    <a href="requests.php" class="flex items-center px-4 py-3 text-gray-700 rounded-xl hover:bg-gray-50">
                        <span class="flex items-center justify-center w-9 h-9 bg-gray-100 text-gray-600 rounded-lg">
                            <i class='bx bxs-message-square-detail text-xl'></i>
                        </span>
                        <span class="ml-3">Superior Reviews</span>
                    </a>
                    
                    <a href="#" class="flex items-center px-4 py-3 text-primary-600 bg-primary-50 rounded-xl">
                        <span class="flex items-center justify-center w-9 h-9 bg-primary-100 text-primary-600 rounded-lg">
                            <i class='bx bx-history text-xl'></i>
                        </span>
                        <span class="ml-3 font-medium">Review History</span>
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

                <!-- User Profile -->
                <div class="px-4 py-4 border-t border-gray-100">
                    <div class="flex items-center space-x-3">
                        <div class="flex-shrink-0">
                            <div class="w-10 h-10 rounded-full bg-primary-100 flex items-center justify-center text-primary-600">
                                <i class='bx bxs-user text-xl'></i>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">
                                <?php echo htmlspecialchars($_SESSION['admin_username']); ?>
                            </p>
                            <p class="text-xs text-gray-500 truncate">
                                Superior
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

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
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Request No.</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Requestor</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Access Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Review Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">View</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (!empty($reviewed_requests)): ?>
                                    <?php foreach ($reviewed_requests as $index => $request): ?>
                                        <tr class="hover:bg-gray-50">
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
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                                                <button 
                                                    type="button" 
                                                    class="view-btn bg-primary-100 text-primary-700 hover:bg-primary-200 px-3 py-1 rounded-md text-sm"
                                                    data-request='<?php echo json_encode($request); ?>'
                                                    data-index="<?php echo $index; ?>"
                                                >
                                                    View Details
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="px-6 py-4 text-center text-sm text-gray-500">
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
    <div id="viewRequestModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-4xl max-h-[80vh] overflow-y-auto">
            <div class="border-b border-gray-200 px-6 py-4 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-900">Request Details</h3>
                <button id="closeModal" class="text-gray-500 hover:text-gray-700 focus:outline-none">
                    <i class='bx bx-x text-2xl'></i>
                </button>
            </div>
            <div id="modalContent" class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Request Information -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h4 class="font-semibold text-gray-700 mb-3">Request Information</h4>
                        <div class="space-y-2">
                            <div>
                                <span class="text-sm font-medium text-gray-500">Request Number:</span>
                                <p id="modal-request-number" class="text-gray-800"></p>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-gray-500">Access Type:</span>
                                <p id="modal-access-type" class="text-gray-800"></p>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-gray-500">System Type:</span>
                                <p id="modal-system-type" class="text-gray-800"></p>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-gray-500">Role/Access Type:</span>
                                <p id="modal-role-access" class="text-gray-800"></p>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-gray-500">Duration:</span>
                                <p id="modal-duration" class="text-gray-800"></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Requestor Information -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h4 class="font-semibold text-gray-700 mb-3">Requestor Information</h4>
                        <div class="space-y-2">
                            <div>
                                <span class="text-sm font-medium text-gray-500">Name:</span>
                                <p id="modal-requestor-name" class="text-gray-800"></p>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-gray-500">Department:</span>
                                <p id="modal-department" class="text-gray-800"></p>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-gray-500">Business Unit:</span>
                                <p id="modal-business-unit" class="text-gray-800"></p>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-gray-500">Employee ID:</span>
                                <p id="modal-employee-id" class="text-gray-800"></p>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-gray-500">Email:</span>
                                <p id="modal-email" class="text-gray-800"></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Justification -->
                <div class="mt-6 bg-gray-50 rounded-lg p-4">
                    <h4 class="font-semibold text-gray-700 mb-3">Justification</h4>
                    <p id="modal-justification" class="text-gray-800 whitespace-pre-wrap break-words"></p>
                </div>

                <!-- Review Information -->
                <div class="mt-6 bg-gray-50 rounded-lg p-4">
                    <h4 class="font-semibold text-gray-700 mb-3">Your Review</h4>
                    <div class="space-y-2">
                        <div>
                            <span class="text-sm font-medium text-gray-500">Review Date:</span>
                            <p id="modal-review-date" class="text-gray-800"></p>
                        </div>
                        <div>
                            <span class="text-sm font-medium text-gray-500">Action:</span>
                            <p id="modal-action" class="text-gray-800"></p>
                        </div>
                        <div>
                            <span class="text-sm font-medium text-gray-500">Notes:</span>
                            <p id="modal-review-notes" class="text-gray-800 whitespace-pre-wrap break-words"></p>
                        </div>
                    </div>
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
                    
                    // Populate modal with request data
                    document.getElementById('modal-request-number').textContent = requestData.access_request_number;
                    document.getElementById('modal-access-type').textContent = requestData.access_type || 'N/A';
                    document.getElementById('modal-system-type').textContent = requestData.system_type || 'N/A';
                    document.getElementById('modal-role-access').textContent = requestData.role_access_type || 'N/A';
                    
                    // Duration information
                    let durationText = requestData.duration_type || 'N/A';
                    if (requestData.duration_type === 'temporary' && requestData.start_date && requestData.end_date) {
                        const startDate = new Date(requestData.start_date).toLocaleDateString();
                        const endDate = new Date(requestData.end_date).toLocaleDateString();
                        durationText += ` (${startDate} to ${endDate})`;
                    }
                    document.getElementById('modal-duration').textContent = durationText;
                    
                    // Requestor information
                    document.getElementById('modal-requestor-name').textContent = requestData.requestor_name;
                    document.getElementById('modal-department').textContent = requestData.department;
                    document.getElementById('modal-business-unit').textContent = requestData.business_unit;
                    document.getElementById('modal-employee-id').textContent = requestData.employee_id || 'N/A';
                    document.getElementById('modal-email').textContent = requestData.email || 'N/A';
                    
                    // Justification
                    document.getElementById('modal-justification').textContent = requestData.justification || 'N/A';
                    
                    // Review information
                    document.getElementById('modal-review-date').textContent = new Date(requestData.review_date).toLocaleString();
                    
                    const actionElem = document.getElementById('modal-action');
                    actionElem.textContent = requestData.action;
                    actionElem.className = requestData.action === 'Rejected' ? 'text-red-600 font-medium' : 'text-green-600 font-medium';
                    
                    document.getElementById('modal-review-notes').textContent = requestData.review_notes || 'No notes provided';
                    
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

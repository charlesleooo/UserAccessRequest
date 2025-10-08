<?php
session_start();
require_once '../config.php';

// Check if help desk is logged in
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'help_desk') {
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

// Get all requests pending technical review
try {
    $sql = "SELECT DISTINCT ar.*, 
            CASE 
                WHEN ar.status = 'pending_superior' THEN 'Pending Superior Review'
                WHEN ar.status = 'pending_help_desk' THEN 'Pending Your Review'
                WHEN ar.status = 'pending_technical' THEN 'Pending Technical Review'
                WHEN ar.status = 'pending_testing_setup' THEN 'Pending Testing Setup'
                WHEN ar.status = 'pending_testing_review' THEN 'Pending Testing Review'
                WHEN ar.status = 'pending_process_owner' THEN 'Pending Process Owner Review'
                WHEN ar.status = 'pending_admin' THEN 'Pending Admin Review'
                WHEN ar.status = 'approved' THEN 'Approved'
                WHEN ar.status = 'rejected' THEN 'Rejected'
                ELSE ar.status
            END as status_display,
            COALESCE(ir.date_needed, gr.date_needed) as date_needed
            FROM access_requests ar
            LEFT JOIN individual_requests ir ON ar.access_request_number = ir.access_request_number
            LEFT JOIN group_requests gr ON ar.access_request_number = gr.access_request_number
            WHERE ar.status IN ('pending_help_desk', 'pending_technical', 'pending_testing_setup', 'pending_testing_review')
            ORDER BY ar.submission_date DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Check if any approved requests with successful testing should be removed from access_requests
    foreach ($requests as $index => $request) {
        if ($request['status'] === 'approved' && $request['testing_status'] === 'success') {
            // Check if this request has already been moved to approval history
            $checkSql = "SELECT COUNT(*) FROM approval_history WHERE access_request_number = ?";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute([$request['access_request_number']]);
            $exists = $checkStmt->fetchColumn();

            if ($exists > 0) {
                // Remove from the results array as it's already in approval history
                unset($requests[$index]);

                // Also remove from access_requests table to ensure consistency
                $deleteSql = "DELETE FROM access_requests WHERE id = ?";
                $deleteStmt = $pdo->prepare($deleteSql);
                $deleteStmt->execute([$request['id']]);
            }
        }
    }

    // Re-index the array after removing elements
    $requests = array_values($requests);
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
    <title>Help Desk - Pending Reviews</title>
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
                    <h1 class="text-2xl font-bold text-gray-800">Help Desk Reviews</h1>
                    <p class="text-gray-600 mt-1">Review and process access requests from superiors</p>
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
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Business Unit</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Requested</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Needed</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Days Pending</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (!empty($requests)): ?>
                                    <?php foreach ($requests as $request): ?>
                                        <tr class="hover:bg-gray-50 cursor-pointer" onclick="window.location='view_request.php?id=<?php echo $request['id']; ?>'">
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
                                                <?php echo date('M d, Y', strtotime($request['submission_date'])); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo $request['date_needed'] ? date('M d, Y', strtotime($request['date_needed'])) : 'N/A'; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php
                                                $submission_date = new DateTime($request['submission_date']);
                                                $today = new DateTime();
                                                $interval = $submission_date->diff($today);
                                                echo $interval->days . ' days';
                                                ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                    <?php echo htmlspecialchars($request['status_display']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                                <!-- Action buttons will be moved to modal -->
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="px-6 py-4 text-center text-gray-500">
                                            No pending technical reviews found
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

    <!-- Details Modal -->
    <div id="detailsModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-xl w-[96%] max-w-7xl mx-auto shadow-xl flex flex-col max-h-[90vh]">
                <div class="flex items-center px-6 py-4 border-b border-gray-200 flex-shrink-0">
                    <div class="w-1/4">
                        <p class="text-sm font-medium text-gray-500">Request Number</p>
                        <p id="detail_request_number" class="text-lg font-semibold text-gray-900"></p>
                    </div>
                    <div class="flex-1 text-center">
                        <h3 class="text-xl font-semibold text-gray-800">Access Request Details</h3>
                    </div>
                    <div class="w-1/4 flex justify-end">
                        <button onclick="hideDetailsModal()" class="text-gray-500 hover:text-gray-700">
                            <i class='bx bx-x text-2xl'></i>
                        </button>
                    </div>
                </div>
                <div class="p-6 overflow-y-auto">
                    <div id="detailsModalContent">
                        <!-- Modal content will be populated by JavaScript -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showRequestDetails(requestId) {
            const modalContainer = document.getElementById('detailsModalContent');
            modalContainer.innerHTML = `
                <div class="flex justify-center items-center p-8">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
                </div>
            `;

            document.getElementById('detailsModal').classList.remove('hidden');

            fetch(`../admin/get_request_details.php?id=${requestId}`)
                .then(response => response.json())
                .then(response => {
                    if (!response.success) {
                        throw new Error(response.message || 'Failed to load request details');
                    }

                    const data = response.data;
                    document.getElementById('detail_request_number').textContent = data.access_request_number;

                    // Display superior's comments directly if they exist
                    let superiorComments = '';
                    if (data.superior_review_notes && data.superior_review_notes.trim() !== '') {
                        superiorComments = `
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mt-6">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                                    <i class='bx bx-message-detail text-primary-600 text-xl mr-2'></i>
                                    Superior's Comments
                                </h3>
                                <div class="bg-gray-50 p-4 rounded-lg text-gray-700">
                                    ${data.superior_review_notes}
                                </div>
                            </div>
                        `;
                    }

                    modalContainer.innerHTML = `
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            <!-- Request Overview -->
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                                    <i class='bx bx-info-circle text-primary-600 text-xl mr-2'></i>
                                    Request Overview
                                </h3>
                                <div class="space-y-3">
                                    <div class="flex justify-between"><span class="text-gray-600">Request Number:</span><span class="font-medium text-gray-900">${data.access_request_number}</span></div>
                                    <div class="flex justify-between items-center"><span class="text-gray-600">Status:</span><div class="flex items-center ${data.status.includes('pending') ? 'bg-yellow-50' : (data.status === 'approved' ? 'bg-green-50' : 'bg-red-50')} rounded-lg px-2 py-1"><span class="px-3 py-1 text-xs font-medium rounded-full ${data.status.includes('pending') ? 'bg-yellow-100 text-yellow-700' : (data.status === 'approved' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700')}">${data.status_display}</span></div></div>
                                    <div class="flex justify-between"><span class="text-gray-600">Submitted:</span><span class="font-medium text-gray-900">${new Date(data.submission_date).toLocaleString()}</span></div>
                                    <div class="flex justify-between"><span class="text-gray-600">Request Date:</span><span class="font-medium text-gray-900">${data.request_date ? new Date(data.request_date).toLocaleDateString() : 'N/A'}</span></div>
                                    <div class="flex justify-between"><span class="text-gray-600">Date Needed:</span><span class="font-medium text-gray-900">${data.date_needed ? new Date(data.date_needed).toLocaleDateString() : 'N/A'}</span></div>
                                    <div class="flex justify-between"><span class="text-gray-600">Duration:</span><span class="font-medium text-gray-900">${data.duration_type === 'permanent' ? 'Permanent' : (data.start_date && data.end_date ? `${new Date(data.start_date).toLocaleDateString()} - ${new Date(data.end_date).toLocaleDateString()}` : 'N/A')}</span></div>
                                </div>
                            </div>
                            <!-- Requestor Info -->
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                                    <i class='bx bx-user text-primary-600 text-xl mr-2'></i>
                                    Requestor Information
                                </h3>
                                <div class="space-y-3">
                                    <div class="flex justify-between"><span class="text-gray-600">Name:</span><span class="font-medium text-gray-900">${data.requestor_name}</span></div>
                                    <div class="flex justify-between"><span class="text-gray-600">Business Unit:</span><span class="font-medium text-gray-900">${data.business_unit}</span></div>
                                    <div class="flex justify-between"><span class="text-gray-600">Department:</span><span class="font-medium text-gray-900">${data.department}</span></div>
                                    <div class="flex justify-between"><span class="text-gray-600">Email:</span><span class="font-medium text-gray-900">${data.email}</span></div>
                                    <div class="flex justify-between"><span class="text-gray-600">Employee ID:</span><span class="font-medium text-gray-900">${data.employee_id}</span></div>
                                </div>
                            </div>
                            <!-- Access Details -->
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                                    <i class='bx bx-lock-open text-primary-600 text-xl mr-2'></i>
                                    Access Details
                                </h3>
                                <div class="space-y-3">
                                    <div class="flex justify-between"><span class="text-gray-600">Access Type:</span><span class="font-medium text-gray-900">${data.access_type}</span></div>
                                    ${data.system_type ? `<div class="flex justify-between"><span class="text-gray-600">System Type:</span><span class="font-medium text-gray-900">${data.system_type}</span></div>` : ''}
                                    ${data.application_system ? `<div class="flex justify-between"><span class="text-gray-600">Application System:</span><span class="font-medium text-gray-900 ${(() => {
                                        const highlightList = [
                                            'Canvassing',
                                            'ERP/NAV/SAP',
                                            'Legacy Inventory',
                                            'HRIS',
                                            'Piece Rate Payroll System',
                                            'Legacy Ledger System',
                                            'Legacy Payroll',
                                            'Legacy Purchasing',
                                            'Legacy Vouchering',
                                            'Fresh Chilled Receiving System',
                                            'ZankPOS',
                                            'Quickbooks'
                                        ];
                                        return highlightList.map(s => s.toLowerCase()).includes((data.application_system || '').toLowerCase())
                                            ? 'bg-green-100 text-green-800 px-2 py-1 rounded'
                                            : 'bg-red-100 text-red-800 px-2 py-1 rounded';
                                    })()}">${data.application_system}</span></div>` : ''}
                                    ${data.other_system_type ? `<div class="flex justify-between"><span class="text-gray-600">Other System:</span><span class="font-medium text-gray-900">${data.other_system_type}</span></div>` : ''}
                                    ${data.role_access_type ? `<div class="flex justify-between"><span class="text-gray-600">Role Access Type:</span><span class="font-medium text-gray-900">${data.role_access_type}</span></div>` : ''}
                                    ${data.access_level ? `<div class="flex justify-between"><span class="text-gray-600">Access Level:</span><span class="font-medium text-gray-900">${data.access_level}</span></div>` : ''}
                                    ${data.usernames ? `<div class="flex justify-between"><span class="text-gray-600">Usernames:</span><span class="font-medium text-gray-900">${(() => {
                                        if (Array.isArray(data.usernames)) {
                                            return data.usernames.join(', ');
                                        } else if (typeof data.usernames === 'string') {
                                            try {
                                                const arr = JSON.parse(data.usernames);
                                                if (Array.isArray(arr)) return arr.join(', ');
                                                return data.usernames;
                                            } catch {
                                                // Remove brackets and quotes if present
                                                return data.usernames.replace(/\[|\]|"/g, '');
                                            }
                                        } else {
                                            return data.usernames;
                                        }
                                    })()}</span></div>` : ''}
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
                                <div class="bg-gray-50 p-4 rounded-lg text-gray-700">${data.justification || 'No justification provided.'}</div>
                            </div>
                            <!-- Superior's Comments (if any) -->
                            ${superiorComments ? `<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6"><h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center"><i class='bx bx-message-detail text-primary-600 text-xl mr-2'></i>Superior's Comments</h3><div class="bg-gray-50 p-4 rounded-lg text-gray-700">${data.superior_review_notes}</div></div>` : ''}
                        </div>
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
                            <!-- Additional Details -->
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                                    <i class='bx bx-detail text-primary-600 text-xl mr-2'></i>
                                    Additional Details
                                </h3>
                                <div class="space-y-3">
                                    ${data.contact_number ? `<div class='flex justify-between'><span class='text-gray-600'>Contact Number:</span><span class='font-medium text-gray-900'>${data.contact_number}</span></div>` : ''}
                                    ${data.testing_status ? `<div class='flex justify-between'><span class='text-gray-600'>Testing Status:</span><span class='font-medium text-gray-900'>${data.testing_status}</span></div>` : ''}
                                    ${data.testing_notes ? `<div class='flex justify-between'><span class='text-gray-600'>Testing Notes:</span><span class='font-medium text-gray-900'>${data.testing_notes}</span></div>` : ''}
                                    ${data.testing_instructions ? `<div class='flex justify-between'><span class='text-gray-600'>Testing Instructions:</span><span class='font-medium text-gray-900'>${data.testing_instructions}</span></div>` : ''}
                                    ${data.superior_id ? `<div class='flex justify-between'><span class='text-gray-600'>Superior ID:</span><span class='font-medium text-gray-900'>${data.superior_id}</span></div>` : ''}
                                    ${data.help_desk_id ? `<div class='flex justify-between'><span class='text-gray-600'>Help Desk ID:</span><span class='font-medium text-gray-900'>${data.help_desk_id}</span></div>` : ''}
                                    ${data.technical_id ? `<div class='flex justify-between'><span class='text-gray-600'>Technical ID:</span><span class='font-medium text-gray-900'>${data.technical_id}</span></div>` : ''}
                                    ${data.process_owner_id ? `<div class='flex justify-between'><span class='text-gray-600'>Process Owner ID:</span><span class='font-medium text-gray-900'>${data.process_owner_id}</span></div>` : ''}
                                    ${data.admin_id ? `<div class='flex justify-between'><span class='text-gray-600'>Admin ID:</span><span class='font-medium text-gray-900'>${data.admin_id}</span></div>` : ''}
                                    ${data.reviewed_by ? `<div class='flex justify-between'><span class='text-gray-600'>Reviewed By:</span><span class='font-medium text-gray-900'>${data.reviewed_by}</span></div>` : ''}
                                    ${data.review_date ? `<div class='flex justify-between'><span class='text-gray-600'>Review Date:</span><span class='font-medium text-gray-900'>${data.review_date}</span></div>` : ''}
                                    ${data.review_notes ? `<div class='flex justify-between'><span class='text-gray-600'>Review Notes:</span><span class='font-medium text-gray-900'>${data.review_notes}</span></div>` : ''}
                                </div>
                            </div>
                        </div>
                        ${data.review_history && data.review_history.length > 0 ? `
                        <!-- Review History -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mt-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                                <i class='bx bx-history text-primary-600 text-xl mr-2'></i>
                                Review History
                            </h3>
                            <div class="space-y-4">
                                ${data.review_history.map(review => `
                                    <div class="bg-gray-50 p-4 rounded-lg">
                                        <div class="flex justify-between items-center mb-2">
                                            <span class="font-medium text-gray-900">${review.role}</span>
                                            <span class="text-sm text-gray-500">${new Date(review.date).toLocaleString()}</span>
                                        </div>
                                        <div class="flex items-center mb-2">
                                            <span class="text-sm font-medium ${review.action === 'Declined' ? 'text-red-600' : (review.action === 'Approved' ? 'text-green-600' : 'text-primary-600')}">${review.action}</span>
                                        </div>
                                        <p class="text-gray-700 text-sm">${review.note}</p>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                        ` : ''}
                        ${data.testing_status ? `
                        <!-- Testing Status -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mt-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                                <i class='bx bx-test-tube text-primary-600 text-xl mr-2'></i>
                                Testing Status
                            </h3>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600">Status:</span>
                                <span class="px-3 py-1 text-xs font-medium rounded-full ${data.testing_status === 'success' ? 'bg-green-100 text-green-700' : data.testing_status === 'failed' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700'}">${data.testing_status.charAt(0).toUpperCase() + data.testing_status.slice(1)}</span>
                            </div>
                            ${data.testing_notes ? `<div class="mt-4"><span class="text-gray-600">Testing Notes:</span><div class="mt-2 bg-gray-50 p-4 rounded-lg text-gray-700">${data.testing_notes}</div></div>` : ''}
                        </div>
                        ` : ''}
                        
                        <!-- Action Buttons -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mt-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                                <i class='bx bx-cog text-primary-600 text-xl mr-2'></i>
                                Actions
                            </h3>
                            <div class="flex flex-wrap gap-3">
                                ${data.status === 'pending_help_desk' ? `
                                    <button onclick="handleRequest(${data.id}, 'approve')"
                                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-green-600 bg-green-50 hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                        <i class='bx bx-share align-middle'></i>
                                        <span class="ml-1.5">Forward</span>
                                    </button>
                                    <button onclick="handleRequest(${data.id}, 'decline')"
                                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-red-600 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                        <i class='bx bx-x align-middle'></i>
                                        <span class="ml-1.5">Decline</span>
                                    </button>
                                ` : ''}
                                ${data.status === 'pending_technical' ? `
                                    <button onclick="handleRequest(${data.id}, 'approve')"
                                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-green-600 bg-green-50 hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                        <i class='bx bx-check align-middle'></i>
                                        <span class="ml-1.5">Recommend</span>
                                    </button>
                                    <button onclick="handleRequest(${data.id}, 'decline')"
                                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-red-600 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                        <i class='bx bx-x align-middle'></i>
                                        <span class="ml-1.5">Decline</span>
                                    </button>
                                ` : ''}
                                ${data.status === 'pending_testing_setup' ? `
                                    <button onclick="handleTestingSetup(${data.id})"
                                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-blue-600 bg-blue-50 hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <i class='bx bx-test-tube align-middle'></i>
                                        <span class="ml-1.5">Send Testing Instructions</span>
                                    </button>
                                ` : ''}
                                ${data.status === 'pending_testing_review' && data.testing_status === 'failed' ? `
                                    <button onclick="showActionModal(${data.id}, 'approve')" 
                                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-yellow-600 bg-yellow-50 hover:bg-yellow-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500">
                                        <i class='bx bx-refresh'></i>
                                        <span class="ml-1">Send for Retest</span>
                                    </button>
                                    <button onclick="showActionModal(${data.id}, 'decline')" 
                                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-red-600 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                        <i class='bx bx-x'></i>
                                        <span class="ml-1">Reject Access</span>
                                    </button>
                                ` : ''}
                            </div>
                        </div>
                    `;
                })
                .catch(error => {
                    console.error('Error:', error);
                    modalContainer.innerHTML = `
                        <div class="text-center py-8">
                            <div class="text-red-600 mb-2">
                                <i class='bx bx-error-circle text-3xl'></i>
                            </div>
                            <p class="text-red-600 font-medium">Error loading request details</p>
                            <p class="text-gray-500 text-sm mt-1">${error.message}</p>
                            <button onclick="hideDetailsModal()" 
                                    class="mt-4 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                                Close
                            </button>
                        </div>
                    `;
                });
        }

        function hideDetailsModal() {
            document.getElementById('detailsModal').classList.add('hidden');
        }

        // Close modal when clicking outside
        document.getElementById('detailsModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideDetailsModal();
            }
        });

        function handleTestingSetup(requestId) {
            Swal.fire({
                title: 'Send Testing Instructions',
                input: 'textarea',
                inputLabel: 'Testing Instructions for Requestor',
                inputPlaceholder: 'Enter detailed instructions for the requestor to test the access...',
                inputAttributes: {
                    'aria-label': 'Testing instructions',
                    'rows': '6'
                },
                showCancelButton: true,
                confirmButtonText: 'Send to Requestor',
                confirmButtonColor: '#3085d6',
                showLoaderOnConfirm: true,
                preConfirm: (instructions) => {
                    if (!instructions || instructions.trim() === '') {
                        Swal.showValidationMessage('Please enter testing instructions');
                        return false;
                    }

                    return fetch('../admin/process_request.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `request_id=${requestId}&action=approve&review_notes=${encodeURIComponent(instructions)}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (!data.success) {
                                throw new Error(data.message || 'Error processing request');
                            }
                            return data;
                        })
                        .catch(error => {
                            Swal.showValidationMessage(error.message);
                        });
                },
                allowOutsideClick: () => !Swal.isLoading()
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Success!',
                        text: 'Testing instructions have been sent to the requestor.',
                        icon: 'success'
                    }).then(() => {
                        window.location.reload();
                    });
                }
            });
        }

        function handleRequest(requestId, action) {
            if (action === 'approve') {
                Swal.fire({
                    title: 'Forward Request',
                    html: `
                        <div class="mb-4">
                            <label for="forward-to" class="block text-sm font-medium text-gray-700 mb-1">Forward to:</label>
                            <select id="forward-to" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" onchange="updateUserDropdown()">
                                <option  value="technical">Technical Support</option>
                                <option  value="process_owner">Process Owner</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label for="user-id" class="block text-sm font-medium text-gray-700 mb-1">Select User:</label>
                            <select id="user-id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                <option value="">Loading users...</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label for="review-notes" class="block text-sm font-medium text-gray-700 mb-1">Review Notes:</label>
                            <textarea id="review-notes" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" placeholder="Enter your review notes..."></textarea>
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: 'Forward Request',
                    confirmButtonColor: '#10B981',
                    showLoaderOnConfirm: true,
                    didOpen: () => {
                        // Load technical support users by default
                        updateUserDropdown();
                    },
                    preConfirm: () => {
                        const forwardTo = document.getElementById('forward-to').value;
                        const userId = document.getElementById('user-id').value;
                        const notes = document.getElementById('review-notes').value;

                        if (!notes) {
                            Swal.showValidationMessage('Please enter review notes');
                            return false;
                        }

                        if (!userId) {
                            Swal.showValidationMessage('Please select a user');
                            return false;
                        }

                        return fetch('../admin/process_request.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: `request_id=${requestId}&action=${action}&review_notes=${encodeURIComponent(notes)}&forward_to=${forwardTo}&user_id=${userId}`
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (!data.success) {
                                    throw new Error(data.message || 'Error processing request');
                                }
                                return data;
                            })
                            .catch(error => {
                                Swal.showValidationMessage(error.message);
                            });
                    },
                    allowOutsideClick: () => !Swal.isLoading()
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Success!',
                            text: result.value.message,
                            icon: 'success'
                        }).then(() => {
                            window.location.reload();
                        });
                    }
                });
            } else {
                // Handle decline case (existing code)
                Swal.fire({
                    title: 'Decline Request?',
                    input: 'textarea',
                    inputLabel: 'Review Notes',
                    inputPlaceholder: 'Enter your review notes...',
                    inputAttributes: {
                        'aria-label': 'Review notes'
                    },
                    showCancelButton: true,
                    confirmButtonText: 'Decline',
                    confirmButtonColor: '#EF4444',
                    showLoaderOnConfirm: true,
                    preConfirm: (notes) => {
                        if (!notes) {
                            Swal.showValidationMessage('Please enter review notes');
                            return false;
                        }

                        return fetch('../admin/process_request.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: `request_id=${requestId}&action=${action}&review_notes=${encodeURIComponent(notes)}`
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (!data.success) {
                                    throw new Error(data.message || 'Error processing request');
                                }
                                return data;
                            })
                            .catch(error => {
                                Swal.showValidationMessage(error.message);
                            });
                    },
                    allowOutsideClick: () => !Swal.isLoading()
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Success!',
                            text: result.value.message,
                            icon: 'success'
                        }).then(() => {
                            window.location.reload();
                        });
                    }
                });
            }
        }

        // Function to update the user dropdown based on the selected role
        function updateUserDropdown() {
            const forwardTo = document.getElementById('forward-to').value;
            const userDropdown = document.getElementById('user-id');

            // Set loading state
            userDropdown.innerHTML = '<option value="">Loading users...</option>';

            // Determine the role to fetch
            const role = forwardTo === 'technical' ? 'technical_support' : 'process_owner';

            console.log('Fetching users with role:', role);

            // Fetch users with the selected role
            fetch(`fetch_users_by_role.php?role=${role}`)
                .then(response => response.json())
                .then(data => {
                    console.log('API response:', data);

                    if (data.success) {
                        // Clear dropdown
                        userDropdown.innerHTML = '';

                        // Add a default option
                        const defaultOption = document.createElement('option');
                        defaultOption.value = '';
                        defaultOption.textContent = `Select a ${forwardTo === 'technical' ? 'Technical Support' : 'Process Owner'} user...`;
                        userDropdown.appendChild(defaultOption);

                        // Add user options
                        if (data.users && data.users.length > 0) {
                            console.log('Users found:', data.users.length);
                            data.users.forEach(user => {
                                console.log('Adding user:', user);
                                if (user && user.employee_id && user.employee_name) {
                                    const option = document.createElement('option');
                                    option.value = user.employee_id;
                                    option.textContent = user.employee_name;
                                    userDropdown.appendChild(option);
                                } else {
                                    console.warn('Skipping invalid user data:', user);
                                }
                            });
                        } else {
                            // No users found
                            console.log('No users found with role:', role);
                            const noUsersOption = document.createElement('option');
                            noUsersOption.value = '';
                            noUsersOption.textContent = `No ${forwardTo === 'technical' ? 'Technical Support' : 'Process Owner'} users found`;
                            userDropdown.appendChild(noUsersOption);
                        }
                    } else {
                        // Error fetching users
                        console.error('Error from API:', data.message);
                        userDropdown.innerHTML = `<option value="">Error loading users: ${data.message}</option>`;
                    }
                })
                .catch(error => {
                    console.error('Error fetching users:', error);
                    userDropdown.innerHTML = '<option value="">Error loading users</option>';
                });
        }

        function showActionModal(requestId, action) {
            const title = action === 'approve' ? 'Send for Retest?' : 'Reject Access Request?';
            const buttonText = action === 'approve' ? 'Send for Retest' : 'Reject Access';
            const buttonColor = action === 'approve' ? '#EAB308' : '#EF4444';
            const placeholder = action === 'approve' ?
                'Enter instructions for retesting...' :
                'Enter reason for rejection...';

            Swal.fire({
                title: title,
                input: 'textarea',
                inputLabel: 'Technical Review Notes',
                inputPlaceholder: placeholder,
                inputAttributes: {
                    'aria-label': 'Technical review notes',
                    'rows': '4'
                },
                showCancelButton: true,
                confirmButtonText: buttonText,
                confirmButtonColor: buttonColor,
                cancelButtonText: 'Cancel',
                showLoaderOnConfirm: true,
                preConfirm: (notes) => {
                    if (!notes || notes.trim() === '') {
                        Swal.showValidationMessage('Please enter review notes');
                        return false;
                    }

                    return fetch('../admin/process_request.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `request_id=${requestId}&action=${action}&review_notes=${encodeURIComponent(notes)}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (!data.success) {
                                throw new Error(data.message || 'Error processing request');
                            }
                            return data;
                        })
                        .catch(error => {
                            Swal.showValidationMessage(error.message);
                        });
                },
                allowOutsideClick: () => !Swal.isLoading()
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        icon: 'success',
                        title: action === 'approve' ? 'Request Sent for Retesting!' : 'Request Rejected',
                        text: action === 'approve' ?
                            'The request has been sent back to the user for retesting.' : 'The request has been rejected.',
                        confirmButtonColor: '#3085d6',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        window.location.reload();
                    });
                }
            });
        }
    </script>
</body>

</html>
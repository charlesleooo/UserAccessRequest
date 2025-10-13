<?php
session_start();
require_once '../config.php';

// Check if technical support is logged in
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'technical_support') {
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

// Get the admin_users.id for this user to ensure correct filtering
$adminQuery = $pdo->prepare("SELECT id FROM admin_users WHERE username = :username OR username = :employee_id");
$adminQuery->execute([
    'username' => $_SESSION['admin_username'] ?? '',
    'employee_id' => $_SESSION['admin_id'] ?? ''
]);
$adminRecord = $adminQuery->fetch(PDO::FETCH_ASSOC);
$admin_users_id = $adminRecord ? $adminRecord['id'] : null;

// Use admin_users.id instead of session admin_id
$current_admin_id = $admin_users_id;

// Get all requests pending technical review assigned to this specific user ONLY
try {
    $sql = "SELECT ar.*, 
            CASE 
                WHEN ar.status = 'pending_superior' THEN 'Pending Superior Review'
                WHEN ar.status = 'pending_help_desk' THEN 'Pending Help Desk Review'
                WHEN ar.status = 'pending_technical' THEN 'Pending Your Review'
                WHEN ar.status = 'pending_process_owner' THEN 'Pending Process Owner Review'
                WHEN ar.status = 'pending_admin' THEN 'Pending Admin Review'
                WHEN ar.status = 'pending_testing_review' THEN 'Pending Testing Review'
                WHEN ar.status = 'pending_testing_setup' THEN 'Pending Testing Setup'
                WHEN ar.status = 'approved' THEN 'Approved'
                WHEN ar.status = 'rejected' THEN 'Rejected'
                ELSE ar.status
            END as status_display,
            (SELECT COALESCE(
                (SELECT date_needed FROM individual_requests WHERE access_request_number = ar.access_request_number LIMIT 1),
                (SELECT date_needed FROM group_requests WHERE access_request_number = ar.access_request_number LIMIT 1)
            )) as date_needed
            FROM access_requests ar 
            WHERE (ar.status IN ('pending_technical', 'pending_testing_review') AND ar.technical_id = :current_admin_id)
            OR (ar.status = 'pending_testing_setup')
            ORDER BY ar.submission_date DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['current_admin_id' => $current_admin_id]);
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
    <title>Technical Support - Pending Reviews</title>
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
                    <h1 class="text-2xl font-bold text-white">Technical Reviews</h1>
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
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Days Pending</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Needed</th>
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
                                                <?php
                                                $submission_date = new DateTime($request['submission_date']);
                                                $today = new DateTime();
                                                $interval = $submission_date->diff($today);
                                                echo $interval->days . ' day/s';
                                                ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" data-label="Date Needed">
                                                <?php echo !empty($request['date_needed']) ? date('M d, Y', strtotime($request['date_needed'])) : 'N/A'; ?>
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
                                        <td colspan="8" class="px-6 py-4 text-center text-gray-500">
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

    <!-- Details Modal -->
    <div id="detailsModal" class="absolute top-0 left-0 w-full bg-gray-500 bg-opacity-75 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-xl w-[90%] max-w-7xl mx-auto shadow-xl">
                <div class="flex items-center px-6 py-4 border-b border-gray-200">
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
                <div class="p-6">
                    <div id="detailsModalContent">
                        <!-- Modal content will be populated by JavaScript -->
                    </div>
                    <div id="modalActions" class="mt-6 flex justify-end space-x-3 border-t border-gray-200 pt-4">
                        <button onclick="handleRequest(currentRequestId, 'approve')"
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-green-600 bg-green-50 hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                            <i class='bx bx-check align-middle'></i>
                            <span class="ml-1.5">Approve</span>
                        </button>
                        <button onclick="handleRequest(currentRequestId, 'decline')"
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-red-600 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                            <i class='bx bx-x align-middle'></i>
                            <span class="ml-1.5">Decline</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentRequestId = null;

        // Function to check URL parameters
        function getUrlParameter(name) {
            name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
            var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
            var results = regex.exec(location.search);
            return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
        }

        // Check if we should show the modal on page load
        window.addEventListener('DOMContentLoaded', function() {
            const requestId = getUrlParameter('id');
            const showModal = getUrlParameter('show_modal');

            if (requestId && showModal === 'true') {
                showRequestDetails(requestId);
                // Remove the parameters from URL without refreshing the page
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        });

        function showRequestDetails(requestId) {
            currentRequestId = requestId;
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
                    modalContainer.innerHTML = `
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <!-- Request Overview -->
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                                    <i class='bx bx-info-circle text-primary-600 text-xl mr-2'></i>
                                    Request Overview
                                </h3>
                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Request Number:</span>
                                        <span class="font-medium text-gray-900">${data.access_request_number}</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-600">Status:</span>
                                        <div class="flex items-center ${
                                            data.status.includes('pending') ? 'bg-yellow-50' : 
                                            (data.status === 'approved' ? 'bg-green-50' : 'bg-red-50')
                                        } rounded-lg px-2 py-1">
                                            <span class="px-3 py-1 text-xs font-medium rounded-full ${
                                                data.status.includes('pending') ? 'bg-yellow-100 text-yellow-700' : 
                                                (data.status === 'approved' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700')
                                            }">
                                                ${data.status_display}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Submitted:</span>
                                        <span class="font-medium text-gray-900">
                                            ${new Date(data.submission_date).toLocaleString()}
                                        </span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Duration:</span>
                                        <span class="font-medium text-gray-900">
                                            ${data.duration_type === 'permanent' ? 'Permanent' : 
                                            `${new Date(data.start_date).toLocaleDateString()} - ${new Date(data.end_date).toLocaleDateString()}`}
                                        </span>
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
                                        <span class="font-medium text-gray-900">${data.requestor_name}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Business Unit:</span>
                                        <span class="font-medium text-gray-900">${data.business_unit}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Department:</span>
                                        <span class="font-medium text-gray-900">${data.department}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Email:</span>
                                        <span class="font-medium text-gray-900">${data.email}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Employee ID:</span>
                                        <span class="font-medium text-gray-900">${data.employee_id}</span>
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
                                        <span class="font-medium text-gray-900">${data.access_type}</span>
                                    </div>
                                    ${data.system_type ? `
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">System Type:</span>
                                        <span class="font-medium text-gray-900">${data.system_type}</span>
                                    </div>
                                    ` : ''}
                                    ${data.other_system_type ? `
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Other System:</span>
                                        <span class="font-medium text-gray-900">${data.other_system_type}</span>
                                    </div>
                                    ` : ''}
                                    ${data.role_access_type ? `
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Role Access Type:</span>
                                        <span class="font-medium text-gray-900">${data.role_access_type}</span>
                                    </div>
                                    ` : ''}
                                </div>
                            </div>
                        </div>
                        
                        <!-- Justification -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mt-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                                <i class='bx bx-comment-detail text-primary-600 text-xl mr-2'></i>
                                Justification
                            </h3>
                            <div class="bg-gray-50 p-4 rounded-lg text-gray-700">
                                ${data.justification || 'No justification provided.'}
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
                                            <span class="text-sm font-medium ${
                                                review.action === 'Declined' ? 'text-red-600' : 
                                                (review.action === 'Approved' ? 'text-green-600' : 'text-primary-600')
                                            }">${review.action}</span>
                                        </div>
                                        <p class="text-gray-700 text-sm">${review.note}</p>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                        ` : ''}
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

        function handleRequest(requestId, action) {
            Swal.fire({
                title: action === 'approve' ? 'Approve Request?' : 'Decline Request?',
                input: 'textarea',
                inputLabel: 'Review Notes',
                inputPlaceholder: 'Enter your review notes...',
                inputAttributes: {
                    'aria-label': 'Review notes'
                },
                showCancelButton: true,
                confirmButtonText: action === 'approve' ? 'Approve' : 'Decline',
                confirmButtonColor: action === 'approve' ? '#10B981' : '#EF4444',
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

        // Add new function for handling test review
        function handleTestReview(requestId, action, notes) {
            return fetch('../api/handle_test_review.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        request_id: requestId,
                        action: action,
                        notes: notes
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            title: 'Success!',
                            text: data.message,
                            icon: 'success'
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        throw new Error(data.message);
                    }
                })
                .catch(error => {
                    Swal.fire({
                        title: 'Error!',
                        text: error.message,
                        icon: 'error'
                    });
                });
        }

        // Update the showActionModal function to handle test reviews
        function showActionModal(requestId, action) {
            const request = requests.find(r => r.id === requestId);
            if (!request) return;

            let title, buttonText, buttonColor, placeholder;

            if (request.status === 'pending_testing_review') {
                if (action === 'approve') {
                    title = 'Approve Test Results?';
                    buttonText = 'Approve & Forward to Admin';
                    buttonColor = '#10B981';
                    placeholder = 'Enter any additional notes about the test results...';
                } else {
                    title = 'Request Retest?';
                    buttonText = 'Send for Retest';
                    buttonColor = '#EAB308';
                    placeholder = 'Enter instructions for retesting...';
                }
            } else {
                title = action === 'approve' ? 'Approve Request?' : 'Reject Request?';
                buttonText = action === 'approve' ? 'Approve Request' : 'Reject Request';
                buttonColor = action === 'approve' ? '#10B981' : '#EF4444';
                placeholder = action === 'approve' ? 'Enter any notes...' : 'Enter reason for rejection...';
            }

            Swal.fire({
                title: title,
                input: 'textarea',
                inputPlaceholder: placeholder,
                showCancelButton: true,
                confirmButtonText: buttonText,
                confirmButtonColor: buttonColor,
                showLoaderOnConfirm: true,
                preConfirm: (notes) => {
                    if (request.status === 'pending_testing_review') {
                        return handleTestReview(requestId, action, notes);
                    } else {
                        return handleRequest(requestId, action, notes);
                    }
                }
            });
        }
    </script>
</body>
</html>

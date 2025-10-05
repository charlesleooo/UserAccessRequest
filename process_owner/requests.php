<?php
session_start();
require_once '../config.php';

// Check if process owner is logged in
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'process_owner') {
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

// Get all requests pending process owner review
try {
    $sql = "SELECT ar.*, 
            CASE 
                WHEN ar.status = 'pending_superior' THEN 'Pending Superior Review'
                WHEN ar.status = 'pending_technical' THEN 'Pending Technical Review'
                WHEN ar.status = 'pending_process_owner' THEN 'Pending Your Review'
                WHEN ar.status = 'pending_admin' THEN 'Pending Admin Review'
                WHEN ar.status = 'approved' THEN 'Approved'
                WHEN ar.status = 'rejected' THEN 'Rejected'
                ELSE ar.status
            END as status_display
            FROM access_requests ar 
            WHERE ar.status = 'pending_process_owner'
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
    <title>Process Owner - Pending Reviews</title>
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
                    <h1 class="text-2xl font-bold text-gray-800">Process Reviews</h1>
                    <p class="text-gray-600 mt-1">Review and approve access requests from a business process perspective</p>
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
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"></th>
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
                                                <?php echo date('M d, Y', strtotime($request['date_needed'])); ?>
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

                    // Display previous reviewer comments directly if they exist
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

                    let technicalComments = '';
                    if (data.technical_review_notes && data.technical_review_notes.trim() !== '') {
                        technicalComments = `
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mt-6">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                                    <i class='bx bx-code-alt text-primary-600 text-xl mr-2'></i>
                                    Technical Review Comments
                                </h3>
                                <div class="bg-gray-50 p-4 rounded-lg text-gray-700">
                                    ${data.technical_review_notes}
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
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Request Number:</span>
                                        <span class="font-medium text-gray-900">${data.access_request_number}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Status:</span>
                                        <span class="font-medium text-gray-900">${data.status_display}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Submitted:</span>
                                        <span class="font-medium text-gray-900">${new Date(data.submission_date).toLocaleString()}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Duration:</span>
                                        <span class="font-medium text-gray-900">${data.duration_type === 'permanent' ? 'Permanent' : 'Temporary'}</span>
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
                                        <span class="font-medium text-gray-900">${data.email || 'N/A'}</span>
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
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">System Type:</span>
                                        <span class="font-medium text-gray-900">${data.system_type || 'N/A'}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Role Access Type:</span>
                                        <span class="font-medium text-gray-900">${data.role_access_type || 'N/A'}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Justification -->
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 lg:col-span-3">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                                    <i class='bx bx-message-square-detail text-primary-600 text-xl mr-2'></i>
                                    Justification
                                </h3>
                                <p class="text-gray-700 whitespace-pre-wrap">${data.justification}</p>
                            </div>

                            ${data.review_history && data.review_history.length > 0 ? `
                            <!-- Review History -->
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 lg:col-span-3">
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
                        </div>

                        ${data.status === 'pending_process_owner' ? `
                        <!-- Action Buttons -->
                        <div class="mt-6 flex justify-end space-x-3 border-t border-gray-200 pt-4">
                            <button onclick="handleRequest(${data.id}, 'approve')"
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-green-600 bg-green-50 hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                <i class='bx bx-check align-middle'></i>
                                <span class="ml-1.5">Forward</span>
                            </button>
                            <button onclick="handleRequest(${data.id}, 'decline')"
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-red-600 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                <i class='bx bx-x align-middle'></i>
                                <span class="ml-1.5">Decline</span>
                            </button>
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
                title: action === 'approve' ? 'Recommend Request?' : 'Decline Request?',
                input: 'textarea',
                inputLabel: 'Process Review Notes',
                inputPlaceholder: 'Enter your process review notes...',
                inputAttributes: {
                    'aria-label': 'Process review notes'
                },
                showCancelButton: true,
                confirmButtonText: action === 'approve' ? 'Recommend' : 'Decline',
                confirmButtonColor: action === 'approve' ? '#10B981' : '#EF4444',
                showLoaderOnConfirm: true,
                preConfirm: (notes) => {
                    if (!notes) {
                        Swal.showValidationMessage('Please enter process review notes');
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
    </script>
</body>

</html>
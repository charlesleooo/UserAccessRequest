<?php
session_start();
require_once '../config.php';

// Check if superior is logged in
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'superior') {
    header('Location: ../admin/login.php');
    exit();
}

// Get all requests pending superior review
try {
    $sql = "SELECT ar.*, 
            CASE 
                WHEN ar.status = 'pending_superior' THEN 'Pending Your Review'
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
                    
                    <a href="#" class="flex items-center px-4 py-3 text-primary-600 bg-primary-50 rounded-xl">
                        <span class="flex items-center justify-center w-9 h-9 bg-primary-100 text-primary-600 rounded-lg">
                            <i class='bx bxs-message-square-detail text-xl'></i>
                        </span>
                        <span class="ml-3 font-medium">Requests</span>
                    </a>
                    
                    <a href="review_history.php" class="flex items-center px-4 py-3 text-gray-700 rounded-xl hover:bg-gray-50">
                        <span class="flex items-center justify-center w-9 h-9 bg-gray-100 text-gray-600 rounded-lg">
                            <i class='bx bx-history text-xl'></i>
                        </span>
                        <span class="ml-3">Review History</span>
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
                    <h1 class="text-2xl font-bold text-gray-800">Pending Requests</h1>
                    <p class="text-gray-600 mt-1">Review and manage access requests</p>
                </div>
            </div>

            <!-- Content -->
            <div class="p-8">
                <div class="bg-white rounded-xl shadow overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Request No.</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Requestor</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Access Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (!empty($requests)): ?>
                                    <?php foreach ($requests as $request): ?>
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
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                    <?php echo htmlspecialchars($request['status_display']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                                <button onclick="showRequestDetails(<?php echo $request['id']; ?>)" 
                                                        class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm font-medium rounded-lg text-primary-600 bg-primary-50 hover:bg-primary-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                                    <i class='bx bx-show align-middle'></i>
                                                    <span class="ml-1.5">View</span>
                                                </button>
                                                <button onclick="handleRequest(<?php echo $request['id']; ?>, 'approve')"
                                                        class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm font-medium rounded-lg text-green-600 bg-green-50 hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                                    <i class='bx bx-check align-middle'></i>
                                                    <span class="ml-1.5">Recommend</span>
                                                </button>
                                                <button onclick="handleRequest(<?php echo $request['id']; ?>, 'decline')"
                                                        class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm font-medium rounded-lg text-red-600 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                                    <i class='bx bx-x align-middle'></i>
                                                    <span class="ml-1.5">Decline</span>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">
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
                        
                        ${data.testing_status ? `
                        <!-- Testing Status -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mt-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                                <i class='bx bx-test-tube text-primary-600 text-xl mr-2'></i>
                                Testing Status
                            </h3>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600">Status:</span>
                                <span class="px-3 py-1 text-xs font-medium rounded-full ${
                                    data.testing_status === 'success' ? 'bg-green-100 text-green-700' :
                                    data.testing_status === 'failed' ? 'bg-red-100 text-red-700' :
                                    'bg-yellow-100 text-yellow-700'
                                }">
                                    ${data.testing_status.charAt(0).toUpperCase() + data.testing_status.slice(1)}
                                </span>
                            </div>
                            ${data.testing_notes ? `
                            <div class="mt-4">
                                <span class="text-gray-600">Testing Notes:</span>
                                <div class="mt-2 bg-gray-50 p-4 rounded-lg text-gray-700">
                                    ${data.testing_notes}
                                </div>
                            </div>
                            ` : ''}
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
                inputLabel: 'Review Notes',
                inputPlaceholder: 'Enter your review notes...',
                inputAttributes: {
                    'aria-label': 'Review notes'
                },
                showCancelButton: true,
                confirmButtonText: action === 'approve' ? 'Recommend' : 'Decline',
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
    </script>
</body>
</html>

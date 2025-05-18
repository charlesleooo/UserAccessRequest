<?php
session_start();
require_once '../config.php';


if (!isset($_SESSION['requestor_id'])) {
    header("Location: login.php");
    exit();
}

$requestorId = $_SESSION['requestor_id'];
$username = $_SESSION['username'] ?? 'User';

try {
    $stmt = $pdo->prepare("SELECT 
        COUNT(*) as total,
        SUM(status = 'approved') as approved,
        SUM(status = 'rejected') as rejected,
        SUM(status = 'pending') as pending
        FROM access_requests
        WHERE employee_id = ?");
    $stmt->execute([$requestorId]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    $total = $data['total'] ?? 0;
    $approved = $data['approved'] ?? 0;
    $rejected = $data['rejected'] ?? 0;
    $pending = $data['pending'] ?? 0;

    $approvalRate = $total > 0 ? round(($approved / $total) * 100, 1) : 0;
    $declineRate = $total > 0 ? round(($rejected / $total) * 100, 1) : 0;

    $stmt = $pdo->prepare("SELECT * FROM access_requests WHERE employee_id = ? ORDER BY submission_date DESC LIMIT 5");
    $stmt->execute([$requestorId]);
    $recentRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $total = $approved = $rejected = $pending = 0;
    $approvalRate = $declineRate = 0;
    $recentRequests = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Requestor Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            DEFAULT: '#0084FF',
                            dark: '#006ACC',
                        },
                        danger: {
                            DEFAULT: '#dc3545',
                            dark: '#c82333',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-100">

<!-- Sidebar -->
<div class="fixed inset-y-0 left-0 z-50 w-72 bg-white shadow-lg transform transition-transform duration-300">
    <div class="flex flex-col h-full">
        <div class="text-center mt-4">
            <img src="../logo.png" alt="Logo" class="w-48 mx-auto">
        </div>
        <nav class="flex-1 pt-6 px-4 space-y-1 overflow-y-auto">
            <a href="dashboard.php" class="flex items-center px-4 py-3 text-indigo-600 bg-indigo-50 rounded-xl transition hover:bg-indigo-100 group">
                <span class="flex items-center justify-center w-9 h-9 bg-indigo-100 text-indigo-600 rounded-lg">
                    <i class='bx bxs-dashboard text-xl'></i>
                </span>
                <span class="ml-3 font-medium">Dashboard</span>
            </a>
            <a href="create_request.php" class="flex items-center px-4 py-3 text-gray-700 rounded-xl transition hover:bg-gray-50 group">
                <span class="flex items-center justify-center w-9 h-9 bg-gray-100 text-gray-600 rounded-lg">
                    <i class='bx bx-send text-xl'></i>
                </span>
                <span class="ml-3">Create Request</span>
            </a>
            <a href="my_requests.php" class="flex items-center px-4 py-3 text-gray-700 rounded-xl transition hover:bg-gray-50 group">
                <span class="flex items-center justify-center w-9 h-9 bg-gray-100 text-gray-600 rounded-lg">
                    <i class='bx bx-list-ul text-xl'></i>
                </span>
                <span class="ml-3">My Requests</span>
            </a>
            <a href="request_history.php" class="flex items-center px-4 py-3 text-gray-700 rounded-xl transition hover:bg-gray-50 group">
                <span class="flex items-center justify-center w-9 h-9 bg-gray-100 text-gray-600 rounded-lg">
                    <i class='bx bx-history text-xl'></i>
                </span>
                <span class="ml-3">Request History</span>
            </a>
        </nav>

        <div class="p-4 border-t border-gray-100">
            <a href="logout.php" class="flex items-center px-4 py-3 text-red-600 bg-red-50 rounded-xl transition hover:bg-red-100 group">
                <span class="flex items-center justify-center w-9 h-9 bg-red-100 text-red-600 rounded-lg">
                    <i class='bx bx-log-out text-xl'></i>
                </span>
                <span class="ml-3 font-medium">Logout</span>
            </a>
        </div>

        <div class="px-4 py-4 border-t border-gray-100">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600">
                    <i class='bx bxs-user text-xl'></i>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($username); ?></p>
                    <p class="text-xs text-gray-500">Requestor</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="flex-1 ml-72">
    <!-- Header -->
    <div class="bg-white border-b border-gray-200 sticky top-0 z-10 shadow-sm">
        <div class="flex justify-between items-center px-8 py-4">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">User Access Request System</h2>
                <p class="text-gray-600 text-xl mt-1">Welcome back <?php echo htmlspecialchars($username); ?></p>
            </div>
        </div>
    </div>

    <div class="p-8">
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-xl border border-gray-200 p-6 flex items-center">
                <div class="bg-blue-50 p-3 rounded-lg">
                    <i class='bx bx-folder text-2xl text-blue-500'></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-500">Total Requests</p>
                    <h4 class="text-2xl font-bold text-gray-900"><?php echo $total; ?></h4>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-6 flex items-center">
                <div class="bg-green-50 p-3 rounded-lg">
                    <i class='bx bx-check-circle text-2xl text-green-500'></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-500">Approved</p>
                    <h4 class="text-2xl font-bold text-gray-900"><?php echo $approved; ?></h4>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-6 flex items-center">
                <div class="bg-yellow-50 p-3 rounded-lg">
                    <i class='bx bx-time text-2xl text-yellow-500'></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-500">Pending</p>
                    <h4 class="text-2xl font-bold text-gray-900"><?php echo $pending; ?></h4>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-6 flex items-center">
                <div class="bg-red-50 p-3 rounded-lg">
                    <i class='bx bx-x-circle text-2xl text-red-500'></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-500">Rejected</p>
                    <h4 class="text-2xl font-bold text-gray-900"><?php echo $rejected; ?></h4>
                </div>
            </div>
        </div>

        <!-- Requests Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
                <h2 class="text-lg font-medium text-gray-800">My Access Requests</h2>
                <a href="create_request.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                    <i class='bx bx-plus mr-2'></i> New Request
                </a>
            </div>
            
            <!-- Pending Testing Requests Section -->
            <?php
            // Get pending testing requests for this user
            $pendingTestingRequestsQuery = "SELECT * FROM access_requests 
                                            WHERE employee_id = :employee_id 
                                            AND status = 'pending_testing' 
                                            ORDER BY submission_date DESC";
            $stmt = $pdo->prepare($pendingTestingRequestsQuery);
            $stmt->execute(['employee_id' => $requestorId]);
            $pendingTestingRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get regular requests
            $requestsQuery = "SELECT * FROM access_requests 
                            WHERE employee_id = :employee_id 
                            AND status != 'pending_testing'
                            ORDER BY submission_date DESC";
            $stmt = $pdo->prepare($requestsQuery);
            $stmt->execute(['employee_id' => $requestorId]);
            $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($pendingTestingRequests) > 0):
            ?>
            <div class="p-4 bg-yellow-50 border-b border-yellow-100">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <i class='bx bx-test-tube text-yellow-600 text-xl'></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-yellow-800">Testing Required</h3>
                        <div class="mt-2 text-sm text-yellow-700">
                            <p>The following requests require testing before final approval. Please test your access and update the status:</p>
                        </div>
                        
                        <div class="mt-4 space-y-4">
                            <?php foreach ($pendingTestingRequests as $request): ?>
                            <div class="bg-white p-4 rounded-lg border border-yellow-200 flex justify-between items-center">
                                <div>
                                    <div class="flex items-center">
                                        <span class="font-medium text-gray-900"><?php echo htmlspecialchars($request['access_request_number']); ?></span>
                                        <span class="ml-2 px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            Testing Required
                                        </span>
                                    </div>
                                    <div class="mt-1 text-sm text-gray-600">
                                        <span><?php echo htmlspecialchars($request['access_type']); ?></span>
                                        <?php if ($request['system_type']): ?>
                                        <span class="mx-1">•</span>
                                        <span><?php echo htmlspecialchars($request['system_type']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($request['testing_status'] === 'pending'): ?>
                                    <div class="mt-2 text-xs text-yellow-700">
                                        <i class='bx bx-info-circle'></i> Please test your access and provide feedback
                                    </div>
                                    <?php elseif ($request['testing_status'] === 'success'): ?>
                                    <div class="mt-2 text-xs text-green-700">
                                        <i class='bx bx-check-circle'></i> Testing successful - awaiting final approval
                                    </div>
                                    <?php elseif ($request['testing_status'] === 'failed'): ?>
                                    <div class="mt-2 text-xs text-red-700">
                                        <i class='bx bx-x-circle'></i> Testing failed - awaiting admin response
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <?php if ($request['testing_status'] === 'pending'): ?>
                                    <a href="testing_status.php?id=<?php echo $request['id']; ?>" 
                                       class="inline-flex items-center px-3 py-1 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <i class='bx bx-test-tube mr-1'></i> Test
                                    </a>
                                    <?php elseif ($request['testing_status'] === 'success' || $request['testing_status'] === 'failed'): ?>
                                    <span class="inline-flex items-center px-3 py-1 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-gray-100">
                                        Awaiting Admin Review
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Regular Requests Table -->
            <div class="flex flex-col">
                <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                    <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                        <div class="shadow overflow-hidden border-b border-gray-200 sm:rounded-lg">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Request Number
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Access Type
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Status
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Submitted
                                        </th>
                                        <th scope="col" class="relative px-6 py-3">
                                            <span class="sr-only">Actions</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($requests as $request): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($request['access_request_number']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($request['access_type']); ?>
                                            <?php if ($request['system_type']): ?>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($request['system_type']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php 
                                            $statusClass = '';
                                            $status = strtolower($request['status']);
                                            
                                            switch ($status) {
                                                case 'pending_superior':
                                                    $statusClass = 'bg-yellow-100 text-yellow-800';
                                                    $displayStatus = 'Pending Superior Review';
                                                    break;
                                                case 'pending_technical':
                                                    $statusClass = 'bg-blue-100 text-blue-800';
                                                    $displayStatus = 'Pending Technical Review';
                                                    break;
                                                case 'pending_process_owner':
                                                    $statusClass = 'bg-indigo-100 text-indigo-800';
                                                    $displayStatus = 'Pending Process Owner Review';
                                                    break;
                                                case 'pending_admin':
                                                    $statusClass = 'bg-purple-100 text-purple-800';
                                                    $displayStatus = 'Pending Admin Review';
                                                    break;
                                                case 'pending_testing':
                                                    $statusClass = 'bg-cyan-100 text-cyan-800';
                                                    $displayStatus = 'Pending Testing';
                                                    break;
                                                case 'approved':
                                                    $statusClass = 'bg-green-100 text-green-800';
                                                    $displayStatus = 'Approved';
                                                    break;
                                                case 'rejected':
                                                    $statusClass = 'bg-red-100 text-red-800';
                                                    $displayStatus = 'Rejected';
                                                    break;
                                                default:
                                                    $statusClass = 'bg-gray-100 text-gray-800';
                                                    $displayStatus = ucfirst($status);
                                            }
                                            ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                                <?php echo $displayStatus; ?>
                                            </span>
                                            <?php if ($status === 'pending_testing'): ?>
                                            <span class="ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $request['testing_status'] === 'success' ? 'bg-green-100 text-green-800' : ($request['testing_status'] === 'failed' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800'); ?>">
                                                <?php echo ucfirst($request['testing_status']); ?>
                                            </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo date('M d, Y', strtotime($request['submission_date'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div class="flex justify-end gap-2">
                                                <button onclick="viewRequest(<?php echo $request['id']; ?>)" 
                                                        class="inline-flex items-center px-3 py-1 bg-gray-50 text-gray-700 rounded-lg hover:bg-gray-100">
                                                    <i class='bx bx-info-circle'></i>
                                                    <span class="ml-1">View</span>
                                                </button>
                                                <?php if ($status === 'pending_testing' && $request['testing_status'] === 'pending'): ?>
                                                <button onclick="updateTestingStatus(<?php echo $request['id']; ?>, 'success')" 
                                                        class="inline-flex items-center px-3 py-1 bg-green-50 text-green-700 rounded-lg hover:bg-green-100">
                                                    <i class='bx bx-check'></i>
                                                    <span class="ml-1">Testing Success</span>
                                                </button>
                                                <button onclick="updateTestingStatus(<?php echo $request['id']; ?>, 'failed')" 
                                                        class="inline-flex items-center px-3 py-1 bg-red-50 text-red-700 rounded-lg hover:bg-red-100">
                                                    <i class='bx bx-x'></i>
                                                    <span class="ml-1">Testing Failed</span>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add a workflow diagram to show the request flow -->
<div class="mt-8 p-4 bg-white shadow rounded-lg">
    <h3 class="text-lg font-medium text-gray-900 mb-4">Request Workflow</h3>
    <div class="flex items-center justify-between max-w-4xl mx-auto">
        <div class="flex flex-col items-center">
            <div class="w-12 h-12 rounded-full bg-yellow-100 flex items-center justify-center">
                <i class='bx bx-user text-yellow-600 text-xl'></i>
            </div>
            <span class="mt-2 text-sm font-medium text-gray-600">Superior</span>
            <span class="text-xs text-gray-500">Initial Review</span>
        </div>
        <div class="flex-1 h-0.5 bg-gray-200 relative">
            <div class="absolute -top-3 left-1/2 transform -translate-x-1/2">
                <i class='bx bx-right-arrow-alt text-gray-400'></i>
            </div>
        </div>
        <div class="flex flex-col items-center">
            <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center">
                <i class='bx bx-wrench text-blue-600 text-xl'></i>
            </div>
            <span class="mt-2 text-sm font-medium text-gray-600">Technical</span>
            <span class="text-xs text-gray-500">Technical Review</span>
        </div>
        <div class="flex-1 h-0.5 bg-gray-200 relative">
            <div class="absolute -top-3 left-1/2 transform -translate-x-1/2">
                <i class='bx bx-right-arrow-alt text-gray-400'></i>
            </div>
        </div>
        <div class="flex flex-col items-center">
            <div class="w-12 h-12 rounded-full bg-indigo-100 flex items-center justify-center">
                <i class='bx bx-briefcase text-indigo-600 text-xl'></i>
            </div>
            <span class="mt-2 text-sm font-medium text-gray-600">Process Owner</span>
            <span class="text-xs text-gray-500">Process Review</span>
        </div>
        <div class="flex-1 h-0.5 bg-gray-200 relative">
            <div class="absolute -top-3 left-1/2 transform -translate-x-1/2">
                <i class='bx bx-right-arrow-alt text-gray-400'></i>
            </div>
        </div>
        <div class="flex flex-col items-center">
            <div class="w-12 h-12 rounded-full bg-purple-100 flex items-center justify-center">
                <i class='bx bx-shield text-purple-600 text-xl'></i>
            </div>
            <span class="mt-2 text-sm font-medium text-gray-600">Admin</span>
            <span class="text-xs text-gray-500">Final Approval</span>
        </div>
    </div>
</div>

<!-- Add JavaScript for handling testing status updates -->
<script>
function updateTestingStatus(requestId, status) {
    Swal.fire({
        title: 'Update Testing Status',
        text: 'Please provide any notes about the testing process:',
        input: 'textarea',
        showCancelButton: true,
        confirmButtonText: 'Submit',
        showLoaderOnConfirm: true,
        preConfirm: (notes) => {
            return fetch('testing_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `request_id=${requestId}&testing_status=${status}&testing_notes=${encodeURIComponent(notes)}`,
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(response.statusText);
                }
                return response.json();
            })
            .catch(error => {
                Swal.showValidationMessage(`Request failed: ${error}`);
            });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Success!',
                text: 'Testing status has been updated.',
                icon: 'success'
            }).then(() => {
                window.location.reload();
            });
        }
    });
}

function viewRequest(requestId) {
    window.location.href = `view_request.php?id=${requestId}`;
}
</script>

</body>
</html>

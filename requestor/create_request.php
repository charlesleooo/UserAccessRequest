<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['requestor_id'])) {
    header("Location: login.php");
    exit();
}

$requestorId = $_SESSION['requestor_id'];
$username = $_SESSION['username'] ?? 'User';
?>
<?php
require_once '../config.php';



$requestorId = $_SESSION['requestor_id'];
$username = $_SESSION['username'] ?? 'User';

$businessUnits = [
    'AAC' => 'AAC',
    'ALDEV' => 'ALDEV',
    'ARC' => 'ARC',
    'FHI' => 'FHI',
    'SACI' => 'SACI',
    'SAVI' => 'SAVI',
    'SCCI' => 'SCCI',
    'SFC' => 'SFC'
];

$systemApplications = [
    'Active Directory Access',
    'Canvasing',
    'CCTV Access',
    'Email Access',
    'ERP/NAV',
    'Firewall Access',
    'Fresh Chilled Receiving System',
    'HRIS',
    'Internet Access',
    'Legacy Inventory',
    'Legacy Ledger System',
    'Legacy Payroll',
    'Legacy Purchasing',
    'Legacy Vouchering',
    'Memorandum Receipt',
    'Offsite Storage Facility Access',
    'PC Access - Local',
    'PC Access - Network',
    'Piece Rate Payroll System',
    'Printer Access',
    'Quickbooks',
    'Server Access',
    'TNA Biometric Device Access',
    'USB/PC-port Access',
    'VPN Access',
    'Wi-Fi/Access Point Access',
    'ZankPOS'
];

try {
    $stmt = $pdo->prepare("SELECT 
        COUNT(*) as total,
        SUM(status = 'approved') as approved,
        SUM(status = 'rejected') as rejected,
        SUM(status = 'pending') as pending
        FROM access_requests
        WHERE requestor_id = ?");
    $stmt->execute([$requestorId]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    $total = $data['total'] ?? 0;
    $approved = $data['approved'] ?? 0;
    $rejected = $data['rejected'] ?? 0;
    $pending = $data['pending'] ?? 0;

    $approvalRate = $total > 0 ? round(($approved / $total) * 100, 1) : 0;
    $declineRate = $total > 0 ? round(($rejected / $total) * 100, 1) : 0;

    $stmt = $pdo->prepare("SELECT * FROM access_requests WHERE requestor_id = ? ORDER BY submission_date DESC LIMIT 5");
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
    <title>Create Request</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f4f7fc;
            margin: 0;
            padding: 0;
        }

        .form-container {
            background-color: #ffffff;
            border: 1px solid #dcdcdc;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
            padding: 30px;
            max-width: 800px;
            margin: 40px auto;
        }

        input, select, button {
            font-family: 'Poppins', sans-serif;
        }
    </style>
</head>
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
            <a href="dashboard.php" class="flex items-center px-4 py-3 text-gray-700 rounded-xl transition hover:bg-gray-50 group">
                <span class="flex items-center justify-center w-9 h-9 bg-gray-100 text-gray-600 rounded-lg">
                    <i class='bx bxs-dashboard text-xl'></i>
                </span>
                <span class="ml-3">Dashboard</span>
            </a>
            <a href="create_request.php" class="flex items-center px-4 py-3 text-indigo-600 bg-indigo-50 rounded-xl transition hover:bg-indigo-100 group">
                <span class="flex items-center justify-center w-9 h-9 bg-indigo-100 text-indigo-600 rounded-lg">
                    <i class='bx bx-send text-xl'></i>
                </span>
                <span class="ml-3 font-medium">Create Request</span>
            </a>
            <a href="my_requests.php" class="flex items-center px-4 py-3 text-gray-700 rounded-xl transition hover:bg-gray-50 group">
                <span class="flex items-center justify-center w-9 h-9 bg-gray-100 text-gray-600 rounded-lg">
                    <i class='bx bx-list-ul text-xl'></i>
                </span>
                <span class="ml-3">My Requests</span>
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
                <h2 class="text-2xl font-bold text-gray-800">Create New Request</h2>
                <p class="text-gray-600 text-xl mt-1">Fill out the form below to submit a new access request</p>
            </div>
        </div>
    </div>

    <div class="p-5">
        <form id="uarForm" class="w-full">
            <!-- Requestor Information -->
            <div class="bg-primary text-white py-3 px-5 rounded mb-5 text-center text-base font-bold">Requestor Information</div>
            <div class="mb-8 w-full">
                <table class="w-full border-collapse mb-6 shadow-sm table-fixed">
                    <tr>
                        <td class="border border-gray-200 p-4 w-1/12"><strong><span class="after:content-['*'] after:text-red-500 after:ml-1">Name</span></strong>
                        <td class="border border-gray-200 p-4 w-5/12"><input type="text" name="name" class="w-full p-3 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition shadow-inner" placeholder="Enter your full name" required></td>
                        <td class="border border-gray-200 p-4 w-1/12"><strong><span class="after:content-['*'] after:text-red-500 after:ml-1">Date</span></strong></td>
                        <td class="border border-gray-200 p-4 w-5/12"><input type="text" name="date" value="<?php echo date('Y-m-d'); ?>" class="w-full p-3 border border-gray-300 rounded bg-gray-50 shadow-inner" readonly></td>
                    </tr>
                    <tr class="bg-gray-50">
                        <td class="border border-gray-200 p-4"><strong><span class="after:content-['*'] after:text-red-500 after:ml-1">Business Unit Entity</span></strong></td>
                        <td class="border border-gray-200 p-4">
                            <select name="business_unit" id="business_unit" class="w-full p-3 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition shadow-inner" required>
                                <option value="">Select Business Unit Entity</option>
                                <?php foreach ($businessUnits as $key => $value): ?>
                                    <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td class="border border-gray-200 p-4"><strong><span class="after:content-['*'] after:text-red-500 after:ml-1">Department</span></strong></td>
                        <td class="border border-gray-200 p-4">
                            <select name="department" id="department" class="w-full p-3 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition shadow-inner" required>
                                <option value="">Select Department</option>
                            </select>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Request Details -->
            <div class="bg-primary text-white py-3 px-5 rounded mb-5 text-center text-base font-bold">Request Details</div>
            
            <!-- Access Type Selector -->
            <div class="p-5 mb-6 bg-gray-50 rounded-md border-l-4 border-primary flex space-x-8">
                <label class="flex items-center cursor-pointer font-bold ">
                    <input type="radio" name="access_type" value="individual" checked class="mr-2 cursor-pointer"> Individual Access
                </label>
                <label class="flex items-center cursor-pointer font-bold">
                    <input type="radio" name="access_type" value="group" class="mr-2 cursor-pointer"> Group Access
                </label>
            </div>
            
            <!-- Individual Access -->
            <div id="individualAccess" class="mb-8">
                <h3 class="mb-4 text-primary text-base font-medium">I. For Individual Access</h3>
                <div class="p-4 mb-5 bg-blue-50 border-l-4 border-blue-500 rounded">
                    <i class="fas fa-info-circle"></i> Add additional access requests for the same user below.
                </div>
                <button type="button" class="bg-primary hover:bg-primary-dark text-white py-2.5 px-4 rounded mb-5 transition flex items-center" onclick="addRow('individualTable')">
                    <i class="fas fa-plus-circle mr-2"></i> Add Row
                </button>
                <div class="w-full overflow-x-auto">
                    <table id="individualTable" class="w-full border-collapse mb-6 shadow-sm">
                        <thead>
                            <tr>
                                <th class="border border-gray-200 p-3 bg-blue-50 text-primary font-semibold w-[25%]">User Name</th>
                                <th class="border border-gray-200 p-3 bg-blue-50 text-primary font-semibold w-[17%]">Application/System</th>
                                <th class="border border-gray-200 p-3 bg-blue-50 text-primary font-semibold w-[8%]">Access Type</th>
                                <th class="border border-gray-200 p-3 bg-blue-50 text-primary font-semibold w-[11%]">Access Duration</th>
                                <th class="border border-gray-200 p-1 bg-blue-50 text-primary font-semibold w-[4%]">Start Date</th>
                                <th class="border border-gray-200 p-1 bg-blue-50 text-primary font-semibold w-[4%]">End Date</th>
                                <th class="border border-gray-200 p-1 bg-blue-50 text-primary font-semibold w-[4%]">Date Needed</th>
                                <th class="border border-gray-200 p-3 bg-blue-50 text-primary font-semibold w-[15.5%]">Justification</th>
                                <th class="border border-gray-200 p-3 bg-blue-50 text-primary font-semibold w-[5%] text-center">Delete</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="border border-gray-200 p-2">
                                    <input type="text" placeholder="User Name" name="ind_username" class="w-full p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" required>
                                </td>
                                <td class="border border-gray-200 p-2">
                                    <select name="ind_application[]" class="w-full p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" required>
                                        <option value="">Select Application</option>
                                        <?php foreach ($systemApplications as $app): ?>
                                            <option value="<?php echo $app; ?>"><?php echo $app; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="border border-gray-200 p-2">
                                    <select name="ind_access_type[]" class="w-full p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" required>
                                        <option value=""></option>
                                        <option value="full">Full</option>
                                        <option value="read">Read</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </td>
                                <td class="border border-gray-200 p-2">
                                    <select name="ind_duration_type[]" class="duration-select w-full p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" required onchange="toggleDateFields(this)">
                                        <option value=""></option>
                                        <option value="permanent">Permanent</option>
                                        <option value="temporary">Temporary</option>
                                    </select>
                                </td>
                                <td class="border border-gray-200 p-1">
                                    <input type="date" name="ind_start_date[]" class="start-date w-full p-1 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition bg-gray-50 opacity-70 cursor-not-allowed" disabled required>
                                </td>
                                <td class="border border-gray-200 p-1">
                                    <input type="date" name="ind_end_date[]" class="end-date w-full p-1 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition bg-gray-50 opacity-70 cursor-not-allowed" disabled required>
                                </td>
                                <td class="border border-gray-200 p-1">
                                    <input type="date" name="ind_date_needed[]" class="date-needed w-full p-1 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" required>
                                </td>
                                <td class="border border-gray-200 p-2">
                                    <input type="text" placeholder="Click to add justification" name="ind_justification[]" class="justification-input w-full p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition bg-gray-50 cursor-pointer" required>
                                </td>
                                <td class="border border-gray-200 p-2 text-center w-16">
                                    <button type="button" class="bg-danger hover:bg-danger-dark text-white p-2 rounded transition" onclick="deleteRow(this)">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Group Access -->
            <div id="groupAccess" class="mb-8 hidden">
                <h3 class="mb-4 text-primary text-base font-medium">II. For Group Access</h3>
                <div class="p-4 mb-5 bg-blue-50 border-l-4 border-blue-500 rounded">
                    <i class="fas fa-info-circle"></i> Add group access requests below. For multiple applications, add additional rows.
                </div>
                <button type="button" class="bg-primary hover:bg-primary-dark text-white py-2.5 px-4 rounded mb-5 transition flex items-center" onclick="addRow('groupTable')">
                    <i class="fas fa-plus-circle mr-2"></i> Add Application Row
                </button>
                <div class="w-full overflow-x-auto">
                    <table id="groupTable" class="w-full border-collapse mb-6 shadow-sm">
                        <thead>
                            <tr>
                                <th class="border border-gray-200 p-3 bg-blue-50 text-primary font-semibold w-[18.5%]">Application/System</th>
                                <th class="border border-gray-200 p-3 bg-blue-50 text-primary font-semibold w-[32%] min-w-[200px]">User Name</th>
                                <th class="border border-gray-200 p-3 bg-blue-50 text-primary font-semibold w-[8%] min-w-[110px]">Access Type</th>
                                <th class="border border-gray-200 p-3 bg-blue-50 text-primary font-semibold w-[12%]">Access Duration</th>
                                <th class="border border-gray-200 p-1 bg-blue-50 text-primary font-semibold w-[3%]">Start Date</th>
                                <th class="border border-gray-200 p-1 bg-blue-50 text-primary font-semibold w-[3%]">End Date</th>
                                <th class="border border-gray-200 p-1 bg-blue-50 text-primary font-semibold w-[3%]">Date Needed</th>
                                <th class="border border-gray-200 p-3 bg-blue-50 text-primary font-semibold w-[15.5%]">Justification</th>
                                <th class="border border-gray-200 p-3 bg-blue-50 text-primary font-semibold w-[5%] text-center">Delete</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="application-row" data-app-id="1">
                                <td class="border border-gray-200 p-2">
                                    <div class="flex gap-2 items-center">
                                        <select name="grp_application[]" class="app-select flex-1 p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" required>
                                        <option value="">Select Application</option>
                                        <?php foreach ($systemApplications as $app): ?>
                                            <option value="<?php echo $app; ?>"><?php echo $app; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                        
                                    </div>
                                </td>
                                <td class="border border-gray-200 p-2">
                                    <div class="flex items-center gap-2">
                                        <input type="text" placeholder="User Name" name="grp_username[]" class="w-full p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" required>
                                        <button type="button" class="bg-primary hover:bg-primary-dark text-white p-2 rounded transition flex-shrink-0" onclick="addUserRow(this)" title="Add User">
                                            <i class="fa fa-user-plus"></i>
                                        </button>
                                    </div>
                                </td>
                                <td class="border border-gray-200 p-2">
                                    <select name="grp_access_type[]" class="w-full p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" required>
                                        <option value=""></option>
                                        <option value="full">Full</option>
                                        <option value="read">Read</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </td>
                                <td class="border border-gray-200 p-2">
                                    <select name="grp_duration_type[]" class="duration-select w-full p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" required onchange="toggleDateFields(this)">
                                        <option value=""></option>
                                        <option value="permanent">Permanent</option>
                                        <option value="temporary">Temporary</option>
                                    </select>
                                </td>
                                <td class="border border-gray-200 p-1">
                                    <input type="date" name="grp_start_date[]" class="start-date w-full p-1 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition bg-gray-50 opacity-70 cursor-not-allowed" disabled required>
                                </td>
                                <td class="border border-gray-200 p-1">
                                    <input type="date" name="grp_end_date[]" class="end-date w-full p-1 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition bg-gray-50 opacity-70 cursor-not-allowed" disabled required>
                                </td>
                                <td class="border border-gray-200 p-1">
                                    <input type="date" name="grp_date_needed[]" class="date-needed w-full p-1 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" required>
                                </td>
                                <td class="border border-gray-200 p-2">
                                    <input type="text" placeholder="Click to add justification" name="grp_justification[]" class="justification-input w-full p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition bg-gray-50 cursor-pointer" required>
                                </td>
                                <td class="border border-gray-200 p-2 text-center w-16">
                                    <button type="button" class="bg-danger hover:bg-danger-dark text-white p-2 rounded transition" onclick="deleteRow(this)">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-5 text-center flex justify-center gap-5 flex-wrap">
                <button type="submit" class="bg-primary hover:bg-primary-dark text-white py-3.5 px-8 rounded transition text-base font-medium min-w-[200px]">
                    <i class="fas fa-paper-plane mr-2"></i> Submit Request
                </button>
                <button type="reset" class="bg-danger hover:bg-danger-dark text-white py-3.5 px-8 rounded transition text-base font-medium min-w-[200px]">
                    <i class="fas fa-undo mr-2"></i> Reset Form
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Structure -->
<div class="fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm z-50 hidden" id="modalOverlay"></div>
<div class="fixed top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white rounded-lg p-8 z-50 shadow-lg max-w-xl w-11/12 hidden" id="justificationModal">
    <h3 class="mb-5 text-primary text-lg font-medium">Reason for Access</h3>
    <textarea id="justificationTextarea" rows="8" placeholder="Enter detailed reason for access here..." class="w-full p-4 text-sm font-normal border border-gray-300 rounded mb-4 focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition resize-y min-h-[150px]"></textarea>
    <button id="closeModal" class="bg-primary hover:bg-primary-dark text-white py-3 px-6 rounded transition text-base">
        <i class="fas fa-check mr-2"></i> Save & Close
    </button>
</div>

<script>
    const businessUnitDepartments = {
        'AAC': [
            'AFFILIATES',
            'APP',
            'CATFISH GROW-OUT',
            'ENGINEERING',
            'FINANCE',
            'GENSAN PROCESSING PLANT (GPP)',
            'GROW OUT',
            'HUMAN RESOURCE & ADMIN',
            'INFORMATION TECHNOLOGY (IT)',
            'LAND BASED',
            'MANUFACTURING',
            'MARKETING',
            'MATERIALS MANAGEMENT',
            'OFFICE OF THE VP-OPERATIONS',
            'PPP-SLICING/OTHER PROCESSING',
            'REGIONAL SALES',
            'RPP',
            'SALES & MARKETING',
            'SEA CAGE',
            'SPECIAL IMPORTATION/TRADING',
            'TECHNICAL SERVICES',
            'TH - CLEARING',
            'TILAPIA HATCHERY (TH)',
            'VAP'
        ].sort(),
        'ALDEV': [
            'ALD Cattle',
            'ALD Banana-San Jose',
            'ALD Engineering',
            'ALD Operations Services',
            'ALD Technical Services',
            'ALD-PROD PLANNING'
        ].sort(),
        'ARC': [
            'ARC - NURSERY',
            'ARC Engineering',
            'ARC Growout',
            'Administrative services'
        ].sort(),
        'FHI': [
            'FIELDS',
            'SELLING & MARKETING DEPARTMENT',
            'OPERATIONS DEPARTMENT',
            'OTHER SPECIE DEPARTMENT'
        ].sort(),
        'SACI': [
            'ALDEVINCO-AGRI',
            'FHI',
            'ARC',
            'SCCI',
            'CLAFI',
            'ALSEC',
            'SAVI',
            'CONAL',
            'ABBA BLESS',
            'ALC',
            'SBSTG',
            'G3 AQUAVENTURES INC',
            'AAC',
            'VARIOUS AFFILIATES'
        ].sort(),
        'SAVI': [
            'SCCI',
            'ALSEC',
            'SUNFARMS',
            'AAC',
            'OPERATIONS SERVICES',
            'BANANA OPERATION',
            'BANANA LEAVES OPERATION',
            'AGRI-ENGINEERING',
            'G&A',
            'TSD Agri',
            'G&A - Distribution',
            'OOM',
            'Conal Corporation'
        ].sort(),
        'SCCI': [
            'SCC Banana-Lanton',
            'SCC Cattle',
            'SCC Engineering',
            'SCC Pineapple',
            'SCC Technical Services',
            'SCCI Operations Services'
        ].sort(),
        'SFC': [
            'AGRI-ENGINEERING',
            'AGRI-OPERATIONS SERVICES',
            'AGRI-PINEAPPLE OPERATIONS',
            'FIELD OVERHEAD'
        ].sort()
    };

    // Modal functionality
    let currentInput = null;
    const modalOverlay = document.getElementById('modalOverlay');
    const justificationModal = document.getElementById('justificationModal');
    const justificationTextarea = document.getElementById('justificationTextarea');
    const closeModalBtn = document.getElementById('closeModal');

    function setupJustificationInputs() {
        document.querySelectorAll('.justification-input').forEach(input => {
            input.addEventListener('click', function(e) {
                currentInput = e.target;
                justificationTextarea.value = currentInput.value;
                modalOverlay.style.display = 'block';
                justificationModal.style.display = 'block';
            });
        });
    }

    // Initial setup
    setupJustificationInputs();

    closeModalBtn.addEventListener('click', function() {
        if (currentInput) {
            currentInput.value = justificationTextarea.value;
        }
        modalOverlay.style.display = 'none';
        justificationModal.style.display = 'none';
    });

    modalOverlay.addEventListener('click', function() {
        closeModalBtn.click();
    });

    // Handle access type selection
    document.querySelectorAll('input[name="access_type"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const switchingToIndividual = this.value === 'individual';
            
            // Simply show/hide appropriate sections
            if (switchingToIndividual) {
                document.getElementById('individualAccess').classList.remove('hidden');
                document.getElementById('groupAccess').classList.add('hidden');
            } else {
                document.getElementById('individualAccess').classList.add('hidden');
                document.getElementById('groupAccess').classList.remove('hidden');
            }
        });
    });

    // Function to toggle date fields based on duration type
    function toggleDateFields(select) {
        const row = select.closest('tr');
        const startDate = row.querySelector('.start-date');
        const endDate = row.querySelector('.end-date');
        const isTemporary = select.value === 'temporary';

        startDate.disabled = !isTemporary;
        endDate.disabled = !isTemporary;

        if (!isTemporary) {
            startDate.value = '';
            endDate.value = '';
        } else {
            // Set minimum date for start date as today
            const today = new Date().toISOString().split('T')[0];
            startDate.min = today;
            
            // Add event listeners for date validation
            startDate.addEventListener('change', function() {
                endDate.min = this.value;
                if (endDate.value && endDate.value < this.value) {
                    endDate.value = this.value;
                }
            });

            endDate.addEventListener('change', function() {
                if (this.value < startDate.value) {
                    this.value = startDate.value;
                }
            });
        }

        // Update required attribute
        startDate.required = isTemporary;
        endDate.required = isTemporary;
    }

    const today = new Date().toISOString().split('T')[0];
    document.querySelectorAll('.date-needed').forEach(input => {
        input.setAttribute('min', today);

        input.addEventListener('change', function() {
            if (this.value < today) {
                alert('Date needed cannot be in the past.');
                this.value = today;
            }
        });
    });

    // Function to delete row
    function deleteRow(button) {
        const row = button.closest('tr');
        const table = row.closest('table');
        const appId = row.getAttribute('data-app-id');
        
        // Check if it's the last row in the table
        if (table.querySelectorAll('tbody tr').length <= 1) {
            alert('Cannot delete the last row.');
            return;
        }
        
        if (row.classList.contains('application-row')) {
            // If it's an application row, also delete all associated user rows
            const userRows = table.querySelectorAll(`.user-row[data-app-id="${appId}"]`);
            userRows.forEach(userRow => userRow.remove());
        }
        
        row.remove();
    }

    // Function to add new row for individual access
    function addRow(tableId) {
        const table = document.getElementById(tableId);
        
        if (tableId === 'individualTable') {
            const tbody = table.querySelector('tbody');
            const firstRow = tbody.querySelector('tr');
            const username = firstRow.querySelector('input[name="ind_username"]').value;
            
            if (!username) {
                alert('Please enter a username first');
                return;
            }
            
            const newRow = document.createElement('tr');
            newRow.innerHTML = `
                <td class="border border-gray-200 p-2">
                    <input type="hidden" name="ind_username_copy[]" value="${username}">
                    <div class="invisible">Username</div>
                </td>
                <td class="border border-gray-200 p-2">
                    <select name="ind_application[]" class="w-full p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" required>
                        <option value="">Select Application</option>
                        <?php foreach ($systemApplications as $app): ?>
                            <option value="<?php echo $app; ?>"><?php echo $app; ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td class="border border-gray-200 p-2">
                    <select name="ind_access_type[]" class="w-full p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" required>
                        <option value=""></option>
                        <option value="full">Full</option>
                        <option value="read">Read</option>
                        <option value="admin">Admin</option>
                    </select>
                </td>
                <td class="border border-gray-200 p-2">
                    <select name="ind_duration_type[]" class="duration-select w-full p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" required onchange="toggleDateFields(this)">
                        <option value=""></option>
                        <option value="permanent">Permanent</option>
                        <option value="temporary">Temporary</option>
                    </select>
                </td>
                <td class="border border-gray-200 p-1">
                    <input type="date" name="ind_start_date[]" class="start-date w-full p-1 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition bg-gray-50 opacity-70 cursor-not-allowed" disabled required>
                </td>
                <td class="border border-gray-200 p-1">
                    <input type="date" name="ind_end_date[]" class="end-date w-full p-1 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition bg-gray-50 opacity-70 cursor-not-allowed" disabled required>
                </td>
                <td class="border border-gray-200 p-1">
                    <input type="date" name="ind_date_needed[]" class="date-needed w-full p-1 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" required>
                </td>
                <td class="border border-gray-200 p-2">
                    <input type="text" placeholder="Click to add justification" name="ind_justification[]" class="justification-input w-full p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition bg-gray-50 cursor-pointer" required>
                </td>
                <td class="border border-gray-200 p-2 text-center w-16">
                    <button type="button" class="bg-danger hover:bg-danger-dark text-white p-2 rounded transition" onclick="deleteRow(this)">
                        <i class="fa fa-trash"></i>
                    </button>
                </td>
            `;
            
            tbody.appendChild(newRow);
            setupJustificationInputs();
            
            // Add event listener to the new duration select
            const durationSelect = newRow.querySelector('.duration-select');
            if (durationSelect) {
                durationSelect.addEventListener('change', function() {
                    toggleDateFields(this);
                });
            }
        } else {
            // Existing group table row addition code
            const tbody = table.querySelector('tbody');
            const lastAppId = parseInt(tbody.lastElementChild?.getAttribute('data-app-id') || '0');
            const newAppId = lastAppId + 1;
            
            const newRow = document.createElement('tr');
            newRow.className = 'application-row';
            newRow.setAttribute('data-app-id', newAppId);
            
            newRow.innerHTML = `
                <td class="border border-gray-200 p-2">
                    <div class="flex gap-2 items-center">
                        <select name="grp_application[]" class="app-select flex-1 p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" required>
                            <option value="">Select Application</option>
                            <?php foreach ($systemApplications as $app): ?>
                                <option value="<?php echo $app; ?>"><?php echo $app; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="bg-primary hover:bg-primary-dark text-white p-2 rounded transition flex-shrink-0" onclick="addUserRow(this)" title="Add User">
                            <i class="fa fa-user-plus"></i>
                        </button>
                    </div>
                </td>
                <td class="border border-gray-200 p-2">
                    <input type="text" placeholder="User Name" name="grp_username[]" class="w-full p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" required>
                </td>
                <td class="border border-gray-200 p-2">
                    <select name="grp_access_type[]" class="w-full p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" required>
                        <option value="">Select Access Type</option>
                        <option value="full">Full</option>
                        <option value="read">Read</option>
                        <option value="admin">Admin</option>
                    </select>
                </td>
                <td class="border border-gray-200 p-2">
                    <select name="grp_duration_type[]" class="duration-select w-full p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" required onchange="toggleDateFields(this)">
                        <option value="">Select Duration</option>
                        <option value="permanent">Permanent</option>
                        <option value="temporary">Temporary</option>
                    </select>
                </td>
                <td class="border border-gray-200 p-1">
                    <input type="date" name="grp_start_date[]" class="start-date w-full p-1 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition bg-gray-50 opacity-70 cursor-not-allowed" disabled required>
                </td>
                <td class="border border-gray-200 p-1">
                    <input type="date" name="grp_end_date[]" class="end-date w-full p-1 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition bg-gray-50 opacity-70 cursor-not-allowed" disabled required>
                </td>
                <td class="border border-gray-200 p-1">
                    <input type="date" name="grp_date_needed[]" class="date-needed w-full p-1 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" required>
                </td>
                <td class="border border-gray-200 p-2">
                    <input type="text" placeholder="Click to add justification" name="grp_justification[]" class="justification-input w-full p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition bg-gray-50 cursor-pointer" required>
                </td>
                <td class="border border-gray-200 p-2 text-center w-16">
                    <button type="button" class="bg-danger hover:bg-danger-dark text-white p-2 rounded transition" onclick="deleteRow(this)">
                        <i class="fa fa-trash"></i>
                    </button>
                </td>
            `;
            
            tbody.appendChild(newRow);
            setupJustificationInputs();
        }
    }

    // Function to add new user row under an application
    function addUserRow(button) {
        const appRow = button.closest('.application-row');
        const appId = appRow.getAttribute('data-app-id');
        const appSelect = appRow.querySelector('.app-select');
        const selectedApp = appSelect.value;
        
        if (!selectedApp) {
            alert('Please select an application first');
            return;
        }
        
        const newRow = document.createElement('tr');
        newRow.className = 'user-row';
        newRow.setAttribute('data-app-id', appId);
        
        newRow.innerHTML = `
            <td class="border border-gray-200 p-2 bg-gray-50">
                ${selectedApp}
                <input type="hidden" name="grp_application[]" value="${selectedApp}">
            </td>
            <td class="border border-gray-200 p-2">
                <input type="text" placeholder="User Name" name="grp_username[]" class="w-full p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" required>
            </td>
            <td class="border border-gray-200 p-2">
                <select name="grp_access_type[]" class="w-full p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" required>
                    <option value="">Select Access Type</option>
                    <option value="full">Full</option>
                    <option value="read">Read</option>
                    <option value="admin">Admin</option>
                </select>
            </td>
            <td class="border border-gray-200 p-2">
                <select name="grp_duration_type[]" class="duration-select w-full p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" required onchange="toggleDateFields(this)">
                    <option value="">Select Duration</option>
                    <option value="permanent">Permanent</option>
                    <option value="temporary">Temporary</option>
                </select>
            </td>
            <td class="border border-gray-200 p-1">
                <input type="date" name="grp_start_date[]" class="start-date w-full p-1 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition bg-gray-50 opacity-70 cursor-not-allowed" disabled required>
            </td>
            <td class="border border-gray-200 p-1">
                <input type="date" name="grp_end_date[]" class="end-date w-full p-1 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition bg-gray-50 opacity-70 cursor-not-allowed" disabled required>
            </td>
            <td class="border border-gray-200 p-1">
                <input type="date" name="grp_date_needed[]" class="date-needed w-full p-1 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition" required>
            </td>
            <td class="border border-gray-200 p-2">
                <input type="text" placeholder="Click to add justification" name="grp_justification[]" class="justification-input w-full p-2 border border-gray-300 rounded focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-20 transition bg-gray-50 cursor-pointer" required>
            </td>
            <td class="border border-gray-200 p-2 text-center w-16">
                <button type="button" class="bg-danger hover:bg-danger-dark text-white p-2 rounded transition" onclick="deleteRow(this)">
                    <i class="fa fa-trash"></i>
                </button>
            </td>
        `;
        
        appRow.after(newRow);
        setupJustificationInputs();

        // Add event listener to the new duration select
        const durationSelect = newRow.querySelector('.duration-select');
        if (durationSelect) {
            durationSelect.addEventListener('change', function() {
                toggleDateFields(this);
            });
        }
    }

    // Handle Business Unit change
    $('#business_unit').change(function() {
        const selectedUnit = this.value;
        const deptSelect = $('#department');
        
        deptSelect.empty().append('<option value="">Select Department</option>');
        
        if (selectedUnit && businessUnitDepartments[selectedUnit]) {
            businessUnitDepartments[selectedUnit].forEach(dept => {
                deptSelect.append(`<option value="${dept}">${dept}</option>`);
            });
            deptSelect.prop('disabled', false);
        } else {
            deptSelect.prop('disabled', true);
        }
    });

    // Form submission handler
    $('#uarForm').submit(function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        fetch('submit.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('modalMessage').textContent = data.message;
                document.getElementById('successModal').classList.remove('hidden');
                form.reset();
                resetFormSections();
            } else {
                alert(data.message || 'An error occurred while submitting the form.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while submitting the form.');
        });
    });

    // Handle form reset
    $('#uarForm').on('reset', function(e) {
        // Prevent the default reset behavior
        e.preventDefault();
        
        // Store the current access type selection
        const currentAccessType = $('input[name="access_type"]:checked').val();
        
        // Reset all form elements except radio buttons
        this.querySelectorAll('input:not([type="radio"]), select, textarea').forEach(element => {
            if (element.type === 'text' || element.type === 'date' || element.tagName === 'SELECT' || element.tagName === 'TEXTAREA') {
                element.value = '';
            }
        });

            // Reset business unit and department
            $('#business_unit').val('').trigger('change');
            $('#department').val('').prop('disabled', true);

            // Reset all date fields to disabled state
        $('.start-date, .end-date').prop('disabled', true);

            // Reset all duration selects
            $('.duration-select').val('');

            // Remove extra rows from tables, leaving only one row in each
            const individualTable = document.getElementById('individualTable');
            const groupTable = document.getElementById('groupTable');

            while (individualTable.querySelectorAll('tbody tr').length > 1) {
                individualTable.querySelector('tbody tr:last-child').remove();
            }
            while (groupTable.querySelectorAll('tbody tr').length > 1) {
                groupTable.querySelector('tbody tr:last-child').remove();
            }

        // Reset the first row in individual table
        const firstIndRow = individualTable.querySelector('tbody tr');
        if (firstIndRow) {
            firstIndRow.querySelectorAll('input:not([type="radio"]), select').forEach(input => {
                if (input.type === 'text' || input.type === 'date') {
                    input.value = '';
                } else if (input.tagName === 'SELECT') {
                    input.selectedIndex = 0;
                }
            });
        }

        // Reset the first row in group table
        const firstGrpRow = groupTable.querySelector('tbody tr');
        if (firstGrpRow) {
            firstGrpRow.querySelectorAll('input:not([type="radio"]), select').forEach(input => {
                if (input.type === 'text' || input.type === 'date') {
                    input.value = '';
                } else if (input.tagName === 'SELECT') {
                    input.selectedIndex = 0;
                }
            });
        }

        // Reset justification fields
        document.querySelectorAll('.justification-input').forEach(input => {
            input.value = '';
        });
    });
</script>
</body>
</html>
<?php
require_once '../config.php';
session_start();

// Track if a transaction is active
$transaction_active = false;

// Security headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("Content-Security-Policy: default-src 'self' https:; script-src 'self' 'unsafe-inline' https:; style-src 'self' 'unsafe-inline' https:;");

// Check if user is logged in and is admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// CSRF Protection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['message'] = [
            'title' => 'Error',
            'text' => 'Invalid security token. Please try again.',
            'type' => 'error'
        ];
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'update':
                    $employee_id = filter_input(INPUT_POST, 'employee_id', FILTER_SANITIZE_STRING);
                    $employee_name = filter_input(INPUT_POST, 'employee_name', FILTER_SANITIZE_STRING);
                    $company = filter_input(INPUT_POST, 'company', FILTER_SANITIZE_STRING);
                    $department = filter_input(INPUT_POST, 'department', FILTER_SANITIZE_STRING);
                    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
                    
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        throw new Exception('Invalid email format');
                    }
                    
                    $stmt = $pdo->prepare("UPDATE employees SET employee_name = ?, company = ?, department = ?, employee_email = ? WHERE employee_id = ?");
                    $stmt->execute([$employee_name, $company, $department, $email, $employee_id]);
                    
                    $_SESSION['message'] = [
                        'title' => 'Success',
                        'text' => 'Employee updated successfully',
                        'type' => 'success'
                    ];
                    break;

                case 'archive':
                    $employee_id = $_POST['employee_id'];
                    $archive_reason = $_POST['archive_reason'];
                    
                    try {
                        // Start transaction
                        $pdo->beginTransaction();
                        $transaction_active = true;
                        
                        // First get the employee details with error handling
                        $stmt = $pdo->prepare("SELECT * FROM employees WHERE employee_id = ?");
                        if (!$stmt->execute([$employee_id])) {
                            throw new Exception("Failed to fetch employee details");
                        }
                        
                        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
                        if (!$employee) {
                            throw new Exception("User not found");
                        }
                        
                        // Get the correct admin_id from admin_users table
                        // When logging in, admin_id in session is set to employee_id, not the admin_users.id
                        if (isset($_SESSION['admin_username'])) {
                            $adminUsername = $_SESSION['admin_username'];
                            $findAdmin = $pdo->prepare("SELECT id FROM admin_users WHERE username = ?");
                            $findAdmin->execute([$_SESSION['admin_id']]);
                            $adminData = $findAdmin->fetch(PDO::FETCH_ASSOC);
                            
                            if ($adminData) {
                                $admin_id = $adminData['id'];
                            } else {
                                // Fallback to any admin user if the current admin is not found
                                $get_admin = $pdo->query("SELECT id FROM admin_users LIMIT 1");
                                $admin_record = $get_admin->fetch(PDO::FETCH_ASSOC);
                                $admin_id = $admin_record ? $admin_record['id'] : null;
                                
                                if (!$admin_id) {
                                    throw new Exception("No valid admin user found to perform this action");
                                }
                            }
                        } else {
                            // Fallback to any admin user if no admin is logged in
                            $get_admin = $pdo->query("SELECT id FROM admin_users LIMIT 1");
                            $admin_record = $get_admin->fetch(PDO::FETCH_ASSOC);
                            $admin_id = $admin_record ? $admin_record['id'] : null;
                            
                            if (!$admin_id) {
                                throw new Exception("No valid admin user found to perform this action");
                            }
                        }
                        
                        // Insert into archive with verified admin_id
                        $stmt = $pdo->prepare("INSERT INTO employees_archive (employee_id, company, employee_name, department, employee_email, archived_by, archive_reason) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        if (!$stmt->execute([
                            $employee['employee_id'],
                            $employee['company'],
                            $employee['employee_name'],
                            $employee['department'],
                            $employee['employee_email'],
                            $admin_id,
                            $archive_reason
                        ])) {
                            throw new Exception("Failed to archive employee");
                        }
                        
                        // Delete from active employees
                        $stmt = $pdo->prepare("DELETE FROM employees WHERE employee_id = ?");
                        if (!$stmt->execute([$employee_id])) {
                            throw new Exception("Failed to remove employee from active list");
                        }
                        
                        // Commit transaction
                        $pdo->commit();
                        $transaction_active = false;
                        
                        $_SESSION['message'] = [
                            'type' => 'success',
                            'text' => 'Employee archived successfully'
                        ];
                    } catch (Exception $e) {
                        // Rollback transaction on error
                        if ($transaction_active) {
                            $pdo->rollBack();
                            $transaction_active = false;
                        }
                        throw $e;
                    }
                    break;

                case 'restore':
                    $employee_id = $_POST['employee_id'];
                    
                    try {
                        // Start transaction
                        $pdo->beginTransaction();
                        $transaction_active = true;
                        
                        // First get the archived employee details with error handling
                        $stmt = $pdo->prepare("SELECT * FROM employees_archive WHERE employee_id = ?");
                        if (!$stmt->execute([$employee_id])) {
                            throw new Exception("Failed to fetch archived employee details");
                        }
                        
                        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
                        if (!$employee) {
                            throw new Exception("Employee not found in archive");
                        }
                        
                        // Insert back into employees
                        $stmt = $pdo->prepare("INSERT INTO employees (employee_id, company, employee_name, department, employee_email) VALUES (?, ?, ?, ?, ?)");
                        if (!$stmt->execute([
                            $employee['employee_id'],
                            $employee['company'],
                            $employee['employee_name'],
                            $employee['department'],
                            $employee['employee_email']
                        ])) {
                            throw new Exception("Failed to restore employee");
                        }
                        
                        // Delete from archive
                        $stmt = $pdo->prepare("DELETE FROM employees_archive WHERE employee_id = ?");
                        if (!$stmt->execute([$employee_id])) {
                            throw new Exception("Failed to remove employee from archive");
                        }
                        
                        // Commit transaction
                        $pdo->commit();
                        $transaction_active = false;
                        
                        $_SESSION['message'] = [
                            'type' => 'success',
                            'text' => 'Employee restored successfully'
                        ];
                    } catch (Exception $e) {
                        // Rollback transaction on error
                        if ($transaction_active) {
                            $pdo->rollBack();
                            $transaction_active = false;
                        }
                        throw $e;
                    }
                    break;

                case 'add':
                    $employee_id = filter_input(INPUT_POST, 'employee_id', FILTER_SANITIZE_STRING);
                    $employee_name = filter_input(INPUT_POST, 'employee_name', FILTER_SANITIZE_STRING);
                    $company = filter_input(INPUT_POST, 'company', FILTER_SANITIZE_STRING);
                    $department = filter_input(INPUT_POST, 'department', FILTER_SANITIZE_STRING);
                    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
                    
                    // IMPORTANT FIX: Get the exact role value from POST without any filtering or sanitization
                    $role = $_POST['role'] ?? 'requestor';
                    
                    // Debug logs to track the issue
                    error_log("RAW POST DATA: " . print_r($_POST, true));
                    error_log("SELECTED ROLE VALUE: " . $role);
                    
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        throw new Exception('Invalid email format');
                    }
                    
                    // Check if employee ID already exists
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE employee_id = ?");
                    $stmt->execute([$employee_id]);
                    if ($stmt->fetchColumn() > 0) {
                        throw new Exception('Employee ID already exists');
                    }
                    
                    // Check if email already exists
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE employee_email = ?");
                    $stmt->execute([$email]);
                    if ($stmt->fetchColumn() > 0) {
                        throw new Exception('Email address already exists');
                    }
                    
                    // Start transaction to ensure both inserts succeed or fail together
                    $pdo->beginTransaction();
                    $transaction_active = true;
                    
                    try {
                        // Insert into employees table WITH THE CORRECT ROLE
                        $stmt = $pdo->prepare("INSERT INTO employees (employee_id, employee_name, company, department, employee_email, role) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$employee_id, $employee_name, $company, $department, $email, $role]);
                        
                        // Default password for admin users
                        $default_password = password_hash('password123', PASSWORD_DEFAULT);
                        
                        // Delete any existing admin user entry first
                        $delete_stmt = $pdo->prepare("DELETE FROM admin_users WHERE username = ?");
                        $delete_stmt->execute([$employee_id]);
                        
                        // Now create a fresh admin user entry with the selected role
                        $insert_stmt = $pdo->prepare("INSERT INTO admin_users (username, password, role) VALUES (?, ?, ?)");
                        $insert_result = $insert_stmt->execute([$employee_id, $default_password, $role]);
                        
                        if (!$insert_result) {
                            throw new Exception("Failed to create admin user with role: " . $role);
                        }
                        
                        // Commit the transaction
                        $pdo->commit();
                        $transaction_active = false;
                        
                        error_log("SUCCESS: User created with role: " . $role . " in both tables");
                    } catch (Exception $e) {
                        // Rollback on error
                        if ($transaction_active) {
                            $pdo->rollBack();
                            $transaction_active = false;
                        }
                        throw $e;
                    }
                    
                    $_SESSION['message'] = [
                        'title' => 'Success',
                        'text' => 'Employee added successfully',
                        'type' => 'success'
                    ];
                    break;
            }
        } catch (Exception $e) {
            $_SESSION['message'] = [
                'title' => 'Error',
                'text' => $e->getMessage(),
                'type' => 'error'
            ];
        }
    }
}

// Get view type (active or archived)
$view = $_GET['view'] ?? 'active';

// Get filter values with proper sanitization
$company_filter = isset($_GET['company']) ? htmlspecialchars($_GET['company']) : '';
$department_filter = isset($_GET['department']) ? htmlspecialchars($_GET['department']) : '';

// Fetch unique companies and departments for filters with error handling
try {
    // Fetch from both active and archived employees for comprehensive filtering
    $companies = $pdo->query("SELECT DISTINCT company FROM employees 
                           UNION 
                           SELECT DISTINCT company FROM employees_archive 
                           ORDER BY company")->fetchAll(PDO::FETCH_COLUMN);
    if ($companies === false) {
        $companies = [];
        error_log("Failed to fetch companies list");
    }
    
    $departments = $pdo->query("SELECT DISTINCT department FROM employees 
                              UNION 
                              SELECT DISTINCT department FROM employees_archive 
                              ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);
    if ($departments === false) {
        $departments = [];
        error_log("Failed to fetch departments list");
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $companies = [];
    $departments = [];
}

// Build the query to fetch both active and archived employees
try {
    // Debug output
    error_log("Fetching employees data...");
    
    // Status filter
    $status_filter = isset($_GET['status']) ? $_GET['status'] : '';
    
    // Initial query parts
    $parts = [];
    $params = [];
    
    // Only include active employees if status is empty or 'active'
    if ($status_filter === '' || $status_filter === 'active') {
        $active_query = "SELECT 
                          employee_id, 
                          company, 
                          employee_name, 
                          department, 
                          employee_email, 
                          'active' as status 
                        FROM 
                          employees 
                        WHERE 1=1";
        
        if (isset($_GET['company']) && $_GET['company'] !== '') {
            $active_query .= " AND company = ?";
            $params[] = $_GET['company'];
        }

        if (isset($_GET['department']) && $_GET['department'] !== '') {
            $active_query .= " AND department = ?";
            $params[] = $_GET['department'];
        }
        
        $parts[] = $active_query;
    }
    
    // Only include inactive employees if status is empty or 'inactive'
    if ($status_filter === '' || $status_filter === 'inactive') {
        $inactive_query = "SELECT 
                            employee_id, 
                            company, 
                            employee_name, 
                            department, 
                            employee_email, 
                            'inactive' as status 
                          FROM 
                            employees_archive 
                          WHERE 1=1";
        
        if (isset($_GET['company']) && $_GET['company'] !== '') {
            $inactive_query .= " AND company = ?";
            $params[] = $_GET['company'];
        }

        if (isset($_GET['department']) && $_GET['department'] !== '') {
            $inactive_query .= " AND department = ?";
            $params[] = $_GET['department'];
        }
        
        $parts[] = $inactive_query;
    }
    
    // Combine the parts with UNION ALL
    $query = implode(" UNION ALL ", $parts);
    
    // Add ordering
    $query .= " ORDER BY CASE WHEN status = 'active' THEN 0 ELSE 1 END, employee_name ASC";

    error_log("SQL Query: " . $query);
    error_log("Query params: " . print_r($params, true));
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Query result count: " . count($employees));
    
    if ($employees === false) {
        $employees = [];
        error_log("Failed to fetch employees, result was false");
    }
} catch (Exception $e) {
    error_log("Error fetching employees: " . $e->getMessage());
    $employees = [];
    $_SESSION['message'] = [
        'type' => 'error',
        'text' => 'Failed to load employees. Please try again. Error: ' . $e->getMessage()
    ];
}

// Fetch all admin users
$stmt = $pdo->query("SELECT * FROM admin_users");
$admin_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Create a lookup of existing admin usernames
$existing_usernames = array_column($admin_users, 'username');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Management</title>

    <!-- External CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
    <!-- Tailwind Configuration -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#4F46E5',
                        secondary: '#1F2937',
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <div class="fixed inset-y-0 left-0 z-50 w-72 bg-white shadow-lg transform transition-transform duration-300">
            <div class="flex flex-col h-full">
                <!-- Logo -->
                <div class="text-center">
                    <img src="../logo.png" alt="Alsons Agribusiness Logo" class="mt-1 w-60 h-auto mx-auto">
                </div><br>

                <!-- Navigation Menu -->
                <nav class="flex-1 pt-6 pb-4 px-4 space-y-1 overflow-y-auto">
                    <p class="px-4 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                        Main Menu
                    </p>
                    
                    <a href="dashboard.php" class="flex items-center px-4 py-3 text-gray-700 rounded-xl transition-all hover:bg-gray-50 group">
                        <span class="flex items-center justify-center w-9 h-9 bg-gray-100 text-gray-600 rounded-lg group-hover:bg-gray-200">
                            <i class='bx bxs-dashboard text-xl'></i>
                        </span>
                        <span class="ml-3">Dashboard</span>
                    </a>
                    
                    <a href="analytics.php" class="flex items-center px-4 py-3 text-gray-700 rounded-xl transition-all hover:bg-gray-50 group">
                        <span class="flex items-center justify-center w-9 h-9 bg-gray-100 text-gray-600 rounded-lg group-hover:bg-gray-200">
                            <i class='bx bx-line-chart text-xl'></i>
                        </span>
                        <span class="ml-3">Analytics</span>
                    </a>
                    
                    <a href="requests.php" class="flex items-center px-4 py-3 text-gray-700 rounded-xl transition-all hover:bg-gray-50 group">
                        <span class="flex items-center justify-center w-9 h-9 bg-gray-100 text-gray-600 rounded-lg group-hover:bg-gray-200">
                            <i class='bx bxs-message-square-detail text-xl'></i>
                        </span>
                        <span class="ml-3">Requests</span>
                    </a>
                    
                    <a href="approval_history.php" class="flex items-center px-4 py-3 text-gray-700 rounded-xl transition-all hover:bg-gray-50 group">
                        <span class="flex items-center justify-center w-9 h-9 bg-gray-100 text-gray-600 rounded-lg group-hover:bg-gray-200">
                            <i class='bx bx-history text-xl'></i>
                        </span>
                        <span class="ml-3">Approval History</span>
                    </a>

                    <!-- Add a divider -->
                    <div class="my-4 border-t border-gray-100"></div>
                    
                    <p class="px-4 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                        Account
                    </p>
                    
                    <a href="#" class="flex items-center px-4 py-3 text-indigo-600 bg-indigo-50 rounded-xl transition-all hover:bg-indigo-100 group">
                        <span class="flex items-center justify-center w-9 h-9 bg-indigo-100 text-indigo-600 rounded-lg group-hover:bg-indigo-200">
                            <i class='bx bx-user text-xl'></i>
                        </span>
                        <span class="ml-3 font-medium">User Management</span>
                    </a>
                    
                    <a href="settings.php" class="flex items-center px-4 py-3 text-gray-700 rounded-xl transition-all hover:bg-gray-50 group">
                        <span class="flex items-center justify-center w-9 h-9 bg-gray-100 text-gray-600 rounded-lg group-hover:bg-gray-200">
                            <i class='bx bx-cog text-xl'></i>
                        </span>
                        <span class="ml-3">Settings</span>
                    </a>
                </nav>
                
                <!-- Logout Button -->
                <div class="p-4 border-t border-gray-100">
                    <a href="logout.php" class="flex items-center px-4 py-3 text-red-600 bg-red-50 rounded-xl transition-all hover:bg-red-100 group">
                        <span class="flex items-center justify-center w-9 h-9 bg-red-100 text-red-600 rounded-lg group-hover:bg-red-200">
                            <i class='bx bx-log-out text-xl group-hover:rotate-90 transition-transform duration-300'></i>
                        </span>
                        <span class="ml-3 font-medium">Logout</span>
                    </a>
                </div>

                <!-- User Profile -->
                <div class="px-4 py-4 border-t border-gray-100">
                    <div class="flex items-center space-x-3">
                        <div class="flex-shrink-0">
                            <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600">
                                <i class='bx bxs-user text-xl'></i>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">
                                <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?>
                            </p>
                            <p class="text-xs text-gray-500 truncate">
                                Administrator
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile menu button (for responsive design) -->
        <div class="lg:hidden fixed bottom-6 right-6 z-50">
            <button type="button" class="flex items-center justify-center w-14 h-14 rounded-full bg-indigo-600 text-white shadow-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                <i class='bx bx-menu text-2xl'></i>
            </button>
        </div>

        <!-- Main Content -->
        <div class="flex-1 ml-72">
            <!-- Header -->
            <div class="bg-white border-b border-gray-200">
                <div class="flex justify-between items-center px-8 py-4">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">User Management</h2>
                        <p class="text-gray-600 text-sm mt-1">
                            Manage and organize user information
                        </p>
                    </div>
                    <div class="flex items-center gap-4">

                    <div class="relative">
                            <input type="text" 
                                   id="searchInput"
                                   placeholder="Search employees..." 
                                   class="pl-10 pr-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20">
                            <i class='bx bx-search absolute left-3 top-2.5 text-gray-400'></i>
                        </div>
                        <button type="button" 
                                class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 flex items-center gap-2"
                                data-bs-toggle="modal"
                                data-bs-target="#addEmployeeModal">
                            <i class="fas fa-plus"></i>
                            Add New User
                        </button>
                    </div>
                </div>
            </div>

            <!-- Content Area -->
            <div class="p-8">
                <!-- Filters -->
                <div class="bg-white rounded-xl shadow-sm p-6 mb-8">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Filter Users</h3>
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">Business Unit</label>
                            <select name="company" class="block w-full h-12 rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-base">
                                <option value="">All Business Units</option>
                                <?php foreach ($companies as $company): ?>
                                    <option value="<?php echo htmlspecialchars($company); ?>" 
                                            <?php echo isset($_GET['company']) && $_GET['company'] === $company ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($company); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">Department</label>
                            <select name="department" class="block w-full h-12 rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-base">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $department): ?>
                                    <option value="<?php echo htmlspecialchars($department); ?>"
                                            <?php echo isset($_GET['department']) && $_GET['department'] === $department ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($department); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">Status</label>
                            <select name="status" class="block w-full h-12 rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-base">
                                <option value="">All Status</option>
                                <option value="active" <?php echo isset($_GET['status']) && $_GET['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo isset($_GET['status']) && $_GET['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <div class="flex gap-2">
                                <button type="submit" class="px-6 py-3 bg-primary text-white rounded-lg hover:bg-primary/90 transition-colors">
                                    <i class="fas fa-filter me-2"></i>Apply Filters
                                </button>
                                <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="px-6 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                                    <i class="fas fa-undo me-2"></i>Reset
                                </a>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Employees Table -->
                <div class="bg-white rounded-xl shadow-sm">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-800">User List</h3>  
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200" id="employeesTable">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Business Unit</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($employees as $employee): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($employee['employee_id']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($employee['employee_name']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($employee['company']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($employee['department']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($employee['employee_email']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($employee['status'] === 'active'): ?>
                                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">Active</span>
                                        <?php else: ?>
                                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex justify-end gap-2">
                                            <?php if ($employee['status'] === 'active'): ?>
                                                <button class="inline-flex items-center px-3 py-1 bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 edit-employee"
                                                        data-employee-id="<?php echo htmlspecialchars($employee['employee_id']); ?>"
                                                        data-employee-name="<?php echo htmlspecialchars($employee['employee_name']); ?>"
                                                        data-company="<?php echo htmlspecialchars($employee['company']); ?>"
                                                        data-department="<?php echo htmlspecialchars($employee['department']); ?>"
                                                        data-email="<?php echo htmlspecialchars($employee['employee_email']); ?>">
                                                    <i class="fas fa-edit me-1"></i>
                                                    Edit
                                                </button>
                                                <button class="inline-flex items-center px-3 py-1 bg-red-50 text-red-700 rounded-lg hover:bg-red-100 archive-employee"
                                                        data-employee-id="<?php echo htmlspecialchars($employee['employee_id']); ?>"
                                                        data-employee-name="<?php echo htmlspecialchars($employee['employee_name']); ?>">
                                                    <i class="fas fa-archive me-1"></i>
                                                    Archive
                                                </button>
                                            <?php else: ?>
                                                <button class="inline-flex items-center px-3 py-1 bg-green-50 text-green-700 rounded-lg hover:bg-green-100 restore-employee"
                                                        data-employee-id="<?php echo htmlspecialchars($employee['employee_id']); ?>"
                                                        data-employee-name="<?php echo htmlspecialchars($employee['employee_name']); ?>">
                                                    <i class="fas fa-undo me-1"></i>
                                                    Restore
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

    <!-- Edit Modal -->
    <div id="editModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 hidden z-50">
        <div class="flex items-center justify-center min-h-screen">
            <div class="bg-white rounded-xl p-8 max-w-2xl w-full mx-4">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-semibold text-gray-800">Edit User</h3>
                    <button onclick="hideEditModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <form id="editForm" method="POST">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="employee_id" id="edit_employee_id">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                    <div class="grid grid-cols-2 gap-6">
                        <div class="col-span-2">
                            <label for="edit_employee_name" class="block text-sm font-medium text-gray-700 mb-2">Employee Name</label>
                            <input type="text" id="edit_employee_name" name="employee_name" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary/20">
                        </div>

                        <div>
                            <label for="edit_company" class="block text-sm font-medium text-gray-700 mb-2">Company</label>
                            <select id="edit_company" name="company" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary/20">
                                <?php foreach ($companies as $company): ?>
                                    <option value="<?php echo htmlspecialchars($company); ?>">
                                        <?php echo htmlspecialchars($company); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="edit_department" class="block text-sm font-medium text-gray-700 mb-2">Department</label>
                            <select id="edit_department" name="department" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary/20">
                                <?php foreach ($departments as $department): ?>
                                    <option value="<?php echo htmlspecialchars($department); ?>">
                                        <?php echo htmlspecialchars($department); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-span-2">
                            <label for="edit_email" class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                            <input type="email" id="edit_email" name="email" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary/20">
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end space-x-3">
                        <button type="button" onclick="hideEditModal()" 
                                class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 text-white bg-primary rounded-lg hover:bg-primary/90">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Archive Modal -->
    <div id="archiveModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 hidden z-50">
        <div class="flex items-center justify-center min-h-screen">
            <div class="bg-white rounded-xl p-8 max-w-md w-full mx-4">
                <div class="text-center mb-6">
                    <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-archive text-2xl text-red-600"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800">Archive User</h3>
                    <p class="text-gray-600 mt-2" id="archive_confirm_text"></p>
                </div>

                <form id="archiveForm" method="POST" class="mt-6">
                    <input type="hidden" name="action" value="archive">
                    <input type="hidden" name="employee_id" id="archive_employee_id">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                    <div class="mb-4">
                        <label for="archive_reason" class="block text-sm font-medium text-gray-700 mb-2">
                            Reason for Archiving <span class="text-red-500">*</span>
                        </label>
                        <textarea id="archive_reason" name="archive_reason" rows="4" required
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary/20"
                                  placeholder="Please provide a detailed reason for archiving this employee..."></textarea>
                    </div>

                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-yellow-700">
                                    This action will move the User to the archived list. 
                                    The User will no longer appear in the active User list.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end space-x-3">
                        <button type="button" onclick="hideArchiveModal()" 
                                class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 text-white bg-red-600 rounded-lg hover:bg-red-700">
                            Archive User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Reason Modal -->
    <div class="modal fade" id="viewReasonModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Archive Reason</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6 class="mb-3" id="view_employee_name"></h6>
                    <div class="reason-text" id="view_reason_text"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Employee Modal -->
    <div class="modal fade" id="addEmployeeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New User    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addEmployeeForm" method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <div class="mb-3">
                            <label for="add_employee_id" class="form-label">Employee ID <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="add_employee_id" name="employee_id" required 
                                   pattern="[a-zA-Z0-9-]+" title="Only letters, numbers, and hyphens are allowed">
                            <div class="form-text">Only letters, numbers, and hyphens are allowed</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="add_employee_name" class="form-label">Employee Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="add_employee_name" name="employee_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="add_company" class="form-label">Company <span class="text-danger">*</span></label>
                            <select class="form-select" id="add_company" name="company" required>
                                <option value="">Select Company</option>
                                <?php foreach ($companies as $company): ?>
                                    <option value="<?php echo htmlspecialchars($company); ?>">
                                        <?php echo htmlspecialchars($company); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="add_department" class="form-label">Department <span class="text-danger">*</span></label>
                            <select class="form-select" id="add_department" name="department" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $department): ?>
                                    <option value="<?php echo htmlspecialchars($department); ?>">
                                        <?php echo htmlspecialchars($department); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="add_email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="add_email" name="email" required>
                            <div class="form-text">Enter a valid email address</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="add_role" class="form-label">Role</label>
                            <select class="form-select" id="add_role" name="role" required>
                                <option value="requestor">Requestor</option>
                                <option value="admin">Admin</option>
                                <option value="superior">Immediate Superior</option>
                                <option value="technical_support">Technical Support</option>
                                <option value="process_owner">Process Owner</option>
                            </select>
                            <div class="form-text">Assign a role if this employee needs system access</div>
                        </div>
                        
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Add User</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- View Details Modal -->
    <div class="modal fade" id="viewDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Employee Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Employee ID</label>
                                <p id="view_employee_id" class="mb-0"></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Name</label>
                                <p id="view_name" class="mb-0"></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Email</label>
                                <p id="view_email" class="mb-0"></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Company</label>
                                <p id="view_company" class="mb-0"></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Department</label>
                                <p id="view_department" class="mb-0"></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Status</label>
                                <p id="view_status" class="mb-0"></p>
                            </div>
                            <div class="mb-3 archive-reason-section" style="display: none;">
                                <label class="form-label fw-bold">Archive Reason</label>
                                <p id="view_archive_reason" class="mb-0"></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary edit-from-view">Edit Employee</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden form for restore action -->
    <form id="restoreForm" method="POST" class="hidden">
        <input type="hidden" name="action" value="restore">
        <input type="hidden" name="employee_id" id="restore_employee_id">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        $(document).ready(function() {
            // Initialize DataTable with fixed configuration
            const table = $('#employeesTable').DataTable({
                columnDefs: [
                    { 
                        targets: 5, // Status column index
                        type: 'string',
                        render: function(data, type, row) {
                            // For sorting purposes
                            if (type === 'sort') {
                                return data.includes('Active') ? '0' : '1'; // Active comes before Inactive
                            }
                            // Return the original HTML for display
                            return data;
                        }
                    }
                ],
                order: [[5, 'asc'], [1, 'asc']], // First by status (active first), then by name
                pageLength: 10,
                searching: true,
                language: {
                    lengthMenu: "Show _MENU_ entries per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    paginate: {
                        first: "First",
                        last: "Last",
                        next: "Next",
                        previous: "Previous"
                    },
                    search: "Search in table:"
                },
                initComplete: function() {
                    // Move the search box to our custom search input
                    $('#searchInput').on('keyup', function() {
                        table.search($(this).val()).draw();
                    });
                }
            });

            // View Employee Details
            $(document).on('click', '.view-employee', function(e) {
                e.preventDefault();
                const employeeId = $(this).data('employee-id');
                const employeeName = $(this).data('employee-name');
                const company = $(this).data('company');
                const department = $(this).data('department');
                const email = $(this).data('email');
                const status = $(this).data('status');
                
                $('#view_employee_id').text(employeeId);
                $('#view_name').text(employeeName);
                $('#view_company').text(company);
                $('#view_department').text(department);
                $('#view_email').text(email);
                
                // Set status with proper styling
                if (status === 'active') {
                    $('#view_status').html('<span class="badge bg-success">Active</span>');
                    $('.archive-reason-section').hide();
                    $('.edit-from-view').show();
                } else {
                    $('#view_status').html('<span class="badge bg-danger">Inactive</span>');
                    
                    // For inactive users, get the archive reason directly
                    $.ajax({
                        url: 'get_archive_reason.php',
                        type: 'POST',
                        data: {
                            employee_id: employeeId,
                            csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                $('#view_archive_reason').text(response.reason);
                                $('.archive-reason-section').show();
                            } else {
                                $('#view_archive_reason').text('No archive reason found');
                                $('.archive-reason-section').show();
                            }
                        },
                        error: function() {
                            $('#view_archive_reason').text('Error retrieving archive reason');
                            $('.archive-reason-section').show();
                        }
                    });
                    
                    $('.edit-from-view').hide();
                }
                
                const viewModal = new bootstrap.Modal(document.getElementById('viewDetailsModal'));
                viewModal.show();
            });

            // Add Employee form submission
            $('#addEmployeeForm').on('submit', function(e) {
                e.preventDefault();
                
                if (!validateForm('addEmployeeForm')) {
                    Swal.fire({
                        title: 'Validation Error',
                        text: 'Please check all required fields and ensure they are filled correctly.',
                        icon: 'error'
                    });
                    return;
                }

                // Show confirmation dialog
                Swal.fire({
                    title: 'Add New Employee?',
                    text: 'Are you sure you want to add this employee?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#4F46E5',
                    confirmButtonText: 'Add Employee',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Use AJAX to submit the form
                        const formData = new FormData(this);
                        
                        // Show loading state
                        Swal.fire({
                            title: 'Processing...',
                            text: 'Please wait while we add the employee.',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        
                        $.ajax({
                            url: $(this).attr('action'),
                            type: 'POST',
                            data: formData,
                            processData: false,
                            contentType: false,
                            success: function(response) {
                                // Close the modal
                                $('#addEmployeeModal').modal('hide');
                                
                                // Show success message
                                Swal.fire({
                                    title: 'Success',
                                    text: 'Employee added successfully',
                                    icon: 'success',
                                    timer: 2000,
                                    showConfirmButton: false
                                }).then(() => {
                                    // Reload the table or page
                                    location.reload();
                                });
                            },
                            error: function(xhr) {
                                Swal.fire({
                                    title: 'Error',
                                    text: 'Failed to add employee. Please try again.',
                                    icon: 'error'
                                });
                            }
                        });
                    }
                });
            });

            // Edit Form submission with AJAX
            $('#editForm').on('submit', function(e) {
                e.preventDefault();
                const form = $(this);
                
                if (!validateForm(this.id)) {
                    Swal.fire({
                        title: 'Validation Error',
                        text: 'Please check all required fields and ensure they are filled correctly.',
                        icon: 'error'
                    });
                    return;
                }

                // Show confirmation dialog
                Swal.fire({
                    title: 'Save Changes?',
                    text: 'Are you sure you want to save these changes?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#4F46E5',
                    confirmButtonText: 'Save Changes',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Show loading state
                        Swal.fire({
                            title: 'Processing...',
                            text: 'Please wait while we update the employee.',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        
                        $.ajax({
                            url: window.location.href,
                            type: 'POST',
                            data: form.serialize(),
                            success: function(response) {
                                // Hide modal
                                hideEditModal();
                                
                                // Show success message
                                Swal.fire({
                                    title: 'Success',
                                    text: 'Employee updated successfully',
                                    icon: 'success',
                                    timer: 2000,
                                    showConfirmButton: false
                                }).then(() => {
                                    // Reload the table or page
                                    location.reload();
                                });
                            },
                            error: function(xhr) {
                                Swal.fire({
                                    title: 'Error',
                                    text: 'Failed to update employee. Please try again.',
                                    icon: 'error'
                                });
                            }
                        });
                    }
                });
            });
            
            // Archive Form submission with AJAX
            $('#archiveForm').on('submit', function(e) {
                e.preventDefault();
                const form = $(this);
                
                if (!validateForm(this.id)) {
                    Swal.fire({
                        title: 'Validation Error',
                        text: 'Please check all required fields and ensure they are filled correctly.',
                        icon: 'error'
                    });
                    return;
                }

                // Show confirmation dialog
                Swal.fire({
                    title: 'Archive Employee?',
                    text: 'Are you sure you want to archive this employee?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#dc2626',
                    confirmButtonText: 'Archive',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Show loading state
                        Swal.fire({
                            title: 'Processing...',
                            text: 'Please wait while we archive the employee.',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        
                        $.ajax({
                            url: window.location.href,
                            type: 'POST',
                            data: form.serialize(),
                            success: function(response) {
                                // Hide modal
                                hideArchiveModal();
                                
                                // Show success message
                                Swal.fire({
                                    title: 'Success',
                                    text: 'Employee archived successfully',
                                    icon: 'success',
                                    timer: 2000,
                                    showConfirmButton: false
                                }).then(() => {
                                    // Reload the table or page
                                    location.reload();
                                });
                            },
                            error: function(xhr) {
                                Swal.fire({
                                    title: 'Error',
                                    text: 'Failed to archive employee. Please try again.',
                                    icon: 'error'
                                });
                            }
                        });
                    }
                });
            });

            // Handle restore button click
            $(document).on('click', '.restore-employee', function(e) {
                e.preventDefault();
                const employeeId = $(this).data('employee-id');
                const employeeName = $(this).data('employee-name');
                
                Swal.fire({
                    title: 'Restore Employee?',
                    text: `Are you sure you want to restore ${employeeName}?`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#16a34a',
                    confirmButtonText: 'Restore',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Show loading state
                        Swal.fire({
                            title: 'Processing...',
                            text: 'Please wait while we restore the employee.',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        
                        // Use AJAX to submit the restore action
                        $.ajax({
                            url: window.location.href,
                            type: 'POST',
                            data: {
                                action: 'restore',
                                employee_id: employeeId,
                                csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
                            },
                            success: function(response) {
                                // Show success message
                                Swal.fire({
                                    title: 'Success',
                                    text: 'Employee restored successfully',
                                    icon: 'success',
                                    timer: 2000,
                                    showConfirmButton: false
                                }).then(() => {
                                    // Reload the table or page
                                    location.reload();
                                });
                            },
                            error: function(xhr) {
                                Swal.fire({
                                    title: 'Error',
                                    text: 'Failed to restore employee. Please try again.',
                                    icon: 'error'
                                });
                            }
                        });
                    }
                });
            });

            // Success/Error message handling
            <?php if (isset($_SESSION['message'])): ?>
                Swal.fire({
                    title: '<?php echo $_SESSION['message']['title'] ?? ''; ?>',
                    text: '<?php echo $_SESSION['message']['text']; ?>',
                    icon: '<?php echo $_SESSION['message']['type']; ?>',
                    timer: 3000,
                    showConfirmButton: false
                });
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>
            
            // Handle edit button click
            $(document).on('click', '.edit-employee', function(e) {
                e.preventDefault();
                const employeeId = $(this).data('employee-id');
                const employeeName = $(this).data('employee-name');
                const company = $(this).data('company');
                const department = $(this).data('department');
                const email = $(this).data('email');
                
                showEditModal(employeeId, employeeName, company, department, email);
            });

            // Handle archive button click
            $(document).on('click', '.archive-employee', function(e) {
                e.preventDefault();
                const employeeId = $(this).data('employee-id');
                const employeeName = $(this).data('employee-name');
                
                showArchiveModal(employeeId, employeeName);
            });

            // Close modals when clicking outside
            $('.fixed.inset-0').click(function(e) {
                if (e.target === this) {
                    $(this).addClass('hidden');
                }
            });
        });

        // Show/Hide Edit Modal
        function showEditModal(employeeId, employeeName, company, department, email) {
            $('#edit_employee_id').val(employeeId);
            $('#edit_employee_name').val(employeeName);
            $('#edit_company').val(company);
            $('#edit_department').val(department);
            $('#edit_email').val(email);
            $('#editModal').removeClass('hidden');
        }

        function hideEditModal() {
            $('#editModal').addClass('hidden');
        }

        // Show/Hide Archive Modal
        function showArchiveModal(employeeId, employeeName) {
            $('#archive_employee_id').val(employeeId);
            $('#archive_confirm_text').text(`Are you sure you want to archive ${employeeName}?`);
            $('#archiveModal').removeClass('hidden');
        }

        function hideArchiveModal() {
            $('#archiveModal').addClass('hidden');
        }

        // Form validation function
        function validateForm(formId) {
            const form = document.getElementById(formId);
            if (!form) return false;

            const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
            let isValid = true;

            inputs.forEach(input => {
                if (!input.value.trim()) {
                    isValid = false;
                    input.classList.add('is-invalid');
                } else {
                    input.classList.remove('is-invalid');
                }

                // Email validation
                if (input.type === 'email' && input.value) {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(input.value)) {
                        isValid = false;
                        input.classList.add('is-invalid');
                    }
                }

                // Employee ID validation (alphanumeric)
                if (input.id === 'add_employee_id' || input.id === 'edit_employee_id') {
                    const idRegex = /^[a-zA-Z0-9-]+$/;
                    if (!idRegex.test(input.value)) {
                        isValid = false;
                        input.classList.add('is-invalid');
                    }
                }
            });

            return isValid;
        }
    </script>
</body>
</html>


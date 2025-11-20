<?php
session_start();
require_once '../config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    try {
        // First get the employee record by email
        $stmt = $pdo->prepare("SELECT e.*, au.id as admin_user_id, au.password as admin_password 
                               FROM uar.employees e 
                               JOIN uar.admin_users au ON e.employee_id = au.username 
                               WHERE e.employee_email = ? AND au.role = 'uar_admin'");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['admin_password'])) {
            $_SESSION['admin_id'] = $user['admin_user_id'];
            $_SESSION['admin_username'] = $user['employee_id'];
            $_SESSION['admin_email'] = $user['employee_email'];
            $_SESSION['role'] = 'uar_admin';
            $_SESSION['admin_name'] = $user['employee_name'];

            header('Location: dashboard.php');
            exit();
        } else {
            $error = 'Invalid email or password';
        }
    } catch (PDOException $e) {
        $error = 'An error occurred. Please try again.';
        error_log("Login error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UAR Admin Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
</head>

<body class="bg-gradient-to-br from-blue-900 via-indigo-900 to-purple-900 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
            <div class="bg-gradient-to-r from-indigo-600 to-purple-600 p-8 text-center">
                <div class="mb-4">
                    <img src="../logo.png" alt="Logo" class="h-20 w-auto mx-auto">
                </div>
                <h1 class="text-3xl font-bold text-white mb-2">UAR Admin Portal</h1>
                <p class="text-indigo-100">System Administrator Access</p>
            </div>

            <div class="p-8">
                <?php if ($error): ?>
                    <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 rounded-lg">
                        <div class="flex items-center">
                            <i class='bx bx-error text-red-500 text-xl mr-2'></i>
                            <p class="text-red-700 text-sm"><?php echo htmlspecialchars($error); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-6">
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                            Email Address
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class='bx bx-envelope text-gray-400'></i>
                            </div>
                            <input type="email"
                                id="email"
                                name="email"
                                required
                                class="pl-10 w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                placeholder="Enter your email address">
                        </div>
                    </div>

                    <div class="mb-6">
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                            Password
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class='bx bx-lock text-gray-400'></i>
                            </div>
                            <input type="password"
                                id="password"
                                name="password"
                                required
                                class="pl-10 w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                placeholder="Enter your password">
                        </div>
                    </div>

                    <button type="submit"
                        class="w-full bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-semibold py-3 px-4 rounded-lg hover:from-indigo-700 hover:to-purple-700 transition-all duration-200 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5">
                        <i class='bx bx-log-in mr-2'></i>Sign In
                    </button>
                </form>

                <div class="mt-6 text-center">
                    <a href="../index.php" class="text-sm text-indigo-600 hover:text-indigo-800 transition-colors">
                        <i class='bx bx-arrow-back mr-1'></i>Back to Main Site
                    </a>
                </div>
            </div>
        </div>

        <div class="mt-8 text-center text-white text-sm">
            <p class="opacity-75">© <?php echo date('Y'); ?> User Access Request System</p>
            <p class="opacity-60 mt-1">System Administrator Portal</p>
        </div>
    </div>
</body>

</html>
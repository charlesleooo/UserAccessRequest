<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['requestor_id'])) {
    header("Location: login.php");
    exit();
}

// Check if this is a force password change (first time login or temp password)
$force_password_change = isset($_SESSION['force_password_change']) && $_SESSION['force_password_change'] === true;

// If normal password change, check if user has a temp password
if (!$force_password_change) {
    $stmt = $pdo->prepare("SELECT is_temp_password FROM employees WHERE employee_id = ?");
    $stmt->execute([$_SESSION['requestor_id']]);
    $user = $stmt->fetch();
    if ($user && $user['is_temp_password']) {
        $_SESSION['force_password_change'] = true;
        $force_password_change = true;
    }
}

$error = null;
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    try {
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            throw new Exception("All fields are required");
        }

        if ($new_password !== $confirm_password) {
            throw new Exception("New passwords do not match");
        }

        if (strlen($new_password) < 8) {
            throw new Exception("Password must be at least 8 characters long");
        }

        // Verify current password
        $stmt = $pdo->prepare("SELECT password, is_temp_password FROM employees WHERE employee_id = ?");
        $stmt->execute([$_SESSION['requestor_id']]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($current_password, $user['password'])) {
            throw new Exception("Current password is incorrect");
        }        // Hash and update new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE employees SET password = ?, is_temp_password = 0 WHERE employee_id = ?");
        if ($stmt->execute([$hashed_password, $_SESSION['requestor_id']])) {
            if (isset($_SESSION['force_password_change'])) {
                unset($_SESSION['force_password_change']);
                header("Location: dashboard.php");
                exit();
            }
            $success = true;
        } else {
            throw new Exception("Failed to update password");
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-md p-8">        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-gray-800">Change Password</h1>
            <?php if ($force_password_change): ?>
                <p class="text-gray-600 text-sm mt-2">You must change your temporary password before continuing</p>
                <p class="text-gray-500 text-xs mt-1">Please create a strong password that you'll remember</p>
            <?php else: ?>
                <p class="text-gray-600 text-sm mt-2">Please create a new password for your account</p>
            <?php endif; ?>
        </div>

        <form method="POST" class="space-y-6">
            <div>
                <label for="current_password" class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                <div class="relative">
                    <input type="password" id="current_password" name="current_password" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                </div>
            </div>

            <div>
                <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                <div class="relative">
                    <input type="password" id="new_password" name="new_password" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                </div>
            </div>

            <div>
                <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                <div class="relative">
                    <input type="password" id="confirm_password" name="confirm_password" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                </div>
            </div>

            <button type="submit"
                    class="w-full flex items-center justify-center bg-primary-600 hover:bg-primary-700 text-white font-semibold py-3 px-4 rounded-lg transition duration-300">
                Change Password
            </button>
        </form>
    </div>

    <script>
        <?php if ($error): ?>
        Swal.fire({
            title: 'Error!',
            text: '<?php echo addslashes($error); ?>',
            icon: 'error',
            confirmButtonColor: '#0ea5e9'
        });
        <?php endif; ?>

        <?php if ($success): ?>
        Swal.fire({
            title: 'Success!',
            text: 'Your password has been changed successfully.',
            icon: 'success',
            confirmButtonColor: '#0ea5e9'
        }).then(() => {
            window.location.href = 'dashboard.php';
        });
        <?php endif; ?>
    </script>
</body>
</html>

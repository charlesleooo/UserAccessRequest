<?php
session_start();
require_once '../config.php';

// Authentication check
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'superior') {
    header("Location: ../admin/login.php");
    exit();
}

// Process encryption code update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_encryption_code'])) {
    $newCode = $_POST['encryption_code'];

    // Validate the code is 6 alphanumeric characters
    if (strlen($newCode) === 6 && ctype_alnum($newCode)) {
        // Get the current user's ID
        $userId = $_SESSION['admin_id'];

        try {
            // Check if an encryption code already exists for this user
            $checkStmt = $pdo->prepare("SELECT id FROM user_encryption_codes WHERE user_id = ?");
            $checkStmt->execute([$userId]);
            $existingCode = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existingCode) {
                // Update existing code
                $updateStmt = $pdo->prepare("UPDATE user_encryption_codes SET encryption_code = ?, updated_at = NOW() WHERE user_id = ?");
                $result = $updateStmt->execute([$newCode, $userId]);
            } else {
                // Insert new code
                $insertStmt = $pdo->prepare("INSERT INTO user_encryption_codes (user_id, encryption_code, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
                $result = $insertStmt->execute([$userId, $newCode]);
            }

            if ($result) {
                $success_message = "Encryption code updated successfully!";
            } else {
                $error_message = "Failed to update encryption code.";
            }
        } catch (PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    } else {
        $error_message = "Encryption code must be exactly 6 alphanumeric characters.";
    }
}

// Check if user has an existing encryption code
try {
    $userId = $_SESSION['admin_id'];
    $stmt = $pdo->prepare("SELECT encryption_code FROM user_encryption_codes WHERE user_id = ?");
    $stmt->execute([$userId]);
    $userCode = $stmt->fetch(PDO::FETCH_ASSOC);
    $hasExistingCode = !empty($userCode);
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
    $hasExistingCode = false;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Superior</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Custom Styles -->
    <style>
        /* SweetAlert2 Customizations */
        .swal2-title {
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            font-weight: 600 !important;
            color: #1F2937 !important;
        }

        .swal2-html-container {
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
        }

        .swal2-popup {
            border-radius: 0.75rem !important;
            padding: 1.5rem !important;
        }

        .swal2-styled.swal2-confirm {
            border-radius: 0.5rem !important;
            font-weight: 500 !important;
            padding: 0.5rem 1.25rem !important;
        }

        .swal2-styled.swal2-cancel {
            border-radius: 0.5rem !important;
            font-weight: 500 !important;
            padding: 0.5rem 1.25rem !important;
        }
    </style>

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
                        },
                        danger: {
                            DEFAULT: '#dc3545',
                            dark: '#c82333',
                        }
                    },
                }
            }
        }
    </script>
</head>

<body class="bg-gray-50">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 ml-72">
            <!-- Header -->
            <div class="bg-blue-900 border-b border-gray-200 sticky top-0 z-10">
                <div class="px-8 py-4">
                    <h2 class="text-4xl font-bold text-white">Settings</h2>
                    <p class="text-white text-sm mt-1">
                        Manage your account settings and preferences
                    </p>
                </div>
            </div>

            <!-- Content Area -->
            <div class="p-8">
                <!-- Encryption Code Settings Card -->
                <div class="bg-white rounded-xl shadow-sm mb-6">
                    <div class="border-b border-gray-100 px-6 py-4">
                        <h3 class="text-xl font-semibold text-gray-800">Requests Access Security</h3>
                        <p class="text-gray-600 mt-1">
                            Set your personal 6-digit encryption code used for accessing the requests page
                        </p>
                    </div>
                    <div class="p-6">
                        <form method="POST" class="space-y-6">
                            <div class="space-y-2">
                                <label for="encryption_code" class="block text-sm font-medium text-gray-700">
                                    Personal Encryption Code
                                </label>
                                <input type="text"
                                    id="encryption_code"
                                    name="encryption_code"
                                    maxlength="6"
                                    class="block w-full px-4 py-3 rounded-lg border border-gray-300 shadow-sm focus:ring-primary-500 focus:border-primary-500"
                                    placeholder="Enter 6-digit code (letters & numbers)"
                                    value="<?php echo $hasExistingCode ? '******' : ''; ?>"
                                    required>
                                <p class="text-sm text-gray-500 mt-1">
                                    This code will be required whenever you access the requests page. Use a combination of 6 letters and numbers.
                                </p>
                            </div>

                            <div class="flex items-center space-x-4">
                                <button type="submit"
                                    name="update_encryption_code"
                                    class="inline-flex items-center px-5 py-2.5 border border-transparent text-sm font-medium rounded-lg shadow-sm text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                    <i class='bx bx-save mr-2'></i>
                                    <?php echo $hasExistingCode ? 'Update Encryption Code' : 'Set Encryption Code'; ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Password Settings Card -->
                <div class="bg-white rounded-xl shadow-sm mb-6">
                    <div class="border-b border-gray-100 px-6 py-4">
                        <h3 class="text-xl font-semibold text-gray-800">Account Security</h3>
                        <p class="text-gray-600 mt-1">
                            Update your account password
                        </p>
                    </div>
                    <div class="p-6">
                        <form method="POST" class="space-y-6">
                            <div class="space-y-4">
                                <div>
                                    <label for="current_password" class="block text-sm font-medium text-gray-700">
                                        Current Password
                                    </label>
                                    <input type="password"
                                        id="current_password"
                                        name="current_password"
                                        class="block w-full px-4 py-3 rounded-lg border border-gray-300 shadow-sm focus:ring-primary-500 focus:border-primary-500"
                                        placeholder="Enter your current password">
                                </div>

                                <div>
                                    <label for="new_password" class="block text-sm font-medium text-gray-700">
                                        New Password
                                    </label>
                                    <input type="password"
                                        id="new_password"
                                        name="new_password"
                                        class="block w-full px-4 py-3 rounded-lg border border-gray-300 shadow-sm focus:ring-primary-500 focus:border-primary-500"
                                        placeholder="Enter your new password">
                                </div>

                                <div>
                                    <label for="confirm_password" class="block text-sm font-medium text-gray-700">
                                        Confirm New Password
                                    </label>
                                    <input type="password"
                                        id="confirm_password"
                                        name="confirm_password"
                                        class="block w-full px-4 py-3 rounded-lg border border-gray-300 shadow-sm focus:ring-primary-500 focus:border-primary-500"
                                        placeholder="Confirm your new password">
                                </div>
                            </div>

                            <div class="flex items-center space-x-4">
                                <button type="submit"
                                    name="update_password"
                                    class="inline-flex items-center px-5 py-2.5 border border-transparent text-sm font-medium rounded-lg shadow-sm text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                    <i class='bx bx-lock-alt mr-2'></i>
                                    Update Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Profile Settings Card -->
                <div class="bg-white rounded-xl shadow-sm">
                    <div class="border-b border-gray-100 px-6 py-4">
                        <h3 class="text-xl font-semibold text-gray-800">Profile Information</h3>
                        <p class="text-gray-600 mt-1">
                            Update your personal information
                        </p>
                    </div>
                    <div class="p-6">
                        <form method="POST" class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="name" class="block text-sm font-medium text-gray-700">
                                        Full Name
                                    </label>
                                    <input type="text"
                                        id="name"
                                        name="name"
                                        class="block w-full px-4 py-3 rounded-lg border border-gray-300 shadow-sm focus:ring-primary-500 focus:border-primary-500"
                                        placeholder="Enter your full name"
                                        value="<?php echo htmlspecialchars($_SESSION['admin_name'] ?? ''); ?>">
                                </div>

                                <div>
                                    <label for="email" class="block text-sm font-medium text-gray-700">
                                        Email Address
                                    </label>
                                    <input type="email"
                                        id="email"
                                        name="email"
                                        class="block w-full px-4 py-3 rounded-lg border border-gray-300 shadow-sm focus:ring-primary-500 focus:border-primary-500"
                                        placeholder="Enter your email address"
                                        value="<?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="flex items-center space-x-4">
                                <button type="submit"
                                    name="update_profile"
                                    class="inline-flex items-center px-5 py-2.5 border border-transparent text-sm font-medium rounded-lg shadow-sm text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                    <i class='bx bx-user-circle mr-2'></i>
                                    Update Profile
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Clear password field on focus if it contains placeholder asterisks
            const encryptionCodeInput = document.getElementById('encryption_code');
            if (encryptionCodeInput) {
                encryptionCodeInput.addEventListener('focus', function() {
                    if (this.value === '******') {
                        this.value = '';
                    }
                });
            }

            <?php if (isset($success_message)): ?>
                Swal.fire({
                    title: 'Success!',
                    text: '<?php echo addslashes($success_message); ?>',
                    icon: 'success',
                    confirmButtonColor: '#0284c7'
                });
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                Swal.fire({
                    title: 'Error!',
                    text: '<?php echo addslashes($error_message); ?>',
                    icon: 'error',
                    confirmButtonColor: '#0284c7'
                });
            <?php endif; ?>
        });
    </script>
</body>

</html>
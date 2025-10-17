<?php
session_start();
require_once '../config.php';
require_once __DIR__ . '/../vendor/autoload.php';

// Enable error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$token = $_GET['token'] ?? '';
$valid_token = false;
$token_expired = false;
$email = '';
$debug_info = [];
$expires_at = '';

// Enhanced debugging
error_log("==== RESET PASSWORD ATTEMPT ====");
error_log("Token received: " . $token);
error_log("Current time: " . date('Y-m-d H:i:s'));

// Check if token is provided
if ($token) {
    // Trim token to remove any whitespace
    $token = trim($token);
    
    // Check the token directly from the database regardless of expiration
    $stmt = $pdo->prepare("SELECT * FROM uar.password_resets WHERE token = ?");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();
    
    if ($reset) {
        $email = $reset['employee_email'];
        $expires_at = $reset['expires_at'];
        
        error_log("Token found in database for email: " . $email);
        error_log("Token expiration time: " . $expires_at);
        
        // Check if token is expired
        if (strtotime($expires_at) > time()) {
            $valid_token = true;
            error_log("Token is still valid");
        } else {
            $token_expired = true;
            error_log("Token has expired. Expired at: " . $expires_at);
            
            // Calculate how long ago it expired
            $expired_ago = time() - strtotime($expires_at);
            $expired_ago_minutes = round($expired_ago / 60);
            $debug_info['expired_ago'] = $expired_ago_minutes . " minutes ago";
        }
    } else {
        error_log("Token not found in database");
        
        // Try checking if any token exists for debugging
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM uar.password_resets");
        $stmt->execute();
        $count = $stmt->fetch();
        error_log("Total password reset tokens in database: " . $count['count']);
    }
}

// Handle AJAX requests for password reset
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');
    
    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate input
    if (empty($token) || empty($password) || empty($confirm_password)) {
        echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
        exit;
    }
    
    if ($password !== $confirm_password) {
        echo json_encode(['status' => 'error', 'message' => 'Passwords do not match']);
        exit;
    }
    
    if (strlen($password) < 8) {
        echo json_encode(['status' => 'error', 'message' => 'Password must be at least 8 characters long']);
        exit;
    }
    
    // Check if token is valid
    $stmt = $pdo->prepare("SELECT * FROM uar.password_resets WHERE token = ? AND expires_at > GETDATE()");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();
    
    if (!$reset) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid or expired token']);
        exit;
    }
    
    // Update password
    try {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Update user password
        $stmt = $pdo->prepare("UPDATE uar.employees SET password = ?, is_temp_password = 0 WHERE employee_email = ?");
        $stmt->execute([$hashed_password, $reset['employee_email']]);
        
        // Delete reset token
        $stmt = $pdo->prepare("DELETE FROM uar.password_resets WHERE token = ?");
        $stmt->execute([$reset['token']]);
        
        echo json_encode(['status' => 'success', 'message' => 'Password has been reset successfully']);
    } catch (PDOException $e) {
        error_log("Database error during password reset: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error occurred. Please try again later.']);
    }
    
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/alpinejs/3.12.0/cdn.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#0084FF',
                        secondary: '#006ACC',
                        accent: '#4797FF',
                        dark: '#001A33'
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-out',
                        'slide-up': 'slideUp 0.5s ease-out'
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' }
                        },
                        slideUp: {
                            '0%': { transform: 'translateY(20px)', opacity: '0' },
                            '100%': { transform: 'translateY(0)', opacity: '1' }
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .form-container {
            backdrop-filter: blur(10px);
        }
        
        .bg-pattern {
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%236366f1' fill-opacity='0.1'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="form-container animate-fade-in bg-white p-0 rounded-2xl shadow-2xl w-full max-w-xl overflow-hidden">
        <div class="p-10 text-center relative bg-white">
            <div class="absolute inset-x-0 top-1/2 transform -translate-y-1/4 h-full" 
                 style="background-image: url('../logo.png'); background-repeat: no-repeat; background-size: contain; background-position: center;">
            </div>
        </div>
        
        <div class="p-8 bg-white">
            <div class="text-center mb-6">
                <h1 class="text-3xl font-bold text-gray-800 mb-2">Reset Password</h1>
                <?php if ($valid_token): ?>
                    <p class="text-gray-600 text-sm">Enter your new password below</p>
                <?php elseif ($token_expired): ?>
                    <p class="text-red-600 text-sm">Your password reset link has expired</p>
                    <?php if (!empty($expires_at)): ?>
                    <p class="text-gray-500 text-xs mt-1">Expired on: <?php echo date('F j, Y, g:i a', strtotime($expires_at)); ?></p>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-red-600 text-sm">Invalid password reset link</p>
                <?php endif; ?>
            </div>

            <?php if ($valid_token): ?>
            <form method="POST" id="resetPasswordForm" class="space-y-6" onsubmit="handleResetPassword(event)">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                
                <div class="animate-slide-up" style="animation-delay: 100ms">
                    <label for="password" class="block text-sm font-medium text-gray-800 mb-1">New Password</label>
                    <div class="relative" x-data="{ passwordVisible: false }">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <input :type="passwordVisible ? 'text' : 'password'" id="password" name="password" required minlength="8"
                            class="w-full pl-10 pr-10 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition duration-200"
                            placeholder="Enter new password">
                        <button type="button" @click="passwordVisible = !passwordVisible" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                            <svg x-show="!passwordVisible" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                            </svg>
                            <svg x-show="passwordVisible" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor" style="display: none;">
                                <path fill-rule="evenodd" d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 4.26l1.514 1.515a2.003 2.003 0 012.45 2.45l1.514 1.514a4 4 0 00-5.478-5.478z" clip-rule="evenodd" />
                                <path d="M12.454 16.697L9.75 13.992a4 4 0 01-3.742-3.741L2.335 6.578A9.98 9.98 0 00.458 10c1.274 4.057 5.065 7 9.542 7 .847 0 1.669-.105 2.454-.303z" />
                            </svg>
                        </button>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Password must be at least 8 characters long</p>
                </div>
                
                <div class="animate-slide-up" style="animation-delay: 200ms">
                    <label for="confirm_password" class="block text-sm font-medium text-gray-800 mb-1">Confirm Password</label>
                    <div class="relative" x-data="{ passwordVisible: false }">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <input :type="passwordVisible ? 'text' : 'password'" id="confirm_password" name="confirm_password" required minlength="8"
                            class="w-full pl-10 pr-10 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition duration-200"
                            placeholder="Confirm new password">
                        <button type="button" @click="passwordVisible = !passwordVisible" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                            <svg x-show="!passwordVisible" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                            </svg>
                            <svg x-show="passwordVisible" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor" style="display: none;">
                                <path fill-rule="evenodd" d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 4.26l1.514 1.515a2.003 2.003 0 012.45 2.45l1.514 1.514a4 4 0 00-5.478-5.478z" clip-rule="evenodd" />
                                <path d="M12.454 16.697L9.75 13.992a4 4 0 01-3.742-3.741L2.335 6.578A9.98 9.98 0 00.458 10c1.274 4.057 5.065 7 9.542 7 .847 0 1.669-.105 2.454-.303z" />
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit"
                    class="w-full flex items-center justify-center bg-primary hover:bg-secondary text-white font-semibold py-3 px-4 rounded-lg transition duration-300 transform hover:scale-[1.02] active:scale-[0.98]">
                    Reset Password
                </button>
            </form>
            <?php else: ?>
                <div class="text-center">
                    <p class="text-gray-700 mb-4">
                        <?php if ($token_expired): ?>
                            Your password reset link has expired. Please request a new one.
                        <?php else: ?>
                            Invalid password reset link. Please check your email or request a new link.
                        <?php endif; ?>
                    </p>
                    <a href="forgot_password.php" class="inline-block bg-primary hover:bg-secondary text-white font-semibold py-2 px-4 rounded-lg transition duration-300" id="requestNewLink">
                        Request New Link
                    </a>
                </div>
            <?php endif; ?>
            
            <div class="text-center mt-6">
                <a href="login.php" class="text-primary hover:text-secondary text-sm">Back to Login</a>
            </div>
        </div>
        
        <div class="px-8 py-4 bg-gray-50 text-center text-sm text-gray-600">
            &copy; <?php echo date('Y'); ?> Alsons Agribusiness Unit. All rights reserved.
        </div>
    </div>

    <script>
        function handleResetPassword(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            
            // Validate passwords match
            const password = formData.get('password');
            const confirmPassword = formData.get('confirm_password');
            
            if (password !== confirmPassword) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Passwords do not match',
                    confirmButtonColor: '#0084FF'
                });
                return;
            }
            
            // Validate password length
            if (password.length < 8) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Password must be at least 8 characters long',
                    confirmButtonColor: '#0084FF'
                });
                return;
            }
            
            // Show loading
            Swal.fire({
                title: 'Resetting Password',
                html: `
                    <div class="flex flex-col items-center">
                        <div class="w-16 h-16 mb-4">
                            <svg class="animate-spin w-full h-full text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>
                        <p class="text-gray-600">Please wait while we update your password</p>
                    </div>
                `,
                allowOutsideClick: false,
                showConfirmButton: false
            });
            
            // Send request with AJAX
            fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: data.message,
                        confirmButtonColor: '#0084FF'
                    }).then(() => {
                        window.location.href = 'login.php';
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message,
                        confirmButtonColor: '#0084FF'
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An unexpected error occurred. Please try again.',
                    confirmButtonColor: '#0084FF'
                });
            });
        }
        
        // Add click handler for the request new link button
        document.addEventListener('DOMContentLoaded', function() {
            const requestNewLinkBtn = document.getElementById('requestNewLink');
            if (requestNewLinkBtn) {
                requestNewLinkBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    window.location.href = 'forgot_password.php';
                });
            }
        });
    </script>
</body>
</html> 
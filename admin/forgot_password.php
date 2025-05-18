<?php
session_start();
require_once '../config.php';
require_once __DIR__ . '/../vendor/autoload.php';

// Enable error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Handle AJAX requests
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');
    
    $employee_email = $_POST['employee_email'] ?? '';
    
    // Log the password reset attempt
    error_log("Admin password reset attempt for: " . $employee_email);
    
    // Check if email exists with admin privileges
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE employee_email = ? AND role IN ('admin', 'superior', 'technical_support', 'process_owner')");
    $stmt->execute([$employee_email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'No admin account found with that email address']);
        exit;
    }
    
    // Generate a unique token - make it shorter for better URL compatibility
    $token = bin2hex(random_bytes(16)); // 32 characters instead of 64
    $expires = date('Y-m-d H:i:s', strtotime('+24 hours')); // Increased from 1 hour to 24 hours
    
    error_log("==== ADMIN PASSWORD RESET REQUESTED ====");
    error_log("Admin Email: " . $employee_email);
    error_log("Generated token: " . $token);
    error_log("Expiration time: " . $expires);
    
    // Store the token in the database
    try {
        // Check if there's an existing token and delete it
        $stmt = $pdo->prepare("DELETE FROM password_resets WHERE employee_email = ?");
        $stmt->execute([$employee_email]);
        
        // Insert new token
        $stmt = $pdo->prepare("INSERT INTO password_resets (employee_email, token, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$employee_email, $token, $expires]);
        
        // Create reset URL - ensure URL encoding of token
        $encodedToken = urlencode($token);
        $resetUrl = "http://{$_SERVER['HTTP_HOST']}/uar/admin/reset_password.php?token=" . $encodedToken;
        
        // Log the generated URL for debugging
        error_log("Generated admin reset URL for {$employee_email}: {$resetUrl}");
        
        // Send email with PHPMailer
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'charlesondota@gmail.com';
            $mail->Password = 'crpf bbcb vodv xbjk';
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('charlesondota@gmail.com', 'Alsons Agribusiness');
            $mail->addAddress($employee_email, $user['employee_name']);
            $mail->isHTML(true);
            $mail->Subject = 'Admin Portal Password Reset Request';
            
            // Improved email body with clearer instructions
            $mail->Body = "
                <h2>Admin Password Reset Request</h2>
                <p>Hello {$user['employee_name']},</p>
                <p>You have requested to reset your admin password. Click the link below to set a new password:</p>
                <p><a href='{$resetUrl}' style='padding: 10px 20px; background-color: #0ea5e9; color: white; text-decoration: none; border-radius: 5px;'>Reset Password</a></p>
                <p>If the button above doesn't work, copy and paste this link into your browser:</p>
                <p style='word-break: break-all;'>{$resetUrl}</p>
                <p>This link will expire in 24 hours.</p>
                <p>If you did not request a password reset, please ignore this email.</p>
                <p>Regards,<br>Alsons Agribusiness</p>
            ";
            
            // Also include plain text version
            $mail->AltBody = "
                Admin Password Reset Request
                
                Hello {$user['employee_name']},
                
                You have requested to reset your admin password. Please copy and paste the following link into your browser to set a new password:
                
                {$resetUrl}
                
                This link will expire in 24 hours.
                
                If you did not request a password reset, please ignore this email.
                
                Regards,
                Alsons Agribusiness
            ";
            
            $mail->send();
            error_log("Admin reset email sent successfully to {$employee_email}");
            echo json_encode(['status' => 'success', 'message' => 'Password reset link has been sent to your email']);
        } catch (Exception $e) {
            error_log("Failed to send admin password reset email: " . $mail->ErrorInfo);
            echo json_encode(['status' => 'error', 'message' => 'Failed to send reset email. Please try again later.']);
        }
    } catch (PDOException $e) {
        error_log("Database error during admin password reset: " . $e->getMessage());
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
    <title>Admin Forgot Password</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/alpinejs/3.12.0/cdn.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
                    },
                    fontFamily: {
                        'sans': ['Inter', 'sans-serif'],
                    },
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
                <h1 class="text-3xl font-bold text-gray-800 mb-2">Admin Forgot Password</h1>
                <p class="text-gray-600 text-sm">Enter your email to receive a password reset link</p>
            </div>

            <form method="POST" id="forgotPasswordForm" class="space-y-6" onsubmit="handleForgotPassword(event)">
                <div class="animate-slide-up" style="animation-delay: 100ms">
                    <label for="employee_email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
                                <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                            </svg>
                        </div>
                        <input type="email" id="employee_email" name="employee_email" required autofocus
                            class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition duration-200"
                            placeholder="Enter your email">
                    </div>
                </div>

                <button type="submit"
                    class="w-full flex items-center justify-center bg-primary-600 hover:bg-primary-700 text-white font-semibold py-3 px-4 rounded-lg transition duration-300 transform hover:scale-[1.02] active:scale-[0.98]">
                    Send Reset Link
                </button>
                
                <div class="text-center mt-4">
                    <a href="login.php" class="text-primary-600 hover:text-primary-700 text-sm">Back to Login</a>
                </div>
            </form>
        </div>
        
        <div class="px-8 py-4 bg-gray-50 text-center text-sm text-gray-600">
            &copy; <?php echo date('Y'); ?> Alsons Agribusiness Unit. All rights reserved.
        </div>
    </div>

    <script>
        function handleForgotPassword(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            
            // Show loading
            Swal.fire({
                title: 'Processing',
                html: `
                    <div class="flex flex-col items-center">
                        <div class="w-16 h-16 mb-4">
                            <svg class="animate-spin w-full h-full text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>
                        <p class="text-gray-600">Please wait while we process your request</p>
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
                        confirmButtonColor: '#0ea5e9'
                    }).then(() => {
                        window.location.href = 'login.php';
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message,
                        confirmButtonColor: '#0ea5e9'
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An unexpected error occurred. Please try again.',
                    confirmButtonColor: '#0ea5e9'
                });
            });
        }
    </script>
</body>
</html> 
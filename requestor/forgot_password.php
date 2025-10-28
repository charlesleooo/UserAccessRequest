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
    error_log("Password reset attempt for: " . $employee_email);
    
    // Check if email exists
    $stmt = $pdo->prepare("SELECT * FROM uar.employees WHERE employee_email = ?");
    $stmt->execute([$employee_email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'No account found with that email address']);
        exit;
    }
    
    // Generate a unique token
    $token = bin2hex(random_bytes(16)); // 32 characters
    $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    error_log("==== PASSWORD RESET REQUESTED ====");
    error_log("Email: " . $employee_email);
    error_log("Generated token: " . $token);
    error_log("Expiration time: " . $expires);
    
    try {
        // Delete any existing tokens for this email
        $stmt = $pdo->prepare("DELETE FROM uar.password_resets WHERE employee_email = ?");
        $stmt->execute([$employee_email]);
        
        // Insert new token
        $stmt = $pdo->prepare("INSERT INTO uar.password_resets (employee_email, token, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$employee_email, $token, $expires]);
        
        // Create reset URL with proper encoding and path construction
        $encodedToken = urlencode($token);
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $domain = $_SERVER['HTTP_HOST'];
        $path = dirname(dirname($_SERVER['PHP_SELF']));  // Go up one level from requestor directory
        $resetUrl = $protocol . $domain . $path . '/requestor/reset_password.php?token=' . $encodedToken;
        
        error_log("Generated reset URL: " . $resetUrl);
        
        // Configure and send email
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'charlesondota@gmail.com';  // Your email
            $mail->Password = 'crpf bbcb vodv xbjk';     // Your app password
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            // Recipients
            $mail->setFrom('charlesondota@gmail.com', 'Alsons Agribusiness');
            $mail->addAddress($employee_email, $user['employee_name']);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Request';
            
            // Email body with improved styling and clear instructions
            $mail->Body = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <h2 style='color: #0084FF; margin-bottom: 20px;'>Password Reset Request</h2>
                    <p style='margin-bottom: 15px;'>Hello {$user['employee_name']},</p>
                    <p style='margin-bottom: 20px;'>You have requested to reset your password. Click the button below to set a new password:</p>
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='{$resetUrl}' style='background-color: #0084FF; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>Reset Password</a>
                    </div>
                    <p style='margin-bottom: 15px;'>If the button above doesn't work, copy and paste this link into your browser:</p>
                    <p style='background-color: #f3f4f6; padding: 10px; word-break: break-all; margin-bottom: 20px; font-size: 14px;'>{$resetUrl}</p>
                    <p style='margin-bottom: 10px;'><strong>Important:</strong> This link will expire in 24 hours.</p>
                    <p style='color: #6b7280; font-size: 14px;'>If you did not request a password reset, please ignore this email.</p>
                    <hr style='margin: 30px 0; border: none; border-top: 1px solid #e5e7eb;'>
                    <p style='color: #6b7280; font-size: 14px; margin-bottom: 0;'>Regards,<br>Alsons Agribusiness</p>
                </div>
            ";
            
            // Plain text version
            $mail->AltBody = "
                Password Reset Request

                Hello {$user['employee_name']},

                You have requested to reset your password. Please copy and paste the following link into your browser to set a new password:

                {$resetUrl}

                This link will expire in 24 hours.

                If you did not request a password reset, please ignore this email.

                Regards,
                Alsons Agribusiness
            ";
            
            $mail->send();
            error_log("Reset email sent successfully to {$employee_email}");
            echo json_encode(['status' => 'success', 'message' => 'Password reset link has been sent to your email']);
            
        } catch (Exception $e) {
            error_log("Failed to send reset email: " . $mail->ErrorInfo);
            echo json_encode(['status' => 'error', 'message' => 'Failed to send reset email. Please try again later.']);
        }
        
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'A database error occurred. Please try again later.']);
    }
    
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
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
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md overflow-hidden">
        <div class="p-8">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-gray-900">Forgot Password</h1>
                <p class="text-gray-600 mt-2">Enter your email to receive a password reset link</p>
            </div>

            <form id="forgotPasswordForm" class="space-y-6" onsubmit="handleForgotPassword(event)">
                <div>
                    <label for="employee_email" class="block text-sm font-medium text-gray-700">Company Email</label>
                    <input type="email" id="employee_email" name="employee_email" required
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary focus:border-primary">
                </div>

                <button type="submit"
                        class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary hover:bg-secondary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                    Send Reset Link
                </button>
            </form>

            <div class="mt-6 text-center">
                <a href="login.php" class="text-sm text-primary hover:text-secondary">Back to Login</a>
            </div>
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
            
            // Show loading state
            Swal.fire({
                title: 'Sending Reset Link',
                text: 'Please wait...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Send AJAX request
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
                        title: 'Success!',
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
    </script>
</body>
<?php include '../footer.php'; ?>
</html>
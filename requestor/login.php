<?php
session_start();
require_once '../config.php';
require_once __DIR__ . '/../vendor/autoload.php';

// Variables
$otp_attempt = false;
$user = null;

// OTP Generation function
function generateOTP($length = 6) {
    $characters = '0123456789';
    $otp = '';
    for ($i = 0; $i < $length; $i++) {
        $otp .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $otp;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['send_otp'])) {
        $employee_email = $_POST['employee_email'] ?? '';

        // Fetch employee by email
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE employee_email = ?");
        $stmt->execute([$employee_email]);
        $user = $stmt->fetch();

        if ($user) {
            // Generate and store OTP
            $otp = generateOTP();
            $_SESSION['otp'] = $otp;
            $_SESSION['otp_expiry'] = time() + 300;
            $_SESSION['temp_user'] = $user;

            // Send OTP via PHPMailer
            $mail = new PHPMailer\PHPMailer\PHPMailer();
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'charlesondota@gmail.com'; // Replace with actual
            $mail->Password = 'crpf bbcb vodv xbjk'; // App password, not real password
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('your-email@gmail.com', 'Alsons Agribusiness');
            $mail->addAddress($user['employee_email'], $user['employee_name']);
            $mail->isHTML(true);
            $mail->Subject = 'Your One Time Password for Login';
            $mail->Body = "Hello {$user['employee_name']}, your OTP is <b>$otp</b>. It expires in 5 minutes.";

            if ($mail->send()) {
                $otp_attempt = true;
            } else {
                $error = 'Failed to send OTP. Try again later.';
            }
        } else {
            $error = 'Email not found.';
        }
    } elseif (isset($_POST['verify_otp'])) {
        $entered_otp = $_POST['otp'] ?? '';
        if (!isset($_SESSION['otp'], $_SESSION['otp_expiry'])) {
            $error = "OTP session expired. Request a new one.";
        } elseif ($entered_otp == $_SESSION['otp'] && time() < $_SESSION['otp_expiry']) {
            if (isset($_SESSION['temp_user'])) {
                $user = $_SESSION['temp_user'];

                // Set necessary session variables
                $_SESSION['requestor_id'] = $user['employee_id']; // This is already correct
                $_SESSION['employee_email'] = $user['employee_email'];
                $_SESSION['username'] = $user['employee_name'];
                $_SESSION['role'] = 'employee';

                // Clear OTP data
                unset($_SESSION['otp'], $_SESSION['otp_expiry'], $_SESSION['temp_user']);

                // Redirect
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Session data missing. Please try again.";
            }
        } else {
            $error = "Invalid or expired OTP.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Login</title>
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
    <div x-data="{ loading: false, passwordVisible: false }" 
         class="form-container animate-fade-in bg-white p-0 rounded-2xl shadow-2xl w-full max-w-xl overflow-hidden">
        
        <div class="p-12 text-center relative bg-white">
            <div class="absolute inset-x-0 top-1/2 transform -translate-y-1/4 h-full" 
                 style="background-image: url('../logo.png'); background-repeat: no-repeat; background-size: contain; background-position: center;">
            </div>
        </div>
        
        <div class="p-8 bg-white">
            <div class="text-center mb-6">
                <h1 class="text-3xl font-bold text-gray-800 mb-2">Requestor Portal</h1>
                <p class="text-gray-600 text-sm">Sign in to access your dashboard</p>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded animate-slide-up" role="alert">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p><?php echo htmlspecialchars($error); ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!$otp_attempt): ?>
                <!-- Send OTP Form -->
                <form method="POST" class="space-y-6" x-on:submit="loading = true">
                    <div class="animate-slide-up" style="animation-delay: 100ms">
                        <label for="employee_email" class="block text-sm font-medium text-gray-800 mb-1">Please Enter Your Company Email</label>
                        <div class="relative">
                            <!-- SVG Icon Inside the Input -->
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <input type="email" id="employee_email" name="employee_email" required autofocus
                                class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition duration-200"
                                placeholder="Enter your email">
                        </div>
                    </div>

                    <button type="submit" name="send_otp" 
                        class="w-full flex items-center justify-center bg-primary hover:bg-secondary text-white font-semibold py-3 px-4 rounded-lg transition duration-300 transform hover:scale-[1.02] active:scale-[0.98]">
                        Send OTP
                    </button>
                </form>

            <?php else: ?>
                <!-- OTP Verification Form -->
                <form method="POST" class="space-y-6" x-on:submit="loading = true">
                    <div class="animate-slide-up" style="animation-delay: 100ms">
                        <label for="otp" class="block text-l font-medium text-gray-700 mb-1">Enter OTP</label>
                        <div class="relative">
                            <!-- Lock Icon Inside the OTP Input -->
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <input type="text" id="otp" name="otp" required
                                class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition duration-200"
                                placeholder="Enter the OTP that was sent to your email">
                        </div>
                    </div>
                    
                    <button type="submit" name="verify_otp" 
                        class="w-full flex items-center justify-center bg-primary hover:bg-secondary text-white font-semibold py-3 px-4 rounded-lg transition duration-300 transform hover:scale-[1.02] active:scale-[0.98]">
                        Login
                    </button>
                </form>
            <?php endif; ?>
        </div>
        
        <div class="px-8 py-4 bg-gray-50 text-center text-sm text-gray-600">
            &copy; <?php echo date('Y'); ?> Alsons Agribusiness Unit. All rights reserved.
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle OTP Send Form
            const otpForm = document.querySelector('form[name="send_otp"]');
            if (otpForm) {
                otpForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    Swal.fire({
                        title: 'Sending OTP...',
                        text: 'Please wait while we send the OTP to your email',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        willOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    setTimeout(() => {
                        this.submit();
                    }, 1000);
                });
            }

            // Handle OTP Verification Form
            const verifyForm = document.querySelector('form[name="verify_otp"]');
            if (verifyForm) {
                verifyForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    Swal.fire({
                        title: 'Verifying OTP...',
                        text: 'Please wait while we verify your OTP',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        willOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    setTimeout(() => {
                        this.submit();
                    }, 1000);
                });
            }

            <?php if (isset($error)): ?>
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: '<?php echo addslashes($error); ?>',
                    confirmButtonColor: '#0084FF'
                });
            <?php endif; ?>

            <?php if ($otp_attempt && !isset($error)): ?>
                Swal.fire({
                    icon: 'success',
                    title: 'OTP Sent Successfully',
                    text: 'Please check your email for the OTP',
                    confirmButtonColor: '#0084FF'
                });
            <?php endif; ?>
        });
    </script>
</body>
</html>
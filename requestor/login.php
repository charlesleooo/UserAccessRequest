<?php
session_start();
require_once '../config.php';
require_once __DIR__ . '/../vendor/autoload.php';

// Handle AJAX requests
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');
    
    if (isset($_POST['send_otp'])) {
        $employee_email = $_POST['employee_email'] ?? '';
        $password = $_POST['password'] ?? '';
        
        // Fetch employee by email
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE employee_email = ?");
        $stmt->execute([$employee_email]);
        $user = $stmt->fetch();

        if (!$user) {
            echo json_encode(['status' => 'error', 'message' => 'Email not found']);
            exit;
        }
        
        if (!password_verify($password, $user['password'])) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid password']);
            exit;
        }

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
        $mail->Username = 'charlesondota@gmail.com';
        $mail->Password = 'crpf bbcb vodv xbjk';
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('your-email@gmail.com', 'Alsons Agribusiness');
        $mail->addAddress($user['employee_email'], $user['employee_name']);
        $mail->isHTML(true);
        $mail->Subject = 'Your One Time Password for Login';
        $mail->Body = "Hello {$user['employee_name']}, your OTP is <b>$otp</b>. It expires in 5 minutes.";

        if ($mail->send()) {
            echo json_encode(['status' => 'success', 'message' => 'OTP sent successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to send OTP. Please try again.']);
        }
        exit;
    }
    
    if (isset($_POST['verify_otp'])) {
        $entered_otp = $_POST['otp'] ?? '';
        if (!isset($_SESSION['otp'], $_SESSION['otp_expiry'])) {
            echo json_encode(['status' => 'error', 'message' => 'OTP session expired. Please try again.']);
            exit;
        }
        
        if ($entered_otp == $_SESSION['otp'] && time() < $_SESSION['otp_expiry']) {
            if (isset($_SESSION['temp_user'])) {
                $user = $_SESSION['temp_user'];
                $_SESSION['requestor_id'] = $user['employee_id'];
                $_SESSION['employee_email'] = $user['employee_email'];
                $_SESSION['username'] = $user['employee_name'];
                $_SESSION['role'] = $user['role'] ?? 'requestor';

                unset($_SESSION['otp'], $_SESSION['otp_expiry'], $_SESSION['temp_user']);

                if ($user['password'] === null || $user['is_temp_password']) {
                    echo json_encode(['status' => 'success', 'redirect' => 'change_password.php']);
                } else {
                    echo json_encode(['status' => 'success', 'redirect' => 'dashboard.php']);
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Session data missing. Please try again.']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid or expired OTP']);
        }
        exit;
    }
}

// OTP Generation function
function generateOTP($length = 6) {
    $characters = '0123456789';
    $otp = '';
    for ($i = 0; $i < $length; $i++) {
        $otp .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $otp;
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
        }    </style>
    <script>
        function handleLogin(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            formData.append('send_otp', '1');

            // Show loading
            Swal.fire({
                title: 'Please wait...',
                text: 'Verifying your credentials',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });

            // Send request with AJAX
            fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Show OTP input modal
                    Swal.fire({
                        title: 'Enter OTP',
                        text: 'Please enter the OTP sent to your email',
                        input: 'text',
                        inputAttributes: {
                            autocapitalize: 'off',
                            maxlength: 6,
                            autocomplete: 'off',
                            placeholder: 'Enter 6-digit OTP'
                        },
                        showCancelButton: true,
                        confirmButtonText: 'Verify',
                        showLoaderOnConfirm: true,
                        allowOutsideClick: false,
                        preConfirm: (otp) => {
                            return fetch(form.action, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                                body: `verify_otp=1&otp=${otp}`
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.status === 'error') {
                                    throw new Error(data.message)
                                }
                                return data;
                            });
                        }
                    }).then((result) => {
                        if (result.isConfirmed && result.value.status === 'success') {
                            window.location.href = result.value.redirect;
                        }
                    }).catch(error => {
                        Swal.showValidationMessage(error.message);
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

            <!-- Login Form -->
            <form method="POST" class="space-y-6" onsubmit="handleLogin(event)">
                <div class="animate-slide-up" style="animation-delay: 100ms">
                    <label for="employee_email" class="block text-sm font-medium text-gray-800 mb-1">Company Email</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
                                <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                            </svg>
                        </div>
                        <input type="email" id="employee_email" name="employee_email" required autofocus
                            class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition duration-200"
                            placeholder="Enter your email">
                    </div>
                </div>

                <div class="animate-slide-up" style="animation-delay: 200ms">
                    <label for="password" class="block text-sm font-medium text-gray-800 mb-1">Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                            <button type="button" @click="passwordVisible = !passwordVisible" class="text-gray-400 hover:text-gray-500 focus:outline-none">
                                <svg x-show="!passwordVisible" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                    <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                </svg>
                                <svg x-show="passwordVisible" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" style="display: none;">
                                    <path fill-rule="evenodd" d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 4.26l1.514 1.515a2.003 2.003 0 012.45 2.45l1.514 1.514a4 4 0 00-5.478-5.478z" clip-rule="evenodd" />
                                    <path d="M12.454 16.697L9.75 13.992a4 4 0 01-3.742-3.741L2.335 6.578A9.98 9.98 0 00.458 10c1.274 4.057 5.065 7 9.542 7 .847 0 1.669-.105 2.454-.303z" />
                                </svg>
                            </button>
                        </div>
                        <input :type="passwordVisible ? 'text' : 'password'" id="password" name="password" required
                            class="w-full pl-10 pr-12 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition duration-200"
                            placeholder="Enter your password">
                    </div>
                </div>

                <button type="submit" name="send_otp" value="1"
                    class="w-full flex items-center justify-center bg-primary hover:bg-secondary text-white font-semibold py-3 px-4 rounded-lg transition duration-300 transform hover:scale-[1.02] active:scale-[0.98]">
                    Login
                </button>
            </form>
        </div>
        
        <div class="px-8 py-4 bg-gray-50 text-center text-sm text-gray-600">
            &copy; <?php echo date('Y'); ?> Alsons Agribusiness Unit. All rights reserved.
        </div>
    </div>
</body>
</html>
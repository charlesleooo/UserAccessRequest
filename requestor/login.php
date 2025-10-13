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

    if (isset($_POST['send_otp'])) {
        $employee_email = $_POST['employee_email'] ?? '';
        $password = $_POST['password'] ?? '';

        // Log the credentials attempt (without the actual password)
        error_log("Login attempt for: " . $employee_email);
        error_log("Session ID: " . session_id());

        // Fetch employee by email
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE employee_email = ?");
        $stmt->execute([$employee_email]);
        $user = $stmt->fetch();

        if (!$user) {
            echo json_encode(['status' => 'error', 'message' => 'Email not found']);
            exit;
        }

        // Check if user is archived (inactive)
        $archiveStmt = $pdo->prepare("SELECT * FROM employees_archive WHERE employee_id = ?");
        $archiveStmt->execute([$user['employee_id']]);
        $archivedUser = $archiveStmt->fetch();

        if ($archivedUser) {
            echo json_encode(['status' => 'error', 'message' => 'Your account has been deactivated. Please contact the administrator.']);
            exit;
        }

        if (!password_verify($password, $user['password'])) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid password']);
            exit;
        }


        // Generate and store OTP (only used for development mode)
        $otp = generateOTP();
        $_SESSION['otp'] = $otp;
        $_SESSION['otp_expiry'] = time() + 300; // 5 minutes
        $_SESSION['temp_user'] = $user;

        // === DEVELOPMENT MODE ===
        // Instead of sending email, just log the OTP to error log
        error_log("DEV OTP for {$user['employee_email']}: " . $otp);
        error_log("Session data stored - temp_user: " . print_r($user, true));

        // Respond with success
        echo json_encode([
            'status' => 'success',
            'message' => 'OTP generated (DEV MODE). Check server logs for OTP.'
        ]);
        exit;


        /*
        // Generate and store OTP
        $otp = generateOTP();
        $_SESSION['otp'] = $otp;
        $_SESSION['otp_expiry'] = time() + 300; // 5 minutes    
        $_SESSION['temp_user'] = $user;

        error_log("OTP generated for " . $employee_email . ": " . $otp);

        // Send OTP via PHPMailer
        $mail = new PHPMailer\PHPMailer\PHPMailer(true); // Enable exceptions
        try {
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = SMTP_PORT;
            $mail->SMTPDebug = 0; // 0 = off, 1 = client messages, 2 = client and server messages

            $mail->setFrom(SMTP_FROM_EMAIL ?: SMTP_USERNAME, SMTP_FROM_NAME);
            $mail->addAddress($user['employee_email'], $user['employee_name']);
            $mail->isHTML(true);
            $mail->Subject = 'Your One Time Password for Login';
            $mail->Body = "Hello {$user['employee_name']}, your OTP is <b>$otp</b>. It expires in 5 minutes.";

            $mail->send();

            // Log OTP details for debugging
            error_log("OTP generated and stored in session: " . $otp);
            error_log("Session ID before write: " . session_id());

            // Ensure session is written to storage
            session_write_close();
            session_start();

            error_log("Session ID after restart: " . session_id());

            error_log("OTP email sent successfully to " . $user['employee_email']);
            echo json_encode(['status' => 'success', 'message' => 'OTP sent successfully']);
        } catch (Exception $e) {
            error_log("Failed to send OTP email: " . $mail->ErrorInfo);
            echo json_encode(['status' => 'error', 'message' => 'Failed to send OTP: ' . $mail->ErrorInfo]);
        }
        exit;
        */
        
    }

    if (isset($_POST['verify_otp'])) {
        $entered_otp = $_POST['otp'] ?? '';
        if (!isset($_SESSION['otp'], $_SESSION['otp_expiry'])) {
            echo json_encode(['status' => 'error', 'message' => 'OTP session expired. Please try again.']);
            exit;
        }

        if ($_SESSION['otp_expiry'] < time()) {
            echo json_encode(['status' => 'error', 'message' => 'OTP has expired. Please request a new one.']);
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
            echo json_encode(['status' => 'error', 'message' => 'Invalid OTP. Please try again.']);
        }
        exit;
    }
}

// OTP Generation function
function generateOTP($length = 6)
{
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
                            '0%': {
                                opacity: '0'
                            },
                            '100%': {
                                opacity: '1'
                            }
                        },
                        slideUp: {
                            '0%': {
                                transform: 'translateY(20px)',
                                opacity: '0'
                            },
                            '100%': {
                                transform: 'translateY(0)',
                                opacity: '1'
                            }
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

            // Custom SweetAlert2 styling
            const customClass = {
                popup: 'bg-white rounded-2xl shadow-2xl',
                title: 'text-gray-800 font-bold text-xl',
                htmlContainer: 'text-gray-600',
                input: 'mt-4 w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent text-center text-2xl tracking-widest',
                confirmButton: 'bg-primary hover:bg-secondary text-white font-semibold px-6 py-2.5 rounded-lg transition duration-300 transform hover:scale-[1.02] active:scale-[0.98] mx-2',
                cancelButton: 'bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold px-6 py-2.5 rounded-lg transition duration-300 mx-2'
            };

            // Show loading with improved design
            Swal.fire({
                title: 'Authenticating',
                html: `
                    <div class="flex flex-col items-center">
                        <div class="w-16 h-16 mb-4">
                            <svg class="animate-spin w-full h-full text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>
                        <p class="text-gray-600">Please wait while we verify your credentials</p>
                    </div>
                `,
                allowOutsideClick: false,
                showConfirmButton: false,
                customClass
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
                        // Show OTP input modal with improved design
                        Swal.fire({
                            title: 'Enter OTP',
                            html: `
                            <div class="space-y-4">
                                <div class="flex justify-center">
                                    <div class="rounded-full bg-green-100 p-3">
                                        <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                </div>
                                <p class="text-gray-600">We've sent a verification code to your email</p>
                                <p class="text-sm text-gray-500">Please check your inbox and enter the code below</p>
                                <div id="otp-container" class="flex justify-center space-x-2 mt-4">
                                    <input type="text" maxlength="1" class="w-10 h-12 text-center text-xl border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-primary" autocomplete="off">
                                    <input type="text" maxlength="1" class="w-10 h-12 text-center text-xl border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-primary" autocomplete="off">
                                    <input type="text" maxlength="1" class="w-10 h-12 text-center text-xl border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-primary" autocomplete="off">
                                    <input type="text" maxlength="1" class="w-10 h-12 text-center text-xl border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-primary" autocomplete="off">
                                    <input type="text" maxlength="1" class="w-10 h-12 text-center text-xl border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-primary" autocomplete="off">
                                    <input type="text" maxlength="1" class="w-10 h-12 text-center text-xl border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-primary" autocomplete="off">
                                </div>
                                <input type="hidden" id="complete-otp">
                            </div>
                        `,
                            showCancelButton: true,
                            confirmButtonText: 'Verify',
                            cancelButtonText: 'Cancel',
                            showLoaderOnConfirm: true,
                            allowOutsideClick: false,
                            customClass,
                            buttonsStyling: false,
                            didOpen: () => {
                                const otpInputs = document.querySelectorAll('#otp-container input');
                                const completeOtp = document.getElementById('complete-otp');

                                // Focus first input on open
                                otpInputs[0].focus();

                                // Handle input for each box
                                otpInputs.forEach((input, index) => {
                                    // Auto focus next input
                                    input.addEventListener('input', function() {
                                        if (this.value.length === 1) {
                                            if (index < otpInputs.length - 1) {
                                                otpInputs[index + 1].focus();
                                            }
                                        }

                                        // Update complete OTP value
                                        let otp = '';
                                        otpInputs.forEach(input => otp += input.value);
                                        completeOtp.value = otp;
                                    });

                                    // Handle backspace
                                    input.addEventListener('keydown', function(e) {
                                        if (e.key === 'Backspace' && !this.value) {
                                            if (index > 0) {
                                                otpInputs[index - 1].focus();
                                            }
                                        }
                                    });

                                    // Handle paste
                                    input.addEventListener('paste', function(e) {
                                        e.preventDefault();
                                        const paste = (e.clipboardData || window.clipboardData).getData('text');
                                        if (!isNaN(paste) && paste.length <= otpInputs.length) {
                                            for (let i = 0; i < paste.length; i++) {
                                                if (index + i < otpInputs.length) {
                                                    otpInputs[index + i].value = paste[i];
                                                }
                                            }

                                            // Focus appropriate input after paste
                                            const focusIndex = Math.min(index + paste.length, otpInputs.length - 1);
                                            otpInputs[focusIndex].focus();

                                            // Update complete OTP
                                            let otp = '';
                                            otpInputs.forEach(input => otp += input.value);
                                            completeOtp.value = otp;
                                        }
                                    });
                                });
                            },
                            preConfirm: () => {
                                const completeOtp = document.getElementById('complete-otp').value;
                                if (!completeOtp || completeOtp.length !== 6) {
                                    Swal.showValidationMessage('Please enter the complete 6-digit OTP code');
                                    return false;
                                }

                                return fetch(form.action, {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/x-www-form-urlencoded',
                                            'X-Requested-With': 'XMLHttpRequest'
                                        },
                                        body: new URLSearchParams({
                                            verify_otp: '1',
                                            otp: completeOtp
                                        }).toString()
                                    })
                                    .then(response => {
                                        if (!response.ok) {
                                            throw new Error('Network response was not ok');
                                        }
                                        return response.json();
                                    })
                                    .then(data => {
                                        if (data.status === 'error') {
                                            throw new Error(data.message);
                                        }
                                        return data;
                                    })
                                    .catch(error => {
                                        Swal.showValidationMessage(error.message || 'An error occurred');
                                        return false;
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
                            customClass: {
                                ...customClass,
                                icon: 'text-red-500 border-red-200'
                            },
                            buttonsStyling: false
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
                <h1 class="text-3xl font-bold text-gray-800 mb-2">User Access Request (UAR)</h1>
                <p class="text-gray-600 text-sm">Sign in to access your dashboard</p>
            </div>

            <form method="POST" action="login.php" class="space-y-6" onsubmit="handleLogin(event)">
                <!-- Email Input -->
                <div>
                    <label for="employee_email" class="block text-sm font-medium text-gray-700 mb-1">Company Email</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
                                <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                            </svg>
                        </div>
                        <input type="email" id="employee_email" name="employee_email" required
                            class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="Enter your email">
                    </div>
                </div>

                <!-- Password Input -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label for="password" class="block text-sm font-medium text-gray-700">
                            Password
                        </label>
                        <a href="forgot_password.php" class="text-sm text-primary hover:text-secondary">
                            Forgot your password?
                        </a>
                    </div>

                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400"
                                viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 
                            01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 
                            3 0 016 0z" clip-rule="evenodd" />
                            </svg>
                        </div>

                        <input :type="passwordVisible ? 'text' : 'password'" id="password"
                            name="password" required
                            class="w-full pl-10 pr-10 py-2 border border-gray-300 rounded-md 
                        focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                            placeholder="Enter your password">

                        <button type="button" @click="passwordVisible = !passwordVisible"
                            class="absolute inset-y-0 right-0 pr-3 flex items-center">
                            <!-- Eye Icons -->
                            <svg x-show="!passwordVisible" class="h-5 w-5 text-gray-400"
                                xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                                fill="currentColor">
                                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                <path fill-rule="evenodd"
                                    d="M.458 10C1.732 5.943 5.522 3 
                            10 3s8.268 2.943 9.542 7c-1.274 
                            4.057-5.064 7-9.542 7S1.732 
                            14.057.458 10zM14 10a4 4 0 
                            11-8 0 4 4 0 018 0z"
                                    clip-rule="evenodd" />
                            </svg>

                            <svg x-show="passwordVisible" class="h-5 w-5 text-gray-400"
                                xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                                fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M3.707 2.293a1 1 0 
                            00-1.414 1.414l14 14a1 1 
                            0 001.414-1.414l-1.473-1.473A10.014 
                            10.014 0 0019.542 10C18.268 
                            5.943 14.478 3 10 3a9.958 
                            9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 
                            4.26l1.514 1.515a2.003 2.003 
                            0 012.45 2.45l1.514 
                            1.514a4 4 0 
                            00-5.478-5.478z"
                                    clip-rule="evenodd" />
                                <path
                                    d="M12.454 16.697L9.75 13.992a4 4 
                            0 01-3.742-3.741L2.335 
                            6.578A9.98 9.98 0 
                            00.458 10c1.274 
                            4.057 5.065 7 
                            9.542 7 .847 0 
                            1.669-.105 
                            2.454-.303z" />
                            </svg>
                        </button>
                    </div>
                </div>


                <!-- Sign In Button -->
                <button type="submit" name="send_otp" value="1"
                    class="w-full bg-primary text-white py-2 px-4 rounded-md hover:bg-secondary focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
                    Sign In
                </button>

                <!-- Sign Up     Button -->
                <div class="mt-4 text-center">
                    <span class="text-gray-600">Don't have an account?</span>
                    <a href="signup.php" class="ml-1 text-primary hover:text-secondary font-medium">
                        Sign up
                    </a>
                </div>
            </form>

            <!-- Back to Home Page -->
            <div class="mt-6 text-center">
                <a href="../index.php" class="inline-flex items-center text-sm text-gray-600 hover:text-primary">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back to Home Page
                </a>
            </div>
        </div>

        <div class="px-8 py-4 bg-gray-50 text-center text-sm text-gray-600">
            &copy; <?php echo date('Y'); ?> Alsons Agribusiness Unit. All rights reserved.
        </div>
    </div>
</body>

</html>
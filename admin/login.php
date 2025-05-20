<?php
session_start();
require_once '../config.php';
require_once __DIR__ . '/../vendor/autoload.php';

// Enable error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// OTP Generation function
function generateOTP($length = 6) {
    $characters = '0123456789';
    $otp = '';
    for ($i = 0; $i < $length; $i++) {
        $otp .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $otp;
}

// Handle AJAX requests
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');
    
    if (isset($_POST['send_otp'])) {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        // Log the credentials attempt (without the actual password)
        error_log("Admin login attempt for: " . $username);
        
        // Fetch user by email
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE employee_email = ? AND role IN ('admin', 'superior', 'technical_support', 'process_owner')");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user) {
            echo json_encode(['status' => 'error', 'message' => 'Email not found or insufficient privileges']);
            exit;
        }
        
        if (!password_verify($password, $user['password'])) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid password']);
            exit;
        }

        // Generate and store OTP
        $otp = generateOTP();
        $_SESSION['otp'] = $otp;
        $_SESSION['otp_expiry'] = time() + 300; // 5 minutes
        $_SESSION['temp_admin'] = $user;
        
        error_log("OTP generated for admin " . $username . ": " . $otp);

        // Send OTP via PHPMailer
        $mail = new PHPMailer\PHPMailer\PHPMailer(true); // Enable exceptions
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'charlesondota@gmail.com';
            $mail->Password = 'crpf bbcb vodv xbjk';
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            $mail->SMTPDebug = 0; // 0 = off, 1 = client messages, 2 = client and server messages

            $mail->setFrom('charlesondota@gmail.com', 'Alsons Agribusiness');
            $mail->addAddress($user['employee_email'], $user['employee_name']);
            $mail->isHTML(true);
            $mail->Subject = 'Your Admin Portal One Time Password';
            $mail->Body = "Hello {$user['employee_name']}, your OTP for admin login is <b>$otp</b>. It expires in 5 minutes.";

            $mail->send();
            
            // Ensure session is written to storage
            session_write_close();
            session_start();
            
            error_log("OTP email sent successfully to " . $user['employee_email']);
            echo json_encode(['status' => 'success', 'message' => 'OTP sent successfully']);
        } catch (Exception $e) {
            error_log("Failed to send OTP email: " . $mail->ErrorInfo);
            echo json_encode(['status' => 'error', 'message' => 'Failed to send OTP: ' . $mail->ErrorInfo]);
        }
        exit;
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
            if (isset($_SESSION['temp_admin'])) {
                $user = $_SESSION['temp_admin'];
                $_SESSION['admin_id'] = $user['employee_id'];
                $_SESSION['admin_username'] = $user['employee_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['admin_name'] = $user['employee_name'];

                // Ensure this user exists in admin_users table for foreign key relationships
                try {
                    // Check if user already exists in admin_users
                    $adminStmt = $pdo->prepare("SELECT id FROM admin_users WHERE username = ?");
                    $adminStmt->execute([$user['employee_id']]);
                    $adminUser = $adminStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$adminUser) {
                        // Create an entry in admin_users for this employee
                        $role = $user['role'];
                        $password = $user['password'] ?? password_hash('default123', PASSWORD_DEFAULT);
                        
                        $insertStmt = $pdo->prepare("INSERT INTO admin_users (role, username, password) 
                                                 VALUES (?, ?, ?)");
                        $insertStmt->execute([$role, $user['employee_id'], $password]);
                        
                        error_log("Created admin_users entry for " . $user['employee_id']);
                    }
                } catch (Exception $e) {
                    // Log error but don't prevent login
                    error_log("Error ensuring admin_users entry: " . $e->getMessage());
                }

                unset($_SESSION['otp'], $_SESSION['otp_expiry'], $_SESSION['temp_admin']);

                // Determine redirect location based on role
                $redirect = 'dashboard.php';
                switch ($user['role']) {
                    case 'superior':
                        $redirect = '../superior/dashboard.php';
                        break;
                    case 'technical_support':
                        $redirect = '../technical_support/dashboard.php';
                        break;
                    case 'process_owner':
                        $redirect = '../process_owner/dashboard.php';
                        break;
                }

                echo json_encode(['status' => 'success', 'redirect' => $redirect]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Session data missing. Please try again.']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid OTP. Please try again.']);
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/alpinejs/3.12.0/cdn.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
    <?php
    $login_attempt = false;
    
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $login_attempt = true;
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        error_log("Login attempt for username: " . $username);
          try {
            // Check employees table for users with admin roles
            $stmt = $pdo->prepare("SELECT * FROM employees WHERE employee_email = ? AND role IN ('admin', 'superior', 'technical_support', 'process_owner')");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            error_log("User data found: " . ($user ? "yes" : "no"));
            if ($user) {
                error_log("User role: " . $user['role']);
            }
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['admin_id'] = $user['employee_id'];
                $_SESSION['admin_username'] = $user['employee_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['admin_name'] = $user['employee_name'];
                
                // Ensure this user exists in admin_users table for foreign key relationships
                try {
                    // Check if user already exists in admin_users
                    $adminStmt = $pdo->prepare("SELECT id FROM admin_users WHERE username = ?");
                    $adminStmt->execute([$user['employee_id']]);
                    $adminUser = $adminStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$adminUser) {
                        // Create an entry in admin_users for this employee
                        $role = $user['role'];
                        $password = $user['password'] ?? password_hash('default123', PASSWORD_DEFAULT);
                        
                        $insertStmt = $pdo->prepare("INSERT INTO admin_users (role, username, password) 
                                                 VALUES (?, ?, ?)");
                        $insertStmt->execute([$role, $user['employee_id'], $password]);
                        
                        error_log("Created admin_users entry for " . $user['employee_id']);
                    }
                } catch (Exception $e) {
                    // Log error but don't prevent login
                    error_log("Error ensuring admin_users entry: " . $e->getMessage());
                }
                
                error_log("Login successful. Session data: " . print_r($_SESSION, true));

                // Redirect based on role
                switch ($user['role']) {
                    case 'superior':
                        error_log("Redirecting to superior dashboard");
                        header('Location: ../superior/dashboard.php');
                        break;
                    case 'technical_support':
                        header('Location: ../technical_support/dashboard.php');
                        break;
                    case 'process_owner':
                        header('Location: ../process_owner/dashboard.php');
                        break;
                    default:
                        header('Location: dashboard.php');
                }
                exit;
            } else {
                error_log("Login failed: " . ($user ? "invalid password" : "user not found"));
                $error = "Invalid username or password";
            }
        } catch (PDOException $e) {
            error_log("Database error during login: " . $e->getMessage());
            $error = "Database error occurred";
        }
    }
    ?>

    <div x-data="{ loading: false, passwordVisible: false }" 
         class="form-container animate-fade-in bg-white p-0 rounded-2xl shadow-2xl w-full max-w-xl overflow-hidden">
        
        <!-- Replace the current logo container with this -->
        <div class="p-8 text-center relative bg-white">
            <img src="../logo.png" alt="Alcantara Group" class="h-16 mx-auto mb-4">
            <h1 class="text-2xl font-bold text-gray-800 mb-2">Admin Portal</h1>
            <p class="text-gray-600 text-sm">Sign in to access your dashboard</p>
        </div>
        
        <div class="p-8 bg-white">
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
            
            <form method="POST" action="login.php" class="space-y-6" id="loginForm" onsubmit="handleLogin(event)">
                <!-- Company Email Input -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Company Email</label>
                    <input type="email" id="username" name="username" required 
                        class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="Enter your email">
                </div>

                <!-- Password Input -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <input type="password" id="password" name="password" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="Enter your password">
                </div>

                <!-- Remember Me and Forgot Password -->
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input type="checkbox" id="remember-me" name="remember-me"
                            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="remember-me" class="ml-2 text-sm text-gray-700">Remember me</label>
                    </div>
                    <a href="forgot_password.php" class="text-sm text-blue-600 hover:text-blue-800">
                        Forgot your password?
                    </a>
                </div>

                <!-- Sign In Button -->
                <button type="submit" name="send_otp" value="1"
                        class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    Sign In
                </button>
            </form>

            <!-- Back to Home Page -->
            <div class="mt-6 text-center">
                <a href="../index.php" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-800">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back to Home Page
                </a>
            </div>
        
        <div class="px-8 py-4 bg-gray-50 text-center text-sm text-gray-600">
            &copy; <?php echo date('Y'); ?> Alsons Agribusiness Unit. All rights reserved.
        </div>
    </div>

    <script>
        function handleLogin(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            formData.append('send_otp', '1');

            // Custom SweetAlert2 styling
            const customClass = {
                popup: 'bg-white rounded-2xl shadow-2xl',
                title: 'text-gray-800 font-bold text-xl',
                htmlContainer: 'text-gray-600',
                input: 'mt-4 w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent text-center text-2xl tracking-widest',
                confirmButton: 'bg-primary-600 hover:bg-primary-700 text-white font-semibold px-6 py-2.5 rounded-lg transition duration-300 transform hover:scale-[1.02] active:scale-[0.98] mx-2',
                cancelButton: 'bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold px-6 py-2.5 rounded-lg transition duration-300 mx-2'
            };

            // Show loading with improved design
            Swal.fire({
                title: 'Authenticating',
                html: `
                    <div class="flex flex-col items-center">
                        <div class="w-16 h-16 mb-4">
                            <svg class="animate-spin w-full h-full text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
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
                                    <input type="text" maxlength="1" class="w-10 h-12 text-center text-xl border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-primary-500" autocomplete="off">
                                    <input type="text" maxlength="1" class="w-10 h-12 text-center text-xl border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-primary-500" autocomplete="off">
                                    <input type="text" maxlength="1" class="w-10 h-12 text-center text-xl border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-primary-500" autocomplete="off">
                                    <input type="text" maxlength="1" class="w-10 h-12 text-center text-xl border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-primary-500" autocomplete="off">
                                    <input type="text" maxlength="1" class="w-10 h-12 text-center text-xl border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-primary-500" autocomplete="off">
                                    <input type="text" maxlength="1" class="w-10 h-12 text-center text-xl border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-primary-500" autocomplete="off">
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
                    confirmButtonColor: '#0284c7'
                });
            });
        }
    </script>
</body>
</html>
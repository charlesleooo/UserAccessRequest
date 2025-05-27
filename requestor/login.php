<?php
session_start();
require_once '../config.php';
require_once __DIR__ . '/../vendor/autoload.php';

// Enable error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (isset($_SESSION['requestor_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM requestors WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['requestor_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                header("Location: dashboard.php");
                exit();
            } else {
                $error = 'Invalid username or password';
            }
        } catch (PDOException $e) {
            $error = 'An error occurred. Please try again later.';
            error_log($e->getMessage());
        }
    }
}

// Handle AJAX requests
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');
    
    if (isset($_POST['send_otp'])) {
        $employee_email = $_POST['employee_email'] ?? '';
        $password = $_POST['password'] ?? '';
        
        // Log the credentials attempt (without the actual password)
        error_log("Login attempt for: " . $employee_email);
        
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
        $_SESSION['otp_expiry'] = time() + 300; // 5 minutes
        $_SESSION['temp_user'] = $user;
        
        error_log("OTP generated for " . $employee_email . ": " . $otp);

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
            $mail->Subject = 'Your One Time Password for Login';
            $mail->Body = "Hello {$user['employee_name']}, your OTP is <b>$otp</b>. It expires in 5 minutes.";

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
    <title>Login - User Access Request System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            DEFAULT: '#2563eb',
                            dark: '#1d4ed8',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-50 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <img src="../logo.png" alt="Logo" class="w-48 mx-auto mb-4 shadow-lg">
            <h1 class="text-3xl font-bold text-gray-900">Welcome Back</h1>
            <p class="text-gray-600 mt-2">Please sign in to your account</p>
        </div>

        <div class="glass-effect rounded-xl p-8">
            <?php if ($error): ?>
            <div class="alert alert-error mb-6">
                <div class="flex items-center">
                    <i class='bx bx-error-circle text-xl mr-2'></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            </div>
            <?php endif; ?>

            <form method="POST" action="" class="space-y-6">
                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">
                            <i class='bx bx-user'></i>
                        </span>
                        <input type="text" id="username" name="username" required
                               class="form-input pl-10"
                               placeholder="Enter your username">
                    </div>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">
                            <i class='bx bx-lock-alt'></i>
                        </span>
                        <input type="password" id="password" name="password" required
                               class="form-input pl-10"
                               placeholder="Enter your password">
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input type="checkbox" id="remember" name="remember"
                               class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded">
                        <label for="remember" class="ml-2 block text-sm text-gray-700">
                            Remember me
                        </label>
                    </div>
                    <a href="forgot_password.php" class="text-sm text-primary-600 hover:text-primary-500">
                        Forgot password?
                    </a>
                </div>

                <button type="submit"
                        class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all duration-200">
                    Sign in
                </button>
            </form>

            <div class="mt-6 text-center">
                <p class="text-sm text-gray-600">
                    Don't have an account?
                    <a href="signup.php" class="font-medium text-primary-600 hover:text-primary-500">
                        Sign up
                    </a>
                </p>
            </div>
        </div>
    </div>

    <script>
        // Add smooth transitions
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            form.classList.add('opacity-0');
            setTimeout(() => {
                form.classList.add('transition-opacity', 'duration-500', 'ease-in-out');
                form.classList.remove('opacity-0');
            }, 100);
        });
    </script>
</body>
</html>
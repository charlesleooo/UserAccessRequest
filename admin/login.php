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
    session_start();
    require_once '../config.php';
    
    // Enable error reporting for debugging
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    $login_attempt = false;
    
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $login_attempt = true;
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        error_log("Login attempt for username: " . $username);
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            error_log("User data found: " . ($user ? "yes" : "no"));
            if ($user) {
                error_log("User role: " . $user['role']);
            }
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['admin_id'] = $user['id'];
                $_SESSION['admin_username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['admin_name'] = $user['username'];
                
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
        
        <div class="p-12 text-center relative bg-white">
            <div class="absolute inset-x-0 top-1/2 transform -translate-y-1/4 h-full" 
                 style="background-image: url('../logo.png'); background-repeat: no-repeat; background-size: contain; background-position: center;">
            </div>
        </div>
        
        <div class="p-8 bg-white">
            <div class="text-center mb-6">
                <h1 class="text-3xl font-bold text-gray-800 mb-2">Admin Portal</h1>
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
            
            <form method="POST" class="space-y-6" x-on:submit="loading = true">
                <div class="animate-slide-up" style="animation-delay: 100ms">
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <input type="text" id="username" name="username" required autofocus
                            class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition duration-200"
                            placeholder="Enter your username">
                    </div>
                </div>
                
                <div class="animate-slide-up" style="animation-delay: 200ms">
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <input :type="passwordVisible ? 'text' : 'password'" id="password" name="password" required
                            class="w-full pl-10 pr-10 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition duration-200"
                            placeholder="Enter your password">
                        <button type="button" @click="passwordVisible = !passwordVisible" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                            <svg x-show="!passwordVisible" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                            </svg>
                            <svg x-show="passwordVisible" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 4.26l1.514 1.515a2.003 2.003 0 012.45 2.45l1.514 1.514a4 4 0 00-5.478-5.478z" clip-rule="evenodd" />
                                <path d="M12.454 16.697L9.75 13.992a4 4 0 01-3.742-3.741L2.335 6.578A9.98 9.98 0 00.458 10c1.274 4.057 5.065 7 9.542 7 .847 0 1.669-.105 2.454-.303z" />
                            </svg>
                        </button>
                    </div>
                </div>
                
                <div class="animate-slide-up" style="animation-delay: 300ms">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center">
                            <input id="remember-me" name="remember-me" type="checkbox" 
                                class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded">
                            <label for="remember-me" class="ml-2 block text-sm text-gray-700">
                                Remember me
                            </label>
                        </div>
                    </div>
                    
                    <button type="submit"
                        class="w-full flex items-center justify-center bg-primary-600 hover:bg-primary-700 text-white font-semibold py-3 px-4 rounded-lg transition duration-300 transform hover:scale-[1.02] active:scale-[0.98]"
                        :class="{ 'opacity-75 cursor-not-allowed': loading }"
                        :disabled="loading">
                        <span x-show="!loading">Sign In</span>
                        <svg x-show="loading" class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </button>
                </div>
            </form>
        </div>
        
        <div class="px-8 py-4 bg-gray-50 text-center text-sm text-gray-600">
            &copy; <?php echo date('Y'); ?> Alsons Agribusiness Unit. All rights reserved.
        </div>
    </div>

    <script>
        // Add some animation when form is submitted
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                Swal.fire({
                    title: 'Logging in...',
                    text: 'Please wait while we verify your credentials',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    willOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Submit the form after showing the alert
                setTimeout(() => {
                    this.submit();
                }, 1000);
            });
            
            <?php if ($login_attempt): ?>
                <?php if (isset($error)): ?>
                    Swal.fire({
                        icon: 'error',
                        title: 'Login Failed',
                        text: '<?php echo addslashes($error); ?>',
                        confirmButtonColor: '#0084FF'
                    });
                <?php else: ?>
                    Swal.fire({
                        icon: 'success',
                        title: 'Login Successful',
                        text: 'Redirecting to dashboard...',
                        showConfirmButton: false,
                        timer: 1500
                    });
                <?php endif; ?>
            <?php endif; ?>
        });
    </script>
</body>
</html>
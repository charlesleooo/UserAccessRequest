<?php
session_start();
require_once '../config.php';

// Function to generate a 6-digit encryption code
function generateEncryptionCode() {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < 6; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}

// If user is trying to verify the encryption code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['encryption_code'])) {
    $entered_code = $_POST['encryption_code'];
    
    if (!isset($_SESSION['encryption_code'], $_SESSION['encryption_code_expiry'])) {
        $error = "Session expired. Please refresh the page.";
    } elseif ($_SESSION['encryption_code_expiry'] < time()) {
        $error = "Encryption code has expired. Please refresh the page.";
    } elseif ($entered_code === $_SESSION['encryption_code']) {
        // Code is correct, set verification flag and redirect to requests page
        $_SESSION['requests_verified'] = true;
        $_SESSION['requests_verified_time'] = time();
        header('Location: requests.php');
        exit();
    } else {
        $error = "Invalid encryption code. Please try again.";
    }
}

// Get user's personal encryption code if they have set one
$userId = $_SESSION['admin_id'] ?? '';
$userHasCode = false;
$userCode = '';

if ($userId) {
    try {
        $stmt = $pdo->prepare("SELECT encryption_code FROM user_encryption_codes WHERE user_id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && !empty($result['encryption_code'])) {
            $userHasCode = true;
            $userCode = $result['encryption_code'];
        }
    } catch (PDOException $e) {
        error_log("Error fetching user encryption code: " . $e->getMessage());
    }
}

// Set the encryption code - use user's personal code if available, otherwise generate a random one
if ($userHasCode) {
    $encryption_code = $userCode;
    error_log("Using user-defined encryption code for {$_SESSION['role']}: $encryption_code");
} else {
    $encryption_code = generateEncryptionCode();
    error_log("Generated random encryption code for {$_SESSION['role']}: $encryption_code");
}

$_SESSION['encryption_code'] = $encryption_code;
$_SESSION['encryption_code_expiry'] = time() + 300; // 5 minutes

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Requests Access Verification</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .code-input {
            width: 3rem;
            height: 3rem;
            text-align: center;
            font-size: 1.5rem;
            border-radius: 0.5rem;
            margin: 0 0.25rem;
            border: 2px solid #e5e7eb;
        }
        .code-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }
    </style>
</head>
<body class="min-h-screen bg-gray-100 flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-white rounded-xl shadow-lg p-8">
        <div class="text-center mb-6">
            <img src="../logo.png" alt="Logo" class="h-16 mx-auto mb-4">
            <h1 class="text-2xl font-bold text-gray-800">Secure Access Verification</h1>
            <p class="text-gray-600 mt-2">Enter the 6-digit encryption code to access the requests page</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="bg-red-50 text-red-700 p-4 rounded-lg mb-6">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6" id="verificationForm">
            <div class="flex justify-center space-x-2">
                <input type="text" maxlength="1" class="code-input" name="code_1" autofocus>
                <input type="text" maxlength="1" class="code-input" name="code_2">
                <input type="text" maxlength="1" class="code-input" name="code_3">
                <input type="text" maxlength="1" class="code-input" name="code_4">
                <input type="text" maxlength="1" class="code-input" name="code_5">
                <input type="text" maxlength="1" class="code-input" name="code_6">
                <input type="hidden" name="encryption_code" id="encryptionCode">
            </div>

            <button type="submit" class="w-full py-3 px-4 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors">
                Verify Access
            </button>
        </form>

        <div class="mt-6 text-center">
            <a href="dashboard.php" class="text-blue-600 hover:text-blue-800 text-sm">
                Back to Dashboard
            </a>
        </div>
        
        <?php if (!$userHasCode): ?>
        <div class="mt-6 pt-6 border-t border-gray-200 text-center">
            <p class="text-sm text-gray-600">
                You haven't set a personal encryption code yet.
                <a href="../admin/settings.php" class="text-blue-600 hover:text-blue-800">
                    Set your code
                </a>
            </p>
        </div>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('.code-input');
            const encryptionCodeInput = document.getElementById('encryptionCode');
            const form = document.getElementById('verificationForm');

            // Auto-focus next input
            inputs.forEach((input, index) => {
                input.addEventListener('input', function() {
                    if (this.value.length === this.maxLength) {
                        if (index < inputs.length - 1) {
                            inputs[index + 1].focus();
                        }
                    }
                });

                // Handle backspace
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Backspace' && this.value === '' && index > 0) {
                        inputs[index - 1].focus();
                    }
                });
            });

            // Combine inputs into single value on form submit
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                let code = '';
                inputs.forEach(input => {
                    code += input.value;
                });
                
                // Only submit if code is complete
                if (code.length === 6) {
                    encryptionCodeInput.value = code;
                    this.submit();
                } else {
                    Swal.fire({
                        title: 'Incomplete Code',
                        text: 'Please enter all 6 digits of the encryption code.',
                        icon: 'error',
                        confirmButtonColor: '#3b82f6'
                    });
                }
            });
        });
    </script>
</body>
</html> 
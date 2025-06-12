<?php
session_start();
require_once '../config.php';
require_once '../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

// Check if user is logged in as technical support
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'technical_support') {
    header('Location: ../admin/login.php');
    exit();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'])) {
    try {
        $pdo->beginTransaction();
        
        $request_id = $_POST['request_id'];
        $testing_instructions = $_POST['testing_instructions'];
        $testing_credentials = $_POST['testing_credentials'];
        $additional_notes = $_POST['additional_notes'];
        
        // Get request details
        $stmt = $pdo->prepare("SELECT * FROM access_requests WHERE id = ?");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$request) {
            throw new Exception('Request not found');
        }
        
        // Update request with testing details and change status
        $sql = "UPDATE access_requests SET 
                status = 'pending_testing',
                testing_status = 'pending',
                testing_instructions = :testing_instructions,
                testing_credentials = :testing_credentials,
                tech_support_notes = :additional_notes,
                tech_support_id = :tech_support_id,
                tech_support_setup_date = NOW()
                WHERE id = :request_id";
                
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            'testing_instructions' => $testing_instructions,
            'testing_credentials' => $testing_credentials,
            'additional_notes' => $additional_notes,
            'tech_support_id' => $_SESSION['admin_id'],
            'request_id' => $request_id
        ]);
        
        if (!$result) {
            throw new Exception('Failed to update request with testing details');
        }
        
        // Send email to requestor
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'charlesondota@gmail.com';
        $mail->Password = 'crpf bbcb vodv xbjk';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Recipients
        $mail->setFrom('charlesondota@gmail.com', 'Access Request System');
        $mail->addAddress($request['email'], $request['requestor_name']);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Access Testing Instructions - ' . $request['access_request_number'];
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #1F2937;'>Access Testing Instructions</h2>
                <p>Dear {$request['requestor_name']},</p>
                
                <p>Your access request has been set up for testing. Please follow the instructions below to test your access:</p>
                
                <div style='margin-top: 20px;'>
                    <h3 style='color: #1F2937; border-bottom: 2px solid #E5E7EB; padding-bottom: 10px;'>Testing Instructions</h3>
                    <div style='background-color: #f3f4f6; padding: 15px; border-radius: 8px; margin-top: 10px;'>
                        " . nl2br(htmlspecialchars($testing_instructions)) . "
                    </div>
                    
                    <h3 style='color: #1F2937; border-bottom: 2px solid #E5E7EB; padding-bottom: 10px; margin-top: 20px;'>Testing Credentials</h3>
                    <div style='background-color: #f3f4f6; padding: 15px; border-radius: 8px; margin-top: 10px;'>
                        " . nl2br(htmlspecialchars($testing_credentials)) . "
                    </div>
                    
                    " . ($additional_notes ? "
                    <h3 style='color: #1F2937; border-bottom: 2px solid #E5E7EB; padding-bottom: 10px; margin-top: 20px;'>Additional Notes</h3>
                    <div style='background-color: #f3f4f6; padding: 15px; border-radius: 8px; margin-top: 10px;'>
                        " . nl2br(htmlspecialchars($additional_notes)) . "
                    </div>
                    " : "") . "
                </div>
                
                <div style='margin-top: 30px; padding: 20px; background-color: #f3f4f6; border-radius: 8px;'>
                    <p style='margin: 0; color: #4B5563;'>
                        <strong>Next Steps:</strong><br>
                        1. Test your access using the credentials above<br>
                        2. Return to the Access Request System to confirm your testing results<br>
                        3. If you encounter any issues, please include detailed information in your testing feedback
                    </p>
                </div>
            </div>
        ";

        $mail->send();
        
        $pdo->commit();
        $_SESSION['success_message'] = "Testing details sent to requestor successfully.";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
    }
    
    header('Location: pending_requests.php');
    exit();
}

// Get request details if ID is provided
$request_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$request = null;

if ($request_id) {
    $stmt = $pdo->prepare("
        SELECT * FROM access_requests 
        WHERE id = ? AND status = 'pending_testing_setup'
    ");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$request) {
    $_SESSION['error_message'] = "Invalid request or request not found.";
    header('Location: pending_requests.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Testing Setup - <?php echo htmlspecialchars($request['access_request_number']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex flex-col">
        <!-- Header -->
        <header class="bg-white shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
                <div class="flex items-center">
                    <img src="../logo.png" alt="Logo" class="h-16">
                </div>
                <div>
                    <a href="pending_requests.php" class="text-gray-600 hover:text-primary-600 flex items-center">
                        <i class='bx bx-arrow-back mr-1'></i> Back to Pending Requests
                    </a>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <div class="flex-grow">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h1 class="text-xl font-semibold text-gray-800">Testing Setup</h1>
                        <p class="text-sm text-gray-500 mt-1">
                            Request Number: <?php echo htmlspecialchars($request['access_request_number']); ?>
                        </p>
                    </div>
                    
                    <div class="p-6">
                        <!-- Request Details -->
                        <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <h3 class="text-sm font-medium text-gray-500">Requestor</h3>
                                <p class="mt-1"><?php echo htmlspecialchars($request['requestor_name']); ?></p>
                            </div>
                            <div>
                                <h3 class="text-sm font-medium text-gray-500">Department</h3>
                                <p class="mt-1"><?php echo htmlspecialchars($request['department']); ?></p>
                            </div>
                            <div>
                                <h3 class="text-sm font-medium text-gray-500">Access Type</h3>
                                <p class="mt-1"><?php echo htmlspecialchars($request['access_type']); ?></p>
                            </div>
                            <div>
                                <h3 class="text-sm font-medium text-gray-500">System Type</h3>
                                <p class="mt-1"><?php echo htmlspecialchars($request['system_type']); ?></p>
                            </div>
                        </div>
                        
                        <!-- Testing Setup Form -->
                        <form method="POST" class="space-y-6">
                            <input type="hidden" name="request_id" value="<?php echo htmlspecialchars($request['id']); ?>">
                            
                            <div>
                                <label for="testing_instructions" class="block text-sm font-medium text-gray-700">Testing Instructions</label>
                                <div class="mt-1">
                                    <textarea id="testing_instructions" name="testing_instructions" rows="4" 
                                        class="shadow-sm block w-full sm:text-sm border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                        placeholder="Provide step-by-step instructions for testing the access..." required></textarea>
                                </div>
                            </div>
                            
                            <div>
                                <label for="testing_credentials" class="block text-sm font-medium text-gray-700">Testing Credentials</label>
                                <div class="mt-1">
                                    <textarea id="testing_credentials" name="testing_credentials" rows="3"
                                        class="shadow-sm block w-full sm:text-sm border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                        placeholder="Provide login credentials or access details..." required></textarea>
                                </div>
                            </div>
                            
                            <div>
                                <label for="additional_notes" class="block text-sm font-medium text-gray-700">Additional Notes</label>
                                <div class="mt-1">
                                    <textarea id="additional_notes" name="additional_notes" rows="3"
                                        class="shadow-sm block w-full sm:text-sm border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                        placeholder="Any additional information or special instructions..."></textarea>
                                </div>
                            </div>
                            
                            <div class="flex justify-end space-x-3">
                                <a href="pending_requests.php" 
                                    class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    Cancel
                                </a>
                                <button type="submit"
                                    class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    Send Testing Details
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <footer class="bg-white shadow-sm-up mt-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <p class="text-center text-gray-500 text-sm">
                    &copy; 2025 Alsons/AWS. All rights reserved.
                </p>
            </div>
        </footer>
    </div>
</body>
</html> 
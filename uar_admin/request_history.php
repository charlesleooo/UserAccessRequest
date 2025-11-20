<?php
session_start();
require_once '../config.php';

// Authentication check
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'uar_admin') {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request History - UAR Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
</head>

<body class="bg-gray-50">
    <div class="flex min-h-screen">
        <?php include 'sidebar.php'; ?>
        <div class="flex-1 ml-72">
            <div class="bg-gradient-to-r from-indigo-600 to-purple-600 border-b border-gray-200 sticky top-0 z-30 shadow-lg">
                <div class="flex justify-between items-center px-8 py-4">
                    <div>
                        <h2 class="text-2xl font-bold text-white">Request History</h2>
                        <p class="text-indigo-100 text-sm mt-1">View completed and archived requests</p>
                    </div>
                </div>
            </div>
            <div class="p-8">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
                    <i class='bx bx-history text-6xl text-gray-300 mb-4'></i>
                    <h3 class="text-2xl font-semibold text-gray-700 mb-2">Request History</h3>
                    <p class="text-gray-500 mb-6">This feature is coming soon. You'll be able to view all completed and archived access requests here.</p>
                    <a href="dashboard.php" class="inline-flex items-center px-6 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                        <i class='bx bx-arrow-back mr-2'></i>
                        Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>

</html>
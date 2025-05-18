<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Access Request System - Home</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center p-6">
    <div class="max-w-md w-full bg-white rounded-xl shadow-lg p-8 space-y-8">
        <!-- Logo Container -->
        <div class="flex justify-center items-center">
            <img src="logo.png" alt="Company Logo" class="h-20 object-contain">
        </div>

        <!-- Title section -->
        <div class="text-center space-y-3">
            <h1 class="text-3xl font-bold text-gray-800">
                User Access Request System
            </h1>
            <p class="text-gray-600">
                Please select your login type to proceed
            </p>
        </div>

        <!-- Login Buttons -->
        <div class="space-y-4">
            <a href="requestor/login.php" 
               class="flex items-center justify-center w-full py-3 px-6 rounded-lg text-white bg-emerald-500 hover:bg-emerald-600 transition-colors duration-200 font-medium">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                Login as Requestor
            </a>

            <a href="admin/login.php"
               class="flex items-center justify-center w-full py-3 px-6 rounded-lg text-white bg-blue-500 hover:bg-blue-600 transition-colors duration-200 font-medium">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                Login as Admin
            </a>
        </div>

        <!-- Footer -->
        <div class="text-center pt-6">
            <p class="text-sm text-gray-500">
                © 2025 User Access Request System
            </p>
        </div>
    </div>
</body>
</html>
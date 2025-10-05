<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Access Request System - Home</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body class="min-h-screen bg-gray-100 flex items-center justify-center p-8">
    <div class="max-w-2xl w-full bg-white rounded-2xl shadow-2xl p-10 space-y-10">
        <!-- Logo Container -->
        <div class="flex justify-center items-center">
            <img src="logo.png" alt="Company Logo" class="h-28 object-contain">
        </div>

        <!-- Title section -->
        <div class="text-center space-y-4">
            <h1 class="text-4xl font-bold text-gray-800">
                User Access Request System (UAR)
            </h1>
        </div>

        <!-- Login Buttons -->
        <div class="space-y-6 px-6">
            <a href="requestor/login.php"
                class="flex items-center justify-center w-full py-4 px-8 rounded-xl text-white bg-blue-600 hover:bg-blue-700 transition-colors duration-200 font-medium text-lg shadow-md hover:shadow-lg">
                <svg class="w-7 h-7 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                Login
            </a>
        </div>

        <!-- Footer -->
        <div class="text-center pt-8">
            <p class="text-base text-gray-500">
                © <?php echo date("Y"); ?> Alsons Agribusiness Unit. All rights reserved.
            </p>
        </div>

    </div>
</body>

</html>
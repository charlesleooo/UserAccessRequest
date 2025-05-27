<?php
session_start();
require_once '../config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Access Request System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.1/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="styles.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            DEFAULT: '#2563eb',  // Modern blue
                            dark: '#1d4ed8',
                        },
                        secondary: {
                            DEFAULT: '#7c3aed',  // Modern purple
                            dark: '#6d28d9',
                        },
                        accent: {
                            DEFAULT: '#0ea5e9',  // Sky blue
                            dark: '#0284c7',
                        },
                        success: {
                            DEFAULT: '#10b981',  // Emerald
                            dark: '#059669',
                        },
                        warning: {
                            DEFAULT: '#f59e0b',  // Amber
                            dark: '#d97706',
                        },
                        danger: {
                            DEFAULT: '#ef4444',  // Red
                            dark: '#dc2626',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-100" x-data="{ sidebarOpen: true }" x-init="$store.app = { sidebarOpen: true }">

<!-- Progress bar at the top of the page -->
<div class="progress-container">
    <div class="progress-bar" id="progressBar"></div>
</div>

<!-- Mobile menu toggle -->
<div class="fixed bottom-4 right-4 z-50 md:hidden">
    <button @click="sidebarOpen = !sidebarOpen" 
            class="flex items-center justify-center w-14 h-14 rounded-full bg-primary-600 text-white shadow-lg hover:bg-primary-700 focus:outline-none transition-all duration-300 transform hover:scale-105">
        <i class='bx bx-menu text-2xl' x-show="!sidebarOpen"></i>
        <i class='bx bx-x text-2xl' x-show="sidebarOpen"></i>
    </button>
</div>

<!-- Sidebar -->
<div class="fixed inset-y-0 left-0 z-50 w-72 shadow-lg sidebar-transition md:translate-x-0 sidebar-bg"
     :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'">
    <div class="flex flex-col h-full">
        <div class="text-center mt-4 flex justify-center items-center">
            <img src="../logo.png" alt="Logo" class="w-60 mx-auto shadow-lg">
        </div>
        <nav class="flex-1 pt-6 px-4 space-y-1 overflow-y-auto">
            <a href="dashboard.php" class="nav-link">
                <span class="nav-icon">
                    <i class='bx bxs-dashboard text-xl'></i>
                </span>
                <span class="ml-3 font-medium">Dashboard</span>
            </a>
            <a href="create_request.php" class="nav-link">
                <span class="nav-icon">
                    <i class='bx bx-send text-xl'></i>
                </span>
                <span class="ml-3">Create Request</span>
            </a>
            <a href="my_requests.php" class="nav-link">
                <span class="nav-icon">
                    <i class='bx bx-list-ul text-xl'></i>
                </span>
                <span class="ml-3">My Requests</span>
            </a>
            <a href="request_history.php" class="nav-link">
                <span class="nav-icon">
                    <i class='bx bx-history text-xl'></i>
                </span>
                <span class="ml-3">Request History</span>
            </a>
        </nav>

        <div class="p-4 border-t border-white/10">
            <a href="logout.php" class="flex items-center px-4 py-3 text-white bg-red-500/10 rounded-xl transition hover:bg-red-500/20 group">
                <span class="flex items-center justify-center w-9 h-9 bg-red-500/20 text-white rounded-lg">
                    <i class='bx bx-log-out text-xl'></i>
                </span>
                <span class="ml-3 font-medium">Logout</span>
            </a>
        </div>

        <div class="px-4 py-4 border-t border-white/10">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center text-white">
                    <i class='bx bxs-user text-xl'></i>
                </div>
                <div>
                    <p class="text-sm font-medium text-white"><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></p>
                    <p class="text-xs text-white/70">Requestor</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Mobile header with menu toggle -->
<div class="bg-white sticky top-0 z-20 shadow-sm md:hidden">
    <div class="flex justify-between items-center px-4 py-2">
        <img src="../logo.png" alt="Logo" class="h-10">
        <button @click="sidebarOpen = !sidebarOpen" class="p-2 rounded-lg hover:bg-gray-100">
            <i class='bx bx-menu text-2xl'></i>
        </button>
    </div>
</div>

<!-- Main Content -->
<div class="transition-all duration-300" :class="sidebarOpen ? 'md:ml-72' : 'ml-0'">
    <!-- Header -->
    <div class="gradient-bg bg-white border-b border-gray-200 sticky top-0 z-10 shadow-sm">
        <div class="flex justify-between items-center px-8 py-6">
            <div class="flex items-center">
                <div>
                    <h2 class="text-4xl font-bold text-gray-800 tracking-tight">User Access Request System</h2>
                    <p class="text-gray-600 text-xl mt-2">Welcome back, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?> 👋</p>
                </div>
            </div>
        </div>
    </div>

<script>
    // Initialize AOS animation library
    document.addEventListener('DOMContentLoaded', function() {
        // Progress bar functionality
        window.onscroll = function() {
            const winScroll = document.body.scrollTop || document.documentElement.scrollTop;
            const height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
            const scrolled = (winScroll / height) * 100;
            document.getElementById("progressBar").style.width = scrolled + "%";
        };

        // Set sidebar state based on screen size
        function checkScreenSize() {
            const app = Alpine.store('app') || document.querySelector('[x-data]').__x.$data;
            if (window.innerWidth < 768) {
                app.sidebarOpen = false;
            } else {
                app.sidebarOpen = true;
            }
        }

        // Check on resize
        window.addEventListener('resize', checkScreenSize);
        
        // Initial check
        setTimeout(checkScreenSize, 50);
    });
</script>
</body>
</html> 
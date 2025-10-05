<?php
// Detect current page filename
$current_page = basename($_SERVER['PHP_SELF']);
?>

<div id="sidebar" class="fixed inset-y-0 left-0 z-50 w-72 bg-white shadow-xl transform transition-transform duration-300 lg:translate-x-0 mobile-nav-hidden">
    <div class="flex flex-col h-full">
        <!-- Logo -->
        <div class="flex items-center justify-center py-6 border-b border-gray-100">
            <img src="../logo.png" alt="Alsons Agribusiness Logo" class="w-48 h-auto">
        </div>

        <!-- Navigation Menu -->
        <nav class="flex-1 pt-6 pb-4 px-4 space-y-1 overflow-y-auto custom-scrollbar">
            <p class="px-4 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                Main Menu
            </p>

            <!-- Dashboard -->
            <a href="dashboard.php"
                class="flex items-center px-4 py-3 rounded-xl transition-all group
               <?php echo $current_page == 'dashboard.php'
                    ? 'text-primary-600 bg-primary-50'
                    : 'text-gray-700 hover:bg-gray-50 hover:text-primary-600'; ?>">
                <span class="flex items-center justify-center w-9 h-9 rounded-lg transition-colors
                    <?php echo $current_page == 'dashboard.php'
                        ? 'bg-primary-100 text-primary-600'
                        : 'bg-gray-100 text-gray-600 group-hover:bg-primary-50 group-hover:text-primary-600'; ?>">
                    <i class='bx bxs-dashboard text-xl'></i>
                </span>
                <span class="ml-3 font-medium">Dashboard</span>
            </a>

            <!-- Analytics -->
            <a href="analytics.php"
                class="flex items-center px-4 py-3 rounded-xl transition-all group
               <?php echo $current_page == 'analytics.php'
                    ? 'text-primary-600 bg-primary-50'
                    : 'text-gray-700 hover:bg-gray-50 hover:text-primary-600'; ?>">
                <span class="flex items-center justify-center w-9 h-9 rounded-lg transition-colors
                    <?php echo $current_page == 'analytics.php'
                        ? 'bg-primary-100 text-primary-600'
                        : 'bg-gray-100 text-gray-600 group-hover:bg-primary-50 group-hover:text-primary-600'; ?>">
                    <i class='bx bx-line-chart text-xl'></i>
                </span>
                <span class="ml-3 font-medium">Analytics</span>
            </a>

            <!-- Requests -->
            <a href="requests.php"
                class="flex items-center px-4 py-3 rounded-xl transition-all group
               <?php echo $current_page == 'requests.php'
                    ? 'text-primary-600 bg-primary-50'
                    : 'text-gray-700 hover:bg-gray-50 hover:text-primary-600'; ?>">
                <span class="flex items-center justify-center w-9 h-9 rounded-lg transition-colors
                    <?php echo $current_page == 'requests.php'
                        ? 'bg-primary-100 text-primary-600'
                        : 'bg-gray-100 text-gray-600 group-hover:bg-primary-50 group-hover:text-primary-600'; ?>">
                    <i class='bx bxs-message-square-detail text-xl'></i>
                </span>
                <span class="ml-3 font-medium">Requests</span>
            </a>

            <!-- Approval History -->
            <a href="approval_history.php"
                class="flex items-center px-4 py-3 rounded-xl transition-all group
               <?php echo $current_page == 'approval_history.php'
                    ? 'text-primary-600 bg-primary-50'
                    : 'text-gray-700 hover:bg-gray-50 hover:text-primary-600'; ?>">
                <span class="flex items-center justify-center w-9 h-9 rounded-lg transition-colors
                    <?php echo $current_page == 'approval_history.php'
                        ? 'bg-primary-100 text-primary-600'
                        : 'bg-gray-100 text-gray-600 group-hover:bg-primary-50 group-hover:text-primary-600'; ?>">
                    <i class='bx bx-history text-xl'></i>
                </span>
                <span class="ml-3 font-medium">Approval History</span>
            </a>

            <!-- Divider -->
            <div class="my-4 border-t border-gray-100"></div>

            <p class="px-4 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                Account
            </p>

            <!-- User Management -->
            <a href="user_management.php"
                class="flex items-center px-4 py-3 rounded-xl transition-all group
               <?php echo $current_page == 'user_management.php'
                    ? 'text-primary-600 bg-primary-50'
                    : 'text-gray-700 hover:bg-gray-50 hover:text-primary-600'; ?>">
                <span class="flex items-center justify-center w-9 h-9 rounded-lg transition-colors
                    <?php echo $current_page == 'user_management.php'
                        ? 'bg-primary-100 text-primary-600'
                        : 'bg-gray-100 text-gray-600 group-hover:bg-primary-50 group-hover:text-primary-600'; ?>">
                    <i class='bx bx-user text-xl'></i>
                </span>
                <span class="ml-3 font-medium">User Management</span>
            </a>

            <!-- Settings -->
            <a href="settings.php"
                class="flex items-center px-4 py-3 rounded-xl transition-all group
               <?php echo $current_page == 'settings.php'
                    ? 'text-primary-600 bg-primary-50'
                    : 'text-gray-700 hover:bg-gray-50 hover:text-primary-600'; ?>">
                <span class="flex items-center justify-center w-9 h-9 rounded-lg transition-colors
                    <?php echo $current_page == 'settings.php'
                        ? 'bg-primary-100 text-primary-600'
                        : 'bg-gray-100 text-gray-600 group-hover:bg-primary-50 group-hover:text-primary-600'; ?>">
                    <i class='bx bx-cog text-xl'></i>
                </span>
                <span class="ml-3 font-medium">Settings</span>
            </a>
        </nav>

        <!-- Logout Button -->
        <div class="p-4 border-t border-gray-100">
            <a href="logout.php"
                class="flex items-center px-4 py-3 text-red-600 bg-red-50 rounded-xl transition-all hover:bg-red-100 group">
                <span class="flex items-center justify-center w-9 h-9 bg-red-100 text-red-600 rounded-lg group-hover:bg-red-200 transition-colors">
                    <i class='bx bx-log-out text-xl group-hover:rotate-90 transition-transform duration-300'></i>
                </span>
                <span class="ml-3 font-medium">Logout</span>
            </a>
        </div>

        <!-- User Profile -->
        <div class="px-4 py-4 border-t border-gray-100">
            <div class="flex items-center space-x-3">
                <div class="flex-shrink-0">
                    <div class="w-10 h-10 rounded-full bg-primary-100 flex items-center justify-center text-primary-600">
                        <i class='bx bxs-user text-xl'></i>
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900 truncate">
                        <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?>
                    </p>
                    <p class="text-xs text-gray-500 truncate">
                        <?php echo htmlspecialchars($_SESSION['role'] ?? 'Administrator'); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
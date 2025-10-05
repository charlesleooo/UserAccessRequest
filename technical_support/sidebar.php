<?php
// Detect current page filename
$current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="fixed inset-y-0 left-0 z-50 w-72 bg-white shadow-lg">
    <div class="flex flex-col h-full">
        <!-- Logo -->
        <div class="text-center">
            <img src="../logo.png" alt="Company Logo" class="mt-1 w-60 h-auto mx-auto">
        </div>

        <!-- Navigation Menu -->
        <nav class="flex-1 pt-6 pb-4 px-4 space-y-1 overflow-y-auto">
            <p class="px-4 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                Main Menu
            </p>

            <!-- Dashboard -->
            <a href="dashboard.php"
                class="flex items-center px-4 py-3 rounded-xl transition 
               <?php echo $current_page === 'dashboard.php'
                    ? 'text-primary-600 bg-primary-50'
                    : 'text-gray-700 hover:bg-gray-50'; ?>">
                <span class="flex items-center justify-center w-9 h-9 rounded-lg 
                    <?php echo $current_page === 'dashboard.php'
                        ? 'bg-primary-100 text-primary-600'
                        : 'bg-gray-100 text-gray-600'; ?>">
                    <i class='bx bxs-dashboard text-xl'></i>
                </span>
                <span class="ml-3 font-medium">Dashboard</span>
            </a>

            <!-- Technical Reviews -->
            <a href="requests.php"
                class="flex items-center px-4 py-3 rounded-xl transition 
               <?php echo $current_page === 'requests.php'
                    ? 'text-primary-600 bg-primary-50'
                    : 'text-gray-700 hover:bg-gray-50'; ?>">
                <span class="flex items-center justify-center w-9 h-9 rounded-lg 
                    <?php echo $current_page === 'requests.php'
                        ? 'bg-primary-100 text-primary-600'
                        : 'bg-gray-100 text-gray-600'; ?>">
                    <i class='bx bxs-message-square-detail text-xl'></i>
                </span>
                <span class="ml-3">Technical Reviews</span>
            </a>

            <!-- Review History -->
            <a href="review_history.php"
                class="flex items-center px-4 py-3 rounded-xl transition 
               <?php echo $current_page === 'review_history.php'
                    ? 'text-primary-600 bg-primary-50'
                    : 'text-gray-700 hover:bg-gray-50'; ?>">
                <span class="flex items-center justify-center w-9 h-9 rounded-lg 
                    <?php echo $current_page === 'review_history.php'
                        ? 'bg-primary-100 text-primary-600'
                        : 'bg-gray-100 text-gray-600'; ?>">
                    <i class='bx bx-history text-xl'></i>
                </span>
                <span class="ml-3">Review History</span>
            </a>

            <!-- Settings -->
            <a href="settings.php"
                class="flex items-center px-4 py-3 rounded-xl transition 
               <?php echo $current_page === 'settings.php'
                    ? 'text-primary-600 bg-primary-50'
                    : 'text-gray-700 hover:bg-gray-50'; ?>">
                <span class="flex items-center justify-center w-9 h-9 rounded-lg 
                    <?php echo $current_page === 'settings.php'
                        ? 'bg-primary-100 text-primary-600'
                        : 'bg-gray-100 text-gray-600'; ?>">
                    <i class='bx bx-cog text-xl'></i>
                </span>
                <span class="ml-3">Settings</span>
            </a>
        </nav>

        <!-- Logout Button -->
        <div class="p-4 border-t border-gray-100">
            <a href="../logout.php"
                class="flex items-center px-4 py-3 text-red-600 bg-red-50 rounded-xl hover:bg-red-100">
                <span class="flex items-center justify-center w-9 h-9 bg-red-100 text-red-600 rounded-lg">
                    <i class='bx bx-log-out text-xl'></i>
                </span>
                <span class="ml-3 font-medium">Logout</span>
            </a>
        </div>
    </div>
</div>
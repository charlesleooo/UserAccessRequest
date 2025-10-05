<?php
// Detect current page filename
$current_page = basename($_SERVER['PHP_SELF']);
?>

<div
    class="fixed inset-y-0 left-0 z-50 w-72 bg-white shadow-lg transform transition-transform duration-300"
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
    x-show="sidebarOpen"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="-translate-x-full"
    x-transition:enter-end="translate-x-0"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="translate-x-0"
    x-transition:leave-end="-translate-x-full"
    aria-hidden="false">
    <div class="flex flex-col h-full">
        <!-- Logo -->
        <div class="text-center mt-4">
            <img src="../logo.png" alt="Company Logo" class="w-48 mx-auto">
        </div>

        <!-- Navigation -->
        <nav class="flex-1 pt-6 px-4 space-y-1 overflow-y-auto">
            <a href="dashboard.php"
                class="flex items-center px-4 py-3 rounded-xl transition 
                      <?php echo $current_page === 'dashboard.php'
                            ? 'text-indigo-600 bg-indigo-50'
                            : 'text-gray-700 hover:bg-gray-50'; ?>">
                <span class="flex items-center justify-center w-9 h-9 rounded-lg 
                             <?php echo $current_page === 'dashboard.php'
                                    ? 'bg-indigo-100 text-indigo-600'
                                    : 'bg-gray-100 text-gray-600'; ?>">
                    <i class='bx bxs-dashboard text-xl'></i>
                </span>
                <span class="ml-3 font-medium">Dashboard</span>
            </a>

            <a href="create_request.php"
                class="flex items-center px-4 py-3 rounded-xl transition 
                      <?php echo $current_page === 'create_request.php'
                            ? 'text-indigo-600 bg-indigo-50'
                            : 'text-gray-700 hover:bg-gray-50'; ?>">
                <span class="flex items-center justify-center w-9 h-9 rounded-lg 
                             <?php echo $current_page === 'create_request.php'
                                    ? 'bg-indigo-100 text-indigo-600'
                                    : 'bg-gray-100 text-gray-600'; ?>">
                    <i class='bx bx-send text-xl'></i>
                </span>
                <span class="ml-3">Create Request</span>
            </a>

            <a href="request_history.php"
                class="flex items-center px-4 py-3 rounded-xl transition 
                      <?php echo $current_page === 'request_history.php'
                            ? 'text-indigo-600 bg-indigo-50'
                            : 'text-gray-700 hover:bg-gray-50'; ?>">
                <span class="flex items-center justify-center w-9 h-9 rounded-lg 
                             <?php echo $current_page === 'request_history.php'
                                    ? 'bg-indigo-100 text-indigo-600'
                                    : 'bg-gray-100 text-gray-600'; ?>">
                    <i class='bx bx-history text-xl'></i>
                </span>
                <span class="ml-3">Request History</span>
            </a>
        </nav>

        <!-- Logout -->
        <div class="p-4 border-t border-gray-100">
            <a href="logout.php"
                class="flex items-center px-4 py-3 text-red-600 bg-red-50 rounded-xl transition hover:bg-red-100">
                <span class="flex items-center justify-center w-9 h-9 bg-red-100 text-red-600 rounded-lg">
                    <i class='bx bx-log-out text-xl'></i>
                </span>
                <span class="ml-3 font-medium">Logout</span>
            </a>
        </div>
    </div>
</div>
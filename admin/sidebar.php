<?php
// Detect current page filename
$current_page = basename($_SERVER['PHP_SELF']);

// Get pending requests count for admin
$pendingCount = 0;
try {
    require_once '../config.php';
    $stmt = $pdo->query("SELECT COUNT(*) FROM uar.access_requests WHERE status = 'pending_admin'");
    $pendingCount = (int)$stmt->fetchColumn();
} catch (Exception $e) {
    $pendingCount = 0;
}
?>

<!-- Flowbite Sidebar -->
<aside id="sidebar" class="fixed top-0 left-0 z-40 w-72 h-screen transition-transform -translate-x-full sm:translate-x-0" aria-label="Sidebar">
    <div class="h-full px-3 py-4 overflow-y-auto bg-white border-r border-gray-200">
        <!-- Logo -->
        <div class="flex items-center justify-center mb-6 pb-4 border-b border-gray-200">
            <img src="../logo.png" alt="Company Logo" class="h-20 w-auto">
        </div>

        <!-- Navigation Menu -->
        <ul class="space-y-2 font-medium">
            <li class="mb-2">
                <p class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Main Menu</p>
            </li>

            <!-- Dashboard -->
            <li>
            <a href="dashboard.php"
                   class="flex items-center p-2 rounded-lg group <?php echo $current_page == 'dashboard.php' ? 'text-white bg-blue-700' : 'text-gray-900 hover:bg-gray-100'; ?>">
                    <svg class="w-8 h-8 transition duration-75 <?php echo $current_page == 'dashboard.php' ? 'text-white' : 'text-gray-500 group-hover:text-gray-900'; ?>" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 22 21">
                        <path d="M16.975 11H10V4.025a1 1 0 0 0-1.066-.998 8.5 8.5 0 1 0 9.039 9.039.999.999 0 0 0-1-1.066h.002Z"/>
                        <path d="M12.5 0c-.157 0-.311.01-.565.027A1 1 0 0 0 11 1.02V10h8.975a1 1 0 0 0 1-.935c.013-.188.028-.374.028-.565A8.51 8.51 0 0 0 12.5 0Z"/>
                    </svg>
                    <span class="ms-3">Dashboard</span>
                </a>
            </li>

            <!-- Requests -->
            <li>
            <a href="requests.php"
                   class="flex items-center justify-between p-2 rounded-lg group <?php echo $current_page == 'requests.php' ? 'text-white bg-blue-700' : 'text-gray-900 hover:bg-gray-100'; ?>">
                    <div class="flex items-center">
                        <svg class="w-8 h-8 transition duration-75 <?php echo $current_page == 'requests.php' ? 'text-white' : 'text-gray-500 group-hover:text-gray-900'; ?>" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                            <path d="m17.418 3.623-.018-.008a6.713 6.713 0 0 0-2.4-.569V2h1a1 1 0 1 0 0-2h-2a1 1 0 0 0-1 1v2H9.89A6.977 6.977 0 0 1 12 8v5h-2V8A5 5 0 1 0 0 8v6a1 1 0 0 0 1 1h8v4a1 1 0 0 0 1 1h2a1 1 0 0 0 1-1v-4h6a1 1 0 0 0 1-1V8a5 5 0 0 0-2.582-4.377ZM6 12H4a1 1 0 0 1 0-2h2a1 1 0 0 1 0 2Z"/>
                        </svg>
                        <span class="ms-3">Requests</span>
                    </div>
                    <?php if ($pendingCount > 0): ?>
                        <span class="inline-flex items-center justify-center w-6 h-6 text-xs font-bold text-white bg-red-500 rounded-full">
                            <?php echo $pendingCount > 99 ? '99+' : $pendingCount; ?>
                        </span>
                    <?php endif; ?>
                </a>
            </li>

            <!-- Approval History -->
            <li>
            <a href="approval_history.php"
                   class="flex items-center p-2 rounded-lg group <?php echo $current_page == 'approval_history.php' ? 'text-white bg-blue-700' : 'text-gray-900 hover:bg-gray-100'; ?>">
                    <svg class="w-8 h-8 transition duration-75 <?php echo $current_page == 'approval_history.php' ? 'text-white' : 'text-gray-500 group-hover:text-gray-900'; ?>" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M5 5V.13a2.96 2.96 0 0 0-1.293.749L.879 3.707A2.96 2.96 0 0 0 .13 5H5Z"/>
                        <path d="M6.737 11.061a2.961 2.961 0 0 1 .81-1.515l6.117-6.116A4.839 4.839 0 0 1 16 2.141V2a1.97 1.97 0 0 0-1.933-2H7v5a2 2 0 0 1-2 2H0v11a1.969 1.969 0 0 0 1.933 2h12.134A1.97 1.97 0 0 0 16 18v-3.093l-1.546 1.546c-.413.413-.94.695-1.513.81l-3.4.679a2.947 2.947 0 0 1-1.85-.227 2.96 2.96 0 0 1-1.635-3.257l.681-3.397Z"/>
                        <path d="M8.961 16a.93.93 0 0 0 .189-.019l3.4-.679a.961.961 0 0 0 .49-.263l6.118-6.117a2.884 2.884 0 0 0-4.079-4.078l-6.117 6.117a.96.96 0 0 0-.263.491l-.679 3.4A.961.961 0 0 0 8.961 16Zm7.477-9.8a.958.958 0 0 1 .68-.281.961.961 0 0 1 .682 1.644l-.315.315-1.36-1.36.313-.318Zm-5.911 5.911 4.236-4.236 1.359 1.359-4.236 4.237-1.7.339.341-1.699Z"/>
                    </svg>
                    <span class="ms-3">Approval History</span>
                </a>
            </li>
        </ul>

        <!-- Logout Button -->
        <div class="pt-4 mt-4 space-y-2 font-medium border-t border-gray-200">
            <a href="logout.php"
               class="flex items-center p-2 text-red-600 rounded-lg hover:bg-red-50 group">
                <svg class="w-8 h-8 text-red-600 transition duration-75" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 18 16">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M1 8h11m0 0L8 4m4 4-4 4m4-11h3a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-3"/>
                </svg>
                <span class="ms-3 font-medium">Logout</span>
            </a>
        </div>
    </div>
</aside>

<?php
// Shared role-based sidebar renderer
if (!isset($current_page)) {
    $current_page = basename($_SERVER['PHP_SELF']);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';

$role = $ROLE ?? ($_SESSION['role'] ?? '');
$role = strtolower((string)$role);

// Compute pending count per role
$pendingCount = 0;
try {
    switch ($role) {
        case 'admin':
            $sql = "SELECT COUNT(*) FROM uar.access_requests WHERE status = 'pending_admin'";
            break;
        case 'help_desk':
            $sql = "SELECT COUNT(*) FROM uar.access_requests WHERE status = 'pending_help_desk'";
            break;
        case 'technical_support':
            $sql = "SELECT COUNT(*) FROM uar.access_requests WHERE status IN ('pending_technical', 'pending_testing_review', 'pending_testing_setup')";
            break;
        case 'process_owner':
            $sql = "SELECT COUNT(*) FROM uar.access_requests WHERE status = 'pending_process_owner'";
            break;
        case 'superior':
            $sql = "SELECT COUNT(*) FROM uar.access_requests WHERE status = 'pending_superior'";
            break;
        case 'uar_admin':
            $sql = "SELECT COUNT(*) FROM uar.access_requests";
            break;
        default:
            $sql = null;
    }
    if ($sql) {
        $stmt = $pdo->query($sql);
        $pendingCount = (int)$stmt->fetchColumn();
    }
} catch (Throwable $e) {
    $pendingCount = 0;
}

// Determine logout href defaults per role
$logoutHref = $LOGOUT_HREF ?? null;
if ($logoutHref === null) {
    switch ($role) {
        case 'admin':
            $logoutHref = 'logout.php';
            break;
        case 'help_desk':
        case 'process_owner':
        case 'superior':
            $logoutHref = '../admin/logout.php';
            break;
        case 'technical_support':
            $logoutHref = '../logout.php';
            break;
        case 'uar_admin':
            $logoutHref = 'logout.php';
            break;
        default:
            $logoutHref = 'logout.php';
    }
}

// Build role-specific nav items
$nav = [];

$nav[] = [
    'href' => 'dashboard.php',
    'label' => 'Dashboard',
    'icon' => '<svg class="w-8 h-8 transition duration-75 %ICON_COLOR%" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 22 21"><path d="M16.975 11H10V4.025a1 1 0 0 0-1.066-.998 8.5 8.5 0 1 0 9.039 9.039.999.999 0 0 0-1-1.066h.002Z"/><path d="M12.5 0c-.157 0-.311.01-.565.027A1 1 0 0 0 11 1.02V10h8.975a1 1 0 0 0 1-.935c.013-.188.028-.374.028-.565A8.51 8.51 0 0 0 12.5 0Z"/></svg>',
];

$nav[] = [
    'href' => 'create_request.php',
    'label' => 'Create Request',
    'icon' => '<svg class="w-8 h-8 transition duration-75 %ICON_COLOR%" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20"><path d="M9.546.5a9.5 9.5 0 1 0 9.5 9.5 9.51 9.51 0 0 0-9.5-9.5ZM13.788 11h-3.242v3.242a1 1 0 1 1-2 0V11H5.304a1 1 0 0 1 0-2h3.242V5.758a1 1 0 0 1 2 0V9h3.242a1 1 0 1 1 0 2Z"/></svg>',
];

$nav[] = [
    'href' => 'request_history.php',
    'label' => 'Request History',
    'icon' => '<svg class="w-8 h-8 transition duration-75 %ICON_COLOR%" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a8 8 0 1 0 8 8 8.011 8.011 0 0 0-8-8Zm1 12H9V9h4v2h-2Z"/></svg>',
];

// Add subheader before review/approval section
$subheaderLabel = '';
switch ($role) {
    case 'admin':
        $subheaderLabel = 'Approval';
        break;
    case 'help_desk':
        $subheaderLabel = 'Help Desk';
        break;
    case 'technical_support':
        $subheaderLabel = 'Technical Support';
        break;
    case 'process_owner':
        $subheaderLabel = 'Approval';
        break;
    case 'superior':
        $subheaderLabel = 'Approval';
        break;
    case 'uar_admin':
        $subheaderLabel = 'System Management';
        break;
}

if ($subheaderLabel) {
    $nav[] = [
        'type' => 'subheader',
        'label' => $subheaderLabel,
    ];
}

// Review queue item per role
switch ($role) {
    case 'admin':
        $reviewLabel = 'Requests';
        break;
    case 'process_owner':
        $reviewLabel = 'Requests';
        break;
    case 'technical_support':
        $reviewLabel = 'Requests';
        break;
    case 'help_desk':
        $reviewLabel = 'Requests';
        break;
    case 'superior':
        $reviewLabel = 'Request Approval';
        break;
    case 'uar_admin':
        $reviewLabel = 'All Requests';
        break;
    default:
        $reviewLabel = 'Requests';
}
$nav[] = [
    'href' => 'requests.php',
    'label' => $reviewLabel,
    'icon' => '<svg class="w-8 h-8 transition duration-75 %ICON_COLOR%" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20"><path d="m17.418 3.623-.018-.008a6.713 6.713 0 0 0-2.4-.569V2h1a1 1 0 1 0 0-2h-2a1 1 0 0 0-1 1v2H9.89A6.977 6.977 0 0 1 12 8v5h-2V8A5 5 0 1 0 0 8v6a1 1 0 0 0 1 1h8v4a1 1 0 0 0 1 1h2a1 1 0 0 0 1-1v-4h6a1 1 0 0 0 1-1V8a5 5 0 0 0-2.582-4.377ZM6 12H4a1 1 0 0 1 0-2h2a1 1 0 0 1 0 2Z"/></svg>',
    'badge' => $pendingCount,
];

// Role-specific extras
if ($role === 'admin') {
    $nav[] = [
        'href' => 'approval_history.php',
        'label' => 'Approval History',
        'icon' => '<svg class="w-8 h-8 transition duration-75 %ICON_COLOR%" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20"><path d="M5 5V.13a2.96 2.96 0 0 0-1.293.749L.879 3.707A2.96 2.96 0 0 0 .13 5H5Z"/><path d="M6.737 11.061a2.961 2.961 0 0 1 .81-1.515l6.117-6.116A4.839 4.839 0 0 1 16 2.141V2a1.97 1.97 0 0 0-1.933-2H7v5a2 2 0 0 1-2 2H0v11a1.969 1.969 0 0 0 1.933 2h12.134A1.97 1.97 0 0 0 16 18v-3.093l-1.546 1.546c-.413.413-.94.695-1.513.81l-3.4.679a2.947 2.947 0 0 1-1.85-.227 2.96 2.96 0 0 1-1.635-3.257l.681-3.397Z"/><path d="M8.961 16a.93.93 0 0 0 .189-.019l3.4-.679a.961.961 0 0 0 .49-.263l6.118-6.117a2.884 2.884 0 0 0-4.079-4.078l-6.117 6.117a.96.96 0 0 0-.263.491l-.679 3.4A.961.961 0 0 0 8.961 16Zm7.477-9.8a.958.958 0 0 1 .68-.281.961.961 0 0 1 .682 1.644l-.315.315-1.36-1.36.313-.318Zm-5.911 5.911 4.236-4.236 1.359 1.359-4.236 4.237-1.7.339.341-1.699Z"/></svg>',
    ];
}
if ($role === 'uar_admin') {
    $nav[] = [
        'href' => 'user_management.php',
        'label' => 'User Management',
        'icon' => '<svg class="w-8 h-8 transition duration-75 %ICON_COLOR%" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 18"><path d="M14 2a3.963 3.963 0 0 0-1.4.267 6.439 6.439 0 0 1-1.331 6.638A4 4 0 1 0 14 2Zm1 9h-1.264A6.957 6.957 0 0 1 15 15v2a2.97 2.97 0 0 1-.184 1H19a1 1 0 0 0 1-1v-1a5.006 5.006 0 0 0-5-5ZM6.5 9a4.5 4.5 0 1 0 0-9 4.5 4.5 0 0 0 0 9ZM8 10H5a5.006 5.006 0 0 0-5 5v2a1 1 0 0 0 1 1h11a1 1 0 0 0 1-1v-2a5.006 5.006 0 0 0-5-5Z"/></svg>',
    ];
    $nav[] = [
        'href' => 'system_settings.php',
        'label' => 'System Settings',
        'icon' => '<svg class="w-8 h-8 transition duration-75 %ICON_COLOR%" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20"><path d="M18 7.5h-.423l-.452-1.09.3-.3a1.5 1.5 0 0 0 0-2.121L16.01 2.575a1.5 1.5 0 0 0-2.121 0l-.3.3-1.089-.452V2A1.5 1.5 0 0 0 11 .5H9A1.5 1.5 0 0 0 7.5 2v.423l-1.09.452-.3-.3a1.5 1.5 0 0 0-2.121 0L2.576 3.99a1.5 1.5 0 0 0 0 2.121l.3.3L2.423 7.5H2A1.5 1.5 0 0 0 .5 9v2A1.5 1.5 0 0 0 2 12.5h.423l.452 1.09-.3.3a1.5 1.5 0 0 0 0 2.121l1.415 1.413a1.5 1.5 0 0 0 2.121 0l.3-.3 1.09.452V18A1.5 1.5 0 0 0 9 19.5h2a1.5 1.5 0 0 0 1.5-1.5v-.423l1.09-.452.3.3a1.5 1.5 0 0 0 2.121 0l1.415-1.414a1.5 1.5 0 0 0 0-2.121l-.3-.3.452-1.09H18a1.5 1.5 0 0 0 1.5-1.5V9A1.5 1.5 0 0 0 18 7.5Zm-8 6a3.5 3.5 0 1 1 0-7 3.5 3.5 0 0 1 0 7Z"/></svg>',
    ];
    $nav[] = [
        'href' => 'analytics.php',
        'label' => 'System Analytics',
        'icon' => '<svg class="w-8 h-8 transition duration-75 %ICON_COLOR%" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 22 21"><path d="M16.975 11H10V4.025a1 1 0 0 0-1.066-.998 8.5 8.5 0 1 0 9.039 9.039.999.999 0 0 0-1-1.066h.002Z"/><path d="M12.5 0c-.157 0-.311.01-.565.027A1 1 0 0 0 11 1.02V10h8.975a1 1 0 0 0 1-.935c.013-.188.028-.374.028-.565A8.51 8.51 0 0 0 12.5 0Z"/></svg>',
    ];
}
if ($role === 'process_owner' || $role === 'technical_support') {
    $nav[] = [
        'href' => 'review_history.php',
        'label' => 'Review History',
        'icon' => '<svg class="w-8 h-8 transition duration-75 %ICON_COLOR%" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20"><path d="M5 5V.13a2.96 2.96 0 0 0-1.293.749L.879 3.707A2.96 2.96 0 0 0 .13 5H5Z"/><path d="M6.737 11.061a2.961 2.961 0 0 1 .81-1.515l6.117-6.116A4.839 4.839 0 0 1 16 2.141V2a1.97 1.97 0 0 0-1.933-2H7v5a2 2 0 0 1-2 2H0v11a1.969 1.969 0 0 0 1.933 2h12.134A1.97 1.97 0 0 0 16 18v-3.093l-1.546 1.546c-.413.413-.94.695-1.513.81l-3.4.679a2.947 2.947 0 0 1-1.85-.227 2.96 2.96 0 0 1-1.635-3.257l.681-3.397Z"/><path d="M8.961 16a.93.93 0 0 0 .189-.019l3.4-.679a.961.961 0 0 0 .49-.263l6.118-6.117a2.884 2.884 0 0 0-4.079-4.078l-6.117 6.117a.96.96 0 0 0-.263.491l-.679 3.4A.961.961 0 0 0 8.961 16Zm7.477-9.8a.958.958 0 0 1 .68-.281.961.961 0 0 1 .682 1.644l-.315.315-1.36-1.36.313-.318Zm-5.911 5.911 4.236-4.236 1.359 1.359-4.236 4.237-1.7.339.341-1.699Z"/></svg>',
    ];
    $nav[] = [
        'href' => 'settings.php',
        'label' => 'Settings',
        'icon' => '<svg class="w-8 h-8 transition duration-75 %ICON_COLOR%" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20"><path d="M18 7.5h-.423l-.452-1.09.3-.3a1.5 1.5 0 0 0 0-2.121L16.01 2.575a1.5 1.5 0 0 0-2.121 0l-.3.3-1.089-.452V2A1.5 1.5 0 0 0 11 .5H9A1.5 1.5 0 0 0 7.5 2v.423l-1.09.452-.3-.3a1.5 1.5 0 0 0-2.121 0L2.576 3.99a1.5 1.5 0 0 0 0 2.121l.3.3L2.423 7.5H2A1.5 1.5 0 0 0 .5 9v2A1.5 1.5 0 0 0 2 12.5h.423l.452 1.09-.3.3a1.5 1.5 0 0 0 0 2.121l1.415 1.413a1.5 1.5 0 0 0 2.121 0l.3-.3 1.09.452V18A1.5 1.5 0 0 0 9 19.5h2a1.5 1.5 0 0 0 1.5-1.5v-.423l1.09-.452.3.3a1.5 1.5 0 0 0 2.121 0l1.415-1.414a1.5 1.5 0 0 0 0-2.121l-.3-.3.452-1.09H18a1.5 1.5 0 0 0 1.5-1.5V9A1.5 1.5 0 0 0 18 7.5Zm-8 6a3.5 3.5 0 1 1 0-7 3.5 3.5 0 0 1 0 7Z"/></svg>',
    ];
}
if ($role === 'help_desk') {
    $nav[] = [
        'href' => 'completed_requests.php',
        'label' => 'Review History',
        'icon' => '<svg class="w-8 h-8 transition duration-75 %ICON_COLOR%" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20"><path d="M5 5V.13a2.96 2.96 0 0 0-1.293.749L.879 3.707A2.96 2.96 0 0 0 .13 5H5Z"/><path d="M6.737 11.061a2.961 2.961 0 0 1 .81-1.515l6.117-6.116A4.839 4.839 0 0 1 16 2.141V2a1.97 1.97 0 0 0-1.933-2H7v5a2 2 0 0 1-2 2H0v11a1.969 1.969 0 0 0 1.933 2h12.134A1.97 1.97 0 0 0 16 18v-3.093l-1.546 1.546c-.413.413-.94.695-1.513.81l-3.4.679a2.947 2.947 0 0 1-1.85-.227 2.96 2.96 0 0 1-1.635-3.257l.681-3.397Z"/><path d="M8.961 16a.93.93 0 0 0 .189-.019l3.4-.679a.961.961 0 0 0 .49-.263l6.118-6.117a2.884 2.884 0 0 0-4.079-4.078l-6.117 6.117a.96.96 0 0 0-.263.491l-.679 3.4A.961.961 0 0 0 8.961 16Zm7.477-9.8a.958.958 0 0 1 .68-.281.961.961 0 0 1 .682 1.644l-.315.315-1.36-1.36.313-.318Zm-5.911 5.911 4.236-4.236 1.359 1.359-4.236 4.237-1.7.339.341-1.699Z"/></svg>',
    ];
    $nav[] = [
        'href' => 'analytics.php',
        'label' => 'Analytics',
        'icon' => '<svg class="w-8 h-8 transition duration-75 %ICON_COLOR%" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 22 21"><path d="M16.975 11H10V4.025a1 1 0 0 0-1.066-.998 8.5 8.5 0 1 0 9.039 9.039.999.999 0 0 0-1-1.066h.002Z"/><path d="M12.5 0c-.157 0-.311.01-.565.027A1 1 0 0 0 11 1.02V10h8.975a1 1 0 0 0 1-.935c.013-.188.028-.374.028-.565A8.51 8.51 0 0 0 12.5 0Z"/></svg>',
    ];
    $nav[] = [
        'href' => 'user_management.php',
        'label' => 'User Management',
        'icon' => '<svg class="w-8 h-8 transition duration-75 %ICON_COLOR%" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 18"><path d="M14 2a3.963 3.963 0 0 0-1.4.267 6.439 6.439 0 0 1-1.331 6.638A4 4 0 1 0 14 2Zm1 9h-1.264A6.957 6.957 0 0 1 15 15v2a2.97 2.97 0 0 1-.184 1H19a1 1 0 0 0 1-1v-1a5.006 5.006 0 0 0-5-5ZM6.5 9a4.5 4.5 0 1 0 0-9 4.5 4.5 0 0 0 0 9ZM8 10H5a5.006 5.006 0 0 0-5 5v2a1 1 0 0 0 1 1h11a1 1 0 0 0 1-1v-2a5.006 5.006 0 0 0-5-5Z"/></svg>',
    ];
    $nav[] = [
        'href' => 'settings.php',
        'label' => 'Settings',
        'icon' => '<svg class="w-8 h-8 transition duration-75 %ICON_COLOR%" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20"><path d="M18 7.5h-.423l-.452-1.09.3-.3a1.5 1.5 0 0 0 0-2.121L16.01 2.575a1.5 1.5 0 0 0-2.121 0l-.3.3-1.089-.452V2A1.5 1.5 0 0 0 11 .5H9A1.5 1.5 0 0 0 7.5 2v.423l-1.09.452-.3-.3a1.5 1.5 0 0 0-2.121 0L2.576 3.99a1.5 1.5 0 0 0 0 2.121l.3.3L2.423 7.5H2A1.5 1.5 0 0 0 .5 9v2A1.5 1.5 0 0 0 2 12.5h.423l.452 1.09-.3.3a1.5 1.5 0 0 0 0 2.121l1.415 1.413a1.5 1.5 0 0 0 2.121 0l.3-.3 1.09.452V18A1.5 1.5 0 0 0 9 19.5h2a1.5 1.5 0 0 0 1.5-1.5v-.423l1.09-.452.3.3a1.5 1.5 0 0 0 2.121 0l1.415-1.414a1.5 1.5 0 0 0 0-2.121l-.3-.3.452-1.09H18a1.5 1.5 0 0 0 1.5-1.5V9A1.5 1.5 0 0 0 18 7.5Zm-8 6a3.5 3.5 0 1 1 0-7 3.5 3.5 0 0 1 0 7Z"/></svg>',
    ];
}
if ($role === 'superior') {
    $nav[] = [
        'href' => 'review_history.php',
        'label' => 'Review History',
        'icon' => '<svg class="w-8 h-8 transition duration-75 %ICON_COLOR%" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20"><path d="M5 5V.13a2.96 2.96 0 0 0-1.293.749L.879 3.707A2.96 2.96 0 0 0 .13 5H5Z"/><path d="M6.737 11.061a2.961 2.961 0 0 1 .81-1.515l6.117-6.116A4.839 4.839 0 0 1 16 2.141V2a1.97 1.97 0 0 0-1.933-2H7v5a2 2 0 0 1-2 2H0v11a1.969 1.969 0 0 0 1.933 2h12.134A1.97 1.97 0 0 0 16 18v-3.093l-1.546 1.546c-.413.413-.94.695-1.513.81l-3.4.679a2.947 2.947 0 0 1-1.85-.227 2.96 2.96 0 0 1-1.635-3.257l.681-3.397Z"/><path d="M8.961 16a.93.93 0 0 0 .189-.019l3.4-.679a.961.961 0 0 0 .49-.263l6.118-6.117a2.884 2.884 0 0 0-4.079-4.078l-6.117 6.117a.96.96 0 0 0-.263.491l-.679 3.4A.961.961 0 0 0 8.961 16Zm7.477-9.8a.958.958 0 0 1 .68-.281.961.961 0 0 1 .682 1.644l-.315.315-1.36-1.36.313-.318Zm-5.911 5.911 4.236-4.236 1.359 1.359-4.236 4.237-1.7.339.341-1.699Z"/></svg>',
    ];
}

?>
<!-- Unified Sidebar -->
<aside id="sidebar" x-data x-bind:class="(typeof sidebarOpen==='undefined'||sidebarOpen)?'translate-x-0 lg:translate-x-0':'-translate-x-full lg:translate-x-0'" class="fixed top-0 left-0 z-40 w-72 h-screen transition-transform -translate-x-full lg:translate-x-0" aria-label="Sidebar">
    <div class="h-full px-3 py-4 overflow-y-auto bg-white border-r border-gray-200">
        <div class="flex items-center justify-center mb-6 pb-4 border-b border-gray-200">
            <img src="../logo.png" alt="Company Logo" class="h-20 w-auto">
        </div>
        <ul class="space-y-2 font-medium">
            <li class="mb-2">
                <p class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Main Menu</p>
            </li>
            <?php foreach ($nav as $item):
                if (isset($item['type']) && $item['type'] === 'subheader'): ?>
                    <li class="mt-4 mb-2">
                        <p class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider"><?php echo htmlspecialchars($item['label']); ?></p>
                    </li>
                <?php continue;
                endif;
                $isActive = ($current_page === basename($item['href'])); ?>
                <li>
                    <a href="<?php echo htmlspecialchars($item['href']); ?>"
                        class="flex items-center <?php echo isset($item['badge']) ? 'justify-between' : ''; ?> p-2 rounded-lg group <?php echo $isActive ? 'text-white bg-blue-700' : 'text-gray-900 hover:bg-gray-100'; ?>">
                        <div class="flex items-center">
                            <?php
                            $icon = str_replace('%ICON_COLOR%', $isActive ? 'text-white' : 'text-gray-500 group-hover:text-gray-900', $item['icon']);
                            echo $icon;
                            ?>
                            <span class="ms-3"><?php echo htmlspecialchars($item['label']); ?></span>
                        </div>
                        <?php if (isset($item['badge']) && (int)$item['badge'] > 0): ?>
                            <span class="inline-flex items-center justify-center w-6 h-6 text-xs font-bold text-white bg-red-500 rounded-full"><?php echo ((int)$item['badge'] > 99) ? '99+' : (int)$item['badge']; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <div class="pt-4 mt-4 space-y-2 font-medium border-t border-gray-200">
            <a href="<?php echo htmlspecialchars($logoutHref); ?>" class="flex items-center p-2 text-red-600 rounded-lg hover:bg-red-50 group">
                <svg class="w-8 h-8 text-red-600 transition duration-75" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 18 16">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M1 8h11m0 0L8 4m4 4-4 4m4-11h3a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-3" />
                </svg>
                <span class="ms-3 font-medium">Logout</span>
            </a>
        </div>
    </div>
</aside>
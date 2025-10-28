<?php
/**
 * Example usage of the Design System
 * This file shows how to use the reusable components across all user types
 */

// Include the design system
require_once 'design_system.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Design System Example - UAR Application</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Boxicons -->
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <!-- Alpine.js for interactions -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.1/dist/cdn.min.js"></script>
    <!-- Flowbite CSS -->
    <link href="https://cdn.jsdelivr.net/npm/flowbite@2.5.1/dist/flowbite.min.css" rel="stylesheet" />
    <!-- Flowbite JS -->
    <script src="https://cdn.jsdelivr.net/npm/flowbite@2.5.1/dist/flowbite.min.js"></script>
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                            950: '#172554',
                        }
                    },
                    fontFamily: {
                        'sans': ['Inter', 'system-ui', 'sans-serif'],
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-gray-100" x-data="{ sidebarOpen: true }">
    <!-- Example Header -->
    <div class="<?php echo getComponentClass('header'); ?>">
        <div class="<?php echo getComponentClass('header', 'container'); ?>">
            <div class="flex items-center">
                <?php renderHamburgerButton(); ?>
                <h1 class="<?php echo getComponentClass('header', 'title'); ?>">Design System Example</h1>
            </div>
            
            <!-- Privacy Notice -->
            <?php renderPrivacyNotice(); ?>
        </div>
    </div>

    <div class="p-4 md:p-8">
        <!-- Example Section Header -->
        <?php renderSectionHeader('Dashboard Overview', getIcon('chart')); ?>
        
        <!-- Example Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <?php 
            renderStatsCard('Total Requests', '25', 'bx bx-folder text-blue-600', 'from-blue-500 via-blue-400 to-blue-300');
            renderStatsCard('Approved', '18', 'bx bx-check-circle text-green-600', 'from-green-500 via-green-400 to-green-300');
            renderStatsCard('Pending', '7', 'bx bx-time text-yellow-600', 'from-yellow-500 via-yellow-400 to-yellow-300');
            renderStatsCard('Rejected', '2', 'bx bx-x-circle text-red-600', 'from-red-500 via-red-400 to-red-300');
            ?>
        </div>

        <!-- Example Form Section -->
        <?php renderSectionHeader('User Management', getIcon('user')); ?>
        
        <div class="<?php echo getComponentClass('card'); ?> p-6 mb-6">
            <div class="<?php echo getComponentClass('card', 'header'); ?>">
                <h3 class="text-lg font-semibold text-gray-800">Add New User</h3>
            </div>
            <form class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
                    <?php renderFormInput('text', 'full_name', 'Enter full name', true); ?>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                    <?php renderFormInput('email', 'email', 'Enter email address', true); ?>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Role</label>
                    <?php 
                    $roleOptions = [
                        'requestor' => 'Requestor',
                        'admin' => 'Admin', 
                        'help_desk' => 'Help Desk',
                        'superior' => 'Immediate Superior',
                        'technical_support' => 'Technical Support',
                        'process_owner' => 'Process Owner'
                    ];
                    renderFormSelect('role', $roleOptions, 'requestor', true);
                    ?>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Department</label>
                    <?php 
                    $deptOptions = [
                        'IT' => 'Information Technology',
                        'HR' => 'Human Resources',
                        'Finance' => 'Finance',
                        'Operations' => 'Operations'
                    ];
                    renderFormSelect('department', $deptOptions, '', true);
                    ?>
                </div>
                <div class="md:col-span-2 flex gap-2">
                    <?php renderButton('Add User', 'primary', '', getIcon('plus')); ?>
                    <?php renderButton('Cancel', 'secondary', 'resetForm()'); ?>
                </div>
            </form>
        </div>

        <!-- Example Table Section -->
        <?php renderSectionHeader('Recent Requests', getIcon('folder'), true); ?>
        
        <div class="<?php echo getComponentClass('card'); ?>">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <?php 
                    $columns = ['UAR REF NO.', 'Requestor', 'Status', 'Date Requested', 'Actions'];
                    renderTableHeader($columns);
                    ?>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">2025-001</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">John Doe</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                    Pending Review
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Oct 21, 2025</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button class="text-blue-600 hover:text-blue-900 mr-3">View</button>
                                <button class="text-green-600 hover:text-green-900 mr-3">Approve</button>
                                <button class="text-red-600 hover:text-red-900">Reject</button>
                            </td>
                        </tr>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">2025-002</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Jane Smith</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                    Approved
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Oct 20, 2025</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button class="text-blue-600 hover:text-blue-900">View</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Example Filter Buttons -->
        <div class="flex flex-wrap gap-2 mt-4">
            <?php 
            renderButton('All', 'active', 'filterRequests("all")');
            renderButton('Pending', 'secondary', 'filterRequests("pending")');
            renderButton('Approved', 'secondary', 'filterRequests("approved")');
            renderButton('Rejected', 'secondary', 'filterRequests("rejected")');
            ?>
        </div>

        <!-- Example Settings Section -->
        <?php renderSectionHeader('System Settings', getIcon('settings')); ?>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="<?php echo getComponentClass('card'); ?> p-6">
                <h4 class="text-lg font-semibold text-gray-800 mb-4">Email Settings</h4>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">SMTP Server</label>
                        <?php renderFormInput('text', 'smtp_server', 'smtp.company.com'); ?>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Port</label>
                        <?php renderFormInput('number', 'smtp_port', '587'); ?>
                    </div>
                </div>
            </div>
            
            <div class="<?php echo getComponentClass('card'); ?> p-6">
                <h4 class="text-lg font-semibold text-gray-800 mb-4">Notification Settings</h4>
                <div class="space-y-4">
                    <div class="flex items-center">
                        <input type="checkbox" class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500">
                        <label class="ml-2 text-sm text-gray-700">Email notifications</label>
                    </div>
                    <div class="flex items-center">
                        <input type="checkbox" class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500">
                        <label class="ml-2 text-sm text-gray-700">SMS notifications</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function filterRequests(status) {
            console.log('Filtering by:', status);
            // Your filtering logic here
        }

        function resetForm() {
            document.querySelector('form').reset();
        }
    </script>
</body>
</html>

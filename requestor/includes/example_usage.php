<?php
/**
 * Example usage of the Design System
 * This file shows how to use the reusable components
 */

// Include the design system
require_once 'design_system.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Design System Example</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
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
                <button class="<?php echo getComponentClass('header', 'hamburger'); ?>">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="<?php echo getIcon('menu'); ?>" clip-rule="evenodd"></path>
                    </svg>
                </button>
                <h1 class="<?php echo getComponentClass('header', 'title'); ?>">Example Page</h1>
            </div>
            
            <!-- Privacy Notice -->
            <div class="relative" x-data="{ privacyNoticeOpen: false }">
                <button class="<?php echo getComponentClass('header', 'privacy_button'); ?>">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="<?php echo getIcon('info'); ?>" clip-rule="evenodd"></path>
                    </svg>
                </button>
                <div x-cloak x-show="privacyNoticeOpen" class="<?php echo getComponentClass('header', 'privacy_tooltip'); ?>">
                    <p class="font-semibold text-gray-900">Data Privacy Notice</p>
                    <p class="text-gray-600">Your data is handled according to our privacy policy.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="p-4 md:p-8">
        <!-- Example Section Header -->
        <?php renderSectionHeader('Example Section', getIcon('user')); ?>
        
        <!-- Example Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <?php 
            renderStatsCard('Total Requests', '25', 'bx bx-folder text-blue-600', 'from-blue-500 via-blue-400 to-blue-300');
            renderStatsCard('Approved', '18', 'bx bx-check-circle text-green-600', 'from-green-500 via-green-400 to-green-300');
            renderStatsCard('Pending', '7', 'bx bx-time text-yellow-600', 'from-yellow-500 via-yellow-400 to-yellow-300');
            ?>
        </div>

        <!-- Example Form -->
        <div class="<?php echo getComponentClass('card'); ?> p-6 mb-6">
            <div class="<?php echo getComponentClass('card', 'header'); ?>">
                <h3 class="text-lg font-semibold text-gray-800">Example Form</h3>
            </div>
            <form class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Name</label>
                    <?php renderFormInput('text', 'name', 'Enter your name', true); ?>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <?php 
                    $options = ['active' => 'Active', 'inactive' => 'Inactive', 'pending' => 'Pending'];
                    renderFormSelect('status', $options, 'active', true);
                    ?>
                </div>
                <div class="md:col-span-2">
                    <?php renderButton('Submit', 'primary', '', getIcon('download')); ?>
                </div>
            </form>
        </div>

        <!-- Example Table -->
        <div class="<?php echo getComponentClass('card'); ?>">
            <div class="<?php echo getComponentClass('section_header_white'); ?>">
                <h3 class="<?php echo getComponentClass('section_header_white', 'title'); ?>">Example Table</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <?php 
                    $columns = ['ID', 'Name', 'Status', 'Date'];
                    renderTableHeader($columns);
                    ?>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">1</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">John Doe</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                    Active
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">2024-01-15</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Example Filter Buttons -->
        <div class="flex gap-2 mt-4">
            <?php 
            renderButton('All', 'active', 'filterRequests("all")');
            renderButton('Pending', 'secondary', 'filterRequests("pending")');
            renderButton('Approved', 'secondary', 'filterRequests("approved")');
            ?>
        </div>
    </div>

    <script>
        function filterRequests(status) {
            console.log('Filtering by:', status);
            // Your filtering logic here
        }
    </script>
</body>
</html>

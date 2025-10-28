<?php
/**
 * Design System - Reusable components and styles for UAR application
 * This file contains all the common design patterns used across the application
 */

// Common CSS classes and design patterns
$designSystem = [
    'colors' => [
        'primary' => [
            '50' => '#eff6ff',
            '100' => '#dbeafe',
            '200' => '#bfdbfe',
            '300' => '#93c5fd',
            '400' => '#60a5fa',
            '500' => '#3b82f6',
            '600' => '#2563eb',
            '700' => '#1d4ed8',
            '800' => '#1e40af',
            '900' => '#1e3a8a',
            '950' => '#172554',
        ]
    ],
    'components' => [
        'header' => [
            'class' => 'bg-gradient-to-r from-blue-700 to-blue-900 border-b border-blue-800 sticky top-0 z-10 shadow-lg',
            'container' => 'flex flex-col md:flex-row md:justify-between md:items-center px-4 md:px-8 py-4 gap-3',
            'title' => 'text-2xl md:text-3xl lg:text-4xl font-bold text-white',
            'hamburger' => 'inline-flex items-center justify-center rounded-lg p-2 text-white hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-300 mr-3 md:mr-4 transition-all',
            'privacy_button' => 'inline-flex items-center p-2 text-white bg-blue-800 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-300 transition-all',
            'privacy_tooltip' => 'absolute right-0 mt-2 w-72 p-4 bg-white rounded-lg shadow-xl text-gray-700 text-sm z-50 border border-gray-200'
        ],
        'section_header' => [
            'class' => 'flex items-center justify-between p-4 mb-6 bg-gradient-to-r from-blue-700 to-blue-900 rounded-lg shadow-md border-b-4 border-blue-950',
            'icon' => 'w-5 h-5 text-white mr-2',
            'title' => 'text-lg font-semibold text-white',
            'divider' => 'h-6 w-px bg-white opacity-30'
        ],
        'section_header_white' => [
            'class' => 'px-6 py-4 bg-white border-b border-gray-200 flex justify-between items-center',
            'title' => 'text-lg font-medium text-gray-800'
        ],
        'table_header' => [
            'class' => 'text-xs text-white uppercase bg-gradient-to-r from-blue-700 to-blue-900 border-b-2 border-blue-950',
            'th' => 'px-6 py-3 text-left font-semibold tracking-wider'
        ],
        'form_input' => [
            'class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5'
        ],
        'form_select' => [
            'class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5'
        ],
        'button_primary' => [
            'class' => 'text-white bg-gradient-to-r from-blue-700 to-blue-900 hover:from-blue-800 hover:to-blue-950 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center inline-flex items-center justify-center transition-all'
        ],
        'button_secondary' => [
            'class' => 'px-3 py-1.5 text-sm font-medium text-gray-600 bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors'
        ],
        'button_active' => [
            'class' => 'px-3 py-1.5 text-sm font-medium bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 transition-colors'
        ],
        'card' => [
            'class' => 'bg-white rounded-xl shadow-md border border-gray-200',
            'header' => 'border-b border-gray-200 px-6 py-4 bg-gray-50'
        ],
        'stats_card' => [
            'class' => 'stat-card rounded-xl p-6 flex items-center cursor-pointer hover:shadow-lg transition-all duration-300',
            'icon_container' => 'flex-shrink-0 p-3 mr-4 rounded-full shadow-lg',
            'content' => 'text-sm text-white',
            'number' => 'text-2xl font-bold text-white'
        ]
    ]
];

// Function to get component classes
function getComponentClass($component, $variant = null) {
    global $designSystem;
    
    if ($variant && isset($designSystem['components'][$component][$variant])) {
        return $designSystem['components'][$component][$variant];
    }
    
    if (isset($designSystem['components'][$component]['class'])) {
        return $designSystem['components'][$component]['class'];
    }
    
    return '';
}

// Function to render section header
function renderSectionHeader($title, $icon = null, $white = false) {
    $headerClass = $white ? 'section_header_white' : 'section_header';
    $iconHtml = $icon ? '<svg class="w-5 h-5 text-white mr-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="' . $icon . '" clip-rule="evenodd"></path></svg>' : '';
    $dividerHtml = $white ? '' : '<div class="h-6 w-px bg-white opacity-30"></div>';
    
    echo '<div class="' . getComponentClass($headerClass) . '">';
    echo '<div class="flex items-center">';
    echo $iconHtml;
    echo '<h3 class="' . ($white ? 'text-lg font-medium text-gray-800' : 'text-lg font-semibold text-white') . '">' . $title . '</h3>';
    echo '</div>';
    echo $dividerHtml;
    echo '</div>';
}

// Function to render table header
function renderTableHeader($columns) {
    echo '<thead class="' . getComponentClass('table_header') . '">';
    echo '<tr>';
    foreach ($columns as $column) {
        echo '<th scope="col" class="' . getComponentClass('table_header', 'th') . '">' . $column . '</th>';
    }
    echo '</tr>';
    echo '</thead>';
}

// Function to render form input
function renderFormInput($type, $name, $placeholder = '', $required = false, $value = '') {
    $requiredAttr = $required ? 'required' : '';
    echo '<input type="' . $type . '" name="' . $name . '" placeholder="' . $placeholder . '" value="' . $value . '" class="' . getComponentClass('form_input') . '" ' . $requiredAttr . '>';
}

// Function to render form select
function renderFormSelect($name, $options, $selected = '', $required = false) {
    $requiredAttr = $required ? 'required' : '';
    echo '<select name="' . $name . '" class="' . getComponentClass('form_select') . '" ' . $requiredAttr . '>';
    foreach ($options as $value => $label) {
        $selectedAttr = ($value == $selected) ? 'selected' : '';
        echo '<option value="' . $value . '" ' . $selectedAttr . '>' . $label . '</option>';
    }
    echo '</select>';
}

// Function to render button
function renderButton($text, $type = 'primary', $onclick = '', $icon = null) {
    $buttonClass = getComponentClass('button_' . $type);
    $onclickAttr = $onclick ? 'onclick="' . $onclick . '"' : '';
    $iconHtml = $icon ? '<svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="' . $icon . '" clip-rule="evenodd"></path></svg>' : '';
    
    echo '<button type="button" class="' . $buttonClass . '" ' . $onclickAttr . '>';
    echo $iconHtml;
    echo $text;
    echo '</button>';
}

// Function to render stats card
function renderStatsCard($title, $value, $icon, $color, $onclick = '') {
    $onclickAttr = $onclick ? 'onclick="' . $onclick . '"' : '';
    echo '<div class="' . getComponentClass('stats_card') . ' bg-gradient-to-br ' . $color . '" ' . $onclickAttr . '>';
    echo '<div class="' . getComponentClass('stats_card', 'icon_container') . ' bg-gradient-to-br ' . $color . '">';
    echo '<i class="' . $icon . ' text-2xl"></i>';
    echo '</div>';
    echo '<div>';
    echo '<p class="' . getComponentClass('stats_card', 'content') . '">' . $title . '</p>';
    echo '<h4 class="' . getComponentClass('stats_card', 'number') . '">' . $value . '</h4>';
    echo '</div>';
    echo '</div>';
}

// Common icons
$icons = [
    'user' => 'M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z',
    'filter' => 'M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L12 11.414V15a1 1 0 01-.293.707l-2 2A1 1 0 018 17v-5.586L3.293 6.707A1 1 0 013 6V3z',
    'download' => 'M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0015.414 6L12 2.586A2 2 0 0010.586 2H6zm5 6a1 1 0 10-2 0v3.586l-1.293-1.293a1 1 0 10-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 11.586V8z',
    'info' => 'M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z',
    'menu' => 'M2 4.75A.75.75 0 012.75 4h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 4.75zm0 10.5a.75.75 0 01.75-.75h7.5a.75.75 0 010 1.5h-7.5a.75.75 0 01-.75-.75zM2 10a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 10z',
    'settings' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
    'chart' => 'M2 10a8 8 0 018-8v8h8a8 8 0 11-16 0z M12 2.252A8.014 8.014 0 0117.748 8H12V2.252z',
    'folder' => 'M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z',
    'check' => 'M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z',
    'x' => 'M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z',
    'time' => 'M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z',
    'plus' => 'M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z'
];

// Function to get icon path
function getIcon($iconName) {
    global $icons;
    return $icons[$iconName] ?? '';
}

// Function to render privacy notice tooltip
function renderPrivacyNotice() {
    echo '<div class="relative" x-data="{ privacyNoticeOpen: false }" @mouseover="privacyNoticeOpen = true" @mouseleave="privacyNoticeOpen = false">';
    echo '<button type="button" class="' . getComponentClass('header', 'privacy_button') . '">';
    echo '<svg class="w-5 h-5" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">';
    echo '<path fill-rule="evenodd" d="' . getIcon('info') . '" clip-rule="evenodd"></path>';
    echo '</svg>';
    echo '<span class="sr-only">Privacy info</span>';
    echo '</button>';
    echo '<div x-cloak x-show="privacyNoticeOpen" class="' . getComponentClass('header', 'privacy_tooltip') . '"';
    echo ' x-transition:enter="transition ease-out duration-200"';
    echo ' x-transition:enter-start="opacity-0 transform scale-95"';
    echo ' x-transition:enter-end="opacity-100 transform scale-100"';
    echo ' x-transition:leave="transition ease-in duration-75"';
    echo ' x-transition:leave-start="opacity-100 transform scale-100"';
    echo ' x-transition:leave-end="opacity-0 transform scale-95">';
    echo '<div class="flex items-start mb-2">';
    echo '<svg class="w-5 h-5 text-blue-600 mr-2 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">';
    echo '<path fill-rule="evenodd" d="' . getIcon('info') . '" clip-rule="evenodd"></path>';
    echo '</svg>';
    echo '<p class="font-semibold text-gray-900">Data Privacy Notice</p>';
    echo '</div>';
    echo '<p class="ml-7 text-gray-600">Your data is used solely for processing access requests and is handled according to our internal privacy policy.</p>';
    echo '</div>';
    echo '</div>';
}

// Function to render hamburger menu button
function renderHamburgerButton() {
    echo '<button @click="sidebarOpen = !sidebarOpen" type="button" class="' . getComponentClass('header', 'hamburger') . '" aria-label="Toggle sidebar">';
    echo '<svg class="w-6 h-6" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">';
    echo '<path clip-rule="evenodd" fill-rule="evenodd" d="' . getIcon('menu') . '"></path>';
    echo '</svg>';
    echo '</button>';
}
?>

<!-- Common CSS Styles -->
<style>
    body {
        font-family: 'Inter', system-ui, sans-serif;
    }

    [x-cloak] {
        display: none !important;
    }

    .sidebar-transition {
        transition-property: transform, margin, width;
        transition-duration: 300ms;
    }

    /* Enhanced Table Styles */
    .enhanced-table {
        border-radius: 1rem;
        overflow: hidden;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }

    .enhanced-table thead {
        background: linear-gradient(90deg, #1d4ed8 0%, #1e3a8a 100%) !important;
    }

    .dark .enhanced-table thead {
        background: linear-gradient(90deg, #1d4ed8 0%, #1e3a8a 100%) !important;
    }

    .enhanced-table tr {
        transition: all 0.2s ease;
    }

    .enhanced-table tr:hover {
        background-color: rgba(241, 245, 249, 0.5);
    }

    .dark .enhanced-table tr:hover {
        background-color: rgba(31, 41, 55, 0.5);
    }

    /* Status Badge Styles */
    .status-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-weight: 500;
        font-size: 0.75rem;
        transition: all 0.2s ease;
    }

    .status-badge:hover {
        transform: scale(1.05);
    }

    /* Button Styles */
    .action-button {
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        font-weight: 500;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .action-button:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    /* Responsive table */
    @media (max-width: 640px) {
        .responsive-table-card {
            display: block;
            margin-bottom: 1rem;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .responsive-table-card td {
            display: flex;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .responsive-table-card td:before {
            content: attr(data-label);
            font-weight: 600;
            width: 40%;
            margin-right: 1rem;
        }

        .responsive-table-card thead {
            display: none;
        }

        .responsive-table-card tr {
            display: block;
            margin-bottom: 1rem;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            background-color: white;
        }
    }
</style>

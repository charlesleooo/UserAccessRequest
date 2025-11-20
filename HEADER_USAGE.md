# Header Component Usage Guide

## Overview

The `header.php` file provides a consistent, reusable header component across all pages in the UAR system.

## Features

- Alcantara Group logo display
- Responsive design (mobile & desktop)
- Customizable page title
- Hamburger menu for sidebar toggle
- Data privacy notice tooltip
- Sticky positioning
- Gradient blue theme

## How to Use

### Basic Usage

```php
<?php
$pageTitle = "Your Page Title Here";
$logoPath = "../logo.png"; // Adjust path based on your file location
include '../header.php';
?>
```

### Parameters

#### `$pageTitle` (required)

The title to display in the header. Examples:

- "Dashboard"
- "Create New User Access Request (UAR)"
- "Request History"
- "My Requests"

#### `$logoPath` (optional)

Path to the logo image. Defaults to `../logo.png` if not set.

- From root folder: `logo.png`
- From subfolders: `../logo.png`
- From nested folders: `../../logo.png`

### Examples

#### For files in the root directory:

```php
<?php
$pageTitle = "Dashboard";
$logoPath = "logo.png";
include 'header.php';
?>
```

#### For files in requestor/ folder:

```php
<?php
$pageTitle = "Create New User Access Request (UAR)";
$logoPath = "../logo.png";
include '../header.php';
?>
```

#### For files in admin/ folder:

```php
<?php
$pageTitle = "Admin Dashboard";
$logoPath = "../logo.png";
include '../header.php';
?>
```

#### For files in nested folders (e.g., requestor/includes/):

```php
<?php
$pageTitle = "Settings";
$logoPath = "../../logo.png";
include '../../header.php';
?>
```

## Required Dependencies

The header component requires:

- Alpine.js (for sidebar toggle functionality)
- Tailwind CSS (for styling)
- AOS (for animations - optional)

Make sure these are loaded in your page before the header.

## Customization

To modify the header appearance or behavior, edit `/header.php` directly. Changes will apply to all pages using the component.

## Files Already Using the Header

- ✅ `requestor/create_request.php`
- ✅ `requestor/dashboard.php`

## Migration Checklist

To migrate existing pages to use the header component:

1. Define `$pageTitle` variable
2. Define `$logoPath` variable (if needed)
3. Replace the entire header HTML with the include statement
4. Remove duplicate header code
5. Test the page to ensure proper rendering

Example replacement:

**Before:**

```html
<div class="bg-gradient-to-r from-blue-700 to-blue-900 ...">
  <!-- Long header HTML -->
</div>
```

**After:**

```php
<?php
$pageTitle = "Your Page Title";
$logoPath = "../logo.png";
include '../header.php';
?>
```

## Notes

- The header is sticky and will stay at the top when scrolling
- Logo height is responsive: h-12 on mobile, h-14 on desktop
- Privacy notice appears on hover over the info icon
- Hamburger menu requires Alpine.js `sidebarOpen` state

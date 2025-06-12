<?php
session_start();
require_once '../config.php';

// Set verification flag directly
$_SESSION['requests_verified'] = true;
$_SESSION['requests_verified_time'] = time();

// Determine which requests page to redirect to based on role
$redirect = 'requests.php';
switch ($_SESSION['role']) {
    case 'superior':
        $redirect = '../superior/requests.php';
        break;
    case 'technical_support':
        $redirect = '../technical_support/requests.php';
        break;
    case 'process_owner':
        $redirect = '../process_owner/requests.php';
        break;
    case 'help_desk':
        $redirect = '../helpdesk/requests.php';
        break;
}

header("Location: $redirect");
exit();
?> 
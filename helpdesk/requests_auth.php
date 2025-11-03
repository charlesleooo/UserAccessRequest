<?php
session_start();
require_once '../config.php';

// Set verification flag directly
$_SESSION['requests_verified'] = true;
$_SESSION['requests_verified_time'] = time();

// Redirect back to intended page if provided
$return = isset($_GET['return']) ? $_GET['return'] : '';
if (!empty($return)) {
    header('Location: ' . $return);
    exit();
}

header('Location: requests.php');
exit();
?> 
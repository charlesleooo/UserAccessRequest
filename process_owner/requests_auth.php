<?php
session_start();
require_once '../config.php';

// Set verification flag directly
$_SESSION['requests_verified'] = true;
$_SESSION['requests_verified_time'] = time();

header('Location: requests.php');
exit();
?> 
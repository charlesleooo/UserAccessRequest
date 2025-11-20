<?php
// Use unified sidebar with role context
$current_page = basename($_SERVER['PHP_SELF']);
$ROLE = 'uar_admin';
include __DIR__ . '/../includes/role_sidebar.php';

<?php
// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Ensure logs directory exists
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}
define('APP_DEBUG', getenv('APP_DEBUG') === 'true' || true); // Re-enable for troubleshooting
define('LOG_PATH', $logDir . '/error.log');

// Database configuration for MSSQL
define('DB_HOST', getenv('DB_HOST') ?: 'MAKATI24L');
define('DB_NAME', getenv('DB_NAME') ?: 'uar');
define('DB_USER', getenv('DB_USER') ?: 'uar');
define('DB_PASS', getenv('DB_PASS') ?: 'ilovepizza22');
define('BASE_URL', getenv('BASE_URL') ?: 'http://localhost/uar');

// SMTP settings - Updated for better Gmail compatibility
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_PORT', (int)(getenv('SMTP_PORT') ?: 587));
define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: 'charlesondota@gmail.com');
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: 'crpf bbcb vodv xbjk');
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: SMTP_USERNAME);
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'Alsons Agribusiness Unit');

// Load PHPMailer autoloader
$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
} else {
    error_log("PHPMailer autoloader not found at: " . $autoload);
}

// Database connection using MSSQL PDO (single instance)
try {
    $dsn = "sqlsrv:Server=" . DB_HOST . ";Database=" . DB_NAME . ";TrustServerCertificate=yes;MultipleActiveResultSets=true";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_UTF8,
    ]);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage() . PHP_EOL, 3, LOG_PATH);
    die("A database error occurred. Please try again later.");
}

// Session start
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Helper function for redirects
function redirect($url)
{
    header("Location: " . BASE_URL . $url);
    exit;
}

// Helper function for current user
function getCurrentUser()
{
    return $_SESSION['user'] ?? null;
}

// Helper function to check if user is logged in
function isLoggedIn()
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Helper function to require login
function requireLogin()
{
    if (!isLoggedIn()) {
        redirect('/login.php');
    }
}

// Helper function to ensure clean transaction state
function ensureCleanTransaction($pdo) {
    try {
        // Check if there's an active transaction and roll it back
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    } catch (Exception $e) {
        // Log the error but don't fail the request
        error_log("Transaction cleanup error: " . $e->getMessage());
    }
}

// Helper function to create a new PDO connection for critical operations
function getCleanPDOConnection() {
    try {
        $dsn = "sqlsrv:Server=" . DB_HOST . ";Database=" . DB_NAME . ";TrustServerCertificate=yes;MultipleActiveResultSets=true";
        return new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_UTF8,
        ]);
    } catch (PDOException $e) {
        error_log("Clean PDO connection failed: " . $e->getMessage());
        return null;
    }
}

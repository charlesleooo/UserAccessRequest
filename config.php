<?php
// Load environment variables from .env file
function loadEnv($path)
{
    if (!file_exists($path)) {
        throw new Exception(".env file not found at {$path}");
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Parse the line
        $parts = explode('=', $line, 2);
        if (count($parts) < 2) {
            continue; // Skip malformed lines
        }
        $name = trim($parts[0]);
        $value = isset($parts[1]) ? trim($parts[1]) : '';
        
        // Skip if name is empty
        if (empty($name)) {
            continue;
        }

        // Remove quotes if present
        if (strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) {
            $value = substr($value, 1, -1);
        } elseif (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1) {
            $value = substr($value, 1, -1);
        }

        // Set environment variable
        putenv("{$name}={$value}");
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

// Load .env file
try {
    loadEnv(__DIR__ . '/.env');
} catch (Exception $e) {
    die("Error loading .env file: " . $e->getMessage());
}

// Database configuration
define('DB_HOST', getenv('DB_HOST') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: '');
define('DB_USER', getenv('DB_USER') ?: '');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('BASE_URL', getenv('BASE_URL') ?: 'https://royalblue-chimpanzee-160919.hostingersite.com');

// SMTP configuration
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_PORT', getenv('SMTP_PORT') ?: 587);
define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: '');
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: '');
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: '');
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: (getenv('COMPANY_NAME') ?: 'UAR System'));
define('EMAIL_API_KEY', getenv('EMAIL_API_KEY') ?: '');

// Additional configurations (optional, based on your .env)
define('APP_DEBUG', getenv('APP_DEBUG') === 'true');
define('ENCRYPTION_KEY', getenv('ENCRYPTION_KEY') ?: '');
define('LOG_PATH', getenv('LOG_PATH') ?: 'error.log');
define('COMPANY_NAME', getenv('COMPANY_NAME') ?: 'Alsons Agribusiness Unit');
define('ADMIN_EMAIL', getenv('ADMIN_EMAIL') ?: '');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    if (APP_DEBUG) {
        echo "Connection failed: " . $e->getMessage();
    } else {
        error_log("Database connection failed: " . $e->getMessage(), 3, LOG_PATH);
        echo "A database error occurred. Please try again later.";
    }
}

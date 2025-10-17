<?php
$server   = "MAKATI24L";           // Server name (no instance)
$db       = "uar";
$user     = "";                     // Empty for Windows Authentication
$pass     = "";                     // Empty for Windows Authentication

// For Windows Authentication, use Trusted_Connection=yes
$dsn = "sqlsrv:Server={$server};Database={$db};TrustServerCertificate=yes";

try {
    // Pass empty user/pass and add SQLSRV driver options
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_UTF8,
    ]);

    $v = $pdo->query("SELECT @@VERSION AS v")->fetch(PDO::FETCH_ASSOC);
    echo "Connected OK<br>\n";
    echo nl2br($v['v']);
} catch (PDOException $e) {
    http_response_code(500);
    echo "Connection failed: " . $e->getMessage();
}

<?php
// ------------------------------------------------------------
// App settings
// ------------------------------------------------------------
// If you open the site at http://localhost/kostku  -> '/kostku'
// If you open the site at http://kostku.test       -> ''
define('BASE_URL', '/kostku');

// ------------------------------------------------------------
// Database settings (Laragon defaults)
// ------------------------------------------------------------
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'kostku';

try {
    $conn = new PDO(
        "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $exception) {
    http_response_code(500);
    exit('Could not connect to the database. Make sure MySQL is running in Laragon and the "kostku" database has been imported.');
}

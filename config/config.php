<?php
// App settings
// Derive the URL prefix from the project location under the web server root.
$projectRoot = realpath(dirname(__DIR__));
$documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
$baseUrl = '/kostku';

if ($projectRoot !== false && $documentRoot !== false) {
    $projectPath = rtrim(str_replace('\\', '/', $projectRoot), '/');
    $documentPath = rtrim(str_replace('\\', '/', $documentRoot), '/');
    $windowsPaths = DIRECTORY_SEPARATOR === '\\';
    $samePath = $windowsPaths
        ? strcasecmp($projectPath, $documentPath) === 0
        : $projectPath === $documentPath;
    $documentPrefix = $documentPath . '/';
    $insideDocumentRoot = $windowsPaths
        ? strncasecmp($projectPath, $documentPrefix, strlen($documentPrefix)) === 0
        : strncmp($projectPath, $documentPrefix, strlen($documentPrefix)) === 0;

    if ($samePath) {
        $baseUrl = '';
    } elseif ($insideDocumentRoot) {
        $baseUrl = '/' . substr($projectPath, strlen($documentPrefix));
    }
}

define('BASE_URL', $baseUrl);

// Database settings (Laragon defaults)
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

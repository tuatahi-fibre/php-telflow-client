<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Ensure test environment is properly set up
$cacheDir = getenv('CACHE_DIR') ?: '/tmp/cache';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0777, true);
}

// Copy test response files to cache directory if needed
$responseDir = __DIR__ . '/Responses';
if (is_dir($responseDir)) {
    $files = glob($responseDir . '/*.json');
    foreach ($files as $file) {
        $targetFile = $cacheDir . '/' . basename($file);
        if (!file_exists($targetFile)) {
            copy($file, $targetFile);
        }
    }
}

// Set default test credentials if not provided
if (!getenv('TELFLOW_USERNAME')) {
    putenv('TELFLOW_USERNAME=test_user');
}
if (!getenv('TELFLOW_PASSWORD')) {
    putenv('TELFLOW_PASSWORD=test_pass');
}
if (!getenv('TELFLOW_API_URL')) {
    putenv('TELFLOW_API_URL=http://localhost:8080');
}
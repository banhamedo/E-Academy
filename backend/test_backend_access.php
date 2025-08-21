<?php
// Simple test to verify backend is accessible
echo json_encode([
    'success' => true,
    'message' => 'Backend is accessible',
    'timestamp' => date('Y-m-d H:i:s'),
    'server_info' => [
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'request_method' => $_SERVER['REQUEST_METHOD'],
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'Unknown'
    ]
]);
?>

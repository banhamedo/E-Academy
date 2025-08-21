<?php
// Simple test to verify CORS is working
echo json_encode([
    'success' => true,
    'message' => 'CORS test successful',
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'],
    'origin' => $_SERVER['HTTP_ORIGIN'] ?? 'No origin header',
    'headers_sent' => headers_sent()
]);
?>

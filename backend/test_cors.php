<?php
// Simple CORS test file
require __DIR__ . '/config.php';
// Keep only JSON Content-Type; CORS centralized in .htaccess
set_cors_headers();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

echo json_encode(['success' => true, 'message' => 'CORS test successful']);
?>

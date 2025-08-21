<?php
// Simple test to check if PHP is working
header('Content-Type: application/json');

// Test database connection
try {
    require __DIR__ . '/config.php';
    $pdo = get_pdo();
    
    echo json_encode([
        'success' => true,
        'message' => 'PHP and database working',
        'timestamp' => date('Y-m-d H:i:s'),
        'php_version' => PHP_VERSION
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>

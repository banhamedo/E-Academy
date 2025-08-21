<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);
header('Content-Type: application/json');

require __DIR__ . '/config.php';

try {
  $pdo = get_pdo();
  
  // Add username column if it doesn't exist
  $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS username VARCHAR(100) DEFAULT NULL");
  
  // Add bio column if it doesn't exist
  $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS bio TEXT DEFAULT NULL");
  
  echo json_encode([
    'success' => true,
    'message' => 'Database updated successfully'
  ]);
  
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Database update failed', 'details' => $e->getMessage()]);
}

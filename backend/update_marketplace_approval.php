<?php
require __DIR__ . '/config.php';
set_cors_headers();
header('Content-Type: application/json');

try {
  $pdo = get_pdo();

  // Add approval_status column if it doesn't exist
  // MySQL before 8.0.29 doesn't support IF NOT EXISTS for MODIFY/ADD ENUM easily across all hosts
  $stmt = $pdo->query("SHOW COLUMNS FROM products LIKE 'approval_status'");
  $exists = $stmt->fetch();
  if (!$exists) {
    $pdo->exec("ALTER TABLE products ADD COLUMN approval_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending'");
  }

  // Ensure is_active column exists (some schemas already have it)
  $stmt = $pdo->query("SHOW COLUMNS FROM products LIKE 'is_active'");
  $isActiveExists = $stmt->fetch();
  if (!$isActiveExists) {
    $pdo->exec("ALTER TABLE products ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1");
  }

  echo json_encode(['success' => true, 'message' => 'Marketplace approval fields ensured']);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

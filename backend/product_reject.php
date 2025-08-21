<?php
require __DIR__ . '/config.php';
set_cors_headers();
header('Content-Type: application/json');

function require_admin(PDO $pdo, string $email): array {
  if ($email === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing email']);
    exit;
  }
  $stmt = $pdo->prepare('SELECT id, role FROM users WHERE email = ? LIMIT 1');
  $stmt->execute([$email]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$user) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit;
  }
  $role = strtolower((string)($user['role'] ?? ''));
  if (!in_array($role, ['admin','super_admin','superadmin','owner'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden: admin role required']);
    exit;
  }
  return $user;
}

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
  }

  $pdo = get_pdo();
  $raw = file_get_contents('php://input');
  $payload = json_decode($raw, true) ?: [];
  $productId = isset($payload['product_id']) ? (int)$payload['product_id'] : 0;
  $email = trim($payload['email'] ?? '');

  require_admin($pdo, $email);

  if ($productId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid product_id']);
    exit;
  }

  $stmt = $pdo->prepare("UPDATE products SET approval_status = 'rejected', is_active = 0 WHERE id = ?");
  $stmt->execute([$productId]);

  echo json_encode(['success' => true, 'product_id' => $productId, 'status' => 'rejected']);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

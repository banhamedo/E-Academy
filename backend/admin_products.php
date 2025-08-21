<?php
require __DIR__ . '/config.php';
set_cors_headers();
header('Content-Type: application/json');

// Simple helper to verify admin by email via me.php-like query
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
  $pdo = get_pdo();

  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $email = trim($_GET['email'] ?? '');
    require_admin($pdo, $email);

    $status = strtolower(trim($_GET['status'] ?? 'pending'));
    $allowed = ['pending','approved','rejected','all'];
    if (!in_array($status, $allowed)) { $status = 'pending'; }

    $sql = "SELECT id, name, description, price, original_price, category, stock_quantity, discount_percentage, rating, total_reviews, is_active, created_at, seller_name, primary_image, approval_status FROM products";
    $params = [];
    if ($status !== 'all') {
      $sql .= " WHERE approval_status = ?";
      $params[] = $status;
    }
    $sql .= " ORDER BY created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'products' => $products]);
    exit;
  }

  http_response_code(405);
  echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

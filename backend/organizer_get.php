<?php
ini_set('display_errors', '0');
error_reporting(0);
while (ob_get_level()) { ob_end_clean(); }

require __DIR__ . '/config.php';
set_cors_headers();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
  }

  $pdo = get_pdo();

  $email = trim($_GET['email'] ?? '');
  $userId = intval($_GET['user_id'] ?? 0);

  if ($email === '' && !$userId) {
    http_response_code(400);
    echo json_encode(['error' => 'Provide email or user_id']);
    exit;
  }

  if ($email !== '') {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $u = $stmt->fetch();
    if (!$u) {
      http_response_code(404);
      echo json_encode(['error' => 'User not found']);
      exit;
    }
    $userId = intval($u['id']);
  }

  // Ensure organizer table exists
  $pdo->exec("CREATE TABLE IF NOT EXISTS organizer (
    user_id INT UNSIGNED PRIMARY KEY,
    phone VARCHAR(32) NULL,
    location VARCHAR(255) NULL,
    birth_date DATE NULL,
    experience VARCHAR(64) NULL,
    tournaments_organized INT NULL,
    current_organization VARCHAR(255) NULL,
    social_twitter VARCHAR(255) NULL,
    social_linkedin VARCHAR(255) NULL,
    social_instagram VARCHAR(255) NULL,
    social_facebook VARCHAR(255) NULL,
    languages TEXT NULL,
    certifications TEXT NULL,
    specializations TEXT NULL,
    achievements TEXT NULL,
    stats TEXT NULL,
    current_year TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_organizer_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  // Fetch base user
  $stmt = $pdo->prepare('SELECT id, full_name, email, role, avatar_path, cover_path, username, bio, created_at FROM users WHERE id = ? LIMIT 1');
  $stmt->execute([$userId]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$user) {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
    exit;
  }

  // Fetch organizer profile
  $stmt = $pdo->prepare('SELECT * FROM organizer WHERE user_id = ? LIMIT 1');
  $stmt->execute([$userId]);
  $org = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

  echo json_encode([
    'success' => true,
    'user' => $user,
    'organizer' => $org,
  ]);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Server error', 'details' => $e->getMessage()]);
}

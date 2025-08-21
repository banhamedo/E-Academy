<?php
ini_set('display_errors', '0');
error_reporting(0);
while (ob_get_level()) { ob_end_clean(); }

require __DIR__ . '/config.php';
set_cors_headers();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
  }

  $input = json_decode(file_get_contents('php://input'), true);
  if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
  }

  $email = trim($input['email'] ?? '');
  $userId = intval($input['user_id'] ?? 0);

  if ($email === '' && !$userId) {
    http_response_code(400);
    echo json_encode(['error' => 'Provide email or user_id']);
    exit;
  }

  $pdo = get_pdo();

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

  // Optionally update users base fields
  $userUpdates = [];
  $userParams = [];
  if (isset($input['full_name'])) { $userUpdates[] = 'full_name = ?'; $userParams[] = trim((string)$input['full_name']); }
  if (isset($input['username']))  { $userUpdates[] = 'username = ?';  $userParams[] = trim((string)$input['username']); }
  if (isset($input['bio']))       { $userUpdates[] = 'bio = ?';       $userParams[] = (string)$input['bio']; }
  if (isset($input['role']))      { $userUpdates[] = 'role = ?';      $userParams[] = trim((string)$input['role']); }
  if ($userUpdates) {
    $userParams[] = $userId;
    $sql = 'UPDATE users SET ' . implode(', ', $userUpdates) . ' WHERE id = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($userParams);
  }

  // Normalize birth_date if provided
  if (isset($input['birth_date'])) {
    $bd = trim((string)$input['birth_date']);
    if ($bd === '') {
      $input['birth_date'] = null;
    } else {
      $ts = strtotime($bd);
      if ($ts !== false) {
        $input['birth_date'] = date('Y-m-d', $ts);
      } else {
        $input['birth_date'] = null;
      }
    }
  }

  // Upsert into organizer table
  $fields = [
    'phone' => null,
    'location' => null,
    'birth_date' => null,
    'experience' => null,
    'tournaments_organized' => null,
    'current_organization' => null,
    'social_twitter' => null,
    'social_linkedin' => null,
    'social_instagram' => null,
    'social_facebook' => null,
    'languages' => null,
    'certifications' => null,
    'specializations' => null,
    'achievements' => null,
    'stats' => null,
    'current_year' => null,
  ];

  foreach ($fields as $k => $_) {
    if (array_key_exists($k, $input)) {
      if (in_array($k, ['languages','certifications','specializations','achievements','stats','current_year'])) {
        $fields[$k] = json_encode($input[$k] ?? null, JSON_UNESCAPED_UNICODE);
      } else {
        $val = is_string($input[$k]) ? trim((string)$input[$k]) : $input[$k];
        if ($val === '') { $val = null; }
        $fields[$k] = $val;
      }
    }
  }

  // Detect row existence
  $stmt = $pdo->prepare('SELECT user_id FROM organizer WHERE user_id = ? LIMIT 1');
  $stmt->execute([$userId]);
  $exists = (bool)$stmt->fetch();

  if ($exists) {
    $setParts = [];
    $params = [];
    foreach ($fields as $k => $v) { if ($v !== null) { $setParts[] = "$k = ?"; $params[] = $v; } }
    if ($setParts) {
      $params[] = $userId;
      $sql = 'UPDATE organizer SET ' . implode(', ', $setParts) . ' WHERE user_id = ?';
      $stmt = $pdo->prepare($sql);
      $stmt->execute($params);
    }
  } else {
    $columns = ['user_id'];
    $placeholders = ['?'];
    $params = [$userId];
    foreach ($fields as $k => $v) {
      if ($v !== null) { $columns[] = $k; $placeholders[] = '?'; $params[] = $v; }
    }
    $sql = 'INSERT INTO organizer (' . implode(',', $columns) . ') VALUES (' . implode(',', $placeholders) . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
  }

  // Return merged data
  $stmt = $pdo->prepare('SELECT id, full_name, email, role, avatar_path, cover_path, username, bio, created_at FROM users WHERE id = ? LIMIT 1');
  $stmt->execute([$userId]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  $stmt = $pdo->prepare('SELECT * FROM organizer WHERE user_id = ? LIMIT 1');
  $stmt->execute([$userId]);
  $org = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

  echo json_encode([
    'success' => true,
    'message' => 'Organizer profile saved',
    'user' => $user,
    'organizer' => $org,
  ]);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Server error', 'details' => $e->getMessage()]);
}

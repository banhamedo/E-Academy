<?php
ini_set('display_errors', '0');
error_reporting(0);
while (ob_get_level()) { ob_end_clean(); }

require __DIR__ . '/config.php';
// Unified CORS headers
set_cors_headers();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
  }

  $input = json_decode(file_get_contents('php://input'), true);
  if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
  }

  $email = trim($input['email'] ?? '');
  $hasFullName = array_key_exists('full_name', $input);
  $fullName = $hasFullName ? trim((string)$input['full_name']) : null;
  $hasUsername = array_key_exists('username', $input);
  $username = $hasUsername ? trim((string)$input['username']) : null;
  $hasBio = array_key_exists('bio', $input);
  $bio = $hasBio ? (string)$input['bio'] : null; // allow empty string to clear
  $hasUserType = array_key_exists('user_type', $input);
  $userType = $hasUserType ? trim((string)$input['user_type']) : null;

  if ($email === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing email']);
    exit;
  }

  // full_name no longer mandatory; allow partial updates

  $pdo = get_pdo();
  
  // Check if user exists
  $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
  $stmt->execute([$email]);
  $user = $stmt->fetch();
  if (!$user) {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
    exit;
  }

  // Update user profile
  $updateFields = [];
  $params = [];
  
  if ($hasFullName) {
    $updateFields[] = 'full_name = ?';
    $params[] = $fullName; // may be empty to clear
  }
  
  if ($hasUsername) {
    $updateFields[] = 'username = ?';
    $params[] = $username; // may be empty to clear
  }
  
  if ($hasBio) {
    $updateFields[] = 'bio = ?';
    $params[] = $bio; // allow empty string
  }
  
  if ($hasUserType && in_array($userType, ['player', 'coach', 'manager', 'organizer'])) {
    $updateFields[] = 'role = ?';
    $params[] = $userType;
  }

  if (empty($updateFields)) {
    http_response_code(400);
    echo json_encode(['error' => 'No fields to update']);
    exit;
  }

  $params[] = $user['id'];
  $sql = 'UPDATE users SET ' . implode(', ', $updateFields) . ' WHERE id = ?';
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);

  // Get updated user data
  $stmt = $pdo->prepare('SELECT id, full_name, email, role, avatar_path, cover_path, username, bio, created_at FROM users WHERE id = ? LIMIT 1');
  $stmt->execute([$user['id']]);
  $updatedUser = $stmt->fetch();

  // Get stats
  $followersStmt = $pdo->prepare('SELECT COUNT(*) AS count FROM user_follows WHERE following_id = ?');
  $followersStmt->execute([$user['id']]);
  $followersCount = $followersStmt->fetchColumn();

  $followingStmt = $pdo->prepare('SELECT COUNT(*) AS count FROM user_follows WHERE follower_id = ?');
  $followingStmt->execute([$user['id']]);
  $followingCount = $followingStmt->fetchColumn();

  $videosStmt = $pdo->prepare('SELECT COUNT(*) AS count FROM videos WHERE user_id = ?');
  $videosStmt->execute([$user['id']]);
  $videosCount = $videosStmt->fetchColumn();

  echo json_encode([
    'success' => true,
    'message' => 'Profile updated successfully',
    'user' => $updatedUser,
    'stats' => [
      'followers' => (int)$followersCount,
      'following' => (int)$followingCount,
      'videos' => (int)$videosCount,
      'likes' => 0 // TODO: Implement likes count
    ]
  ]);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Server error', 'details' => $e->getMessage()]);
}

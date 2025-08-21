<?php
require __DIR__ . '/config.php';
// Keep only JSON Content-Type; CORS centralized in .htaccess
set_cors_headers();

ini_set('display_errors', '0');
error_reporting(0);
while (ob_get_level()) { ob_end_clean(); }
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
  }

  $email = trim($_GET['email'] ?? '');
  if ($email === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing email']);
    exit;
  }

  $pdo = get_pdo();
  
  // First check if the user exists
  $stmt = $pdo->prepare('SELECT id, full_name, email, role, avatar_path, cover_path, created_at FROM users WHERE email = ? LIMIT 1');
  $stmt->execute([$email]);
  $user = $stmt->fetch();
  if (!$user) {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
    exit;
  }
  
  // Check if username and bio columns exist and get them if they do
  try {
    $stmt = $pdo->prepare('SELECT username, bio FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$user['id']]);
    $additionalData = $stmt->fetch();
    if ($additionalData) {
      $user['username'] = $additionalData['username'] ?? null;
      $user['bio'] = $additionalData['bio'] ?? null;
    }
  } catch (Exception $e) {
    // If columns don't exist, set default values
    $user['username'] = null;
    $user['bio'] = null;
  }

  // Get followers count
  try {
    $followersStmt = $pdo->prepare('SELECT COUNT(*) AS count FROM user_follows WHERE following_id = ?');
    $followersStmt->execute([$user['id']]);
    $followersCount = $followersStmt->fetchColumn();
  } catch (Exception $e) {
    $followersCount = 0;
  }

  // Get following count
  try {
    $followingStmt = $pdo->prepare('SELECT COUNT(*) AS count FROM user_follows WHERE follower_id = ?');
    $followingStmt->execute([$user['id']]);
    $followingCount = $followingStmt->fetchColumn();
  } catch (Exception $e) {
    $followingCount = 0;
  }

  // Get videos count
  try {
    $videosStmt = $pdo->prepare('SELECT COUNT(*) AS count FROM videos WHERE user_id = ?');
    $videosStmt->execute([$user['id']]);
    $videosCount = $videosStmt->fetchColumn();
  } catch (Exception $e) {
    $videosCount = 0;
  }

  // Get user's videos for display
  try {
    $videosStmt = $pdo->prepare('SELECT id, title, file_path, created_at FROM videos WHERE user_id = ? ORDER BY created_at DESC LIMIT 3');
    $videosStmt->execute([$user['id']]);
    $videos = $videosStmt->fetchAll();
  } catch (Exception $e) {
    $videos = [];
  }

  echo json_encode([
    'success' => true,
    'user' => $user,
    'stats' => [
      'followers' => (int)$followersCount,
      'following' => (int)$followingCount,
      'videos' => (int)$videosCount,
      'likes' => 0 // TODO: Implement likes count
    ],
    'videos' => $videos
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Server error', 'details' => $e->getMessage()]);
}



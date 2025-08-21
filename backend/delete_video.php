<?php
ini_set('display_errors', '0');
error_reporting(0);
while (ob_get_level()) { ob_end_clean(); }
set_cors_headers();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require __DIR__ . '/config.php';

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
  }

  $email = trim($_POST['email'] ?? '');
  $videoId = (int)($_POST['video_id'] ?? 0);
  if ($email === '' || $videoId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing fields']);
    exit;
  }

  $pdo = get_pdo();
  $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
  $stmt->execute([$email]);
  $user = $stmt->fetch();
  if (!$user) {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
    exit;
  }

  $q = $pdo->prepare('SELECT id, user_id, file_path, cover_path FROM videos WHERE id = ? AND user_id = ? LIMIT 1');
  $q->execute([$videoId, $user['id']]);
  $video = $q->fetch();
  if (!$video) {
    http_response_code(404);
    echo json_encode(['error' => 'Video not found']);
    exit;
  }

  // Best-effort create tables for relations
  try { $pdo->exec("CREATE TABLE IF NOT EXISTS video_likes (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, video_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX(video_id), INDEX(user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Throwable $e) {}
  try { $pdo->exec("CREATE TABLE IF NOT EXISTS video_comments (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, video_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NOT NULL, content TEXT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX(video_id), INDEX(user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Throwable $e) {}

  // Delete related rows
  try { $pdo->prepare('DELETE FROM video_likes WHERE video_id = ?')->execute([$videoId]); } catch (Throwable $e) {}
  try { $pdo->prepare('DELETE FROM video_comments WHERE video_id = ?')->execute([$videoId]); } catch (Throwable $e) {}

  // Delete files
  $videoFile = __DIR__ . '/' . $video['file_path'];
  if (is_file($videoFile)) { @unlink($videoFile); }
  if (!empty($video['cover_path'])) {
    $coverFile = __DIR__ . '/' . $video['cover_path'];
    if (is_file($coverFile)) { @unlink($coverFile); }
  }

  // Delete video row
  $pdo->prepare('DELETE FROM videos WHERE id = ?')->execute([$videoId]);

  echo json_encode(['success' => true]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Server error', 'details' => $e->getMessage()]);
}




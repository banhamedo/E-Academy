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

  $pdo = get_pdo();

  $pdo->exec("CREATE TABLE IF NOT EXISTS videos (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, file_path VARCHAR(255) NOT NULL, caption TEXT NULL, visibility ENUM('Public','Followers','Private') DEFAULT 'Public', hashtags TEXT NULL, cover_path VARCHAR(255) NULL, is_draft TINYINT(1) DEFAULT 0, allow_comments TINYINT(1) DEFAULT 1, show_on_explore TINYINT(1) DEFAULT 1, share_to_feed TINYINT(1) DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX(user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $userEmail = $_GET['email'] ?? '';
  $userId = null;
  if (!empty($userEmail)) {
      $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
      $stmt->execute([$userEmail]);
      $user = $stmt->fetch();
      if ($user) {
          $userId = $user['id'];
      }
  }
  
  // If no user found, return empty result instead of error
  if ($userId === null) {
      echo json_encode(['success' => true, 'videos' => [], 'stats' => ['videos' => 0]]);
      exit;
  }

  $sql = 'SELECT v.id, v.file_path, v.caption, v.visibility, v.hashtags, v.cover_path, v.is_draft, v.allow_comments, v.show_on_explore, v.share_to_feed, v.created_at, u.full_name AS author_name';
  if ($userId !== null) {
      $sql .= ', EXISTS(SELECT 1 FROM video_likes vl WHERE vl.video_id = v.id AND vl.user_id = :userId) AS is_liked';
  }
  $sql .= ' FROM videos v JOIN users u ON v.user_id = u.id WHERE v.user_id = :filterUserId AND v.is_draft = 0 ORDER BY v.created_at DESC';

  $q = $pdo->prepare($sql);
  if ($userId !== null) {
      $q->bindValue(':userId', $userId, PDO::PARAM_INT);
  }
  $q->bindValue(':filterUserId', $userId, PDO::PARAM_INT);
  $q->execute();
  $rows = $q->fetchAll();

  // Likes and comments count per video (best-effort tables)
  try { $pdo->exec("CREATE TABLE IF NOT EXISTS video_likes (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, video_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX(video_id), INDEX(user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Throwable $e) {}
  try { $pdo->exec("CREATE TABLE IF NOT EXISTS video_comments (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, video_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NOT NULL, content TEXT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX(video_id), INDEX(user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Throwable $e) {}

  $videoIds = array_map(fn($r) => (int)$r['id'], $rows);
  $idList = $videoIds ? implode(',', array_map('intval', $videoIds)) : '0';
  $likesMap = [];
  $commentsMap = [];
  if ($idList !== '0') {
    $likesStmt = $pdo->query("SELECT video_id, COUNT(*) AS c FROM video_likes WHERE video_id IN ($idList) GROUP BY video_id");
    foreach ($likesStmt->fetchAll() as $r) { $likesMap[(int)$r['video_id']] = (int)$r['c']; }
    $commentsStmt = $pdo->query("SELECT video_id, COUNT(*) AS c FROM video_comments WHERE video_id IN ($idList) GROUP BY video_id");
    foreach ($commentsStmt->fetchAll() as $r) { $commentsMap[(int)$r['video_id']] = (int)$r['c']; }
  }

  foreach ($rows as &$row) {
    $vid = (int)$row['id'];
    $row['likes_count'] = $likesMap[$vid] ?? 0;
    $row['comments_count'] = $commentsMap[$vid] ?? 0;
  }

  // Return counts for stats as well
  $countSql = 'SELECT COUNT(*) AS total FROM videos WHERE is_draft = 0';
  $countParams = [];

  if ($userId !== null) {
      $countSql .= ' AND user_id = :userId';
      $countParams[':userId'] = $userId;
  }

  $countStmt = $pdo->prepare($countSql);
  $countStmt->execute($countParams);
  $counts = $countStmt->fetch();

  echo json_encode(['success' => true, 'videos' => $rows, 'stats' => [ 'videos' => (int)($counts['total'] ?? 0) ]]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Server error', 'details' => $e->getMessage()]);
}



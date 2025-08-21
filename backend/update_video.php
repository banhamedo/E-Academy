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
  $caption = trim($_POST['caption'] ?? '');
  $visibility = $_POST['visibility'] ?? 'Public';
  $hashtags = trim($_POST['hashtags'] ?? '');
  $isDraft = isset($_POST['is_draft']) ? (int)!!$_POST['is_draft'] : 0;
  $allowComments = isset($_POST['allow_comments']) ? (int)!!$_POST['allow_comments'] : 1;
  $showOnExplore = isset($_POST['show_on_explore']) ? (int)!!$_POST['show_on_explore'] : 1;
  $shareToFeed = isset($_POST['share_to_feed']) ? (int)!!$_POST['share_to_feed'] : 1;

  if ($email === '' || $videoId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
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

  $updateFields = [
    'caption' => $caption,
    'visibility' => $visibility,
    'hashtags' => $hashtags,
    'is_draft' => $isDraft,
    'allow_comments' => $allowComments,
    'show_on_explore' => $showOnExplore,
    'share_to_feed' => $shareToFeed,
  ];

  $setClauses = [];
  $params = [];
  foreach ($updateFields as $field => $value) {
    $setClauses[] = "`$field` = ?";
    $params[] = $value;
  }

  $params[] = $videoId;
  $params[] = $user['id'];

  $sql = 'UPDATE videos SET ' . implode(', ', $setClauses) . ' WHERE id = ? AND user_id = ?';
  $updateStmt = $pdo->prepare($sql);
  $updateStmt->execute($params);

  if ($updateStmt->rowCount() > 0) {
    echo json_encode(['success' => true]);
  } else {
    http_response_code(404);
    echo json_encode(['error' => 'Video not found or not owned by user']);
  }

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Server error', 'details' => $e->getMessage()]);
}

<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);
while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/json');
// CORS headers will be provided by .htaccess; avoid duplicates

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require __DIR__ . '/config.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $userEmail = trim($input['email'] ?? '');
    $videoId = filter_var($input['video_id'] ?? '', FILTER_VALIDATE_INT);
    $content = trim($input['content'] ?? '');

    if (empty($userEmail) || $videoId === false || empty($content)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing email, invalid video ID, or empty comment content']);
        exit;
    }

    $pdo = get_pdo();

    // Ensure required tables exist (idempotent safety)
    try { $pdo->exec("CREATE TABLE IF NOT EXISTS video_comments (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, video_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NOT NULL, content TEXT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX(video_id), INDEX(user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Throwable $e) {}

    // Get user ID
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$userEmail]);
    $user = $stmt->fetch();
    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        exit;
    }
    $userId = $user['id'];

    // Check if video exists
    $stmt = $pdo->prepare('SELECT id FROM videos WHERE id = ? LIMIT 1');
    $stmt->execute([$videoId]);
    $video = $stmt->fetch();
    if (!$video) {
        http_response_code(404);
        echo json_encode(['error' => 'Video not found']);
        exit;
    }

    // Insert comment
    $ins = $pdo->prepare('INSERT INTO video_comments (user_id, video_id, content) VALUES (?, ?, ?)');
    $ins->execute([$userId, $videoId, $content]);
    $newId = (int)$pdo->lastInsertId();

    // Return the inserted comment with author info
    $BASE = 'http://localhost/eaacademy/purple-green-academy-39-main/backend';
    $stmt = $pdo->prepare('SELECT vc.id, vc.content, vc.created_at, u.full_name AS author_name, u.avatar_path FROM video_comments vc JOIN users u ON vc.user_id = u.id WHERE vc.id = ? LIMIT 1');
    $stmt->execute([$newId]);
    $comment = $stmt->fetch();
    if ($comment) {
        $comment['id'] = (int)$comment['id'];
        $comment['avatar_url'] = !empty($comment['avatar_path']) ? ($BASE . '/' . $comment['avatar_path']) : '/placeholder.svg';
        unset($comment['avatar_path']);
    }

    // Recompute comments count for this video
    $countStmt = $pdo->prepare('SELECT COUNT(*) AS c FROM video_comments WHERE video_id = ?');
    $countStmt->execute([$videoId]);
    $countRow = $countStmt->fetch();
    $newCount = (int)($countRow['c'] ?? 0);

    echo json_encode(['success' => true, 'comment' => $comment, 'new_count' => $newCount]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'details' => $e->getMessage()]);
}

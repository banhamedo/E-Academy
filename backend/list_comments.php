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

    $videoId = filter_var($_GET['video_id'] ?? '', FILTER_VALIDATE_INT);

    if ($videoId === false) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing or invalid video ID']);
        exit;
    }

    $pdo = get_pdo();

    // Fetch comments for the video, joining with users table to get author info
    $stmt = $pdo->prepare('SELECT vc.id, vc.content, vc.created_at, u.full_name AS author_name, u.avatar_path FROM video_comments vc JOIN users u ON vc.user_id = u.id WHERE vc.video_id = ? ORDER BY vc.created_at DESC');
    $stmt->execute([$videoId]);
    $comments = $stmt->fetchAll();

    // Append BASE_URL to avatar_path if it exists
    $BASE = 'http://localhost/eaacademy/purple-green-academy-39-main/backend';
    foreach ($comments as &$comment) {
        if (!empty($comment['avatar_path'])) {
            $comment['avatar_url'] = $BASE . '/' . $comment['avatar_path'];
        } else {
            $comment['avatar_url'] = '/placeholder.svg'; // Default avatar if not set
        }
    }

    echo json_encode(['success' => true, 'comments' => $comments]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'details' => $e->getMessage()]);
}

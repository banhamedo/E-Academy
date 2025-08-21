<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);
while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/json');
// CORS handled globally by .htaccess

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

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

    if (empty($userEmail) || $videoId === false) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing email or invalid video ID']);
        exit;
    }

    $pdo = get_pdo();

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

    // Check if user already liked the video
    $stmt = $pdo->prepare('SELECT id FROM video_likes WHERE user_id = ? AND video_id = ? LIMIT 1');
    $stmt->execute([$userId, $videoId]);
    $like = $stmt->fetch();

    if ($like) {
        // User already liked, so unlike (delete)
        $del = $pdo->prepare('DELETE FROM video_likes WHERE id = ?');
        $del->execute([$like['id']]);
        echo json_encode(['success' => true, 'action' => 'unliked']);
    } else {
        // User hasn't liked, so like (insert)
        $ins = $pdo->prepare('INSERT INTO video_likes (user_id, video_id) VALUES (?, ?)');
        $ins->execute([$userId, $videoId]);
        echo json_encode(['success' => true, 'action' => 'liked']);
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'details' => $e->getMessage()]);
}

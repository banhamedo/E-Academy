<?php
ini_set('display_errors', '0');
error_reporting(0);
while (ob_get_level()) { ob_end_clean(); }
hab_end_clean();
set_cors_headers();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require __DIR__ . '/config.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $followerEmail = trim($input['follower_email'] ?? '');
    $followedUserId = filter_var($input['followed_user_id'] ?? '', FILTER_VALIDATE_INT);

    if (empty($followerEmail) || $followedUserId === false) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing follower email or invalid followed user ID']);
        exit;
    }

    $pdo = get_pdo();

    // Get follower user ID
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$followerEmail]);
    $follower = $stmt->fetch();
    if (!$follower) {
        http_response_code(404);
        echo json_encode(['error' => 'Follower user not found']);
        exit;
    }
    $followerId = $follower['id'];

    // Prevent following self
    if ($followerId === $followedUserId) {
        http_response_code(400);
        echo json_encode(['error' => 'Cannot follow yourself']);
        exit;
    }

    // Check if followed user exists
    $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$followedUserId]);
    $followed = $stmt->fetch();
    if (!$followed) {
        http_response_code(404);
        echo json_encode(['error' => 'User to follow not found']);
        exit;
    }

    // Check if already following
    $stmt = $pdo->prepare('SELECT id FROM user_follows WHERE follower_id = ? AND followed_id = ? LIMIT 1');
    $stmt->execute([$followerId, $followedUserId]);
    $follow = $stmt->fetch();

    if ($follow) {
        // Already following, so unfollow (delete)
        $del = $pdo->prepare('DELETE FROM user_follows WHERE id = ?');
        $del->execute([$follow['id']]);
        echo json_encode(['success' => true, 'action' => 'unfollowed']);
    } else {
        // Not following, so follow (insert)
        $ins = $pdo->prepare('INSERT INTO user_follows (follower_id, followed_id) VALUES (?, ?)');
        $ins->execute([$followerId, $followedUserId]);
        echo json_encode(['success' => true, 'action' => 'followed']);
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'details' => $e->getMessage()]);
}

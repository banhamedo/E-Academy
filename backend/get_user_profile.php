<?php
require __DIR__ . '/config.php';

// Keep only JSON Content-Type; CORS centralized in .htaccess
set_cors_headers();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $pdo = get_pdo();
    $userId = intval($_GET['user_id'] ?? 0);
    
    if (!$userId) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing user_id']);
        exit;
    }
    
    // Get user profile
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.full_name,
            u.email,
            u.avatar_path,
            u.location,
            u.created_at,
            u.is_online,
            u.last_seen
        FROM users u
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        exit;
    }
    
    // Get user stats
    $videoCount = $pdo->prepare("SELECT COUNT(*) as count FROM videos WHERE user_id = ?");
    $videoCount->execute([$userId]);
    $videos = $videoCount->fetch(PDO::FETCH_ASSOC)['count'];
    
    $friendsCount = $pdo->prepare("SELECT COUNT(*) as count FROM friends WHERE user_id1 = ? OR user_id2 = ?");
    $friendsCount->execute([$userId, $userId]);
    $friends = $friendsCount->fetch(PDO::FETCH_ASSOC)['count'];
    
    $likesCount = $pdo->prepare("SELECT COALESCE(SUM(likes_count), 0) as count FROM videos WHERE user_id = ?");
    $likesCount->execute([$userId]);
    $likes = $likesCount->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo json_encode([
        'success' => true,
        'user' => $user,
        'stats' => [
            'videos' => (int)$videos,
            'followers' => 0,
            'following' => (int)$friends,
            'likes' => (int)$likes
        ],
        'achievements' => []
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>

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
    
    // Get user videos
    $stmt = $pdo->prepare("
        SELECT 
            v.id,
            v.title,
            v.description,
            v.video_path,
            v.cover_path,
            v.likes_count,
            v.created_at,
            COALESCE((SELECT COUNT(*) FROM video_comments WHERE video_id = v.id), 0) as comments_count
        FROM videos v
        WHERE v.user_id = ?
        ORDER BY v.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$userId]);
    $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'videos' => $videos
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>

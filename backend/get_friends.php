<?php
require __DIR__ . '/config.php';
// Keep only JSON Content-Type; CORS centralized in .htaccess
set_cors_headers();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$email = $_GET['email'] ?? '';

if (!$email) {
    echo json_encode(['success' => false, 'error' => 'Email is required']);
    exit;
}

try {
    $pdo = get_pdo();
    
    // Get user ID from email
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }
    
    $userId = $user['id'];
    
    // Get all friends
    $stmt = $pdo->prepare('
        SELECT u.id, u.full_name, u.email, u.role, u.avatar_path, u.bio, 
               u.location, u.is_online, u.last_seen, u.created_at,
               COUNT(v.id) as videos_count,
               (SELECT COUNT(*) FROM user_follows WHERE following_id = u.id) as followers_count,
               (SELECT COUNT(*) FROM friends f2 WHERE (f2.user_id1 = u.id OR f2.user_id2 = u.id)) as friends_count
        FROM users u
        INNER JOIN friends f ON (f.user_id1 = ? AND f.user_id2 = u.id) OR (f.user_id2 = ? AND f.user_id1 = u.id)
        LEFT JOIN videos v ON v.user_id = u.id
        WHERE u.id != ?
        GROUP BY u.id, u.full_name, u.email, u.role, u.avatar_path, u.bio, u.location, u.is_online, u.last_seen, u.created_at
        ORDER BY u.is_online DESC, u.last_seen DESC
    ');
    $stmt->execute([$userId, $userId, $userId]);
    $friends = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true, 
        'friends' => $friends,
        'count' => count($friends)
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>

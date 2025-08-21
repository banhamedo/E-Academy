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
    $currentUserId = intval($_GET['current_user_id'] ?? 0);
    
    if (!$currentUserId) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing current_user_id']);
        exit;
    }
    
    // Get only friends of current user
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.full_name,
            u.email,
            u.avatar_path,
            COALESCE(u.is_online, 0) as is_online,
            u.last_seen,
            'صديق' as user_type,
            u.created_at,
            'friends' as friend_status,
            COALESCE((SELECT COUNT(*) FROM chat_messages cm 
             WHERE (cm.sender_id = u.id AND cm.receiver_id = ?) 
                OR (cm.sender_id = ? AND cm.receiver_id = u.id)), 0) as message_count
        FROM users u
        INNER JOIN friends f ON (f.user_id1 = ? AND f.user_id2 = u.id) OR (f.user_id2 = ? AND f.user_id1 = u.id)
        WHERE u.id != ? AND u.full_name IS NOT NULL AND u.full_name != ''
        ORDER BY u.full_name
        LIMIT 50
    ");
    
    $stmt->execute([
        $currentUserId, $currentUserId, $currentUserId, $currentUserId, $currentUserId
    ]);
    
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Log the raw users data
    error_log("Raw users data: " . json_encode($users));
    
    // Format the response
    $formattedUsers = array_map(function($user) {
        return [
            'id' => (string)$user['id'],
            'name' => $user['full_name'] ?: 'مستخدم غير معروف',
            'email' => $user['email'] ?: '',
            'avatar' => $user['avatar_path'] ? 'http://localhost/eaacademy/purple-green-academy-39-main/backend/' . $user['avatar_path'] : null,
            'isOnline' => (bool)$user['is_online'],
            'lastSeen' => $user['last_seen'],
            'userType' => $user['user_type'] ?: 'مستخدم',
            'joinedDate' => $user['created_at'],
            'friendStatus' => $user['friend_status'] ?: 'none',
            'messageCount' => (int)$user['message_count']
        ];
    }, $users);
    
    // Debug: Log the formatted users data
    error_log("Formatted users data: " . json_encode($formattedUsers));
    
    echo json_encode(['success' => true, 'users' => $formattedUsers, 'debug' => ['total_found' => count($users)]]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>

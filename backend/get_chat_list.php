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
    
    // Get chat list with last message for each conversation
    $stmt = $pdo->prepare("
        SELECT DISTINCT
            CASE 
                WHEN cm.sender_id = ? THEN cm.receiver_id 
                ELSE cm.sender_id 
            END as friend_id,
            u.full_name as friend_name,
            u.avatar_path as friend_avatar,
            u.is_online,
            u.last_seen,
            (SELECT message FROM chat_messages cm2 
             WHERE (cm2.sender_id = ? AND cm2.receiver_id = CASE WHEN cm.sender_id = ? THEN cm.receiver_id ELSE cm.sender_id END)
                OR (cm2.receiver_id = ? AND cm2.sender_id = CASE WHEN cm.sender_id = ? THEN cm.receiver_id ELSE cm.sender_id END)
             ORDER BY cm2.created_at DESC LIMIT 1) as last_message,
            (SELECT created_at FROM chat_messages cm2 
             WHERE (cm2.sender_id = ? AND cm2.receiver_id = CASE WHEN cm.sender_id = ? THEN cm.receiver_id ELSE cm.sender_id END)
                OR (cm2.receiver_id = ? AND cm2.sender_id = CASE WHEN cm.sender_id = ? THEN cm.receiver_id ELSE cm.sender_id END)
             ORDER BY cm2.created_at DESC LIMIT 1) as last_message_time,
            (SELECT sender_id FROM chat_messages cm2 
             WHERE (cm2.sender_id = ? AND cm2.receiver_id = CASE WHEN cm.sender_id = ? THEN cm.receiver_id ELSE cm.sender_id END)
                OR (cm2.receiver_id = ? AND cm2.sender_id = CASE WHEN cm.sender_id = ? THEN cm.receiver_id ELSE cm.sender_id END)
             ORDER BY cm2.created_at DESC LIMIT 1) as last_sender_id,
            (SELECT COUNT(*) FROM chat_messages cm3 
             WHERE cm3.sender_id = CASE WHEN cm.sender_id = ? THEN cm.receiver_id ELSE cm.sender_id END
               AND cm3.receiver_id = ? 
               AND cm3.is_read = FALSE) as unread_count
        FROM chat_messages cm
        JOIN users u ON u.id = CASE WHEN cm.sender_id = ? THEN cm.receiver_id ELSE cm.sender_id END
        WHERE cm.sender_id = ? OR cm.receiver_id = ?
        ORDER BY last_message_time DESC
    ");
    
    $stmt->execute([
        $userId, $userId, $userId, $userId, $userId, 
        $userId, $userId, $userId, $userId, 
        $userId, $userId, $userId, $userId,
        $userId, $userId, $userId, $userId, $userId
    ]);
    
    $chats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'chats' => $chats]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>

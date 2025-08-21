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
    
    // Create chat_messages table if it doesn't exist
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS chat_messages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            sender_id INT UNSIGNED NOT NULL,
            receiver_id INT UNSIGNED NOT NULL,
            message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            is_read BOOLEAN DEFAULT FALSE,
            INDEX idx_sender (sender_id),
            INDEX idx_receiver (receiver_id),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {
        // Table creation failed, continue without foreign keys
        error_log("Chat messages table creation warning: " . $e->getMessage());
    }
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        // Get messages between two users
        $senderId = intval($_GET['sender_id'] ?? 0);
        $receiverId = intval($_GET['receiver_id'] ?? 0);
        
        if (!$senderId || !$receiverId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing sender_id or receiver_id']);
            exit;
        }
        
        // Get messages between the two users
        $stmt = $pdo->prepare("
            SELECT cm.*, 
                   COALESCE(u1.full_name, 'مستخدم') as sender_name, 
                   u1.avatar_path as sender_avatar,
                   COALESCE(u2.full_name, 'مستخدم') as receiver_name, 
                   u2.avatar_path as receiver_avatar
            FROM chat_messages cm
            LEFT JOIN users u1 ON cm.sender_id = u1.id
            LEFT JOIN users u2 ON cm.receiver_id = u2.id
            WHERE (cm.sender_id = ? AND cm.receiver_id = ?) 
               OR (cm.sender_id = ? AND cm.receiver_id = ?)
            ORDER BY cm.created_at ASC
        ");
        $stmt->execute([$senderId, $receiverId, $receiverId, $senderId]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Mark messages as read for the current user
        $updateStmt = $pdo->prepare("
            UPDATE chat_messages 
            SET is_read = TRUE 
            WHERE receiver_id = ? AND sender_id = ? AND is_read = FALSE
        ");
        $updateStmt->execute([$senderId, $receiverId]);
        
        echo json_encode(['success' => true, 'messages' => $messages]);
        
    } elseif ($method === 'POST') {
        // Send a new message
        $input = json_decode(file_get_contents('php://input'), true);
        
        $senderId = intval($input['sender_id'] ?? 0);
        $receiverId = intval($input['receiver_id'] ?? 0);
        $message = trim($input['message'] ?? '');
        
        if (!$senderId || !$receiverId || !$message) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            exit;
        }
        
        // Insert new message
        $stmt = $pdo->prepare("
            INSERT INTO chat_messages (sender_id, receiver_id, message) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$senderId, $receiverId, $message]);
        
        $messageId = $pdo->lastInsertId();
        
        // Get the inserted message with user details
        $getStmt = $pdo->prepare("
            SELECT cm.*, 
                   COALESCE(u1.full_name, 'مستخدم') as sender_name, 
                   u1.avatar_path as sender_avatar,
                   COALESCE(u2.full_name, 'مستخدم') as receiver_name, 
                   u2.avatar_path as receiver_avatar
            FROM chat_messages cm
            LEFT JOIN users u1 ON cm.sender_id = u1.id
            LEFT JOIN users u2 ON cm.receiver_id = u2.id
            WHERE cm.id = ?
        ");
        $getStmt->execute([$messageId]);
        $newMessage = $getStmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'message' => $newMessage]);
        
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>

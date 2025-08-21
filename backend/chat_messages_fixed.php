<?php
// Disable error reporting to prevent any output before headers
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/config.php';
// Keep only JSON Content-Type; CORS centralized in .htaccess
set_cors_headers();

// Handle OPTIONS request immediately
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// config already included above

try {
    $pdo = get_pdo();
    
    // Create table without foreign keys to avoid errors
    $pdo->exec("CREATE TABLE IF NOT EXISTS chat_messages (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        sender_id INT UNSIGNED NOT NULL,
        receiver_id INT UNSIGNED NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_read BOOLEAN DEFAULT FALSE,
        INDEX idx_sender_receiver (sender_id, receiver_id),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Send message
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['sender_id']) || !isset($input['receiver_id']) || !isset($input['message'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            exit();
        }
        
        $senderId = intval($input['sender_id']);
        $receiverId = intval($input['receiver_id']);
        $message = trim($input['message']);
        
        if (empty($message)) {
            http_response_code(400);
            echo json_encode(['error' => 'Message cannot be empty']);
            exit();
        }
        
        $stmt = $pdo->prepare("INSERT INTO chat_messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        $result = $stmt->execute([$senderId, $receiverId, $message]);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message_id' => $pdo->lastInsertId(),
                'message' => 'Message sent successfully'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to send message']);
        }
        
    } else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get messages
        $senderId = intval($_GET['sender_id'] ?? 0);
        $receiverId = intval($_GET['receiver_id'] ?? 0);
        
        if (!$senderId || !$receiverId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing sender_id or receiver_id']);
            exit();
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                cm.id,
                cm.sender_id,
                cm.receiver_id,
                cm.message,
                cm.created_at,
                cm.is_read,
                COALESCE(u1.full_name, 'Unknown User') as sender_name,
                COALESCE(u2.full_name, 'Unknown User') as receiver_name
            FROM chat_messages cm
            LEFT JOIN users u1 ON cm.sender_id = u1.id
            LEFT JOIN users u2 ON cm.receiver_id = u2.id
            WHERE (cm.sender_id = ? AND cm.receiver_id = ?) 
               OR (cm.sender_id = ? AND cm.receiver_id = ?)
            ORDER BY cm.created_at ASC
        ");
        
        $stmt->execute([$senderId, $receiverId, $receiverId, $senderId]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'messages' => $messages
        ]);
        
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>

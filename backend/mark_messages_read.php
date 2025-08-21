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
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = intval($input['user_id'] ?? 0);
    $senderId = intval($input['sender_id'] ?? 0);
    
    if (!$userId || !$senderId) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing user_id or sender_id']);
        exit;
    }
    
    // Mark all messages from sender to user as read
    $stmt = $pdo->prepare("
        UPDATE chat_messages 
        SET is_read = TRUE 
        WHERE sender_id = ? AND receiver_id = ? AND is_read = FALSE
    ");
    
    $stmt->execute([$senderId, $userId]);
    $affectedRows = $stmt->rowCount();
    
    echo json_encode([
        'success' => true, 
        'marked_read' => $affectedRows,
        'message' => "Marked $affectedRows messages as read"
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>

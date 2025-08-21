<?php
require __DIR__ . '/config.php';
set_cors_headers();

$email = $_POST['email'] ?? '';
$requestId = intval($_POST['request_id'] ?? 0);

if (!$email || !$requestId) {
    echo json_encode(['success' => false, 'error' => 'Email and request ID are required']);
    exit;
}

try {
    $pdo = get_pdo();
    $pdo->beginTransaction();
    
    // Get user ID from email
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }
    
    $userId = $user['id'];
    
    // Get the friend request
    $stmt = $pdo->prepare('SELECT from_user_id, to_user_id FROM friend_requests WHERE id = ? AND to_user_id = ? AND status = "pending"');
    $stmt->execute([$requestId, $userId]);
    $request = $stmt->fetch();
    
    if (!$request) {
        echo json_encode(['success' => false, 'error' => 'Friend request not found or already processed']);
        exit;
    }
    
    $fromUserId = $request['from_user_id'];
    $toUserId = $request['to_user_id'];
    
    // Update request status to accepted
    $stmt = $pdo->prepare('UPDATE friend_requests SET status = "accepted", updated_at = CURRENT_TIMESTAMP WHERE id = ?');
    $stmt->execute([$requestId]);
    
    // Add to friends table (bidirectional)
    $stmt = $pdo->prepare('INSERT INTO friends (user_id1, user_id2) VALUES (?, ?)');
    $stmt->execute([min($fromUserId, $toUserId), max($fromUserId, $toUserId)]);
    
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Friend request accepted successfully']);
    
} catch (Exception $e) {
    $pdo->rollback();
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>

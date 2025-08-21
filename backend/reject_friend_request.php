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
    
    // Get user ID from email
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }
    
    $userId = $user['id'];
    
    // Update request status to rejected
    $stmt = $pdo->prepare('UPDATE friend_requests SET status = "rejected", updated_at = CURRENT_TIMESTAMP WHERE id = ? AND to_user_id = ? AND status = "pending"');
    $result = $stmt->execute([$requestId, $userId]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Friend request rejected successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Friend request not found or already processed']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>

<?php
require __DIR__ . '/config.php';
set_cors_headers();

$email = $_POST['email'] ?? '';
$friendId = intval($_POST['friend_id'] ?? 0);

if (!$email || !$friendId) {
    echo json_encode(['success' => false, 'error' => 'Email and friend ID are required']);
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
    
    // Remove friendship (bidirectional)
    $stmt = $pdo->prepare('DELETE FROM friends WHERE (user_id1 = ? AND user_id2 = ?) OR (user_id1 = ? AND user_id2 = ?)');
    $result = $stmt->execute([$userId, $friendId, $friendId, $userId]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Friend removed successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Friendship not found']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>

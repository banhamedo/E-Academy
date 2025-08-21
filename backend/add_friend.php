<?php
require __DIR__ . '/config.php';
set_cors_headers();

// Handle both user_id and email based requests
$fromEmail = $_POST['from_email'] ?? '';
$fromUserId = intval($_POST['from_user_id'] ?? 0);
$toUserId = intval($_POST['to_user_id'] ?? 0);
$message = $_POST['message'] ?? '';

try {
    $pdo = get_pdo();
    
    // If email provided, get user ID
    if ($fromEmail && !$fromUserId) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$fromEmail]);
        $user = $stmt->fetch();
        if (!$user) {
            echo json_encode(['success' => false, 'error' => 'User not found']);
            exit;
        }
        $fromUserId = $user['id'];
    }
    
    if (!$fromUserId || !$toUserId || $fromUserId === $toUserId) {
        echo json_encode(['success' => false, 'error' => 'Invalid user IDs']);
        exit;
    }
    
    // Check if already friends
    $stmt = $pdo->prepare('SELECT * FROM friends WHERE (user_id1 = ? AND user_id2 = ?) OR (user_id1 = ? AND user_id2 = ?)');
    $stmt->execute([$fromUserId, $toUserId, $toUserId, $fromUserId]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Already friends']);
        exit;
    }
    
    // Check if request already exists
    $stmt = $pdo->prepare('SELECT * FROM friend_requests WHERE from_user_id = ? AND to_user_id = ? AND status = "pending"');
    $stmt->execute([$fromUserId, $toUserId]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Request already sent']);
        exit;
    }
    
    // Insert friend request
    $stmt = $pdo->prepare('INSERT INTO friend_requests (from_user_id, to_user_id, message) VALUES (?, ?, ?)');
    if ($stmt->execute([$fromUserId, $toUserId, $message])) {
        echo json_encode(['success' => true, 'message' => 'Friend request sent successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

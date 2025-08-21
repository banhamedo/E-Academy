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
    
    // Get pending friend requests sent to this user
    $stmt = $pdo->prepare('
        SELECT fr.id, fr.from_user_id, fr.message, fr.created_at, fr.status,
               u.full_name as from_name, u.avatar_path as from_avatar, u.role as from_role
        FROM friend_requests fr
        INNER JOIN users u ON fr.from_user_id = u.id
        WHERE fr.to_user_id = ? AND fr.status = "pending"
        ORDER BY fr.created_at DESC
    ');
    $stmt->execute([$userId]);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true, 
        'requests' => $requests,
        'count' => count($requests)
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>

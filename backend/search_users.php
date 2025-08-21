<?php
require __DIR__ . '/config.php';
set_cors_headers();

$email = $_GET['email'] ?? '';
$query = $_GET['query'] ?? '';

if (!$email || !$query) {
    echo json_encode(['success' => false, 'error' => 'Email and query are required']);
    exit;
}

try {
    $pdo = get_pdo();
    
    // Get current user ID
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $currentUser = $stmt->fetch();
    
    if (!$currentUser) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }
    
    $currentUserId = $currentUser['id'];
    
    // Search for users (excluding current user and existing friends)
    $searchTerm = '%' . $query . '%';
    $stmt = $pdo->prepare('
        SELECT u.id, u.full_name, u.email, u.role, u.avatar_path, u.bio, u.location
        FROM users u
        WHERE u.id != ? 
        AND (u.full_name LIKE ? OR u.email LIKE ?)
        AND u.id NOT IN (
            SELECT CASE 
                WHEN f.user_id1 = ? THEN f.user_id2 
                ELSE f.user_id1 
            END
            FROM friends f 
            WHERE f.user_id1 = ? OR f.user_id2 = ?
        )
        AND u.id NOT IN (
            SELECT fr.to_user_id 
            FROM friend_requests fr 
            WHERE fr.from_user_id = ? AND fr.status = "pending"
        )
        LIMIT 20
    ');
    $stmt->execute([
        $currentUserId, $searchTerm, $searchTerm, 
        $currentUserId, $currentUserId, $currentUserId, 
        $currentUserId
    ]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true, 
        'users' => $users,
        'count' => count($users)
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>

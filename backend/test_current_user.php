<?php
require __DIR__ . '/config.php';
set_cors_headers();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $pdo = get_pdo();
    
    // Get current user info from localStorage simulation
    $userEmail = $_GET['email'] ?? '';
    $userId = $_GET['user_id'] ?? '';
    
    $response = [
        'received_email' => $userEmail,
        'received_user_id' => $userId
    ];
    
    if ($userEmail) {
        // Get user by email
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$userEmail]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $response['user_by_email'] = $user;
    }
    
    if ($userId) {
        // Get user by ID
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $response['user_by_id'] = $user;
    }
    
    // Get all users count
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $response['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get sample users
    $stmt = $pdo->query("SELECT id, full_name, email FROM users LIMIT 5");
    $response['sample_users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $response]);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Test error: ' . $e->getMessage()]);
}
?>

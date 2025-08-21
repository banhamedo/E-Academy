<?php
require __DIR__ . '/config.php';
set_cors_headers();
header('Content-Type: application/json');

try {
    $pdo = get_pdo();
    
    // Check if chat_messages table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'chat_messages'");
    $chatTableExists = $stmt->rowCount() > 0;
    
    // Check users table
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    $usersTableExists = $stmt->rowCount() > 0;
    
    $response = [
        'chat_table_exists' => $chatTableExists,
        'users_table_exists' => $usersTableExists
    ];
    
    if ($chatTableExists) {
        // Get chat messages count
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM chat_messages");
        $response['chat_messages_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Get sample messages
        $stmt = $pdo->query("SELECT * FROM chat_messages ORDER BY created_at DESC LIMIT 3");
        $response['sample_messages'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    if ($usersTableExists) {
        // Get users count
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE full_name IS NOT NULL AND full_name != ''");
        $response['users_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Get sample users
        $stmt = $pdo->query("SELECT id, full_name, email FROM users WHERE full_name IS NOT NULL AND full_name != '' LIMIT 3");
        $response['sample_users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode(['success' => true, 'debug_info' => $response]);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Debug error: ' . $e->getMessage()]);
}
?>

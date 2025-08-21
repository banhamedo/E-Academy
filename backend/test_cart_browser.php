<?php
require 'config.php';
// Keep only JSON Content-Type; CORS centralized in .htaccess
set_cors_headers();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $pdo = get_pdo();
    
    // Get user ID from query parameter
    $userId = intval($_GET['user_id'] ?? 4); // Default to user 4 for testing
    
    $stmt = $pdo->prepare("
        SELECT c.id, c.quantity, c.created_at,
               p.id as product_id, p.name, p.description, p.price, p.original_price, 
               p.image_url, p.stock_quantity, p.discount_percentage,
               p.rating, p.total_reviews, p.category
        FROM cart c
        JOIN products p ON c.product_id = p.id
        WHERE c.user_id = ?
        ORDER BY c.created_at DESC
    ");
    
    $stmt->execute([$userId]);
    $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'cart_items' => $cartItems,
        'user_id' => $userId,
        'count' => count($cartItems)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

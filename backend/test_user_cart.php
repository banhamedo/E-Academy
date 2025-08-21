<?php
require 'config.php';

// Test with different user IDs
$testUserIds = [1, 2, 3, 4, 5];

foreach ($testUserIds as $userId) {
    echo "Testing user ID: $userId\n";
    
    try {
        $pdo = get_pdo();
        
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
        
        echo "Cart items found: " . count($cartItems) . "\n";
        
        if (count($cartItems) > 0) {
            echo "First item: " . $cartItems[0]['name'] . " x" . $cartItems[0]['quantity'] . "\n";
        }
        
        echo "---\n";
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
?>

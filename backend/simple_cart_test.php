<?php
require 'config.php';

echo "Testing cart API directly...\n";

try {
    $pdo = get_pdo();
    
    // Test the exact query from cart_api.php
    $userId = 4;
    
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
        echo "First item:\n";
        print_r($cartItems[0]);
        
        // Test JSON response
        $response = [
            'success' => true,
            'cart_items' => $cartItems
        ];
        
        $json = json_encode($response, JSON_UNESCAPED_UNICODE);
        echo "\nJSON Response:\n";
        echo $json;
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>

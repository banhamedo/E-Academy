<?php
require 'config.php';

echo "Testing Cart API...\n";

try {
    $pdo = get_pdo();
    
    // Test the cart query
    $userId = 4; // The user ID we saw in the debug
    
    echo "Testing cart query for user ID: $userId\n";
    
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
        echo "First cart item:\n";
        print_r($cartItems[0]);
        
        // Test JSON encoding
        $json = json_encode([
            'success' => true,
            'cart_items' => $cartItems
        ]);
        
        if ($json === false) {
            echo "JSON encoding error: " . json_last_error_msg() . "\n";
        } else {
            echo "JSON encoding successful\n";
            echo "JSON length: " . strlen($json) . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}

echo "Test complete\n";
?>

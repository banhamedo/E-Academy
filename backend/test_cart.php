<?php
require 'config.php';

// Test database connection
try {
    $pdo = get_pdo();
    echo "Database connection successful\n";
    
    // Check if tables exist
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables found: " . implode(', ', $tables) . "\n";
    
    // Check cart table structure
    if (in_array('cart', $tables)) {
        $stmt = $pdo->query("DESCRIBE cart");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Cart table columns:\n";
        foreach ($columns as $col) {
            echo "- " . $col['Field'] . " (" . $col['Type'] . ")\n";
        }
        
        // Check cart items
        $stmt = $pdo->query("SELECT * FROM cart");
        $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Cart items count: " . count($cartItems) . "\n";
        if (count($cartItems) > 0) {
            echo "First cart item:\n";
            print_r($cartItems[0]);
        }
    }
    
    // Check products table
    if (in_array('products', $tables)) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM products");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "Products count: " . $result['count'] . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>

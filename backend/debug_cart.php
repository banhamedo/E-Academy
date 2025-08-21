<?php
require 'config.php';

echo "Starting debug...\n";

try {
    $pdo = get_pdo();
    echo "Database connection successful\n";
    
    // Check tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables: " . implode(', ', $tables) . "\n";
    
    // Check cart table
    if (in_array('cart', $tables)) {
        echo "Cart table exists\n";
        
        // Check cart structure
        $stmt = $pdo->query("DESCRIBE cart");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Cart columns:\n";
        foreach ($columns as $col) {
            echo "- " . $col['Field'] . "\n";
        }
        
        // Check cart data
        $stmt = $pdo->query("SELECT * FROM cart");
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Cart items: " . count($items) . "\n";
        
        if (count($items) > 0) {
            echo "First item:\n";
            print_r($items[0]);
        }
    } else {
        echo "Cart table does not exist\n";
    }
    
    // Check products table
    if (in_array('products', $tables)) {
        echo "Products table exists\n";
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM products");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "Products count: " . $result['count'] . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}

echo "Debug complete\n";
?>

<?php
require 'config.php';

try {
    $pdo = get_pdo();
    
    echo "Checking products table structure...\n";
    
    // Check table structure
    $stmt = $pdo->query("DESCRIBE products");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Products table columns:\n";
    foreach ($columns as $col) {
        echo "- " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
    
    // Check sample data
    $stmt = $pdo->query("SELECT * FROM products LIMIT 1");
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($product) {
        echo "\nSample product:\n";
        print_r($product);
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>

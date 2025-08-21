<?php
require __DIR__ . '/config.php';

// Keep only JSON Content-Type; CORS centralized in .htaccess
set_cors_headers();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $pdo = get_pdo();
    
    // Create products table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS products (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            price DECIMAL(10,2) NOT NULL,
            original_price DECIMAL(10,2) DEFAULT NULL,
            category VARCHAR(100) NOT NULL,
            stock_quantity INT NOT NULL DEFAULT 0,
            discount_percentage INT DEFAULT 0,
            rating DECIMAL(3,2) DEFAULT 0.00,
            total_reviews INT DEFAULT 0,
            seller_id INT UNSIGNED NOT NULL,
            primary_image VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_category (category),
            INDEX idx_seller (seller_id),
            INDEX idx_price (price)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Create cart table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS cart (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            product_id INT UNSIGNED NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_product (user_id, product_id),
            INDEX idx_user (user_id),
            INDEX idx_product (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Create orders table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS orders (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            order_number VARCHAR(50) UNIQUE NOT NULL,
            total_amount DECIMAL(10,2) NOT NULL,
            status ENUM('pending', 'confirmed', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
            payment_method ENUM('card', 'cash') NOT NULL,
            customer_name VARCHAR(255) NOT NULL,
            customer_email VARCHAR(255) NOT NULL,
            customer_phone VARCHAR(50) NOT NULL,
            customer_address TEXT NOT NULL,
            customer_city VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_status (status),
            INDEX idx_order_number (order_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Create order_items table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS order_items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id INT UNSIGNED NOT NULL,
            product_id INT UNSIGNED NOT NULL,
            product_name VARCHAR(255) NOT NULL,
            product_price DECIMAL(10,2) NOT NULL,
            quantity INT NOT NULL,
            total_price DECIMAL(10,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_order (order_id),
            INDEX idx_product (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Create product_likes table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS product_likes (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            product_id INT UNSIGNED NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_product_like (user_id, product_id),
            INDEX idx_user (user_id),
            INDEX idx_product (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Insert sample products if table is empty
    $productCount = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    
    if ($productCount == 0) {
        $sampleProducts = [
            [
                'name' => 'كرة قدم احترافية',
                'description' => 'كرة قدم عالية الجودة للمباريات الاحترافية',
                'price' => 299.99,
                'original_price' => 399.99,
                'category' => 'معدات رياضية',
                'stock_quantity' => 50,
                'discount_percentage' => 25,
                'rating' => 4.8,
                'total_reviews' => 120,
                'seller_id' => 1
            ],
            [
                'name' => 'ملابس رياضية للتدريب',
                'description' => 'ملابس رياضية مريحة ومناسبة للتدريب اليومي',
                'price' => 199.99,
                'original_price' => NULL,
                'category' => 'ملابس رياضية',
                'stock_quantity' => 100,
                'discount_percentage' => 0,
                'rating' => 4.5,
                'total_reviews' => 85,
                'seller_id' => 1
            ],
            [
                'name' => 'ساعة رياضية ذكية',
                'description' => 'ساعة رياضية متطورة لتتبع النشاط البدني',
                'price' => 899.99,
                'original_price' => 999.99,
                'category' => 'أجهزة إلكترونية',
                'stock_quantity' => 25,
                'discount_percentage' => 10,
                'rating' => 4.9,
                'total_reviews' => 200,
                'seller_id' => 1
            ],
            [
                'name' => 'كتاب تدريبات كرة القدم',
                'description' => 'كتاب شامل يحتوي على تمارين وتقنيات كرة القدم',
                'price' => 89.99,
                'original_price' => NULL,
                'category' => 'كتب ومراجع',
                'stock_quantity' => 75,
                'discount_percentage' => 0,
                'rating' => 4.6,
                'total_reviews' => 45,
                'seller_id' => 1
            ]
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO products (name, description, price, original_price, category, stock_quantity, discount_percentage, rating, total_reviews, seller_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($sampleProducts as $product) {
            $stmt->execute([
                $product['name'],
                $product['description'],
                $product['price'],
                $product['original_price'],
                $product['category'],
                $product['stock_quantity'],
                $product['discount_percentage'],
                $product['rating'],
                $product['total_reviews'],
                $product['seller_id']
            ]);
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Marketplace tables created successfully',
        'products_count' => $productCount
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database creation failed: ' . $e->getMessage()
    ]);
}
?>

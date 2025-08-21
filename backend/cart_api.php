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
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        // Get cart items for a user
        $userId = intval($_GET['user_id'] ?? 0);
        
        if (!$userId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing user_id']);
            exit;
        }
        
        $stmt = $pdo->prepare("
            SELECT c.id, c.quantity, c.created_at,
                   p.id as product_id, p.name, p.description, p.price, p.original_price, 
                   p.image_url,
                   (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image,
                   p.stock_quantity, p.discount_percentage,
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
            'cart_items' => $cartItems
        ]);
        
    } elseif ($method === 'POST') {
        // Add item to cart
        $input = json_decode(file_get_contents('php://input'), true);
        
        $userId = intval($input['user_id'] ?? 0);
        $productId = intval($input['product_id'] ?? 0);
        $quantity = intval($input['quantity'] ?? 1);
        
        if (!$userId || !$productId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing user_id or product_id']);
            exit;
        }
        
        // Check if product exists and has stock
        $productStmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ?");
        $productStmt->execute([$productId]);
        $product = $productStmt->fetch();
        
        if (!$product) {
            http_response_code(404);
            echo json_encode(['error' => 'Product not found']);
            exit;
        }
        
        if ($product['stock_quantity'] < $quantity) {
            http_response_code(400);
            echo json_encode(['error' => 'Insufficient stock']);
            exit;
        }
        
        // Check if item already exists in cart
        $existingStmt = $pdo->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
        $existingStmt->execute([$userId, $productId]);
        $existing = $existingStmt->fetch();
        
        if ($existing) {
            // Update quantity
            $newQuantity = $existing['quantity'] + $quantity;
            if ($newQuantity > $product['stock_quantity']) {
                http_response_code(400);
                echo json_encode(['error' => 'Quantity exceeds available stock']);
                exit;
            }
            
            $updateStmt = $pdo->prepare("UPDATE cart SET quantity = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $updateStmt->execute([$newQuantity, $existing['id']]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Cart updated successfully',
                'quantity' => $newQuantity
            ]);
        } else {
            // Add new item
            $insertStmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
            $insertStmt->execute([$userId, $productId, $quantity]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Item added to cart successfully'
            ]);
        }
        
    } elseif ($method === 'PUT') {
        // Update cart item quantity
        $input = json_decode(file_get_contents('php://input'), true);
        
        $cartId = intval($input['cart_id'] ?? 0);
        $quantity = intval($input['quantity'] ?? 1);
        
        if (!$cartId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing cart_id']);
            exit;
        }
        
        if ($quantity <= 0) {
            // Remove item if quantity is 0 or negative
            $deleteStmt = $pdo->prepare("DELETE FROM cart WHERE id = ?");
            $deleteStmt->execute([$cartId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Item removed from cart'
            ]);
        } else {
            // Check stock availability
            $stockStmt = $pdo->prepare("
                SELECT p.stock_quantity FROM cart c 
                JOIN products p ON c.product_id = p.id 
                WHERE c.id = ?
            ");
            $stockStmt->execute([$cartId]);
            $stock = $stockStmt->fetch();
            
            if (!$stock) {
                http_response_code(404);
                echo json_encode(['error' => 'Cart item not found']);
                exit;
            }
            
            if ($quantity > $stock['stock_quantity']) {
                http_response_code(400);
                echo json_encode(['error' => 'Quantity exceeds available stock']);
                exit;
            }
            
            $updateStmt = $pdo->prepare("UPDATE cart SET quantity = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $updateStmt->execute([$quantity, $cartId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Cart updated successfully'
            ]);
        }
        
    } elseif ($method === 'DELETE') {
        // Remove item from cart
        $cartId = intval($_GET['cart_id'] ?? 0);
        
        if (!$cartId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing cart_id']);
            exit;
        }
        
        $deleteStmt = $pdo->prepare("DELETE FROM cart WHERE id = ?");
        $deleteStmt->execute([$cartId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Item removed from cart successfully'
        ]);
        
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>

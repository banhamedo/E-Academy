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
        // Get orders for a user
        $userId = intval($_GET['user_id'] ?? 0);
        
        if (!$userId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing user_id']);
            exit;
        }
        
        $stmt = $pdo->prepare("
            SELECT o.*, 
                   COUNT(oi.id) as items_count
            FROM orders o
            LEFT JOIN order_items oi ON o.id = oi.order_id
            WHERE o.user_id = ?
            GROUP BY o.id
            ORDER BY o.created_at DESC
        ");
        $stmt->execute([$userId]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'orders' => $orders
        ]);
        
    } elseif ($method === 'POST') {
        // Create new order
        $input = json_decode(file_get_contents('php://input'), true);
        
        $userId = intval($input['user_id'] ?? 0);
        $cartItems = $input['cart_items'] ?? [];
        $customerData = $input['customer_data'] ?? [];
        $paymentMethod = $input['payment_method'] ?? 'cash';
        
        if (!$userId || empty($cartItems) || empty($customerData)) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required data']);
            exit;
        }
        
        // Validate customer data
        $requiredFields = ['fullName', 'email', 'phone', 'address', 'city'];
        foreach ($requiredFields as $field) {
            if (empty($customerData[$field])) {
                http_response_code(400);
                echo json_encode(['error' => "Missing required field: $field"]);
                exit;
            }
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        try {
            // Calculate total amount
            $totalAmount = 0;
            foreach ($cartItems as $item) {
                $totalAmount += $item['price'] * $item['quantity'];
            }
            
            // Generate unique order number (not stored in DB per current schema)
            $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(uniqid());

            // Compose shipping address into a single text field to fit current schema
            $shippingAddress = sprintf(
                '%s | %s | %s | %s | %s',
                $customerData['fullName'],
                $customerData['phone'],
                $customerData['address'],
                $customerData['city'],
                $customerData['email']
            );

            // Create order matching marketplace_database.sql (orders table)
            $orderStmt = $pdo->prepare("\n                INSERT INTO orders (user_id, total_amount, payment_method, shipping_address)\n                VALUES (?, ?, ?, ?)\n            ");
            $orderStmt->execute([
                $userId,
                $totalAmount,
                $paymentMethod,
                $shippingAddress
            ]);
            
            $orderId = $pdo->lastInsertId();
            
            // Create order items matching marketplace_database.sql (order_items table)
            $itemStmt = $pdo->prepare("\n                INSERT INTO order_items (order_id, product_id, quantity, price)\n                VALUES (?, ?, ?, ?)\n            ");
            
            foreach ($cartItems as $item) {
                $itemStmt->execute([
                    $orderId,
                    $item['product_id'],
                    $item['quantity'],
                    $item['price']
                ]);
                
                // Update product stock
                $stockStmt = $pdo->prepare("
                    UPDATE products 
                    SET stock_quantity = stock_quantity - ? 
                    WHERE id = ?
                ");
                $stockStmt->execute([$item['quantity'], $item['product_id']]);
            }
            
            // Clear user's cart
            $clearCartStmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
            $clearCartStmt->execute([$userId]);
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Order created successfully',
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'total_amount' => $totalAmount
            ]);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
    } elseif ($method === 'PUT') {
        // Update order status (for admin use)
        $input = json_decode(file_get_contents('php://input'), true);
        
        $orderId = intval($input['order_id'] ?? 0);
        $status = $input['status'] ?? '';
        
        if (!$orderId || !$status) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing order_id or status']);
            exit;
        }
        
        $validStatuses = ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'];
        if (!in_array($status, $validStatuses)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid status']);
            exit;
        }
        
        $updateStmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $updateStmt->execute([$status, $orderId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Order status updated successfully'
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

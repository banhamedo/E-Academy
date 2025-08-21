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
        // Get liked products for a user
        $userId = intval($_GET['user_id'] ?? 0);
        
        if (!$userId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing user_id']);
            exit;
        }
        
        $stmt = $pdo->prepare("
            SELECT p.*, pl.created_at as liked_at
            FROM product_likes pl
            JOIN products p ON pl.product_id = p.id
            WHERE pl.user_id = ?
            ORDER BY pl.created_at DESC
        ");
        $stmt->execute([$userId]);
        $likedProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'liked_products' => $likedProducts
        ]);
        
    } elseif ($method === 'POST') {
        // Toggle like on a product
        $input = json_decode(file_get_contents('php://input'), true);
        
        $userId = intval($input['user_id'] ?? 0);
        $productId = intval($input['product_id'] ?? 0);
        
        if (!$userId || !$productId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing user_id or product_id']);
            exit;
        }
        
        // Check if product exists
        $productStmt = $pdo->prepare("SELECT id FROM products WHERE id = ?");
        $productStmt->execute([$productId]);
        if (!$productStmt->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => 'Product not found']);
            exit;
        }
        
        // Check if already liked
        $existingStmt = $pdo->prepare("SELECT id FROM product_likes WHERE user_id = ? AND product_id = ?");
        $existingStmt->execute([$userId, $productId]);
        $existing = $existingStmt->fetch();
        
        if ($existing) {
            // Remove like
            $deleteStmt = $pdo->prepare("DELETE FROM product_likes WHERE user_id = ? AND product_id = ?");
            $deleteStmt->execute([$userId, $productId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Like removed successfully',
                'liked' => false
            ]);
        } else {
            // Add like
            $insertStmt = $pdo->prepare("INSERT INTO product_likes (user_id, product_id) VALUES (?, ?)");
            $insertStmt->execute([$userId, $productId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Product liked successfully',
                'liked' => true
            ]);
        }
        
    } elseif ($method === 'DELETE') {
        // Remove like
        $userId = intval($_GET['user_id'] ?? 0);
        $productId = intval($_GET['product_id'] ?? 0);
        
        if (!$userId || !$productId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing user_id or product_id']);
            exit;
        }
        
        $deleteStmt = $pdo->prepare("DELETE FROM product_likes WHERE user_id = ? AND product_id = ?");
        $deleteStmt->execute([$userId, $productId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Like removed successfully'
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

<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);
set_cors_headers();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require __DIR__ . '/config.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['product_id', 'user_email', 'rating', 'comment'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            exit;
        }
    }
    
    // Validate rating
    $rating = intval($input['rating']);
    if ($rating < 1 || $rating > 5) {
        http_response_code(400);
        echo json_encode(['error' => 'Rating must be between 1 and 5']);
        exit;
    }
    
    $pdo = get_pdo();
    
    // Get user ID from email
    $userSql = "SELECT id FROM users WHERE email = ?";
    $userStmt = $pdo->prepare($userSql);
    $userStmt->execute([$input['user_email']]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        exit;
    }
    
    // Check if user already reviewed this product
    $checkSql = "SELECT id FROM product_reviews WHERE product_id = ? AND user_id = ?";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([$input['product_id'], $user['id']]);
    
    if ($checkStmt->fetch()) {
        http_response_code(400);
        echo json_encode(['error' => 'You have already reviewed this product']);
        exit;
    }
    
    // Add review
    $sql = "INSERT INTO product_reviews (product_id, user_id, rating, comment) VALUES (?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $input['product_id'],
        $user['id'],
        $rating,
        $input['comment']
    ]);
    
    // Update product rating and review count
    $updateSql = "UPDATE products SET 
                   rating = (SELECT AVG(rating) FROM product_reviews WHERE product_id = ?),
                   total_reviews = (SELECT COUNT(*) FROM product_reviews WHERE product_id = ?)
                   WHERE id = ?";
    $updateStmt = $pdo->prepare($updateSql);
    $updateStmt->execute([
        $input['product_id'],
        $input['product_id'],
        $input['product_id']
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Review added successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>

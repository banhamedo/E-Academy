<?php
require __DIR__ . '/config.php';

// CORS is centrally handled in .htaccess

ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json');

$pdo = get_pdo();

// Handle preflight request (also handled at Apache level)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function getMyProducts($email) {
    global $pdo;
    $email = trim((string)$email);
    if ($email === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Email required']);
        return;
    }

    // Find user id by email
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'User not found']);
        return;
    }

    // List this user's products (any approval_status), keep active ones by default
    $onlyActive = isset($_GET['include_inactive']) && $_GET['include_inactive'] == '1' ? false : true;
    $where = 'seller_id = ?';
    $params = [$user['id']];
    if ($onlyActive) {
        $where .= ' AND is_active = 1';
    }

    $sql = "SELECT p.*, 
                   (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
            FROM products p
            WHERE $where
            ORDER BY p.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'products' => $products]);
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                // Get single product
                getProduct($_GET['id']);
            } else if (!empty($_GET['mine']) && !empty($_GET['email'])) {
                // Get all products created by a specific user (any approval status)
                getMyProducts($_GET['email']);
            } else {
                // Get all approved & active products with filters
                getProducts();
            }
            break;
            
        case 'POST':
            // Create new product
            createProduct();
            break;
            
        case 'PUT':
            // Update product
            updateProduct();
            break;
            
        case 'DELETE':
            // Delete product
            deleteProduct();
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function getProducts() {
    global $pdo;
    
    $category = $_GET['category'] ?? null;
    $search = $_GET['search'] ?? null;
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = 12;
    $offset = ($page - 1) * $limit;
    
    $where = ['p.is_active = 1', "p.approval_status = 'approved'"]; 
    $params = [];
    
    if ($category && $category !== 'الكل') {
        $where[] = 'p.category = ?';
        $params[] = $category;
    }
    
    if ($search) {
        $where[] = '(p.name LIKE ? OR p.description LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $whereClause = implode(' AND ', $where);
    
    $sql = "SELECT p.*, u.full_name as seller_name, 
            (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
            FROM products p 
            JOIN users u ON p.seller_id = u.id 
            WHERE $whereClause 
            ORDER BY p.created_at DESC 
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get total count for pagination
        $countSql = "SELECT COUNT(*) FROM products p WHERE $whereClause";
        $countStmt = $pdo->prepare($countSql);
        $countParams = $params;
        array_pop($countParams); // Remove limit and offset
        array_pop($countParams);
        $countStmt->execute($countParams);
        $total = $countStmt->fetchColumn();
    } catch (Exception $e) {
        $msg = $e->getMessage();
        if (strpos($msg, 'Unknown column') !== false && strpos($msg, 'approval_status') !== false) {
            // Retry without approval_status filter
            $whereNoApproval = str_replace("p.approval_status = 'approved' AND ", '', $whereClause);
            if ($whereNoApproval === $whereClause) {
                $whereNoApproval = str_replace("AND p.approval_status = 'approved'", '', $whereNoApproval);
                $whereNoApproval = str_replace("p.approval_status = 'approved'", '1=1', $whereNoApproval);
            }
            $sql2 = str_replace("WHERE $whereClause", "WHERE $whereNoApproval", $sql);
            $stmt2 = $pdo->prepare($sql2);
            $stmt2->execute($params);
            $products = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            $countSql2 = "SELECT COUNT(*) FROM products p WHERE $whereNoApproval";
            $countStmt2 = $pdo->prepare($countSql2);
            $countParams2 = $params;
            array_pop($countParams2);
            array_pop($countParams2);
            $countStmt2->execute($countParams2);
            $total = $countStmt2->fetchColumn();
        } else {
            throw $e;
        }
    }
    
    echo json_encode([
        'success' => true,
        'products' => $products,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => ceil($total / $limit),
            'total_products' => $total,
            'per_page' => $limit
        ]
    ]);
}

function getProduct($id) {
    global $pdo;
    
    $sql = "SELECT p.*, u.full_name as seller_name 
            FROM products p 
            JOIN users u ON p.seller_id = u.id 
            WHERE p.id = ? AND p.is_active = 1 AND p.approval_status = 'approved'";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $msg = $e->getMessage();
        if (strpos($msg, 'Unknown column') !== false && strpos($msg, 'approval_status') !== false) {
            // Retry without approval_status condition
            $sql2 = "SELECT p.*, u.full_name as seller_name 
                     FROM products p 
                     JOIN users u ON p.seller_id = u.id 
                     WHERE p.id = ? AND p.is_active = 1";
            $stmt2 = $pdo->prepare($sql2);
            $stmt2->execute([$id]);
            $product = $stmt2->fetch(PDO::FETCH_ASSOC);
        } else {
            throw $e;
        }
    }
    
    if (!$product) {
        http_response_code(404);
        echo json_encode(['error' => 'Product not found']);
        return;
    }
    
    // Get product images
    $imageSql = "SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, created_at ASC";
    $imageStmt = $pdo->prepare($imageSql);
    $imageStmt->execute([$id]);
    $product['images'] = $imageStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get product reviews
    $reviewSql = "SELECT pr.*, u.full_name as reviewer_name, u.avatar_path 
                  FROM product_reviews pr 
                  JOIN users u ON pr.user_id = u.id 
                  WHERE pr.product_id = ? 
                  ORDER BY pr.created_at DESC";
    $reviewStmt = $pdo->prepare($reviewSql);
    $reviewStmt->execute([$id]);
    $product['reviews'] = $reviewStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'product' => $product]);
}

function createProduct() {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['name', 'price', 'category', 'description'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            return;
        }
    }
    
    // Get seller ID from user email (you should implement proper authentication)
    $userEmail = $input['user_email'] ?? null;
    if (!$userEmail) {
        http_response_code(401);
        echo json_encode(['error' => 'User email required']);
        return;
    }
    
    $userSql = "SELECT id FROM users WHERE email = ?";
    $userStmt = $pdo->prepare($userSql);
    $userStmt->execute([$userEmail]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        return;
    }
    
    // Try to insert with approval_status. If column does not exist, fallback without it.
    try {
        $sql = "INSERT INTO products (name, description, price, original_price, category, seller_id, stock_quantity, discount_percentage, approval_status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $input['name'],
            $input['description'],
            $input['price'],
            $input['original_price'] ?? null,
            $input['category'],
            $user['id'],
            $input['stock_quantity'] ?? 0,
            $input['discount_percentage'] ?? 0
        ]);
    } catch (Exception $e) {
        // If approval_status column missing (SQLSTATE 42S22), fallback insert without it
        $msg = $e->getMessage();
        if (strpos($msg, 'Unknown column') !== false && strpos($msg, 'approval_status') !== false) {
            // Hide newly created product from public listing until manual approval by setting is_active = 0
            $fallbackSql = "INSERT INTO products (name, description, price, original_price, category, seller_id, stock_quantity, discount_percentage, is_active) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)";
            $stmt = $pdo->prepare($fallbackSql);
            $stmt->execute([
                $input['name'],
                $input['description'],
                $input['price'],
                $input['original_price'] ?? null,
                $input['category'],
                $user['id'],
                $input['stock_quantity'] ?? 0,
                $input['discount_percentage'] ?? 0
            ]);
        } else {
            throw $e;
        }
    }
    
    $productId = $pdo->lastInsertId();
    
    // Handle image uploads if provided
    if (!empty($input['images'])) {
        foreach ($input['images'] as $index => $imageData) {
            $imageSql = "INSERT INTO product_images (product_id, image_url, is_primary) VALUES (?, ?, ?)";
            $imageStmt = $pdo->prepare($imageSql);
            $imageStmt->execute([
                $productId,
                $imageData['url'],
                $index === 0 ? 1 : 0 // First image is primary
            ]);
        }
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Product created successfully',
        'product_id' => $productId
    ]);
}

function updateProduct() {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $productId = $input['id'] ?? null;
    
    if (!$productId) {
        http_response_code(400);
        echo json_encode(['error' => 'Product ID required']);
        return;
    }
    
    // Check if product exists and user owns it
    $checkSql = "SELECT seller_id FROM products WHERE id = ?";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([$productId]);
    $product = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        http_response_code(404);
        echo json_encode(['error' => 'Product not found']);
        return;
    }
    
    // Update product fields
    $updateFields = [];
    $params = [];
    
    $fields = ['name', 'description', 'price', 'original_price', 'category', 'stock_quantity', 'discount_percentage'];
    foreach ($fields as $field) {
        if (isset($input[$field])) {
            $updateFields[] = "$field = ?";
            $params[] = $input[$field];
        }
    }
    
    if (empty($updateFields)) {
        http_response_code(400);
        echo json_encode(['error' => 'No fields to update']);
        return;
    }
    
    $params[] = $productId;
    $sql = "UPDATE products SET " . implode(', ', $updateFields) . " WHERE id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    echo json_encode(['success' => true, 'message' => 'Product updated successfully']);
}

function deleteProduct() {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $productId = $input['id'] ?? null;
    
    if (!$productId) {
        http_response_code(400);
        echo json_encode(['error' => 'Product ID required']);
        return;
    }
    
    // Soft delete - mark as inactive
    $sql = "UPDATE products SET is_active = 0 WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$productId]);
    
    echo json_encode(['success' => true, 'message' => 'Product deleted successfully']);
}
?>

<?php
require __DIR__ . '/config.php';
set_cors_headers();
ini_set('display_errors', '1');
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    $pdo = get_pdo();
    
    // Get total products
    $productsSql = "SELECT COUNT(*) as total FROM products";
    $productsStmt = $pdo->query($productsSql);
    $totalProducts = $productsStmt->fetchColumn();
    
    // Get active products
    $activeSql = "SELECT COUNT(*) as active FROM products WHERE is_active = 1";
    $activeStmt = $pdo->query($activeSql);
    $activeProducts = $activeStmt->fetchColumn();
    
    // Get total users
    $usersSql = "SELECT COUNT(*) as total FROM users";
    $usersStmt = $pdo->query($usersSql);
    $totalUsers = $usersStmt->fetchColumn();
    
    // Get total sales (from orders)
    $salesSql = "SELECT COALESCE(SUM(total_amount), 0) as total FROM orders WHERE status IN ('delivered', 'shipped')";
    $salesStmt = $pdo->query($salesSql);
    $totalSales = $salesStmt->fetchColumn();
    
    // Get recent orders count
    $recentOrdersSql = "SELECT COUNT(*) as recent FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $recentOrdersStmt = $pdo->query($recentOrdersSql);
    $recentOrders = $recentOrdersStmt->fetchColumn();
    
    // Get top categories
    $categoriesSql = "SELECT category, COUNT(*) as count 
                      FROM products 
                      WHERE is_active = 1 
                      GROUP BY category 
                      ORDER BY count DESC 
                      LIMIT 5";
    $categoriesStmt = $pdo->query($categoriesSql);
    $topCategories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get low stock products
    $lowStockSql = "SELECT COUNT(*) as low_stock FROM products WHERE stock_quantity <= 5 AND stock_quantity > 0";
    $lowStockStmt = $pdo->query($lowStockSql);
    $lowStockProducts = $lowStockStmt->fetchColumn();
    
    // Get out of stock products
    $outOfStockSql = "SELECT COUNT(*) as out_of_stock FROM products WHERE stock_quantity = 0";
    $outOfStockStmt = $pdo->query($outOfStockSql);
    $outOfStockProducts = $outOfStockStmt->fetchColumn();
    
    // Get monthly sales data for chart
    $monthlySalesSql = "SELECT 
                           DATE_FORMAT(created_at, '%Y-%m') as month,
                           SUM(total_amount) as sales
                         FROM orders 
                         WHERE status IN ('delivered', 'shipped')
                           AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                         GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                         ORDER BY month";
    $monthlySalesStmt = $pdo->query($monthlySalesSql);
    $monthlySales = $monthlySalesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'totalProducts' => intval($totalProducts),
            'activeProducts' => intval($activeProducts),
            'totalUsers' => intval($totalUsers),
            'totalSales' => floatval($totalSales),
            'recentOrders' => intval($recentOrders),
            'lowStockProducts' => intval($lowStockProducts),
            'outOfStockProducts' => intval($outOfStockProducts),
            'topCategories' => $topCategories,
            'monthlySales' => $monthlySales
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>

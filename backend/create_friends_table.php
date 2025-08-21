<?php
require __DIR__ . '/config.php';

try {
    $pdo = get_pdo();
    echo "🔄 Updating database with complete friends schema...\n\n";
    
    // 1. Create friend_requests table
    echo "📋 Creating friend_requests table...\n";
    $sql1 = "CREATE TABLE IF NOT EXISTS friend_requests (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        from_user_id INT UNSIGNED NOT NULL,
        to_user_id INT UNSIGNED NOT NULL,
        message TEXT DEFAULT NULL,
        status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_request (from_user_id, to_user_id),
        INDEX idx_from_user (from_user_id),
        INDEX idx_to_user (to_user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $pdo->exec($sql1);
    echo "✅ Friend_requests table created!\n";
    
    // 2. Create friends table
    echo "📋 Creating friends table...\n";
    $sql2 = "CREATE TABLE IF NOT EXISTS friends (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id1 INT UNSIGNED NOT NULL,
        user_id2 INT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_friendship (user_id1, user_id2),
        INDEX idx_user_id1 (user_id1),
        INDEX idx_user_id2 (user_id2)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $pdo->exec($sql2);
    echo "✅ Friends table created!\n";
    
    // 3. Add foreign key constraints (if they don't exist)
    echo "📋 Adding foreign key constraints...\n";
    try {
        $pdo->exec("ALTER TABLE friend_requests ADD CONSTRAINT fk_friend_request_from FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE");
        echo "✅ Added foreign key constraint for from_user_id\n";
    } catch (Exception $e) {
        echo "ℹ️ Foreign key constraint for from_user_id already exists\n";
    }
    
    try {
        $pdo->exec("ALTER TABLE friend_requests ADD CONSTRAINT fk_friend_request_to FOREIGN KEY (to_user_id) REFERENCES users(id) ON DELETE CASCADE");
        echo "✅ Added foreign key constraint for to_user_id\n";
    } catch (Exception $e) {
        echo "ℹ️ Foreign key constraint for to_user_id already exists\n";
    }
    
    try {
        $pdo->exec("ALTER TABLE friends ADD CONSTRAINT fk_friend_user1 FOREIGN KEY (user_id1) REFERENCES users(id) ON DELETE CASCADE");
        echo "✅ Added foreign key constraint for user_id1\n";
    } catch (Exception $e) {
        echo "ℹ️ Foreign key constraint for user_id1 already exists\n";
    }
    
    try {
        $pdo->exec("ALTER TABLE friends ADD CONSTRAINT fk_friend_user2 FOREIGN KEY (user_id2) REFERENCES users(id) ON DELETE CASCADE");
        echo "✅ Added foreign key constraint for user_id2\n";
    } catch (Exception $e) {
        echo "ℹ️ Foreign key constraint for user_id2 already exists\n";
    }
    
    // 4. Add additional user fields
    echo "📋 Adding additional user fields...\n";
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS location VARCHAR(255) DEFAULT NULL");
        echo "✅ Added location column to users\n";
    } catch (Exception $e) {
        echo "ℹ️ Location column already exists\n";
    }
    
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_online BOOLEAN DEFAULT FALSE");
        echo "✅ Added is_online column to users\n";
    } catch (Exception $e) {
        echo "ℹ️ is_online column already exists\n";
    }
    
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS last_seen TIMESTAMP NULL DEFAULT NULL");
        echo "✅ Added last_seen column to users\n";
    } catch (Exception $e) {
        echo "ℹ️ last_seen column already exists\n";
    }
    
    // 5. Add video stats columns
    echo "📋 Adding video stats columns...\n";
    try {
        $pdo->exec("ALTER TABLE videos ADD COLUMN IF NOT EXISTS caption TEXT DEFAULT NULL");
        echo "✅ Added caption column to videos\n";
    } catch (Exception $e) {
        echo "ℹ️ caption column already exists\n";
    }
    
    try {
        $pdo->exec("ALTER TABLE videos ADD COLUMN IF NOT EXISTS cover_path VARCHAR(255) DEFAULT NULL");
        echo "✅ Added cover_path column to videos\n";
    } catch (Exception $e) {
        echo "ℹ️ cover_path column already exists\n";
    }
    
    try {
        $pdo->exec("ALTER TABLE videos ADD COLUMN IF NOT EXISTS likes_count INT DEFAULT 0");
        echo "✅ Added likes_count column to videos\n";
    } catch (Exception $e) {
        echo "ℹ️ likes_count column already exists\n";
    }
    
    try {
        $pdo->exec("ALTER TABLE videos ADD COLUMN IF NOT EXISTS comments_count INT DEFAULT 0");
        echo "✅ Added comments_count column to videos\n";
    } catch (Exception $e) {
        echo "ℹ️ comments_count column already exists\n";
    }
    
    // 6. Verify all tables exist
    echo "\n📋 Verifying database structure...\n";
    $tables = ['friend_requests', 'friends', 'users', 'videos'];
    foreach ($tables as $table) {
        $result = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($result->rowCount() > 0) {
            echo "✅ Table '$table' exists\n";
        } else {
            echo "❌ Table '$table' missing!\n";
        }
    }
    
    echo "\n🎉 Database update completed successfully!\n";
    echo "All friends functionality tables and columns are now ready.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>

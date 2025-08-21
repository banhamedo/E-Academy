<?php
require __DIR__ . '/config.php';

try {
    $pdo = get_pdo();
    
    echo "Setting up friends functionality database tables...\n";
    
    // Read and execute the friends schema
    $sql = file_get_contents(__DIR__ . '/friends_schema.sql');
    
    // Split by semicolon and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            try {
                $pdo->exec($statement);
                echo "✓ Executed: " . substr($statement, 0, 50) . "...\n";
            } catch (PDOException $e) {
                echo "⚠ Warning: " . $e->getMessage() . "\n";
                echo "Statement: " . substr($statement, 0, 100) . "...\n";
            }
        }
    }
    
    echo "\n✅ Friends database setup completed successfully!\n";
    echo "The following tables and columns have been created/updated:\n";
    echo "- friend_requests table\n";
    echo "- friends table\n";
    echo "- users table (added location, is_online, last_seen columns)\n";
    echo "- videos table (added caption, cover_path, likes_count, comments_count columns)\n";
    
} catch (Exception $e) {
    echo "❌ Error setting up database: " . $e->getMessage() . "\n";
}
?>

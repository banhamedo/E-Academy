<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);
header('Content-Type: application/json');

require __DIR__ . '/config.php';

try {
  $pdo = get_pdo();
  
  // Create users table if it doesn't exist
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      full_name VARCHAR(120) NOT NULL,
      email VARCHAR(160) NOT NULL UNIQUE,
      password_hash VARCHAR(255) NOT NULL,
      role ENUM('player','coach','manager','organizer') NOT NULL,
      avatar_path VARCHAR(255) DEFAULT NULL,
      cover_path VARCHAR(255) DEFAULT NULL,
      username VARCHAR(100) DEFAULT NULL,
      bio TEXT DEFAULT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  ");
  
  // Create videos table if it doesn't exist
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS videos (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      user_id INT UNSIGNED NOT NULL,
      file_path VARCHAR(255) NOT NULL,
      title VARCHAR(255) DEFAULT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT fk_videos_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  ");
  
  // Create user_follows table if it doesn't exist
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS user_follows (
      follower_id INT UNSIGNED NOT NULL,
      following_id INT UNSIGNED NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (follower_id, following_id),
      CONSTRAINT fk_follower FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
      CONSTRAINT fk_following FOREIGN KEY (following_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  ");
  
  echo json_encode([
    'success' => true,
    'message' => 'All tables created successfully'
  ]);
  
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Database creation failed', 'details' => $e->getMessage()]);
}

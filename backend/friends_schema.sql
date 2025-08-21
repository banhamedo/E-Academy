-- Friends and Friend Requests Tables
CREATE TABLE IF NOT EXISTS friend_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  from_user_id INT UNSIGNED NOT NULL,
  to_user_id INT UNSIGNED NOT NULL,
  message TEXT DEFAULT NULL,
  status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_request (from_user_id, to_user_id),
  CONSTRAINT fk_friend_request_from FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_friend_request_to FOREIGN KEY (to_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Friends table (bidirectional friendship)
CREATE TABLE IF NOT EXISTS friends (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id1 INT UNSIGNED NOT NULL,
  user_id2 INT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_friendship (user_id1, user_id2),
  CONSTRAINT fk_friend_user1 FOREIGN KEY (user_id1) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_friend_user2 FOREIGN KEY (user_id2) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add additional user fields for better friend functionality
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS location VARCHAR(255) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS is_online BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS last_seen TIMESTAMP NULL DEFAULT NULL;

-- Add video stats
ALTER TABLE videos 
ADD COLUMN IF NOT EXISTS caption TEXT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS cover_path VARCHAR(255) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS likes_count INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS comments_count INT DEFAULT 0;

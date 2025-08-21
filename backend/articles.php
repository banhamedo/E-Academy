<?php
header('Content-Type: application/json');
// CORS is handled globally in backend/.htaccess to avoid duplicate headers
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/config.php';

function ensure_articles_table(PDO $pdo) {
  $sql = "CREATE TABLE IF NOT EXISTS articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    author_name VARCHAR(255) NULL,
    title VARCHAR(255) NOT NULL,
    excerpt TEXT NOT NULL,
    content MEDIUMTEXT NOT NULL,
    category VARCHAR(100) NOT NULL,
    tags JSON NULL,
    read_time VARCHAR(50) NULL,
    image_path VARCHAR(500) NULL,
    views INT DEFAULT 0,
    likes INT DEFAULT 0,
    comments INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
  $pdo->exec($sql);
}

function get_user_by_email(PDO $pdo, $email) {
  if (!$email) return null;
  $stmt = $pdo->prepare('SELECT id, full_name FROM users WHERE email = ? LIMIT 1');
  $stmt->execute([$email]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  return $row ?: null;
}

function save_uploaded_image($field, $baseDir) {
  if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return null;
  $dir = __DIR__ . '/uploads/' . trim($baseDir, '/');
  if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
  $tmp = $_FILES[$field]['tmp_name'];
  $name = basename($_FILES[$field]['name']);
  $ext = pathinfo($name, PATHINFO_EXTENSION);
  $safe = uniqid('article_', true) . ($ext ? ('.' . strtolower($ext)) : '');
  $dest = $dir . '/' . $safe;
  if (!move_uploaded_file($tmp, $dest)) return null;
  return 'uploads/' . trim($baseDir, '/') . '/' . $safe; // relative to backend
}

$method = $_SERVER['REQUEST_METHOD'];

try {
  $pdo = get_pdo();
} catch (Throwable $e) {
  echo json_encode(['success' => false, 'error' => 'DB connection not available']);
  exit;
}

ensure_articles_table($pdo);

if ($method === 'GET') {
  // Fetch single by id
  if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare('SELECT id, author_name, title, excerpt, content, category, tags, read_time, image_path, views, likes, comments, created_at FROM articles WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
      $row['tags'] = !empty($row['tags']) ? json_decode($row['tags'], true) : [];
      echo json_encode(['success' => true, 'article' => $row]);
    } else {
      echo json_encode(['success' => false, 'error' => 'Not found']);
    }
    exit;
  }

  // Optional filters (list)
  $category = isset($_GET['category']) ? $_GET['category'] : null;
  $email = isset($_GET['email']) ? trim($_GET['email']) : null;
  $baseSql = 'SELECT id, author_name, title, excerpt, content, category, tags, read_time, image_path, views, likes, comments, created_at FROM articles';
  $where = [];
  $params = [];
  if ($category && strtolower($category) !== 'all') {
    $where[] = 'category = ?';
    $params[] = $category;
  }
  if ($email) {
    $user = get_user_by_email($pdo, $email);
    if ($user && isset($user['id'])) {
      $where[] = 'user_id = ?';
      $params[] = (int)$user['id'];
    } else {
      echo json_encode(['success' => true, 'articles' => []]);
      exit;
    }
  }
  if (!empty($where)) {
    $stmt = $pdo->prepare($baseSql . ' WHERE ' . implode(' AND ', $where));
    $stmt->execute($params);
  } else {
    $stmt = $pdo->query($baseSql);
  }
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  foreach ($rows as &$row) {
    $row['tags'] = !empty($row['tags']) ? json_decode($row['tags'], true) : [];
  }
  echo json_encode(['success' => true, 'articles' => $rows]);
  exit;
}

if ($method === 'POST') {
  $email = isset($_POST['email']) ? trim($_POST['email']) : '';
  $authorName = isset($_POST['authorName']) ? trim($_POST['authorName']) : '';
  $title = isset($_POST['title']) ? trim($_POST['title']) : '';
  $excerpt = isset($_POST['excerpt']) ? trim($_POST['excerpt']) : '';
  $content = isset($_POST['content']) ? trim($_POST['content']) : '';
  $category = isset($_POST['category']) ? trim($_POST['category']) : '';
  $readTime = isset($_POST['readTime']) ? trim($_POST['readTime']) : '';
  $tagsRaw = isset($_POST['tags']) ? $_POST['tags'] : '[]';
  $tags = null;
  if ($tagsRaw) {
    $decoded = json_decode($tagsRaw, true);
    if (is_array($decoded)) { $tags = json_encode($decoded, JSON_UNESCAPED_UNICODE); }
  }

  if (!$title || !$excerpt || !$content || !$category) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
  }

  $user = $email ? get_user_by_email($pdo, $email) : null;
  if (!$authorName && $user && !empty($user['full_name'])) { $authorName = $user['full_name']; }

  $imagePath = save_uploaded_image('image', 'articles');

  $uid = $user ? intval($user['id']) : null;
  $stmt = $pdo->prepare('INSERT INTO articles (user_id, author_name, title, excerpt, content, category, tags, read_time, image_path) VALUES (:user_id, :author_name, :title, :excerpt, :content, :category, :tags, :read_time, :image_path)');
  $ok = $stmt->execute([
    ':user_id' => $uid,
    ':author_name' => $authorName ?: null,
    ':title' => $title,
    ':excerpt' => $excerpt,
    ':content' => $content,
    ':category' => $category,
    ':tags' => $tags,
    ':read_time' => $readTime,
    ':image_path' => $imagePath,
  ]);
  if (!$ok) {
    echo json_encode(['success' => false, 'error' => 'Failed to save article']);
    exit;
  }
  $newId = (int)$pdo->lastInsertId();
  echo json_encode(['success' => true, 'id' => $newId, 'image_path' => $imagePath]);
  exit;
}

echo json_encode(['success' => false, 'error' => 'Unsupported method']);

<?php
require __DIR__ . '/config.php';
set_cors_headers();
ini_set('display_errors', '1');
error_reporting(E_ALL);
while (ob_get_level()) { ob_end_clean(); }
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

$pdo = get_pdo();

$sql = "
  SELECT 
    v.id,
    v.caption,
    v.file_path,
    v.cover_path,
    v.created_at,
    v.user_id AS author_id,
    u.full_name AS author_name
  FROM videos v
  JOIN users u ON v.user_id = u.id
  ORDER BY v.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute();

$videos = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $videos[] = [
        'id' => $row['id'],
        'caption' => $row['caption'],
        'file_path' => $row['file_path'],
        'cover_path' => $row['cover_path'],
        'created_at' => $row['created_at'],
        'author_id' => $row['author_id'],
        'author_name' => $row['author_name'],
    ];
}

echo json_encode(['success' => true, 'videos' => $videos]);

<?php
require __DIR__ . '/config.php';
set_cors_headers();
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

$from = intval($_GET['from_user_id'] ?? 0);
$to = intval($_GET['to_user_id'] ?? 0);

if (!$from || !$to || $from === $to) {
    echo json_encode(['success' => false, 'error' => 'Invalid user IDs']);
    exit;
}

$pdo = get_pdo();

$stmt = $pdo->prepare('SELECT status FROM friend_requests WHERE from_user_id = ? AND to_user_id = ?');
$stmt->execute([$from, $to]);
$row = $stmt->fetch();
if ($row) {
    echo json_encode(['success' => true, 'status' => $row['status']]);
} else {
    echo json_encode(['success' => true, 'status' => 'none']);
}

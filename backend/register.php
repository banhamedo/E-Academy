<?php
require __DIR__ . '/config.php';
set_cors_headers();
// Ensure clean JSON output (avoid PHP warnings in response)
ini_set('display_errors', '0');
error_reporting(0);
while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
  }

  $fullName = trim($_POST['fullName'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';
  $role = $_POST['role'] ?? '';

  if ($fullName === '' || $email === '' || $password === '' || $role === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
  }

  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid email']);
    exit;
  }

  $pdo = get_pdo();

  // Check duplicate email
  $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
  $stmt->execute([$email]);
  if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => 'Email already exists']);
    exit;
  }

  // Handle avatar upload (optional)
  $avatarPath = null;
  if (!empty($_FILES['avatar']['name'])) {
    $uploadsDir = __DIR__ . '/uploads';
    if (!is_dir($uploadsDir)) { mkdir($uploadsDir, 0777, true); }
    $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
    $filename = uniqid('avatar_', true) . '.' . strtolower($ext);
    $dest = $uploadsDir . '/' . $filename;
    if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $dest)) {
      http_response_code(500);
      echo json_encode(['error' => 'Avatar upload failed']);
      exit;
    }
    $avatarPath = 'uploads/' . $filename;
  }

  $passwordHash = password_hash($password, PASSWORD_BCRYPT);

  $insert = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, role, avatar_path) VALUES (?, ?, ?, ?, ?)');
  $insert->execute([$fullName, $email, $passwordHash, $role, $avatarPath]);

  echo json_encode(['success' => true]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Server error', 'details' => $e->getMessage()]);
}



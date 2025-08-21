<?php
require __DIR__ . '/config.php';

// Keep only JSON Content-Type; CORS centralized in .htaccess
set_cors_headers();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
  }

  $input = json_decode(file_get_contents('php://input'), true);

  // USE DIRECT API KEY (per request)
  // WARNING: Hardcoding API keys is insecure. Do not use in production.
  $apiKey = 'AIzaSyBRG0qfDrS_BHCeJnouE1ZBo6XFoiEAFp8';

  $prompt = trim($input['prompt'] ?? '');
  $system = trim($input['system'] ?? 'You are a helpful assistant.');
  $history = $input['history'] ?? [];
  $model = $input['model'] ?? 'gemini-1.5-flash';

  if ($prompt === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing prompt']);
    exit;
  }

  // Build contents with optional history
  $contents = [];
  if ($system !== '') {
    $contents[] = [
      'role' => 'user',
      'parts' => [ ['text' => "[SYSTEM]\n" . $system] ]
    ];
  }
  if (is_array($history)) {
    foreach ($history as $msg) {
      $role = ($msg['sender'] ?? 'user') === 'bot' ? 'model' : 'user';
      $text = $msg['text'] ?? '';
      if ($text !== '') {
        $contents[] = [ 'role' => $role, 'parts' => [ ['text' => $text] ] ];
      }
    }
  }
  // Current user message
  $contents[] = [ 'role' => 'user', 'parts' => [ ['text' => $prompt] ] ];

  $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($apiKey);

  $payload = json_encode([
    'contents' => $contents,
    'generationConfig' => [
      'temperature' => $input['temperature'] ?? 0.7,
      'maxOutputTokens' => $input['max_tokens'] ?? 1024,
    ],
  ], JSON_UNESCAPED_UNICODE);

  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [ 'Content-Type: application/json' ]);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
  $response = curl_exec($ch);
  if ($response === false) {
    $err = curl_error($ch);
    curl_close($ch);
    http_response_code(502);
    echo json_encode(['error' => 'Curl error: ' . $err]);
    exit;
  }
  $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  $json = json_decode($response, true);
  if ($status >= 400 || !is_array($json)) {
    http_response_code(502);
    echo json_encode(['error' => 'Upstream error', 'status' => $status, 'body' => $response]);
    exit;
  }

  // Extract text
  $text = '';
  if (!empty($json['candidates'][0]['content']['parts'][0]['text'])) {
    $text = $json['candidates'][0]['content']['parts'][0]['text'];
  } elseif (!empty($json['candidates'][0]['output'])) {
    $text = $json['candidates'][0]['output'];
  }

  echo json_encode([
    'success' => true,
    'model' => $model,
    'output_text' => $text,
    'raw' => $json,
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}

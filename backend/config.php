<?php
// Basic database configuration for XAMPP (adjust if needed)
const DB_HOST = 'localhost';
const DB_NAME = 'eaacademy';
const DB_USER = 'root';
const DB_PASS = '';

function get_pdo(): PDO {
  static $pdo = null;
  if ($pdo === null) {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $options = [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
  }
  return $pdo;
}

function set_cors_headers() {
  // Centralized CORS is handled by backend/.htaccess.
  // Keep function for compatibility but only ensure JSON content type.
  if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
  }
}

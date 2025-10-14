<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

/* ✅ Cookie sin 'domain' */
session_set_cookie_params([
  'lifetime' => 0,
  'path'     => '/',
  'secure'   => false,   // true si usás HTTPS
  'httponly' => true,
  'samesite' => 'Lax'
]);
session_start();

require __DIR__ . '/api/db.php';

/* Lee JSON o form */
$raw = file_get_contents('php://input') ?: '';
$in  = json_decode($raw, true);
if (!is_array($in)) $in = $_POST ?? [];

$user = trim((string)($in['user'] ?? $in['username'] ?? ''));
$pass = trim((string)($in['pass'] ?? $in['password'] ?? ''));

/* DEMO simple. Reemplazá por admins de tu DB si querés. */
if ($user === 'admin' && $pass === '1234') {
  $_SESSION['admin_id'] = 1;
  echo json_encode(['ok' => true]);
  exit;
}

http_response_code(401);
echo json_encode(['ok' => false, 'error' => 'credenciales inválidas']);

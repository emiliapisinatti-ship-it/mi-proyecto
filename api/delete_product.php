<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

session_set_cookie_params([
  'lifetime' => 0,
  'path'     => '/',
  'secure'   => false,
  'httponly' => true,
  'samesite' => 'Lax'
]);
session_start();

require __DIR__ . '/db.php';

if (empty($_SESSION['admin_id'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'unauthorized']);
  exit;
}

$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
if ($id <= 0) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'id inválido']);
  exit;
}

try {
  $pdo->prepare("UPDATE products SET active=0 WHERE id=?")->execute([$id]);
  $pdo->prepare("UPDATE product_variants SET stock=0 WHERE product_id=?")->execute([$id]);
  echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}

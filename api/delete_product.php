<?php
session_start();
if (empty($_SESSION['admin_id'])) {
  http_response_code(401);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok'=>false,'error'=>'unauthorized']);
  exit;
}
require __DIR__.'/db.php';



<?php
// api/delete_product.php
require __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$id = (int)($input['id'] ?? 0);
if (!$id) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'ID faltante']);
  exit;
}

$stmt = $pdo->prepare("DELETE FROM products WHERE id=?");
$stmt->execute([$id]);

echo json_encode(['ok'=>true]);

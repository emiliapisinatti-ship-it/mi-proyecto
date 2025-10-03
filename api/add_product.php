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
// api/add_product.php
require __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$name     = trim($input['nombre']      ?? '');
$kind     = trim($input['categoria']   ?? '');         // remeras, pantalones, ...
$category = trim($input['category']    ?? 'mujer');    // por defecto mujer
$price    = $input['precio']           ?? 0;
$color    = trim($input['colores']     ?? '');
$sizes    = trim($input['talles']      ?? '');
$img      = trim($input['imagenURL']   ?? $input['img'] ?? '');
$active   = isset($input['activo']) ? (int)!!$input['activo'] : 1;

if ($name === '' || $kind === '' || $category === '' || !is_numeric($price)) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'Datos inválidos']);
  exit;
}

$stmt = $pdo->prepare("INSERT INTO products (name, category, kind, price, color, sizes, img, active)
                       VALUES (?,?,?,?,?,?,?,?)");
$stmt->execute([$name, $category, $kind, $price, $color, $sizes, $img, $active]);

echo json_encode(['ok'=>true,'id'=>$pdo->lastInsertId()]);

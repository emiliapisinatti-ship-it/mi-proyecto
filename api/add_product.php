<?php
// api/add_product.php
declare(strict_types=1);

session_set_cookie_params([
  'lifetime' => 0,
  'path'     => '/',
  'domain'   => 'localhost',
  'secure'   => false,
  'httponly' => true,
  'samesite' => 'Lax',
]);
session_start();

header('Content-Type: application/json; charset=utf-8');

// Si no hay sesión admin -> 401
if (empty($_SESSION['admin_id'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false, 'error'=>'unauthorized (no admin_id en la sesión)']);
  exit;
}

require __DIR__ . '/db.php';

// Helpers
function bad(string $msg, int $code = 400){
  http_response_code($code);
  echo json_encode(['ok'=>false,'error'=>$msg], JSON_UNESCAPED_UNICODE);
  exit;
}
function csv_to_array(?string $s): array {
  if (!$s) return [];
  return array_values(array_filter(array_map(fn($x)=>trim((string)$x), explode(',', (string)$s)), fn($x)=>$x!==''));
}

// Leer JSON o form
$raw   = file_get_contents('php://input');
$input = json_decode($raw ?: '', true);
if (!is_array($input)) $input = $_POST ?? [];

// Mapeo ES -> columnas
$name     = trim((string)($input['nombre']    ?? $input['name'] ?? ''));
$kind     = trim((string)($input['categoria'] ?? $input['kind'] ?? ''));
$category = trim((string)($input['category']  ?? 'mujer'));
$price    = (float)($input['precio']          ?? $input['price'] ?? 0);
$colorCsv = trim((string)($input['colores']   ?? $input['color'] ?? ''));
$sizeCsv  = trim((string)($input['talles']    ?? $input['sizes'] ?? ''));
$img      = trim((string)($input['imagenURL'] ?? $input['img']   ?? ''));
$active   = isset($input['activo']) ? (int)!!$input['activo']
           : (isset($input['active']) ? (int)!!$input['active'] : 1);

if ($name === '' || $kind === '' || $category === '' || $price <= 0) {
  bad('Datos inválidos (nombre/categoría/precio).');
}

$colors = csv_to_array($colorCsv);
$sizes  = csv_to_array($sizeCsv);

try {
  $pdo->beginTransaction();

  // Insert producto
  $stmt = $pdo->prepare("
    INSERT INTO products (name, category, kind, price, color, sizes, img, active)
    VALUES (?,?,?,?,?,?,?,?)
  ");
  $stmt->execute([$name, $category, $kind, $price, $colorCsv, $sizeCsv, $img, $active]);
  $productId = (int)$pdo->lastInsertId();

  // Variantes
  $rows = [];
  if ($colors && $sizes) {
    foreach ($colors as $c) foreach ($sizes as $s) $rows[] = [$s, $c];
  } elseif ($colors) {
    foreach ($colors as $c) $rows[] = [null, $c];
  } elseif ($sizes) {
    foreach ($sizes as $s) $rows[] = [$s, null];
  } else {
    $rows[] = [null, null];
  }

  // Asegurate de tener este índice único (una sola vez en tu BD):
  // ALTER TABLE product_variants ADD UNIQUE KEY uk_product_size_color (product_id, size, color);

  $skuBase = strtoupper(substr(preg_replace('/\s+/', '', $kind), 0, 3)) . '-' . $productId;
  $insVar  = $pdo->prepare("
    INSERT IGNORE INTO product_variants (product_id, sku, size, color, stock)
    VALUES (?, ?, ?, ?, 0)
  ");
  $i = 1;
  foreach ($rows as [$s,$c]) {
    $sku = $skuBase . '-' . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
    $insVar->execute([$productId, $sku, $s, $c]);
    $i++;
  }

  $pdo->commit();
  echo json_encode(['ok'=>true,'id'=>$productId], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  bad('Error guardando: ' . $e->getMessage(), 500);
}

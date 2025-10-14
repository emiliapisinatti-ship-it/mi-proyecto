<?php
// api/products.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/db.php';

// Helper
function out($data, int $code = 200){
  http_response_code($code);
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}
function clean_str($v){ return trim((string)$v); }

// ====== Filtros ======
$isAdmin     = isset($_GET['admin']) && (string)$_GET['admin'] === '1';
$onlyActive  = isset($_GET['only_active']) && (string)$_GET['only_active'] === '1'; // <—

$category  = clean_str($_GET['category'] ?? '');
$kind      = clean_str($_GET['kind'] ?? '');
$size      = clean_str($_GET['size'] ?? '');
$color     = clean_str($_GET['color'] ?? '');

$where  = [];
$params = [];

// Clientes siempre ven solo activos
if (!$isAdmin) {
  $where[] = 'p.active = 1';
} elseif ($onlyActive) {
  // Admin con filtro explícito
  $where[] = 'p.active = 1';
}

// Filtros opcionales
if ($category !== '') { $where[] = 'LOWER(p.category) = LOWER(?)'; $params[] = $category; }
if ($kind     !== '') { $where[] = 'LOWER(p.kind)     = LOWER(?)'; $params[] = $kind; }
if ($size     !== '') { $where[] = 'EXISTS (SELECT 1 FROM product_variants v2 WHERE v2.product_id = p.id AND UPPER(v2.size) = UPPER(?))';  $params[] = $size; }
if ($color    !== '') { $where[] = 'EXISTS (SELECT 1 FROM product_variants v3 WHERE v3.product_id = p.id AND LOWER(COALESCE(v3.color,"")) = LOWER(?))'; $params[] = $color; }

$sql = "SELECT p.id, p.name, p.category, p.kind, p.price, p.color, p.sizes, p.img, p.active
        FROM products p"
      . ($where ? " WHERE " . implode(" AND ", $where) : "")
      . " ORDER BY p.id DESC";

try{
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $prods = $st->fetchAll(PDO::FETCH_ASSOC);

  if (!$prods) out([]);

  // Variantes en 1 tanda y adjuntar
  $ids = array_column($prods, 'id');
  $ph  = implode(',', array_fill(0, count($ids), '?'));
  $sv  = $pdo->prepare("SELECT id, product_id, sku, size, color, stock
                        FROM product_variants
                        WHERE product_id IN ($ph)
                        ORDER BY product_id, color, size");
  $sv->execute($ids);
  $vars = $sv->fetchAll(PDO::FETCH_ASSOC);

  $vv = [];
  foreach ($vars as $v){
    $pid = (int)$v['product_id'];
    if (!isset($vv[$pid])) $vv[$pid] = [];
    $vv[$pid][] = [
      'id'    => (int)$v['id'],
      'sku'   => (string)$v['sku'],
      'size'  => $v['size'] !== null ? (string)$v['size'] : null,
      'color' => $v['color'] !== null ? (string)$v['color'] : null,
      'stock' => (int)$v['stock'],
    ];
  }

  $out = [];
  foreach ($prods as $p){
    $pid = (int)$p['id'];
    $out[] = [
      'id'       => $pid,
      'name'     => (string)$p['name'],
      'category' => (string)$p['category'],
      'kind'     => (string)$p['kind'],
      'price'    => (float)$p['price'],
      'color'    => (string)($p['color'] ?? ''),
      'sizes'    => (string)($p['sizes'] ?? ''),
      'img'      => (string)($p['img'] ?? ''),
      'active'   => (int)$p['active'],
      'variants' => $vv[$pid] ?? [],
    ];
  }

  out($out);

} catch(Throwable $e){
  out(['ok'=>false,'error'=>'DB: '.$e->getMessage()], 500);
}

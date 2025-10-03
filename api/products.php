<?php
// api/products.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/db.php'; // $pdo (PDO MySQL)

function q($k){ return isset($_GET[$k]) ? trim((string)$_GET[$k]) : ''; }

$onlyActive = (q('admin') !== '1');  // admin=1 -> NO filtra por active
$where = [];
$params = [];

if ($onlyActive) { $where[] = 'active = 1'; }

$category = q('category');  // ej: mujer
$kind     = q('kind');      // ej: remeras | pantalones | vestidos
$size     = strtoupper(q('size')); // S,M,L...
$color    = q('color');     // negro, blanco...

if ($category !== '') { $where[] = 'category = :category'; $params[':category'] = $category; }
if ($kind     !== '') { $where[] = 'kind = :kind';         $params[':kind']     = $kind;     }

if ($size !== '') {
  // sizes viene como CSV (p.ej. "S,M,L")
  $where[] = "(FIND_IN_SET(:size, REPLACE(sizes,' ','')) OR UPPER(sizes) LIKE :size_like)";
  $params[':size'] = $size;
  $params[':size_like'] = "%$size%";
}
if ($color !== '') {
  $where[] = "(color = :color
              OR FIND_IN_SET(:color2, REPLACE(color,' ','')) 
              OR color LIKE :color_like)";
  $params[':color'] = $color;
  $params[':color2'] = $color;
  $params[':color_like'] = "%$color%";
}

$sql = 'SELECT id, name, price, category, color, sizes, img, kind, active
        FROM products';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY id DESC LIMIT 500';

try {
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  // Devolvemos ambos juegos de claves para que sirva tanto el front como el admin
  $out = array_map(function($r){
    return [
      // nombres "EN" (admin)
      'id'       => (int)$r['id'],
      'name'     => $r['name'],
      'price'    => (float)$r['price'],
      'category' => $r['category'],
      'color'    => $r['color'],
      'sizes'    => $r['sizes'],
      'img'      => $r['img'],
      'kind'     => $r['kind'],
      'active'   => (int)$r['active'],

      // alias "ES" (front)
      'nombre'    => $r['name'],
      'precio'    => (float)$r['price'],
      'categoria' => $r['category'],
      'colores'   => $r['color'],
      'talles'    => $r['sizes'],
      'imagen'    => $r['img'],
      'activo'    => (int)$r['active'],
    ];
  }, $rows);

  echo json_encode($out, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>'DB', 'details'=>$e->getMessage()]);
}

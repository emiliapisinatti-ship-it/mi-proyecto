<?php
// api/toggle_active.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/db.php'; // Debe definir $pdo (PDO a MySQL)

function input(): array {
  $raw = file_get_contents('php://input') ?: '';
  $j = json_decode($raw, true);
  if (is_array($j)) return $j;
  // fallback a GET/POST para probar en el navegador
  return $_REQUEST;
}

$in = input();

// --- DEBUG: devolvé lo recibido para ver si llega ---
$debug_received = $in;

$id     = isset($in['id']) ? (int)$in['id'] : 0;
$activo = isset($in['activo']) ? (int)$in['activo'] : 0;

if ($id <= 0) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'bad_id','received'=>$debug_received]);
  exit;
}
$activo = $activo ? 1 : 0;

try {
  // Aseguramos errores como excepciones
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // La columna se llama 'active'
  $st = $pdo->prepare('UPDATE `products` SET `active` = :a WHERE `id` = :id');
  $st->execute([':a'=>$activo, ':id'=>$id]);

  echo json_encode([
    'ok'       => true,
    'updated'  => $st->rowCount(), // 0 si ya estaba igual
    'id'       => $id,
    'active'   => $activo,
    'received' => $debug_received
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'ok'       => false,
    'error'    => 'db_error',
    'details'  => $e->getMessage(),
    'received' => $debug_received
  ]);
}


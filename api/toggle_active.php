<?php
// api/toggle_active.php
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

require __DIR__ . '/db.php';

if (empty($_SESSION['admin_id'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'unauthorized']);
  exit;
}

$raw = file_get_contents('php://input') ?: '';
$in  = json_decode($raw, true);
if (!is_array($in)) $in = $_REQUEST;

$id     = isset($in['id']) ? (int)$in['id'] : 0;
$activo = isset($in['activo']) ? (int)$in['activo'] : 0;

if ($id <= 0) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'bad_id','received'=>$in]);
  exit;
}
$activo = $activo ? 1 : 0;

try {
  $st = $pdo->prepare('UPDATE products SET active = :a WHERE id = :id');
  $st->execute([':a'=>$activo, ':id'=>$id]);
  echo json_encode(['ok'=>true,'updated'=>$st->rowCount(),'id'=>$id,'active'=>$activo]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}

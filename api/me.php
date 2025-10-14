<?php
// api/me.php — responde si hay cliente logueado en la sesión
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

session_start();

$cid   = (int)($_SESSION['customer_id'] ?? $_SESSION['cliente_id'] ?? 0);
$email = (string)($_SESSION['customer_email'] ?? $_SESSION['cliente_email'] ?? '');

if ($cid > 0 && $email !== '') {
  echo json_encode(['ok'=>true, 'customer'=>['id'=>$cid, 'email'=>$email]], JSON_UNESCAPED_UNICODE);
} else {
  echo json_encode(['ok'=>false], JSON_UNESCAPED_UNICODE);
}

<?php
// api/order_status.php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Solo admins
if (empty($_SESSION['admin_id'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'unauthorized']);
  exit;
}

require __DIR__ . '/db.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$order_id = (int)($input['order_id'] ?? 0);
$status   = trim($input['status'] ?? '');

$allowed = ['Pendiente','Enviado','Cancelado'];
if ($order_id <= 0 || !in_array($status, $allowed, true)) {
  http_response_code(422);
  echo json_encode(['ok'=>false,'error'=>'invalid params']);
  exit;
}

$stmt = $pdo->prepare("UPDATE orders SET status=? WHERE id=?");
$stmt->execute([$status, $order_id]);

echo json_encode(['ok'=>true]);

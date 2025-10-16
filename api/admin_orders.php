<?php
// api/admin_orders.php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Solo admins
if (empty($_SESSION['admin_id'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'unauthorized']);
  exit;
}

require __DIR__ . '/db.php';

// Filtros opcionales
$status = $_GET['status'] ?? null;         // pending | shipped | cancelled
$q      = trim($_GET['q'] ?? '');          // email o #id

$where  = [];
$params = [];

if ($status) { $where[] = "o.status = ?"; $params[] = $status; }
if ($q !== '') {
  $where[] = "(o.customer_email LIKE ? OR o.id = ?)";
  $params[] = "%$q%";
  $params[] = ctype_digit($q) ? (int)$q : 0;
}
$sqlWhere = $where ? "WHERE ".implode(" AND ", $where) : "";

// Trae totales por ítems y datos del cliente
$sql = "
SELECT 
  o.id, o.customer_id, o.customer_email, o.status, o.created_at,
  COALESCE(SUM(oi.qty * oi.price_unit), 0) AS total,
  COUNT(oi.id) AS items,
  c.name  AS customer_name,
  c.phone AS customer_phone
FROM orders o
LEFT JOIN order_items oi ON oi.order_id = o.id
LEFT JOIN customers c     ON c.id = o.customer_id
$sqlWhere
GROUP BY o.id
ORDER BY o.created_at DESC
LIMIT 200;
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['ok'=>true,'data'=>$rows], JSON_UNESCAPED_UNICODE);

<?php
// api/checkout.php — versión estable con try/catch y salida JSON
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
session_start();
require __DIR__ . '/db.php';

function out($payload, int $code = 200) {
  http_response_code($code);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  if (!($pdo instanceof PDO)) out(['ok'=>false,'error'=>'DB no es PDO'], 500);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

  // Leer body
  $raw = file_get_contents('php://input') ?: '';
  $in  = json_decode($raw, true);
  if (!is_array($in)) out(['ok'=>false,'error'=>'Payload inválido'], 400);

  // Carrito
  $items = $in['items'] ?? [];
  if (!is_array($items) || !$items) out(['ok'=>false,'error'=>'Carrito vacío'], 400);

  // Identidad
  $cid   = (int)($_SESSION['customer_id'] ?? 0);
  $email = strtolower(trim((string)($_SESSION['customer_email'] ?? ($in['email'] ?? ''))));
  if ($cid <= 0 && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    out(['ok'=>false,'error'=>'Email inválido'], 400);
  }

  // Buscar/crear cliente por email si no hay id
  if ($cid <= 0) {
    $st = $pdo->prepare("SELECT id FROM customers WHERE email=? LIMIT 1");
    $st->execute([$email]);
    $cid = (int)($st->fetchColumn() ?: 0);
    if ($cid <= 0) {
      $pdo->prepare("INSERT INTO customers (email, name, phone, address) VALUES (?,?,?,?)")
          ->execute([
            $email,
            trim((string)($in['first_name'] ?? '')),
            trim((string)($in['phone'] ?? '')),
            trim((string)($in['address'] ?? '')),
          ]);
      $cid = (int)$pdo->lastInsertId();
    }
    $_SESSION['customer_id']    = $cid;
    $_SESSION['customer_email'] = $email;
  }

  // Transacción
  $pdo->beginTransaction();

  // Crear pedido (status en minúscula o como uses en tu panel)
  $pdo->prepare("INSERT INTO orders (customer_id, customer_email, status, created_at)
                 VALUES (?, ?, 'pending', NOW())")
      ->execute([$cid, $email]);
  $orderId = (int)$pdo->lastInsertId();

  // Preparados
  $getV = $pdo->prepare("
    SELECT v.id AS variant_id, v.product_id, v.stock, p.price
    FROM product_variants v
    JOIN products p ON p.id = v.product_id
    WHERE v.id = ?
    FOR UPDATE
  ");
  $decStock = $pdo->prepare("
    UPDATE product_variants
    SET stock = stock - ?
    WHERE id = ? AND stock >= ?
  ");
  $insItem = $pdo->prepare("
    INSERT INTO order_items (order_id, product_id, variant_id, qty, price_unit, price)
    VALUES (?, ?, ?, ?, ?, ?)
  ");
  $insMov = $pdo->prepare("
    INSERT INTO stock_movements (variant_id, delta, reason, order_id, created_at)
    VALUES (?, ?, 'order', ?, NOW())
  ");

  // Ítems
  foreach ($items as $it) {
    $vid = (int)($it['variant_id'] ?? 0);
    $qty = max(1, (int)($it['qty'] ?? 0));
    if ($vid <= 0 || $qty <= 0) throw new RuntimeException('Ítem inválido');

    $getV->execute([$vid]);
    $row = $getV->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException("La variante $vid no existe");

    $stock  = (int)$row['stock'];
    $pid    = (int)$row['product_id'];
    $punit  = (float)($row['price'] ?? 0);
    if ($punit <= 0) throw new RuntimeException("Precio inválido para la variante $vid");

    // Descontar stock atómico
    $decStock->execute([$qty, $vid, $qty]);
    if ($decStock->rowCount() === 0) throw new RuntimeException("Sin stock suficiente para la variante $vid (disp: $stock)");

    // Guardar ítem (subtotal en price)
    $subtotal = $qty * $punit;
    $insItem->execute([$orderId, $pid, $vid, $qty, $punit, $subtotal]);

    // Movimiento de stock (salida por venta)
    $insMov->execute([$vid, -$qty, $orderId]);
  }

  // Recalcular total (posicionales para evitar HY093)
  $recalc = $pdo->prepare("
    UPDATE orders o
    JOIN (
      SELECT CAST(order_id AS SIGNED) AS oid,
             SUM(CASE WHEN price_unit IS NOT NULL AND price_unit > 0
                      THEN qty * price_unit ELSE price END) AS total_calc
      FROM order_items
      WHERE CAST(order_id AS SIGNED) = ?
      GROUP BY order_id
    ) t ON t.oid = o.id
    SET o.total = t.total_calc
    WHERE o.id = ?
  ");
  $recalc->execute([$orderId, $orderId]);

  $pdo->commit();
  out(['ok'=>true,'order_id'=>$orderId,'status'=>'pending']);

} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  out(['ok'=>false,'error'=>'Error al finalizar la compra: '.$e->getMessage()], 500);
}

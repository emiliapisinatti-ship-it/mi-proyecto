<?php
// api/checkout.php  (drop-in)
// Crea el pedido, descuenta stock y NO usa first_name en DB.
// Si existe customers.last_name lo usa; si no, concatena en customers.name.

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

session_start();
require __DIR__ . '/db.php';

function json_out($arr, int $code = 200) {
  http_response_code($code);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  exit;
}

// Reemplaza a la versión con SHOW COLUMNS
function has_col(PDO $pdo, string $table, string $col): bool {
  $sql = "SELECT 1
          FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME  = ?
            AND COLUMN_NAME = ?
          LIMIT 1";
  $st = $pdo->prepare($sql);
  $st->execute([$table, $col]);
  return (bool)$st->fetchColumn();
}

try {
  $raw = file_get_contents('php://input') ?: '';
  $in  = json_decode($raw, true);
  if (!is_array($in)) $in = [];

  /* ===== (PUNTO 5) Priorizar sesión si existe ===== */
  $cid   = (int)($_SESSION['customer_id'] ?? 0);
  // Si hay email en la sesión, ese manda; si no, tomo el del payload
  $email = strtolower(trim((string)($_SESSION['customer_email'] ?? ($in['email'] ?? ''))));

  // ===== Validaciones mínimas =====
  // Si NO hay sesión, el email del payload es obligatorio y válido
  if ($cid <= 0 && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_out(['ok'=>false, 'error'=>'Email inválido'], 400);
  }
  $items = $in['items'] ?? [];
  if (!is_array($items) || !$items) {
    json_out(['ok'=>false, 'error'=>'Carrito vacío'], 400);
  }

  // Campos opcionales que vienen del form
  $first = trim((string)($in['first_name'] ?? ''));
  $last  = trim((string)($in['last_name']  ?? ''));
  $nameFromForm = trim((string)($in['name'] ?? '')); // por si tu form manda "name"
  $phone = trim((string)($in['phone'] ?? ''));
  $addr  = trim((string)($in['address'] ?? ''));

  // Detecto columnas reales en tu BD
  $hasLast = has_col($pdo, 'customers', 'last_name'); // last_name opcional
  // first_name no existe; NO lo usaremos en SQL

  // ===== Busco o creo el cliente =====
  if ($cid <= 0) {
    $st = $pdo->prepare("SELECT id FROM customers WHERE email=? LIMIT 1");
    $st->execute([$email]);
    $cid = (int)($st->fetchColumn() ?: 0);
  }

  // Preparo valores a guardar en customers
  // Si tu tabla NO tiene last_name, guardo todo en customers.name
  $nameToSave = $nameFromForm;
  if ($nameToSave === '') {
    $nameToSave = trim($first . ($last ? " $last" : ''));
  }
  if ($nameToSave === '') $nameToSave = null;

  if ($cid > 0) {
    // Update de datos básicos del cliente (no toca password)
    if ($hasLast) {
      $sql = "UPDATE customers SET name=?, last_name=?, phone=?, address=?, email=? WHERE id=?";
      $pdo->prepare($sql)->execute([$first ?: ($nameToSave ?: null), $last ?: null, $phone ?: null, $addr ?: null, $email, $cid]);
    } else {
      $sql = "UPDATE customers SET name=?, phone=?, address=?, email=? WHERE id=?";
      $pdo->prepare($sql)->execute([$nameToSave, $phone ?: null, $addr ?: null, $email, $cid]);
    }
  } else {
    // Insert nuevo cliente
    if ($hasLast) {
      $sql = "INSERT INTO customers (email, name, last_name, phone, address) VALUES (?,?,?,?,?)";
      $pdo->prepare($sql)->execute([$email, $first ?: ($nameToSave ?: null), $last ?: null, $phone ?: null, $addr ?: null]);
    } else {
      $sql = "INSERT INTO customers (email, name, phone, address) VALUES (?,?,?,?)";
      $pdo->prepare($sql)->execute([$email, $nameToSave, $phone ?: null, $addr ?: null]);
    }
    $cid = (int)$pdo->lastInsertId();
    // Dejo logueado al nuevo cliente
    $_SESSION['customer_id']    = $cid;
    $_SESSION['customer_email'] = $email;
  }

  // ===== Creo el pedido + descuento de stock =====
  $pdo->beginTransaction();

  // 1) Pedido
  // Estructura genérica: orders(id, customer_id, customer_email, status, created_at...)
  $st = $pdo->prepare("INSERT INTO orders (customer_id, customer_email, status) VALUES (?, ?, 'pending')");
  $st->execute([$cid ?: null, $email]);
  $orderId = (int)$pdo->lastInsertId();

  // 2) Ítems: valido y descuento stock con lock
  $getV = $pdo->prepare("
    SELECT v.id AS variant_id, v.stock, p.price
    FROM product_variants v
    JOIN products p ON p.id = v.product_id
    WHERE v.id = ? FOR UPDATE
  ");
  $updStock = $pdo->prepare("UPDATE product_variants SET stock = stock - ? WHERE id = ?");
  $insItem  = $pdo->prepare("INSERT INTO order_items (order_id, variant_id, qty, price_unit) VALUES (?, ?, ?, ?)");

  foreach ($items as $it) {
    $vid = (int)($it['variant_id'] ?? 0);
    $qty = max(1, (int)($it['qty'] ?? 0));
    if ($vid <= 0 || $qty <= 0) throw new RuntimeException('Ítem inválido');

    $getV->execute([$vid]);
    $row = $getV->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException("Variante $vid no existe");

    $stock = (int)$row['stock'];
    if ($stock < $qty) throw new RuntimeException("Sin stock para variante $vid");

    $updStock->execute([$qty, $vid]);
    $insItem->execute([$orderId, $vid, $qty, (float)$row['price']]);
  }

  $pdo->commit();

  json_out(['ok'=>true, 'order_id'=>$orderId]);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  json_out(['ok'=>false, 'error'=>'Error al finalizar la compra: '.$e->getMessage()], 500);
}

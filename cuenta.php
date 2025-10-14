<?php
declare(strict_types=1);
session_start();

if (empty($_SESSION['customer_id']) && empty($_SESSION['cliente_id'])) {
  header('Location: cliente_login.php'); exit;
}

require __DIR__ . '/api/db.php';

$cid   = (int)($_SESSION['customer_id'] ?? $_SESSION['cliente_id'] ?? 0);
$email = (string)($_SESSION['customer_email'] ?? $_SESSION['cliente_email'] ?? '');

/* -------- Datos actuales del cliente -------- */
$st = $pdo->prepare("SHOW COLUMNS FROM customers LIKE 'last_name'");
$st->execute();
$hasLastName = (bool)$st->fetch();

if ($hasLastName) {
  $st = $pdo->prepare("SELECT email, name, last_name, phone, address FROM customers WHERE id = ?");
} else {
  // compatibilidad si aún no agregaste la columna
  $st = $pdo->prepare("SELECT email, name, '' AS last_name, phone, address FROM customers WHERE id = ?");
}
$st->execute([$cid]);
$me = $st->fetch(PDO::FETCH_ASSOC) ?: [
  'email'=>'','name'=>'','last_name'=>'','phone'=>'','address'=>''
];

$msg = $err = '';

/* -------- Guardar perfil -------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['profile_save'])) {
  try {
    $name      = trim((string)($_POST['name']      ?? ''));
    $last_name = trim((string)($_POST['last_name'] ?? ''));
    $newEmail  = trim((string)($_POST['email']     ?? ''));
    $phone     = trim((string)($_POST['phone']     ?? ''));
    $address   = trim((string)($_POST['address']   ?? ''));

    if ($name === '') throw new Exception('El nombre es obligatorio.');
    if ($newEmail === '' || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
      throw new Exception('Email inválido.');
    }

    if ($hasLastName) {
      $sql = "UPDATE customers SET name=?, last_name=?, email=?, phone=?, address=? WHERE id=?";
      $params = [$name, $last_name, $newEmail, $phone, $address, $cid];
    } else {
      $sql = "UPDATE customers SET name=?, email=?, phone=?, address=? WHERE id=?";
      $params = [$name, $newEmail, $phone, $address, $cid];
    }

    $u = $pdo->prepare($sql);
    $u->execute($params);

    // actualizo sesión y orders.customer_email para mantener vínculo
    $_SESSION['customer_email'] = $newEmail;
    $pdo->prepare("UPDATE orders SET customer_email=? WHERE customer_id=?")->execute([$newEmail, $cid]);

    // refresco en memoria
    $me['name']      = $name;
    $me['last_name'] = $last_name;
    $me['email']     = $newEmail;
    $me['phone']     = $phone;
    $me['address']   = $address;

    $msg = 'Datos actualizados correctamente.';
  } catch (Throwable $e) {
    if (strpos($e->getMessage(), '1062') !== false) {
      $err = 'Ese email ya está en uso.';
    } else {
      $err = 'Error al guardar: ' . $e->getMessage();
    }
  }
}

/* -------- Listado de pedidos (total robusto) -------- */
$sql = "
  SELECT o.id, o.created_at, o.status,
         COALESCE(SUM(oi.qty * COALESCE(oi.price_unit, oi.price, 0)), 0) AS total
  FROM orders o
  LEFT JOIN order_items oi ON oi.order_id = o.id
  WHERE (o.customer_id = :cid) OR (o.customer_id IS NULL AND o.customer_email = :email)
  GROUP BY o.id
  ORDER BY o.created_at DESC
";

$st = $pdo->prepare($sql);
$st->execute([':cid' => $cid, ':email' => $email]);
$orders = $st->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Mi cuenta • E&amp;S</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen">
  <div class="max-w-3xl mx-auto p-6">
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-2xl font-bold">Mi cuenta</h1>
      <a class="underline text-sm" href="cliente_logout.php">Cerrar sesión</a>
    </div>

    <?php if ($msg): ?>
      <div class="mb-4 rounded-lg bg-green-100 text-green-800 px-3 py-2"><?= htmlspecialchars((string)$msg) ?></div>
    <?php endif; ?>
    <?php if ($err): ?>
      <div class="mb-4 rounded-lg bg-red-100 text-red-800 px-3 py-2"><?= htmlspecialchars((string)$err) ?></div>
    <?php endif; ?>

    <!-- Perfil -->
    <div class="bg-white rounded-xl shadow p-4 mb-6">
      <form method="post" class="grid md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm text-slate-600 mb-1">Nombre</label>
          <input class="input input-bordered w-full" type="text" name="name" required
                 value="<?= htmlspecialchars((string)$me['name']) ?>">
        </div>
        <div>
          <label class="block text-sm text-slate-600 mb-1">Apellido</label>
          <input class="input input-bordered w-full" type="text" name="last_name"
                 value="<?= htmlspecialchars((string)$me['last_name']) ?>">
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm text-slate-600 mb-1">Email</label>
          <input class="input input-bordered w-full" type="email" name="email" required
                 value="<?= htmlspecialchars((string)($me['email'] ?: $email)) ?>">
        </div>
        <div>
          <label class="block text-sm text-slate-600 mb-1">Teléfono</label>
          <input class="input input-bordered w-full" type="text" name="phone"
                 value="<?= htmlspecialchars((string)$me['phone']) ?>">
        </div>
        <div>
          <label class="block text-sm text-slate-600 mb-1">Dirección</label>
          <input class="input input-bordered w-full" type="text" name="address"
                 value="<?= htmlspecialchars((string)$me['address']) ?>">
        </div>
        <div class="md:col-span-2 flex justify-end gap-2">
          <button class="btn" type="submit" name="profile_save" value="1">Guardar cambios</button>
        </div>
      </form>
    </div>

    <!-- Pedidos -->
    <div class="bg-white rounded-xl shadow p-4">
      <h2 class="font-semibold mb-2">Mis pedidos</h2>

      <?php if (!$orders): ?>
        <p class="text-slate-500">Aún no hay pedidos.</p>
      <?php else: ?>
        <ul class="space-y-4">
          <?php foreach ($orders as $o): ?>
            <li class="border p-3 rounded-lg">
              <div class="flex items-center justify-between">
                <div>
                  <div><strong>#<?= (int)$o['id'] ?></strong> · <?= htmlspecialchars((string)$o['status']) ?></div>
                  <div class="text-slate-500 text-sm"><?= date('d/m/Y H:i', strtotime($o['created_at'])) ?></div>
                </div>
                <div class="font-semibold">ARS <?= number_format((float)$o['total'], 0, ',', '.') ?></div>
              </div>

              <?php
              // Ítems con precio robusto (price_unit o price)
              $it = $pdo->prepare("
                SELECT
                  p.name,
                  v.size,
                  v.color,
                  oi.qty,
                  COALESCE(oi.price_unit, oi.price, 0) AS price
                FROM order_items oi
                JOIN product_variants v ON v.id = oi.variant_id
                JOIN products p        ON p.id = v.product_id
                WHERE oi.order_id = ?
              ");
              $it->execute([(int)$o['id']]);
              $items = $it->fetchAll(PDO::FETCH_ASSOC);
              ?>

              <?php if ($items): ?>
                <details class="mt-2">
                  <summary class="cursor-pointer text-sm text-slate-600">Ver ítems</summary>
                  <ul class="mt-2 space-y-1 text-sm">
                    <?php foreach ($items as $row): ?>
                      <li>
                        <?= htmlspecialchars((string)$row['name']) ?>
                        <?= $row['color'] ? ' · '.htmlspecialchars((string)$row['color']) : '' ?>
                        <?= $row['size']  ? ' · Talle '.htmlspecialchars((string)$row['size']) : '' ?>
                        — x<?= (int)$row['qty'] ?>
                        · ARS <?= number_format((float)$row['price'], 0, ',', '.') ?>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                </details>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>

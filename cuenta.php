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

/* -------- Chips de estado -------- */
function render_status_chip(string $status): string {
  $s = strtolower($status);
  // paleta suave
  $map = [
    'pending'   => ['Pendiente', 'chip chip--pendiente'],
    'shipped'   => ['Enviado',   'chip chip--enviado'],
    'cancelled' => ['Cancelado', 'chip chip--cancelado'],
  ];
  $label = $map[$s][0] ?? ucfirst($s);
  $cls   = $map[$s][1] ?? 'chip';
  return '<span class="'.$cls.'">'.$label.'</span>';
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Mi cuenta • E&amp;S</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css">
  <script src="https://cdn.tailwindcss.com"></script>

  <style>
  /* ==== Paleta clara y suave (compartida con checkout) ==== */
  :root{
    --accent:   #7a4545;
    --accent2:  #9b8681;
    --panel:    #f7f9fc;    /* tarjetas claritas */
    --panel-br: #e5e7ef;    /* borde suave */
    --ink:      #0f172a;    /* texto oscuro */
  }

  
  /* Fondo gris medio oscuro, sin ser negro */
.account-page{
  background:#a1a098;   /* gris medio-oscuro */
  color:var(--ink);
}

/* Ajusto las tarjetas para que destaquen sobre el fondo */
.card-accent{
  background:#f7f9fc;
  border:1px solid #d1d5db;
  color:#111827;
  border-radius:14px;
  box-shadow:0 8px 25px rgba(239, 238, 238, 0.25);
}


  /* Título con toque de color, sutil */
  h1{
    background:linear-gradient(90deg,var(--accent),var(--accent2));
    -webkit-background-clip:text; background-clip:text; color:transparent;
  }

  /* Tarjetas */
  .card-accent{
    background:var(--panel);
    border:1px solid var(--panel-br);
    color:var(--ink);
    border-radius:14px;
    box-shadow:0 6px 20px rgba(0,0,0,.06);
  }

  /* Labels + inputs */
  .card-accent label{ color:#475569; font-weight:600; }
  .card-accent .input,
  .card-accent input,
  .card-accent textarea,
  .card-accent select{
    background:#fff !important;
    border:1px solid #cbd5e1 !important;
    color:#0f172a !important;
    border-radius:12px;
  }
  .card-accent .input::placeholder{ color:#94a3b8; }
  .card-accent .input:focus{
    outline:none;
    border-color:#94a3b8 !important;
    box-shadow:0 0 0 3px rgba(148,163,184,.25);
  }

  /* Botón principal (acento suave) */
  .btn-primary{
    background:linear-gradient(135deg,var(--accent),var(--accent2));
    border:0; color:#fff; border-radius:12px;
  }
  .btn-primary:hover{ filter:brightness(1.03); }

  /* Chips de estado (suaves) */
  .chip{ display:inline-block; padding:.28rem .6rem; border-radius:999px; font-weight:700; font-size:.9rem; }
  .chip--pendiente{ background:#f2c97a; color:#2a1d08; }
  .chip--enviado{   background:#8bd3b0; color:#103327; }
  .chip--cancelado{ background:#f29b9b; color:#341010; }

  /* Total visible pero no chillón */
  .order-total{ font-weight:800; color:#0f172a; }

  /* Fecha y “Ver ítems” más apagados */
  .order-date, .order-toggle{ color:#708199; opacity:.9; font-size:.92rem; }
  .order-toggle:hover{ opacity:1; text-decoration:underline; }

  /* Bordes internos */
  .card-accent .border,
  .card-accent .divide-y > *{ border-color:#e5e7eb; }
  /* Título claro sobre fondo oscuro */
.account-page h1{
  /* opción A: texto sólido claro */
  color:#f3f4f6;                  /* bien legible */
  background:none;                /* anula el degradé anterior */
  text-shadow:0 1px 0 rgba(0,0,0,.3);
  letter-spacing:.2px;
}

/* Si preferís mantener degradé, comenta lo de arriba y usá esta opción B:
.account-page h1{
  background:linear-gradient(90deg,#ffd7cc,#ffe7d9);
  -webkit-background-clip:text; background-clip:text; color:transparent;
  text-shadow:0 1px 0 rgba(0,0,0,.35);
}
*/

/* Link “Cerrar sesión” visible */
.account-page .logout-link{
  color:#e5e7eb;                  /* claro */
  font-weight:600;
  text-underline-offset:2px;
}
.account-page .logout-link:hover{ color:#ffffff; }
.account-page .logout-link:focus-visible{
  outline:2px solid #ffffff; outline-offset:2px; border-radius:4px;
}

  </style>
</head>

<body class="account-page">
  <div class="max-w-3xl mx-auto p-6">
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-2xl font-bold">Mi cuenta</h1>
      <a class="underline text-sm logout-link" href="cliente_logout.php">Cerrar sesión</a>
    </div>

    <?php if ($msg): ?>
      <div class="mb-4 rounded-lg bg-green-100 text-green-800 px-3 py-2"><?= htmlspecialchars((string)$msg) ?></div>
    <?php endif; ?>
    <?php if ($err): ?>
      <div class="mb-4 rounded-lg bg-red-100 text-red-800 px-3 py-2"><?= htmlspecialchars((string)$err) ?></div>
    <?php endif; ?>

    <!-- Perfil -->
    <div class="card-accent p-5 mb-6">
      <form method="post" class="grid md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm mb-1">Nombre</label>
          <input class="input input-bordered w-full" type="text" name="name" required
                 value="<?= htmlspecialchars((string)$me['name']) ?>">
        </div>
        <div>
          <label class="block text-sm mb-1">Apellido</label>
          <input class="input input-bordered w-full" type="text" name="last_name"
                 value="<?= htmlspecialchars((string)$me['last_name']) ?>">
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm mb-1">Email</label>
          <input class="input input-bordered w-full" type="email" name="email" required
                 value="<?= htmlspecialchars((string)($me['email'] ?: $email)) ?>">
        </div>
        <div>
          <label class="block text-sm mb-1">Teléfono</label>
          <input class="input input-bordered w-full" type="text" name="phone"
                 value="<?= htmlspecialchars((string)$me['phone']) ?>">
        </div>
        <div>
          <label class="block text-sm mb-1">Dirección</label>
          <input class="input input-bordered w-full" type="text" name="address"
                 value="<?= htmlspecialchars((string)$me['address']) ?>">
        </div>
        <div class="md:col-span-2 flex justify-end gap-2">
          <button class="btn btn-primary" type="submit" name="profile_save" value="1">Guardar cambios</button>
        </div>
      </form>
    </div>

    <!-- Pedidos -->
    <div class="card-accent p-5">
      <h2 class="font-semibold mb-2">Mis pedidos</h2>

      <?php if (!$orders): ?>
        <p class="text-slate-600">Aún no hay pedidos.</p>
      <?php else: ?>
        <ul class="space-y-4">
          <?php foreach ($orders as $o): ?>
            <li class="border p-3 rounded-lg">
              <div class="flex items-center justify-between">
                <div>
                  <div>
                    <strong>#<?= (int)$o['id'] ?></strong>
                    · <?= render_status_chip((string)$o['status']) ?>
                  </div>
                  <div class="order-date"><?= date('d/m/Y H:i', strtotime($o['created_at'])) ?></div>
                </div>
                <div class="order-total">
                  ARS <?= number_format((float)$o['total'], 0, ',', '.') ?>
                </div>
              </div>

              <?php
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
                  <summary class="order-toggle">Ver ítems</summary>
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

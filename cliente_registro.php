<?php
// cliente_registro.php — Alta de cliente con diseño + login automático
declare(strict_types=1);

session_set_cookie_params([
  'lifetime' => 0,
  'path'     => '/',
  'domain'   => 'localhost',
  'secure'   => false,   // true si servís por https
  'httponly' => true,
  'samesite' => 'Lax',
]);
session_start();

require __DIR__ . '/api/db.php';

// -------- util: detectar columnas opcionales --------
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

$next = isset($_GET['next']) ? (string)$_GET['next'] : '';
if ($next === '' && !empty($_POST['next'])) $next = (string)$_POST['next'];

$msg = '';
$err = '';

$hasLast = has_col($pdo, 'customers', 'last_name');   // opcional
$hasAddr = has_col($pdo, 'customers', 'address');     // opcional
$hasPhone= has_col($pdo, 'customers', 'phone');       // opcional

// CSRF
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

// Si ya está logueado, lo mando a destino
if (!empty($_SESSION['customer_id'])) {
  header('Location: ' . ($next ?: 'cuenta.php'));
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_register'])) {
  try {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
      throw new Exception('Token inválido. Refrescá la página.');
    }

    // honeypot anti-bot
    if (!empty($_POST['company'])) {
      throw new Exception('Detenido por verificación antispam.');
    }

    $name  = trim((string)($_POST['name'] ?? ''));
    $last  = trim((string)($_POST['last_name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $pass1 = (string)($_POST['password'] ?? '');
    $pass2 = (string)($_POST['password2'] ?? '');
    $phone = trim((string)($_POST['phone'] ?? ''));
    $addr  = trim((string)($_POST['address'] ?? ''));

    if ($name === '') throw new Exception('El nombre es obligatorio.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Email inválido.');
    if (strlen($pass1) < 6) throw new Exception('La contraseña debe tener al menos 6 caracteres.');
    if ($pass1 !== $pass2) throw new Exception('Las contraseñas no coinciden.');

    // ¿ya existe email?
    $st = $pdo->prepare("SELECT id FROM customers WHERE email=? LIMIT 1");
    $st->execute([$email]);
    if ($st->fetchColumn()) throw new Exception('Ese email ya está registrado.');

    $hash = password_hash($pass1, PASSWORD_DEFAULT);

    // Insert con columnas que existan en tu tabla
    if ($hasLast && $hasPhone && $hasAddr) {
      $sql = "INSERT INTO customers (email, name, last_name, phone, address, password_hash)
              VALUES (?,?,?,?,?,?)";
      $pdo->prepare($sql)->execute([$email, $name, ($last ?: null), ($phone ?: null), ($addr ?: null), $hash]);
    } elseif ($hasLast && $hasPhone) {
      $sql = "INSERT INTO customers (email, name, last_name, phone, password_hash)
              VALUES (?,?,?,?,?)";
      $pdo->prepare($sql)->execute([$email, $name, ($last ?: null), ($phone ?: null), $hash]);
    } elseif ($hasLast) {
      $sql = "INSERT INTO customers (email, name, last_name, password_hash)
              VALUES (?,?,?,?)";
      $pdo->prepare($sql)->execute([$email, $name, ($last ?: null), $hash]);
    } else {
      // sin last_name -> guardo todo en name si querés
      $sql = "INSERT INTO customers (email, name, password_hash"
           . ($hasPhone ? ", phone" : "")
           . ($hasAddr  ? ", address" : "")
           . ") VALUES (?,?,?"
           . ($hasPhone ? ",?" : "")
           . ($hasAddr  ? ",?" : "")
           . ")";
      $params = [$email, trim($name . ($last ? " $last" : "")), $hash];
      if ($hasPhone) $params[] = ($phone ?: null);
      if ($hasAddr)  $params[] = ($addr  ?: null);
      $pdo->prepare($sql)->execute($params);
    }

    // Login automático
    $cid = (int)$pdo->lastInsertId();
    $_SESSION['customer_id']    = $cid;
    $_SESSION['customer_email'] = $email;
    $_SESSION['customer_name']  = $name;

    // Destino seguro (sólo rutas relativas)
    $goto = 'cuenta.php';
    if ($next && preg_match('~^/[a-zA-Z0-9/_\-.?=&%]*$~', '/' . ltrim($next, '/'))) {
      $goto = ltrim($next, '/');
    }
    header('Location: ' . $goto);
    exit;

  } catch (Throwable $e) {
    $err = $e->getMessage();
  }
}

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Crear cuenta • E&amp;S</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet">

  <style>
    :root { --brand:#7a4545; }
    body{
      min-height:100svh;
      background:
        radial-gradient(1200px 700px at 80% -10%, rgba(227, 227, 227, 0.2) 0%, transparent 60%),
        radial-gradient(900px 600px at -10% 100%, rgba(182, 135, 135, 0.33) 0%, transparent 60%),
        #a1a098;;
    }
    .glass-card{
      background: rgba(255,255,255,.92);
      -webkit-backdrop-filter: blur(10px);
      backdrop-filter: blur(10px);
      border:1px solid rgba(255,255,255,.4);
      box-shadow:0 20px 60px rgba(243, 229, 229, 0.25);
    }
    .brand{
      font-family:Verdana, Geneva, Tahoma, sans-serif;
      letter-spacing:.08em;
      color:var(--brand);
    }
    .focus-brand:focus{
      outline:2px solid rgba(122,69,69,.45);
      outline-offset:2px;
    }
 
  </style>
</head>
<body class="grid place-items-center">
<body class="signup-page">

  <nav class="fixed top-4 left-4">
    <a href="cliente_login.php<?= $next ? ('?next='.urlencode($next)) : '' ?>"
       class="text-white no-underline px-3 py-1.5 rounded-full border border-white/20 bg-black/30 hover:bg-black/45 backdrop-blur">
      ← Ya tengo cuenta
    </a>
  </nav>

  <section class="glass-card w-[min(520px,94vw)] rounded-2xl overflow-hidden">
    <header class="px-8 pt-8 pb-3 text-center">
      <div class="text-4xl font-bold brand">E&amp;S</div>
      <h1 class="mt-2 text-xl font-semibold text-gray-900">Crear cuenta</h1>
    </header>

    <?php if ($err): ?>
      <div class="mx-8 mb-2 alert alert-error text-sm"><span><?= htmlspecialchars($err) ?></span></div>
    <?php endif; ?>
    <?php if ($msg): ?>
      <div class="mx-8 mb-2 alert alert-success text-sm"><span><?= htmlspecialchars($msg) ?></span></div>
    <?php endif; ?>

    <form class="px-8 pb-8 pt-3 grid gap-4" method="post" novalidate>
      <input type="hidden" name="do_register" value="1">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
      <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>">
      <!-- honeypot -->
      <input type="text" name="company" value="" style="position:absolute;left:-9999px;opacity:0" tabindex="-1" autocomplete="off">

      <div class="grid md:grid-cols-2 gap-4">
        <label class="form-control">
          <div class="label"><span class="label-text">Nombre</span></div>
          <input class="input input-bordered focus-brand" type="text" name="name" required
                 value="<?= htmlspecialchars((string)($_POST['name'] ?? '')) ?>">
        </label>

        <label class="form-control">
          <div class="label"><span class="label-text">Apellido</span></div>
          <input class="input input-bordered focus-brand" type="text" name="last_name"
                 value="<?= htmlspecialchars((string)($_POST['last_name'] ?? '')) ?>"
                 <?= $hasLast ? '' : 'placeholder="(opcional — tu tabla no lo requiere)"' ?>>
        </label>
      </div>

      <label class="form-control">
        <div class="label"><span class="label-text">Correo electrónico</span></div>
        <input class="input input-bordered focus-brand" type="email" name="email" required autocomplete="email"
               value="<?= htmlspecialchars((string)($_POST['email'] ?? '')) ?>">
      </label>

      <div class="grid md:grid-cols-2 gap-4">
        <label class="form-control">
          <div class="label"><span class="label-text">Contraseña</span></div>
          <input id="p1" class="input input-bordered focus-brand" type="password" name="password" required minlength="6" autocomplete="new-password">
        </label>
        <label class="form-control">
          <div class="label"><span class="label-text">Repetir contraseña</span></div>
          <input id="p2" class="input input-bordered focus-brand" type="password" name="password2" required minlength="6" autocomplete="new-password">
        </label>
      </div>

      <div class="grid md:grid-cols-2 gap-4">
        <label class="form-control">
          <div class="label"><span class="label-text">Teléfono</span></div>
          <input class="input input-bordered focus-brand" type="text" name="phone"
                 value="<?= htmlspecialchars((string)($_POST['phone'] ?? '')) ?>"
                 <?= $hasPhone ? '' : 'placeholder="(opcional)"' ?>>
        </label>
        <label class="form-control">
          <div class="label"><span class="label-text">Dirección</span></div>
          <input class="input input-bordered focus-brand" type="text" name="address"
                 value="<?= htmlspecialchars((string)($_POST['address'] ?? '')) ?>"
                 <?= $hasAddr ? '' : 'placeholder="(opcional)"' ?>>
        </label>
      </div>

      <button class="btn w-full text-white" style="background:var(--brand);">Crear cuenta</button>
    </form>
  </section>

  <script>
    // Validación simple de contraseñas en el cliente
    const p1 = document.getElementById('p1');
    const p2 = document.getElementById('p2');
    [p1,p2].forEach(el => el?.addEventListener('input', ()=>{
      if (p1.value && p2.value && p1.value !== p2.value) {
        p2.setCustomValidity('Las contraseñas no coinciden');
      } else {
        p2.setCustomValidity('');
      }
    }));
  </script>
</body>
</html>

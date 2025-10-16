<?php
// cliente_login.php — versión bonita con Tailwind/DaisyUI
declare(strict_types=1);

// cookies seguras (ajustá domain/secure si lo servís con https o dominio real)
session_set_cookie_params([
  'lifetime' => 0,
  'path'     => '/',
  'domain'   => 'localhost',
  'secure'   => false,     // poné true si usás https
  'httponly' => true,
  'samesite' => 'Lax',
]);
session_start();

require __DIR__ . '/api/db.php';

$next = isset($_GET['next']) ? (string)$_GET['next'] : '';
if ($next === '' && !empty($_POST['next'])) $next = (string)$_POST['next'];

// Si ya está logueado, lo mando directo
if (!empty($_SESSION['customer_id'])) {
  header('Location: ' . ($next ?: 'cuenta.php'));
  exit;
}

$msg = '';
$err = '';

// CSRF simple
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_login'])) {
  try {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
      throw new Exception('Token inválido. Refrescá la página.');
    }

    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $pass  = (string)($_POST['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      throw new Exception('Ingresá un email válido.');
    }
    if (strlen($pass) < 6) {
      throw new Exception('La contraseña debe tener al menos 6 caracteres.');
    }

    // Buscar cliente
    $st = $pdo->prepare("SELECT id, email, password_hash, name FROM customers WHERE email = ? LIMIT 1");
    $st->execute([$email]);
    $u = $st->fetch(PDO::FETCH_ASSOC);

    if (!$u || !password_verify($pass, (string)($u['password_hash'] ?? ''))) {
      // Mensaje genérico para no filtrar información
      throw new Exception('Correo o contraseña incorrectos.');
    }

    // OK -> sesión
    $_SESSION['customer_id']    = (int)$u['id'];
    $_SESSION['customer_email'] = (string)$u['email'];
    $_SESSION['customer_name']  = (string)($u['name'] ?? '');

    // Redirección segura (evitamos URLs absolutas externas)
    $goto = 'cuenta.php';
    if ($next) {
      // permitimos solo rutas relativas
      if (preg_match('~^/[a-zA-Z0-9/_\-.?=&%]*$~', '/' . ltrim($next, '/'))) {
        $goto = ltrim($next, '/');
      }
    }

    header('Location: ' . $goto);
    exit;

  } catch (Throwable $e) {
    $err = $e->getMessage();
  }
}

// Para “¿Olvidaste tu contraseña?” decidí acá si mostrar el link
$hasReset = is_file(__DIR__ . '/request_reset.php');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Iniciar sesión • E&amp;S</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Tailwind + DaisyUI por CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet">

  <style>
    :root { --brand: #7a4545; }
    body{
      min-height:100svh;
      background:
        radial-gradient(1200px 700px at 80% -10%, rgba(122,69,69,.20) 0%, transparent 60%),
        radial-gradient(900px 600px at -10% 100%, rgba(122,69,69,.33) 0%, transparent 60%),
   #a1a098; ;
    }
    .glass-card{
      background: rgba(255,255,255,.92);
      -webkit-backdrop-filter: blur(10px);
      backdrop-filter: blur(10px);
      border:1px solid rgba(255,255,255,.4);
      box-shadow:0 20px 60px rgba(0,0,0,.25);
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

  <!-- Botón volver -->
  <nav class="fixed top-4 left-4">
    <a href="index.html"
       class="text-white no-underline px-3 py-1.5 rounded-full border border-white/20 bg-black/30 hover:bg-black/45 backdrop-blur">
      ← Volver
    </a>
  </nav>

  <section class="glass-card w-[min(420px,92vw)] rounded-2xl overflow-hidden">
    <header class="px-8 pt-8 pb-3 text-center">
      <div class="text-4xl font-bold brand">E&amp;S</div>
      <h1 class="mt-2 text-xl font-semibold text-gray-900">Iniciá sesión</h1>
    </header>

    <?php if ($err): ?>
      <div class="mx-8 mb-2 alert alert-error text-sm">
        <span><?= htmlspecialchars($err) ?></span>
      </div>
    <?php endif; ?>
    <?php if ($msg): ?>
      <div class="mx-8 mb-2 alert alert-success text-sm">
        <span><?= htmlspecialchars($msg) ?></span>
      </div>
    <?php endif; ?>

    <form class="px-8 pb-7 pt-3 space-y-4" method="post" novalidate>
      <input type="hidden" name="do_login" value="1">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
      <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>">

      <label class="form-control">
        <div class="label"><span class="label-text">Correo electrónico</span></div>
        <input class="input input-bordered focus-brand"
               type="email" name="email" required autocomplete="email"
               placeholder="tucorreo@ejemplo.com"
               value="<?= htmlspecialchars((string)($_POST['email'] ?? '')) ?>">
      </label>

      <label class="form-control">
        <div class="label"><span class="label-text">Contraseña</span></div>
        <div class="relative">
          <input id="password" class="input input-bordered w-full pr-10 focus-brand"
                 type="password" name="password" required minlength="6" autocomplete="current-password"
                 placeholder="••••••••">
          <button type="button" id="togglePass"
                  class="absolute right-2 top-1/2 -translate-y-1/2 btn btn-xs btn-ghost"
                  aria-label="Mostrar u ocultar contraseña">👁</button>
        </div>
      </label>

      <div class="flex items-center justify-between text-sm">
        <label class="label cursor-pointer gap-2">
          <input type="checkbox" class="checkbox checkbox-sm" name="remember" value="1">
          <span class="label-text">Recordarme</span>
        </label>

        <?php if ($hasReset): ?>
          <a class="link link-hover text-[var(--brand)] font-semibold" href="request_reset.php">¿Olvidaste tu contraseña?</a>
        <?php else: ?>
          <span class="opacity-50">&nbsp;</span>
        <?php endif; ?>
      </div>

      <button class="btn w-full text-white" style="background:var(--brand);">Entrar</button>

    <p class="text-center text-sm text-gray-600">
  ¿No tenés cuenta?
  <a id="go-register" class="link font-semibold" style="color:var(--brand);" href="#">Crear cuenta</a>
</p>
<script>
  // Enviar a registro preservando ?next=
  (function () {
    const a = document.getElementById('go-register');
    if (!a) return;
    const qs   = new URLSearchParams(location.search);
    const next = qs.get('next') || '';
    a.href = 'cliente_registro.php' + (next ? ('?next=' + encodeURIComponent(next)) : '');
  })();
</script>


  <script>
    // Mostrar/ocultar contraseña
    const pass = document.getElementById('password');
    const tog  = document.getElementById('togglePass');
    tog?.addEventListener('click', ()=>{
      const show = pass.type === 'password';
      pass.type = show ? 'text' : 'password';
      tog.textContent = show ? '🙈' : '👁';
    });
  </script>
</body>
</html>

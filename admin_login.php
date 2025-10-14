<?php
// admin_login.php
declare(strict_types=1);
session_start();
require __DIR__.'/api/db.php';
require __DIR__.'/config.local.php'; // define $ADMIN_GATE y $ADMIN_ALLOWED_IPS

// Gate 1: clave secreta en la URL ?k=...
if (!isset($_GET['k']) || $_GET['k'] !== $ADMIN_GATE) {
  http_response_code(404);
  exit;
}

// Gate 2 (opcional): whitelist de IPs
if (!empty($ADMIN_ALLOWED_IPS) && !in_array($_SERVER['REMOTE_ADDR'] ?? '', $ADMIN_ALLOWED_IPS, true)) {
  http_response_code(403);
  exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  $pass  = (string)($_POST['password'] ?? '');

  $st = $pdo->prepare("SELECT id, password_hash FROM admins WHERE email=? LIMIT 1");
  $st->execute([$email]);
  $u = $st->fetch(PDO::FETCH_ASSOC);

  if ($u && password_verify($pass, $u['password_hash'])) {
    $_SESSION['admin_id'] = (int)$u['id'];
    header('Location: admin.php');
    exit;
  } else {
    $error = 'Usuario o clave incorrectos';
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Login Admin • E&S</title>
  <meta name="robots" content="noindex,nofollow">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen grid place-items-center bg-slate-100">
  <form method="post" class="bg-white p-6 rounded-xl shadow w-full max-w-sm space-y-3">
    <h1 class="text-xl font-semibold">Acceso dueño</h1>
    <input name="email" type="email" class="input input-bordered w-full" placeholder="Email" required>
    <input name="password" type="password" class="input input-bordered w-full" placeholder="Contraseña" required>
    <?php if (!empty($error)): ?>
      <p class="text-red-600 text-sm"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <button class="btn btn-neutral w-full">Ingresar</button>
  </form>
</body>
</html>

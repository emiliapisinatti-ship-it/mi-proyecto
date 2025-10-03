<?php
session_start();
require __DIR__.'/api/db.php';

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  $pass  = $_POST['password'] ?? '';

  $st = $pdo->prepare("SELECT id, password_hash FROM customers WHERE email=?");
  $st->execute([$email]);
  $u = $st->fetch();

  if ($u && password_verify($pass, $u['password_hash'])) {
    $_SESSION['customer_id'] = $u['id'];
    header('Location: cuenta.php'); exit;
  } else {
    $err = 'Email o contraseña incorrectos';
  }
}
?>
<!doctype html><html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Iniciar sesión • E&S</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css">
<script src="https://cdn.tailwindcss.com"></script>
</head><body class="min-h-screen grid place-items-center bg-slate-100">
  <form method="post" class="bg-white p-6 rounded-xl shadow w-full max-w-sm space-y-3">
    <h1 class="text-xl font-semibold">Iniciar sesión</h1>
    <input name="email" type="email" class="input input-bordered w-full" placeholder="Email" required>
    <input name="password" type="password" class="input input-bordered w-full" placeholder="Contraseña" required>
    <?php if ($err): ?><p class="text-red-600 text-sm"><?=htmlspecialchars($err)?></p><?php endif; ?>
    <button class="btn btn-neutral w-full">Entrar</button>
    <p class="text-sm text-center">¿No tenés cuenta? <a href="cliente_registro.php" class="underline">Crear cuenta</a></p>
  </form>
</body></html>

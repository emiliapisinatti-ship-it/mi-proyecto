<?php
session_start();
require __DIR__.'/api/db.php';

$err = ''; $ok = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name  = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $pass  = $_POST['password'] ?? '';

  if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 6) {
    $err = 'Email inválido o contraseña muy corta (mín. 6).';
  } else {
    // ¿ya existe?
    $st = $pdo->prepare("SELECT id FROM customers WHERE email=?");
    $st->execute([$email]);
    if ($st->fetch()) {
      $err = 'Ya existe un usuario con ese email.';
    } else {
      $hash = password_hash($pass, PASSWORD_DEFAULT);
      $ins = $pdo->prepare("INSERT INTO customers (email, password_hash, name) VALUES (?,?,?)");
      $ins->execute([$email, $hash, $name ?: null]);
      $_SESSION['customer_id'] = $pdo->lastInsertId();
      header('Location: cuenta.php'); exit;
      $ok = true;
    }
  }
}
?>
<!doctype html><html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Crear cuenta • E&S</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css">
<script src="https://cdn.tailwindcss.com"></script>
</head><body class="min-h-screen grid place-items-center bg-slate-100">
  <form method="post" class="bg-white p-6 rounded-xl shadow w-full max-w-sm space-y-3">
    <h1 class="text-xl font-semibold">Crear cuenta</h1>
    <input name="name"  class="input input-bordered w-full" placeholder="Nombre (opcional)">
    <input name="email" type="email" class="input input-bordered w-full" placeholder="Email" required>
    <input name="password" type="password" class="input input-bordered w-full" placeholder="Contraseña (mín. 6)" required>
    <?php if ($err): ?><p class="text-red-600 text-sm"><?=htmlspecialchars($err)?></p><?php endif; ?>
    <button class="btn btn-neutral w-full">Registrarme</button>
    <p class="text-sm text-center">¿Ya tenés cuenta? <a href="cliente_login.php" class="underline">Iniciar sesión</a></p>
  </form>
</body></html>

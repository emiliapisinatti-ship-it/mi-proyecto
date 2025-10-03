<?php
session_start();
if (empty($_SESSION['customer_id'])) {
  header('Location: cliente_login.php'); exit;
}
require __DIR__.'/api/db.php';

// Traer datos del cliente (opcional)
$st = $pdo->prepare("SELECT email, name, phone, address FROM customers WHERE id=?");
$st->execute([$_SESSION['customer_id']]);
$me = $st->fetch() ?: ['email'=>'','name'=>'','phone'=>'','address'=>''];
?>
<!doctype html><html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Mi cuenta • E&S</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css">
<script src="https://cdn.tailwindcss.com"></script>
</head><body class="bg-slate-100 min-h-screen">
  <div class="max-w-3xl mx-auto p-6">
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-2xl font-bold">Mi cuenta</h1>
      <a class="underline text-sm" href="cliente_logout.php">Cerrar sesión</a>
    </div>

    <div class="bg-white rounded-xl shadow p-4 mb-6">
      <p><strong>Nombre:</strong> <?=htmlspecialchars($me['name'] ?: '—')?></p>
      <p><strong>Email:</strong> <?=htmlspecialchars($me['email'])?></p>
      <p><strong>Teléfono:</strong> <?=htmlspecialchars($me['phone'] ?: '—')?></p>
      <p><strong>Dirección:</strong> <?=htmlspecialchars($me['address'] ?: '—')?></p>
    </div>

    <div class="bg-white rounded-xl shadow p-4">
      <h2 class="font-semibold mb-2">Mis pedidos</h2>
      <p class="text-sm text-slate-600">Aún no hay pedidos.</p>
    </div>
  </div>
</body></html>

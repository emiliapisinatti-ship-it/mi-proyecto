<?php
// checkout.php (página protegida)
declare(strict_types=1);

session_set_cookie_params([
  'lifetime' => 0,
  'path'     => '/',
  'secure'   => false,
  'httponly' => true,
  'samesite' => 'Lax'
]);
session_start();

if (empty($_SESSION['customer_id']) && empty($_SESSION['cliente_id'])) {
  // volver a esta misma página tras login
  $next = 'checkout.php';
  header('Location: cliente_login.php?next='.urlencode($next));
  exit;
}

// si hay sesión, mostramos el formulario real y finalizamos en api/checkout.php
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Finalizar compra</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" />
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
  <div class="max-w-3xl mx-auto p-6">
    <h1 class="text-2xl font-bold mb-4">Finalizar compra</h1>

    <div id="cartView" class="bg-white rounded-xl shadow p-4 mb-6"></div>

    <form id="chkForm" class="bg-white rounded-xl shadow p-4 grid gap-3">
      <div class="grid gap-2 md:grid-cols-2">
        <label class="form-control">
          <span class="label-text">Nombre</span>
          <input name="first_name" class="input input-bordered" required />
        </label>
        <label class="form-control">
          <span class="label-text">Apellido</span>
          <input name="last_name" class="input input-bordered" required />
        </label>
      </div>

      <label class="form-control">
        <span class="label-text">Email</span>
        <input name="email" type="email" class="input input-bordered" value="<?=htmlspecialchars($_SESSION['customer_email'] ?? '')?>" required />
      </label>

      <div class="grid gap-2 md:grid-cols-2">
        <label class="form-control">
          <span class="label-text">Teléfono</span>
          <input name="phone" class="input input-bordered" />
        </label>
        <label class="form-control">
          <span class="label-text">Dirección</span>
          <input name="address" class="input input-bordered" />
        </label>
      </div>

      <button class="btn btn-neutral mt-2">Confirmar pedido</button>
      <a class="link" href="tienda.html">← Volver a la tienda</a>
    </form>
  </div>

<script>
const CART_KEY = 'cart:tienda';
const loadCart = () => JSON.parse(localStorage.getItem(CART_KEY) || '[]');
const saveCart = x => localStorage.setItem(CART_KEY, JSON.stringify(x));
const money = n => `ARS ${Number(n||0).toLocaleString('es-AR')}`;

function renderCart(){
  const box = document.getElementById('cartView');
  const cart = loadCart();
  if (!cart.length){
    box.innerHTML = '<p class="text-gray-500">Tu carrito está vacío.</p>';
    document.getElementById('chkForm').style.display = 'none';
    return;
  }
  const total = cart.reduce((a,it)=>a+(it.price||0)*(it.qty||1),0);
  box.innerHTML = `
    <h2 class="font-semibold mb-2">Tu carrito</h2>
    <ul class="divide-y">
      ${cart.map(it=>`
        <li class="py-2 flex items-center justify-between gap-3">
          <div class="min-w-0">
            <div class="font-medium truncate">${it.name}</div>
            <div class="text-sm text-gray-500">${money(it.price)} · x${it.qty||1}</div>
          </div>
          <div class="font-semibold whitespace-nowrap">${money((it.price||0)*(it.qty||1))}</div>
        </li>`).join('')}
    </ul>
    <div class="mt-3 text-right text-lg font-bold">Total: ${money(total)}</div>
  `;
}
renderCart();

document.getElementById('chkForm').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const cart = loadCart();
  if (!cart.length){ alert('Tu carrito está vacío'); return; }
  const faltan = cart.filter(it => !it.variant_id);
  if (faltan.length){
    alert('Falta elegir talle/color en:\n\n' + faltan.map(i => '• ' + i.name).join('\n'));
    return;
  }

  const fd = new FormData(e.currentTarget);
  const payload = {
    email:      (fd.get('email')||'').trim(),
    first_name: (fd.get('first_name')||'').trim(),
    last_name:  (fd.get('last_name')||'').trim(),
    phone:      (fd.get('phone')||'').trim(),
    address:    (fd.get('address')||'').trim(),
    items: cart.map(it => ({ variant_id: Number(it.variant_id), qty: Number(it.qty||1) })),
    idempotency_key: (crypto?.getRandomValues ? crypto.getRandomValues(new Uint32Array(4)).join('-') : String(Date.now()))
  };

  try{
    const res = await fetch('api/checkout.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    });
    const raw = await res.text();
    const i = Math.min(...[raw.indexOf('{'), raw.indexOf('[')].filter(n => n >= 0));
    const data = JSON.parse(i >= 0 ? raw.slice(i) : raw);
    if (!res.ok || !data.ok) throw new Error(data?.error || `HTTP ${res.status}`);
    alert('¡Pedido confirmado! #' + data.order_id);
    saveCart([]); location.href = 'cuenta.php';
  }catch(err){
    alert('Error al finalizar la compra: ' + (err?.message || err));
  }
});
</script>
</body>
</html>

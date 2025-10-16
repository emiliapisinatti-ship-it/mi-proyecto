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
  <style>
 /* ===== Checkout claro y suave ===== */
:root{
  --accent:   #7a4545;
  --accent-2: #9b8681;
  --panel:    #f7f9fc;
  --panel-br: #e5e7ef;
  --ink:      #0f172a;
}

.checkout-page{ background:#eef2f7; color:var(--ink); }

/* Caja del modal / tarjetas */
.card-accent{
  background:var(--panel);
  border:1px solid var(--panel-br);
  color:var(--ink);
  box-shadow:0 6px 20px rgba(0,0,0,.06);
}

/* Header del modal (degradé “vino”) */
.cart-header{
  background:linear-gradient(90deg, #b86a6a, #6e2f2f);
  color:#fff;
}

/* Divisores dentro del carrito */
.card-accent .divide-y > *{ border-color:#e5e7eb !important; }

/* Inputs claros + foco suave */
.card-accent .label-text{ color:#475569; font-weight:600; }
.card-accent .input,
.card-accent input,
.card-accent textarea,
.card-accent select{
  background:#ffffff !important;
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

/* Botón principal */
.btn.btn-primary,
.btn-primary{
  background:linear-gradient(135deg,var(--accent),var(--accent-2)) !important;
  border:0 !important;
  color:#fff !important;
  border-radius:12px;
}
.btn.btn-primary:hover,
.btn-primary:hover{ filter:brightness(1.03); }

/* Botón secundario estilo ghost suave */
.btn-ghost{
  border:1px solid rgba(15,23,42,.08) !important;
  color:var(--ink) !important;
  background:transparent !important;
}
.btn-ghost:hover{ background:rgba(15,23,42,.04) !important; }

/* Estado vacío */
.empty{ color:#64748b; text-align:center; padding:2rem 0; }

/* Mini utilidades item */
.cart-thumb{ width:56px; height:56px; object-fit:cover; border-radius:.5rem; }
.cart-name{ font-weight:600; }
.cart-price{ opacity:.85; font-size:.875rem; }


</style>

</head>
<body class="min-h-screen checkout-page">
  <div class="max-w-3xl mx-auto p-6">
    <h1 class="text-2xl font-bold mb-4">Finalizar compra</h1>

    <div id="cartView" class="card-accent rounded-xl p-4 mb-6"></div>

    <form id="chkForm" class="card-accent rounded-xl p-4 grid gap-3">
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

     <button class="btn btn-primary mt-2 w-full">Confirmar pedido</button>
      <a class="back-link" href="tienda.html">← Volver a la tienda</a>
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

// === SUBMIT checkout: leer texto -> intentar JSON y mostrar respuesta cruda si falla ===
document.getElementById('chkForm').addEventListener('submit', async (e)=>{
  e.preventDefault();

  const cart = loadCart();
  if (!cart.length){ alert('Tu carrito está vacío'); return; }

  // Asegurar que cada item tenga variant_id (o que el id del carrito sea la variante)
  const faltan = cart.filter(it => !Number(it.variant_id || it.id));
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
    items: cart.map(it => ({
      variant_id: Number(it.variant_id || it.id), // fallback por si tu carrito guardó el id de la variante en "id"
      qty: Number(it.qty||1)
    })),
    idempotency_key: (crypto?.getRandomValues ? crypto.getRandomValues(new Uint32Array(4)).join('-') : String(Date.now()))
  };

  try{
    const res = await fetch('api/checkout.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    });

    // SIEMPRE leer como texto primero
    const txt = await res.text();

    // Intentar parsear JSON (soporta si el servidor mete BOM/HTML antes)
    let data;
    try {
      const i = Math.min(...[txt.indexOf('{'), txt.indexOf('[')].filter(n => n >= 0));
      data = JSON.parse(i >= 0 ? txt.slice(i) : txt);
    } catch(parseErr) {
      console.error('Respuesta checkout NO-JSON:', txt);
      alert('El servidor no devolvió JSON.\n\nRespuesta:\n' + txt.slice(0, 800));
      throw parseErr;
    }

    if (!res.ok || !data?.ok) {
      throw new Error(data?.error || `HTTP ${res.status}`);
    }

    alert('¡Pedido confirmado! #' + data.order_id);
    saveCart([]); 
    location.href = 'cuenta.php';
  } catch (err) {
    alert('Error al finalizar la compra: ' + (err?.message || err));
  }
});


  
</script>
</body>
</html>

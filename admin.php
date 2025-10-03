
<?php
session_start();
if (empty($_SESSION['admin_id'])) {
  header('Location: login.php'); exit;
}
?>

<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>E&S • Admin (PHP/MySQL)</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-slate-900 max-w-6xl mx-auto p-4">


  <h1 class="text-2xl font-bold mb-4">Panel de productos</h1>

  <!-- Alta -->
  <section class="bg-white rounded-xl shadow p-4 mb-8">
    <h2 class="font-semibold text-lg mb-3">Nuevo producto</h2>

    <!--
      Nombres del form (ES) -> el backend los mapea a tu tabla (EN):
      nombre->name, categoria->kind, category (implícito mujer),
      precio->price, colores->color, talles->sizes, imagenURL->img, activo->active
    -->
    <form id="f" class="grid gap-4 md:grid-cols-2">
      <input  name="nombre"     class="border p-2 rounded" placeholder="Nombre" required />
      <input  name="categoria"  class="border p-2 rounded" placeholder="Tipo (remeras, pantalones…)" required />
      <input  name="precio"     type="number" step="0.01" class="border p-2 rounded" placeholder="Precio ARS" required />
      <input  name="colores"    class="border p-2 rounded" placeholder="Colores (coma: negro, blanco)" />
      <input  name="talles"     class="border p-2 rounded" placeholder="Talles (coma: S, M, L)" />
      <input  name="imagenURL"  type="url" class="border p-2 rounded md:col-span-2" placeholder="URL imagen (p.ej. imagenes/remera1.jpeg)" />
      <textarea name="descripcion" class="border p-2 rounded md:col-span-2" placeholder="Descripción (opcional)"></textarea>

      <label class="inline-flex items-center gap-2 md:col-span-2 text-sm">
        <input type="checkbox" name="activo" checked />
        <span>Activo (visible en la tienda)</span>
      </label>

      <button class="md:col-span-2 bg-black text-white py-2 rounded">Guardar</button>
    </form>

    <p id="msg" class="text-sm text-slate-600 mt-3"></p>
  </section>

  <!-- Listado -->
  <section class="bg-white rounded-xl shadow p-4">
    <div class="flex items-center justify-between mb-3">
      <h2 class="font-semibold text-lg">Productos</h2>
      <button id="reload" class="text-sm underline">Actualizar</button>
    </div>

    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="text-left border-b">
            <th class="py-2 pr-4">Imagen</th>
            <th class="py-2 pr-4">Nombre</th>
            <th class="py-2 pr-4">Tipo / Categoría</th>
            <th class="py-2 pr-4">Precio</th>
            <th class="py-2 pr-4">Activo</th>
            <th class="py-2 pr-4">Acciones</th>
          </tr>
        </thead>
        <tbody id="tbody"></tbody>
      </table>
    </div>
  </section>

  <script>
    // Endpoints
    const API_LIST   = 'api/products.php?admin=1';   // admin=1 -> trae también inactivos
    const API_ADD    = 'api/add_product.php';
    const API_TOGGLE = 'api/toggle_active.php';
    const API_DELETE = 'api/delete_product.php';

    // Refs
    const $f    = document.getElementById('f');
    const $msg  = document.getElementById('msg');
    const $body = document.getElementById('tbody');

    const money = n => `ARS ${Number(n||0).toLocaleString('es-AR')}`;

    // Listado
    async function loadProducts(){
      $body.innerHTML = '<tr><td class="py-4 opacity-70" colspan="6">Cargando…</td></tr>';
      const res = await fetch(API_LIST, { cache:'no-store' });
      const data = await res.json().catch(()=>[]);

      if (!Array.isArray(data) || data.length===0) {
        $body.innerHTML = '<tr><td class="py-4 opacity-70" colspan="6">Sin productos.</td></tr>';
        return;
      }

      $body.innerHTML = data.map(p => `
        <tr class="border-b align-top" data-id="${p.id}">
          <td class="py-2 pr-4">
            ${p.img ? `<img src="${p.img}" alt="" class="w-14 h-14 object-cover rounded">` : '<span class="opacity-60">—</span>'}
          </td>

          <td class="py-2 pr-4">
            <div class="font-medium">${p.name || ''}</div>
            <div class="text-xs opacity-70">Colores: ${p.color || '-'}</div>
            <div class="text-xs opacity-70">Talles: ${p.sizes || '-'}</div>
          </td>

          <td class="py-2 pr-4">${p.kind || p.category || '-'}</td>
          <td class="py-2 pr-4">${money(p.price)}</td>

          <td class="py-2 pr-4">
            <label class="inline-flex items-center gap-2 text-sm">
              <input type="checkbox" class="toggle" ${p.active ? 'checked' : ''}>
              <span>${p.active ? 'Sí' : 'No'}</span>
            </label>
          </td>

          <td class="py-2 pr-4">
            <button class="del text-red-600 underline text-sm">Eliminar</button>
          </td>
        </tr>
      `).join('');
    }

    // Alta
    $f.addEventListener('submit', async (e) => {
      e.preventDefault();
      $msg.textContent = 'Guardando…';

      const fd = new FormData($f);
      const payload = Object.fromEntries(fd.entries());
      payload.precio = Number(payload.precio || 0);
      payload.activo = fd.get('activo') ? 1 : 0;

      const res = await fetch(API_ADD, {
        method: 'POST',
        headers: { 'Content-Type':'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json().catch(()=>({ok:false}));

      if (data.ok) {
        $msg.textContent = 'Producto creado ✅';
        $f.reset();
        document.querySelector('input[name="activo"]').checked = true;
        loadProducts();
      } else {
        $msg.textContent = 'Error guardando: ' + (data.error || 'desconocido');
      }
    });

    // Delegación: toggle y eliminar
    document.addEventListener('click', async (e) => {
      const row = e.target.closest('tr[data-id]');
      if (!row) return;
      const id = row.dataset.id;

      // Activo / inactivo
      if (e.target.classList.contains('toggle')) {
        const activo = e.target.checked ? 1 : 0;
        const res = await fetch(API_TOGGLE, {
          method:'POST',
          headers:{'Content-Type':'application/json'},
          body: JSON.stringify({ id, activo })
        });
        const data = await res.json().catch(()=>({ok:false}));
        if (!data.ok) {
          alert('No se pudo actualizar "Activo".');
          e.target.checked = !e.target.checked;
        } else {
          // Actualiza el texto "Sí/No"
          e.target.nextElementSibling.textContent = activo ? 'Sí' : 'No';
        }
      }

      // Eliminar
      if (e.target.classList.contains('del')) {
        if (!confirm('¿Eliminar este producto?')) return;
        const res = await fetch(API_DELETE, {
          method:'POST',
          headers:{'Content-Type':'application/json'},
          body: JSON.stringify({ id })
        });
        const data = await res.json().catch(()=>({ok:false}));
        if (data.ok) row.remove();
        else alert('No se pudo eliminar');
      }
    });

    document.getElementById('reload').addEventListener('click', loadProducts);
    loadProducts();
  </script>
</body>
</html>


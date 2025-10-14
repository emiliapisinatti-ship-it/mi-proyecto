// BUILD 2025-10-13-01
console.log('Cargando JS NUEVO: BUILD 2025-10-13-01');

//* ========= Config ========= *//
// Endpoints PHP (serví por http://localhost..., no file://)
const API          = new URL('api/products.php',  location.href).toString();
const CHECKOUT_API = new URL('api/checkout.php',  location.href).toString();

/* ========= Refs ========= */
const grid      = document.getElementById('grid');
const tpl       = document.getElementById('tpl-producto');
const menu      = document.getElementById('menuMujer');

// Dialog detalle
const dlgProd   = document.getElementById('productDialog');
const pdImg     = document.getElementById('pdImg');
const pdName    = document.getElementById('pdName');
const pdPrice   = document.getElementById('pdPrice');
const pdColors  = document.getElementById('pdColors');
const pdSizes   = document.getElementById('pdSizes');
const pdThumbs  = document.getElementById('pdThumbs');
const pdAddBtn  = document.getElementById('pdAdd');

// Mini-carrito
const bagBtn    = document.querySelector('.icon-btn.bag');
const bagCnt    = document.getElementById('bagCount');
const cartDlg   = document.getElementById('cartDialog');
const cartList  = document.getElementById('cartItems');
const cartTotal = document.getElementById('cartTotal');
const clearCartBtn = document.getElementById('clearCart');
const checkoutBtn  = document.getElementById('checkout');

/* ========= Estado ========= */
const state = { category: '', kind: '', size: '', color: '' };
let LAST_PRODUCTS = [];
let CURRENT_PROD  = null;
let SELECTED      = { size: null, color: null };

/* ========= Utils ========= */
const money = n => `ARS ${Number(n||0).toLocaleString('es-AR')}`;
const csv   = s => String(s||'').split(',').map(x=>x.trim()).filter(Boolean);
const clean = o => Object.fromEntries(Object.entries(o).filter(([,v]) => v != null && String(v).trim() !== ''));
const toAbs = p => new URL(String(p||'').replace(/^\/+/, ''), location.href).toString();
const uniq  = arr => Array.from(new Set(arr));
function normalizeImg(it){
  let src = String(it.img || it.imagen || it.imagenURL || '').trim();
  if (!src) return 'imagenes/placeholder.jpg';
  return src.replace(/^img\//i,'imagenes/').replace(/\.jpge$/i,'.jpg');
}
function getProductById(id){ return LAST_PRODUCTS.find(x => String(x.id) === String(id)); }
function hasVariants(prod){ return Array.isArray(prod?.variants) && prod.variants.length > 0; }

/* ========= Arranque ========= */
document.addEventListener('DOMContentLoaded', () => {
  loadProducts(state);
  updateBagCount();

  // (Opcional) si volvemos con ?go=checkout, abrir el mini-carrito
  const qs = new URLSearchParams(location.search);
  if (qs.get('go') === 'checkout') bagBtn?.click();
});

/* ========= Filtros (menú) ========= */
menu?.addEventListener('click', async (e) => {
  const a = e.target.closest('a');
  if (!a) return;
  e.preventDefault(); e.stopPropagation();

  if (a.dataset.clear) { state.kind = state.size = state.color = ''; }
  if (a.dataset.kind)  state.kind  = a.dataset.kind;    // remeras | pantalones | vestidos
  if (a.dataset.size)  state.size  = a.dataset.size;    // XS | S | M | L | U
  if (a.dataset.color) state.color = a.dataset.color;   // negro | blanco | rojo | ...

  await loadProducts(state);
  menu.open = false;
  document.getElementById('productos')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
});

/* ========= Carga + render ========= */
async function loadProducts(filters = {}) {
  try {
    const qs  = new URLSearchParams(clean(filters)).toString();
    const url = qs ? `${API}?${qs}` : API;

    const res  = await fetch(url, { cache: 'no-store' });
    const text = await res.text();

    // si PHP imprime warnings, “limpiamos” antes de parsear
    const i = text.indexOf('[') >= 0 ? text.indexOf('[') : text.indexOf('{');
    const data = JSON.parse(i >= 0 ? text.slice(i) : text);

    if (!Array.isArray(data) || !data.length) {
      LAST_PRODUCTS = [];
      grid.innerHTML = '<p style="color:#fff">Sin resultados.</p>';
      return;
    }

    // normalizo variantes si vienen nulas
    LAST_PRODUCTS = data.map(p => ({
      ...p,
      variants: Array.isArray(p.variants) ? p.variants : []  // cada v: {id,size,color,stock}
    }));

    renderGrid(LAST_PRODUCTS);
  } catch (e) {
    console.error(e);
    grid.innerHTML = '<p style="color:#f88">Error leyendo la API.</p>';
  }
}

function renderGrid(lista){
  if (!grid || !tpl) return;
  grid.innerHTML = '';

  for (const it of lista) {
    const card = tpl.content.firstElementChild.cloneNode(true);

    const img     = card.querySelector('[data-src="imagen"]');
    const nameEl  = card.querySelector('[data-txt="nombre"]');
    const priceEl = card.querySelector('[data-txt="precio"]');

    const imgSrc = toAbs(normalizeImg(it));
    if (img) {
      img.src = imgSrc;
      img.alt = it.name || it.nombre || 'Producto';
      img.dataset.id = it.id;
    }
    card.dataset.id = it.id;

    if (nameEl)  nameEl.textContent  = it.name  || it.nombre || 'Producto';
    if (priceEl) priceEl.textContent = money(it.price || it.precio || 0);

    const add = card.querySelector('[data-action="add"]');
    if (add) {
      add.classList.add('add');
      add.dataset.id    = it.id;
      add.dataset.name  = it.name || it.nombre || 'Producto';
      add.dataset.price = it.price || it.precio || 0;
      add.dataset.img   = imgSrc;
    }

    grid.appendChild(card);
  }
}

/* ========= Detalle de producto ========= */
function buildGallery(prod){
  const main = toAbs(normalizeImg(prod));
  const out = [main];
  if (prod.gallery) csv(prod.gallery).forEach(u=>out.push(toAbs(u)));
  return uniq(out);
}

function variantOf(prod, size, color){
  const vars = prod.variants || [];
  return vars.find(v => (v.size||'') === (size||'') && (v.color||'') === (color||'')) || null;
}
function updateAddButtonState(prod){
  const s = SELECTED.size, c = SELECTED.color;
  const v = variantOf(prod, s, c);
  const available = v ? (v.stock ?? 0) : 0;
  if (!pdAddBtn) return;
  if (!s || !c) {
    pdAddBtn.disabled = true;
    pdAddBtn.textContent = 'Elegí talle y color';
  } else if (available <= 0) {
    pdAddBtn.disabled = true;
    pdAddBtn.textContent = 'Sin stock';
  } else {
    pdAddBtn.disabled = false;
    pdAddBtn.textContent = `Agregar al carrito (${available} disp.)`;
  }
}

function openDetail(prod){
  CURRENT_PROD = prod;
  SELECTED = { size:null, color:null };

  const name  = prod.name  || prod.nombre || 'Producto';
  const price = prod.price || prod.precio || 0;

  if (pdName)  pdName.textContent  = name;
  if (pdPrice) pdPrice.textContent = money(price);

  // galería
  const gal = buildGallery(prod);
  if (pdImg) { pdImg.src = gal[0]; pdImg.alt = name; }
  if (pdThumbs) {
    pdThumbs.innerHTML = gal.map((src,i)=>`<img src="${src}" class="pd-thumb ${i? '': 'is-active'}" data-idx="${i}">`).join('');
  }

  // armo listas desde variantes si existen
  let colors = csv(prod.color ?? prod.colores);
  let sizes  = csv(prod.sizes ?? prod.talles);
  if (hasVariants(prod)) {
    colors = uniq((prod.variants || []).map(v=>v.color).filter(Boolean));
    sizes  = uniq((prod.variants || []).map(v=>v.size).filter(Boolean));
  }

  if (pdColors) {
    pdColors.innerHTML = colors.length
      ? colors.map(c=>`<button class="color-chip" data-color="${c}"><span class="dot" style="background:${c}"></span> ${c}</button>`).join('')
      : '<span class="text-sm text-gray-500">—</span>';
  }
  if (pdSizes) {
    pdSizes.innerHTML = sizes.length
      ? sizes.map(s=>`<button class="size-btn" data-size="${s}">${s}</button>`).join('')
      : '<span class="text-sm text-gray-500">—</span>';
  }

  updateAddButtonState(prod);
  dlgProd?.showModal?.();
}

/* ======== Interacciones ======== */
// abrir detalle al tocar la imagen
grid?.addEventListener('click', (e)=>{
  const img = e.target.closest('.product img'); if (!img) return;
  const id  = img.dataset.id || img.closest('.product')?.dataset.id;
  const p   = getProductById(id);
  if (p) openDetail(p);
});

// thumbs
pdThumbs?.addEventListener('click', (e)=>{
  const t = e.target.closest('.pd-thumb'); if (!t) return;
  if (pdImg) pdImg.src = t.src;
  pdThumbs.querySelectorAll('.pd-thumb').forEach(x=>x.classList.remove('is-active'));
  t.classList.add('is-active');
});

// elegir color
pdColors?.addEventListener('click', (e)=>{
  const b = e.target.closest('.color-chip'); if (!b) return;
  SELECTED.color = b.dataset.color || null;
  pdColors.querySelectorAll('.color-chip').forEach(x=>x.classList.remove('is-selected'));
  b.classList.add('is-selected');
  if (CURRENT_PROD) updateAddButtonState(CURRENT_PROD);
});

// elegir talle
pdSizes?.addEventListener('click', (e)=>{
  const b = e.target.closest('.size-btn'); if (!b) return;
  SELECTED.size = b.dataset.size || null;
  pdSizes.querySelectorAll('.size-btn').forEach(x=>x.classList.remove('is-selected'));
  b.classList.add('is-selected');
  if (CURRENT_PROD) updateAddButtonState(CURRENT_PROD);
});

// agregar al carrito desde el modal (usa ID de variante)
pdAddBtn?.addEventListener('click', ()=>{
  if (!CURRENT_PROD) return;
  const s = SELECTED.size, c = SELECTED.color;
  const v = variantOf(CURRENT_PROD, s, c);
  if (!v || (v.stock ?? 0) <= 0) { alert('Elegí una combinación con stock.'); return; }

  addToCart({
    id:   String(v.id), // id = variante
    name: (CURRENT_PROD.name||CURRENT_PROD.nombre||'Producto') +
          (c? ` · ${c}`:'') + (s? ` · Talle ${s}`:''),
    price: + (CURRENT_PROD.price || CURRENT_PROD.precio || 0),
    img:   pdImg?.src || normalizeImg(CURRENT_PROD),
    variant_id: v.id, size: s, color: c,
    qty: 1
  });
  dlgProd?.close?.();
});

/* botón "Agregar al carrito" de la CARD */
grid?.addEventListener('click', (e)=>{
  const b = e.target.closest('.add'); 
  if (!b) return;

  const pid = b.closest('.product')?.dataset.id || b.dataset.id;
  const prod = getProductById(pid);
  if (!prod) return;

  if (hasVariants(prod)) {
    // Si hay solo UNA variante con stock, la agrego directo; sino abrir modal
    const avail = (prod.variants || []).filter(v => (v.stock ?? 0) > 0);
    if (avail.length === 1) {
      const v = avail[0];
      addToCart({
        id: String(v.id),
        name: `${prod.name || prod.nombre} · ${v.color || ''}${v.color ? ' · ' : ''}${v.size ? 'Talle '+v.size : ''}`.trim(),
        price: +(prod.price || prod.precio || 0),
        img:  b.dataset.img,
        variant_id: v.id,
        size: v.size || null,
        color: v.color || null,
        qty: 1
      });
      b.disabled = true; const old=b.textContent; b.textContent='Agregado ✓';
      setTimeout(()=>{ b.disabled=false; b.textContent=old; }, 900);
    } else {
      openDetail(prod);
    }
    return;
  }

  // Productos sin variantes (legacy)
  addToCart({
    id:   String(b.dataset.id),
    name: String(b.dataset.name),
    price:+b.dataset.price || 0,
    img:  b.dataset.img,
    qty: 1
  });
  b.disabled = true; const old=b.textContent; b.textContent='Agregado ✓';
  setTimeout(()=>{ b.disabled=false; b.textContent=old; }, 900);
});

/* ========= Carrito ========= */
const CART_KEY = 'cart:tienda';
const loadCart = () => JSON.parse(localStorage.getItem(CART_KEY) || '[]');
const saveCart = (x) => localStorage.setItem(CART_KEY, JSON.stringify(x));
const updateBagCount = () => bagCnt && (bagCnt.textContent = loadCart().reduce((a,it)=>a+(it.qty||0),0));

function addToCart(item){
  const cart = loadCart();
  const i = cart.findIndex(x => x.id === item.id);
  if (i >= 0) cart[i].qty += item.qty || 1;
  else cart.push({...item, qty: item.qty || 1});
  saveCart(cart);
  updateBagCount();
}

/* mini-carrito simple */
bagBtn?.addEventListener('click', ()=>{
  const cart = loadCart();
  if (!cart.length) {
    cartList.innerHTML = '<div class="text-center text-gray-500 py-8">Tu carrito está vacío.</div>';
    cartTotal.textContent = money(0);
  } else {
    cartList.innerHTML = cart.map(it=>`
      <div class="py-3 flex items-center gap-3">
        <img src="${it.img}" class="cart-thumb" alt="">
        <div class="flex-1 min-w-0">
          <p class="cart-name truncate">${it.name}</p>
          <p class="cart-price">${money(it.price)}</p>
        </div>
        <span>x${it.qty||1}</span>
      </div>`).join('');
    const total = cart.reduce((a,it)=>a+(it.price||0)*(it.qty||1),0);
    cartTotal.textContent = money(total);
  }
  cartDlg?.showModal?.();
});

clearCartBtn?.addEventListener('click', ()=>{
  saveCart([]); updateBagCount();
  cartList.innerHTML = '<div class="text-center text-gray-500 py-8">Tu carrito está vacío.</div>';
  cartTotal.textContent = money(0);
});

/* ===== Finalizar compra (requiere login) ===== */
checkoutBtn?.addEventListener('click', async () => {
  const cart = loadCart();
  if (!cart.length) { alert('Tu carrito está vacío'); return; }

  const faltan = cart.filter(it => !it.variant_id);
  if (faltan.length){
    alert('Falta elegir talle/color en:\n\n' + faltan.map(i => '• ' + i.name).join('\n'));
    return;
  }

  // ¿Está logueado?
  let me = null;
  try {
    const r = await fetch('api/me.php', { cache: 'no-store' });
    me = await r.json();
  } catch {}

  if (!me?.ok || !me?.customer?.id) {
    // no logueado → al login y volvemos a checkout
    const next = encodeURIComponent('checkout.php');
    window.location.href = `cliente_login.php?next=${next}`;
    return;
  }

  // logueado → vamos a la página checkout (form + envío a api/checkout.php)
  window.location.href = 'checkout.php';
});

/* ========= FIX: Dropdown (menú Mujer) siempre pegado al botón ========= */
(function(){
  const dd = menu; 
  if (!dd) return;
  const ddSummary = dd.querySelector('summary');
  const ddPanel   = dd.querySelector('.dd-panel');
  if (!ddSummary || !ddPanel) return;

  function placeDropdown() {
    ddPanel.style.position  = 'fixed';
    ddPanel.style.zIndex    = '20000';

    // mostrar temporalmente para medir
    const prevDisplay = ddPanel.style.display;
    const prevVis     = ddPanel.style.visibility;
    ddPanel.style.visibility = 'hidden';
    ddPanel.style.display    = 'grid';

    const r  = ddSummary.getBoundingClientRect();
    const ph = ddPanel.offsetHeight;
    const pw = ddPanel.offsetWidth;
    const m = 8;

    // X clamp
    let left = Math.min(Math.max(m, r.left), window.innerWidth - pw - m);

    // Y: abajo por defecto; si no entra, arriba
    let top = r.bottom + m;
    if (top + ph + m > window.innerHeight) top = r.top - ph - m;
    top = Math.min(Math.max(m, top), window.innerHeight - ph - m);

    ddPanel.style.left = `${left}px`;
    ddPanel.style.top  = `${top}px`;

    ddPanel.style.visibility = prevVis || '';
    ddPanel.style.display    = prevDisplay || '';
  }

  function onOpen() {
    placeDropdown();
    window.addEventListener('scroll', placeDropdown, { passive:true });
    window.addEventListener('resize', placeDropdown);
  }
  function onClose() {
    window.removeEventListener('scroll', placeDropdown);
    window.removeEventListener('resize', placeDropdown);
  }

  dd.addEventListener('toggle', () => { if (dd.open) onOpen(); else onClose(); });

  // Cerrar al click afuera / Escape
  document.addEventListener('click', (e) => { if (dd.open && !dd.contains(e.target)) dd.removeAttribute('open'); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && dd.open) dd.removeAttribute('open'); });
})();

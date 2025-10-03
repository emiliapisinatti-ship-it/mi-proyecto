
/* ========= Config ========= */
// Endpoint PHP (debe devolver JSON). Serví por http://localhost..., no file://
const API = new URL('api/products.php', location.href).toString();

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
// Si tu columna category tiene valor (p.ej. "mujer"), podés fijarla así: category: 'mujer'
const state = { category: '', kind: '', size: '', color: '' };
let LAST_PRODUCTS = [];
let CURRENT_PROD  = null;
let SELECTED      = { size: null, color: null };

/* ========= Utils ========= */
const money = n => `ARS ${Number(n||0).toLocaleString('es-AR')}`;
const csv   = s => String(s||'').split(',').map(x=>x.trim()).filter(Boolean);
const clean = o => Object.fromEntries(Object.entries(o).filter(([,v]) => v != null && String(v).trim() !== ''));
const toAbs = p => new URL(String(p||'').replace(/^\/+/, ''), location.href).toString();
function normalizeImg(it){
  let src = String(it.img || it.imagen || it.imagenURL || '').trim();
  if (!src) return 'imagenes/placeholder.jpg';
  return src.replace(/^img\//i,'imagenes/').replace(/\.jpge$/i,'.jpg');
}

/* ========= Arranque ========= */
document.addEventListener('DOMContentLoaded', () => {
  loadProducts(state);
  updateBagCount();
});

/* ========= Filtros (menú) ========= */
menu?.addEventListener('click', async (e) => {
  const a = e.target.closest('a');
  if (!a) return;
  e.preventDefault(); e.stopPropagation();

  if (a.dataset.clear) { state.kind = state.size = state.color = ''; }
  if (a.dataset.kind)  state.kind  = a.dataset.kind;   // remeras | pantalones | vestidos
  if (a.dataset.size)  state.size  = a.dataset.size;   // XS | S | M | L
  if (a.dataset.color) state.color = a.dataset.color;  // negro | blanco | rojo | celeste

  await loadProducts(state);
  menu.open = false;
  document.getElementById('productos')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
});

/* ========= Carga + render ========= */
async function loadProducts(filters = {}) {
  try {
    const qs  = new URLSearchParams(clean(filters)).toString();
    const url = qs ? `${API}?${qs}` : API;

    console.log('→ Fetch:', url); // para verificar qué se manda
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
    LAST_PRODUCTS = data;
    renderGrid(data);
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

    // botón agregar
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

  // Si algún día guardás una galería CSV
  if (prod.gallery) csv(prod.gallery).forEach(u=>out.push(toAbs(u)));

  // Intenta _2 _3 _4
  const m = main.match(/(.+)(\.[a-z0-9]+)$/i);
  if (m) out.push(`${m[1]}_2${m[2]}`, `${m[1]}_3${m[2]}`, `${m[1]}_4${m[2]}`);

  return Array.from(new Set(out));
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

  // colores y talles (acepta color/colores y sizes/talles)
  const colors = csv(prod.color ?? prod.colores);
  const sizes  = csv(prod.sizes ?? prod.talles);

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

  dlgProd?.showModal?.();
}

// abrir detalle al tocar la imagen
grid?.addEventListener('click', (e)=>{
  const img = e.target.closest('.product img'); if (!img) return;
  const id  = img.dataset.id || img.closest('.product')?.dataset.id;
  const p   = LAST_PRODUCTS.find(x => String(x.id) === String(id));
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
});

// elegir talle
pdSizes?.addEventListener('click', (e)=>{
  const b = e.target.closest('.size-btn'); if (!b) return;
  SELECTED.size = b.dataset.size || null;
  pdSizes.querySelectorAll('.size-btn').forEach(x=>x.classList.remove('is-selected'));
  b.classList.add('is-selected');
});

// agregar al carrito desde el modal (con variante)
pdAddBtn?.addEventListener('click', ()=>{
  if (!CURRENT_PROD) return;
  const base = CURRENT_PROD.id;
  const id   = `${base}::${SELECTED.size||''}::${SELECTED.color||''}`;
  addToCart({
    id,
    name: (CURRENT_PROD.name||CURRENT_PROD.nombre||'Producto') +
          (SELECTED.color? ` · ${SELECTED.color}`:'') +
          (SELECTED.size ? ` · Talle ${SELECTED.size}`:''),
    price: + (CURRENT_PROD.price || CURRENT_PROD.precio || 0),
    img:   pdImg?.src || normalizeImg(CURRENT_PROD)
  });
  dlgProd?.close?.();
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

/* botón "Agregar al carrito" de la card */
grid?.addEventListener('click', (e)=>{
  const b = e.target.closest('.add'); if (!b) return;
  addToCart({
    id:   String(b.dataset.id),
    name: String(b.dataset.name),
    price:+b.dataset.price || 0,
    img:  b.dataset.img
  });
  b.disabled = true; const old=b.textContent; b.textContent='Agregado ✓';
  setTimeout(()=>{ b.disabled=false; b.textContent=old; }, 900);
});

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
checkoutBtn?.addEventListener('click', ()=>{
  alert('¡Gracias por tu compra! (demo)');
  saveCart([]); updateBagCount(); cartDlg?.close?.();
});









<?php
// admin_stock.php
declare(strict_types=1);

session_start();

require __DIR__ . '/api/db.php';
require __DIR__ . '/config.local.php';

// Gate de admin (usa el mismo k de config.local.php)
if (empty($_SESSION['admin_id'])) {
  header('Location: admin_login.php?k=' . rawurlencode($ADMIN_GATE));
  exit;
}

header('Content-Type: text/html; charset=utf-8');

$LOW_STOCK_LEVEL = 5;
$msg = $err = '';

// ---------- Crear variante (opcional: alta rápida) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_variant'])) {
  try {
    $pid   = (int)($_POST['product_id'] ?? 0);
    $sku   = trim((string)($_POST['sku']   ?? ''));
    $size  = trim((string)($_POST['size']  ?? ''));
    $color = trim((string)($_POST['color'] ?? ''));
    $stock = (int)($_POST['stock'] ?? 0);

    if ($pid <= 0) throw new Exception('Producto inválido.');
    // verificar que el producto exista
    $st = $pdo->prepare("SELECT id FROM products WHERE id=? LIMIT 1");
    $st->execute([$pid]);
    if (!$st->fetchColumn()) throw new Exception('El producto no existe.');

    $ins = $pdo->prepare("INSERT INTO product_variants (product_id, sku, size, color, stock) VALUES (?,?,?,?,?)");
    $ins->execute([$pid, $sku, $size, $color, max(0,$stock)]);
    $vid = (int)$pdo->lastInsertId();
    $msg = "Variante creada (#{$vid}).";
  } catch (Throwable $e) {
    $err = 'Error al crear variante: ' . $e->getMessage();
  }
}

// ---------- Actualización de stock ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
  try {
    $id    = (int)($_POST['id'] ?? 0);
    $new   = (int)($_POST['stock'] ?? 0);
    if ($id <= 0) throw new Exception('Variante inválida');

    $pdo->beginTransaction();

    // stock actual con lock
    $st = $pdo->prepare("SELECT stock FROM product_variants WHERE id = ? FOR UPDATE");
    $st->execute([$id]);
    $old = $st->fetchColumn();
    if ($old === false) throw new Exception('Variante no existe');

    $delta = $new - (int)$old;

    $u = $pdo->prepare("UPDATE product_variants SET stock = ? WHERE id = ?");
    $u->execute([$new, $id]);

    if ($delta != 0) {
      $m = $pdo->prepare("INSERT INTO stock_movements (variant_id, delta, reason, order_id) VALUES (?, ?, 'adjust', NULL)");
      $m->execute([$id, $delta]);
    }

    $pdo->commit();
    $msg = "Stock actualizado (Δ {$delta}).";
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $err = 'Error al actualizar: ' . $e->getMessage();
  }
}

// ---------- Filtros (GET) ----------
$q       = isset($_GET['q'])     ? trim((string)$_GET['q'])     : '';
$only    = isset($_GET['only'])  ? trim((string)$_GET['only'])  : '';
$kind    = isset($_GET['kind'])  ? trim((string)$_GET['kind'])  : '';
$colorF  = isset($_GET['color']) ? trim((string)$_GET['color']) : '';
$sizeF   = strtoupper(isset($_GET['size']) ? (string)$_GET['size'] : '');

$where  = [];
$params = [];

// Búsqueda: nombre, SKU o IDs
if ($q !== '') {
  $where[]  = '(LOWER(p.name) LIKE LOWER(?) OR LOWER(v.sku) LIKE LOWER(?) OR p.id = ? OR v.id = ?)';
  $params[] = "%$q%";
  $params[] = "%$q%";
  $qid      = ctype_digit($q) ? (int)$q : 0;
  $params[] = $qid;
  $params[] = $qid;
}

// Filtro de stock
if ($only === 'zero') {
  $where[] = 'v.stock = 0';
} elseif ($only === 'low') {
  $where[]  = 'v.stock <= ?';
  $params[] = $LOW_STOCK_LEVEL;
}

// Otros filtros (case-insensitive)
if ($kind   !== '') { $where[] = 'LOWER(p.kind) = LOWER(?)';                 $params[] = $kind;   }
if ($colorF !== '') { $where[] = 'LOWER(COALESCE(v.color,\'\')) = LOWER(?)'; $params[] = $colorF; }
if ($sizeF  !== '') { $where[] = 'UPPER(v.size) = UPPER(?)';                 $params[] = $sizeF;  }

// Consulta final (ANTES filtraba active=1 y te dejaba sin resultados)
$sql = "SELECT
          v.id AS variant_id, v.sku, v.size, v.color, v.stock,
          p.id AS product_id, p.name, p.kind, p.price, p.active
        FROM product_variants v
        JOIN products p ON p.id = v.product_id
        " . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . "
        ORDER BY p.id, v.color, v.size";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

// combos (para selects) -> solo activos
$allKinds  = $pdo->query("SELECT DISTINCT kind FROM products WHERE active=1 AND kind<>'' ORDER BY kind")->fetchAll(PDO::FETCH_COLUMN);
$allSizes  = $pdo->query("
  SELECT DISTINCT v.size
  FROM product_variants v
  JOIN products p ON p.id = v.product_id
  WHERE p.active=1 AND v.size <> ''
  ORDER BY v.size
")->fetchAll(PDO::FETCH_COLUMN);
$allColors = $pdo->query("
  SELECT DISTINCT v.color
  FROM product_variants v
  JOIN products p ON p.id = v.product_id
  WHERE p.active=1
  ORDER BY v.color
")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Admin · Stock</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root{ color-scheme: light dark; }
  body{ font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial; margin:0; background:#0b1220; color:#e5e7eb; }
  .wrap{ max-width:1100px; margin:24px auto; padding:0 16px; }
  h1{ margin:0 0 16px; font-size:22px; }
  .bar{ display:flex; gap:8px; align-items:end; flex-wrap:wrap; margin-bottom:16px; }
  label{ display:grid; gap:6px; font-size:12px; color:#cbd5e1; }
  input[type="text"], input[type="number"], select{
    height:36px; padding:0 10px; border:1px solid #334155; border-radius:8px; background:#0f172a; color:#e5e7eb;
  }
  .btn{ height:36px; padding:0 12px; border:1px solid #475569; border-radius:8px; background:#1f2937; color:#fff; cursor:pointer; }
  .btn:hover{ filter:brightness(1.05); }
  .msg{ padding:10px 12px; border-radius:8px; margin:8px 0; }
  .ok { background:#0b4; }
  .err{ background:#b00; }
  table{ width:100%; border-collapse:collapse; }
  th, td{ padding:10px; border-bottom:1px solid #243044; }
  th{ text-align:left; font-weight:600; color:#9ca3af; font-size:12px; }
  tr.zero td{ background:rgba(255,0,0,0.06); }
  tr.low  td{ background:rgba(255,200,0,0.06); }
  .num{ text-align:right; }
  .stk{ width:90px; }
  .sku{ font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size:12px; color:#a5b4fc; }
  .muted{ color:#9ca3af; font-size:12px; }
  .nowrap{ white-space:nowrap; }
  .topbar{ display:flex; gap:10px; align-items:center; margin-bottom:14px; }
  .topbar a{ color:#93c5fd; text-decoration:none; }
  .split{ display:grid; gap:12px; grid-template-columns: 1fr; }
  @media (min-width:900px){ .split{ grid-template-columns: 2fr 1fr; } }
  .card{ background:#0f172a; border:1px solid #243044; border-radius:12px; padding:12px; }
</style>
</head>
<body>
<div class="wrap">
  <div class="topbar">
    <a href="admin.php">← Volver al panel</a>
  </div>
  <h1>Stock por variante</h1>

  <?php if ($msg): ?><div class="msg ok"><?=htmlspecialchars($msg)?></div><?php endif; ?>
  <?php if ($err): ?><div class="msg err"><?=htmlspecialchars($err)?></div><?php endif; ?>

  <div class="split">
    <!-- Filtros -->
    <div class="card">
      <form id="filtros" class="bar" method="get" action="admin_stock.php" autocomplete="off">
        <label>
          Buscar (nombre / SKU / ID)
          <input type="text" name="q" value="<?=htmlspecialchars($q)?>" placeholder="ej. jean, SKU-..., 12">
        </label>
        <label>
          Categoría
          <select name="kind">
            <option value="">Todas</option>
            <?php foreach ($allKinds as $k): ?>
              <option value="<?=$k?>" <?=$k===$kind?'selected':''?>><?=$k?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>
          Talle
          <select name="size">
            <option value="">Todos</option>
            <?php foreach ($allSizes as $s): if($s==='') continue; ?>
              <option value="<?=$s?>" <?=$s===$sizeF?'selected':''?>><?=$s?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>
          Color
          <select name="color">
            <option value="">Todos</option>
            <?php foreach ($allColors as $c): ?>
              <option value="<?=$c?>" <?=$c===$colorF?'selected':''?>><?=$c?:'(sin color)'?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>
          Filtro stock
          <select name="only">
            <option value="">Todos</option>
            <option value="zero" <?=$only==='zero'?'selected':''?>>En 0</option>
            <option value="low"  <?=$only==='low' ?'selected':''?>>≤ <?=$LOW_STOCK_LEVEL?></option>
          </select>
        </label>
        <button class="btn" type="submit">Filtrar</button>
        <a class="btn" href="admin_stock.php">Limpiar</a>
      </form>
    </div>

    <!-- Alta rápida de variante (opcional) -->
    <div class="card">
      <form method="post" class="bar" autocomplete="off">
        <input type="hidden" name="create_variant" value="1">
        <label>
          ID Producto
          <input type="number" name="product_id" min="1" required>
        </label>
        <label>
          SKU
          <input type="text" name="sku" placeholder="opcional">
        </label>
        <label>
          Talle
          <input type="text" name="size" placeholder="ej. S / M / L / U">
        </label>
        <label>
          Color
          <input type="text" name="color" placeholder="ej. negro">
        </label>
        <label>
          Stock
          <input type="number" name="stock" min="0" value="0">
        </label>
        <button class="btn">Crear variante</button>
      </form>
      <div class="muted" style="margin-top:6px;">Usá esto cuando cargás un producto nuevo y todavía no aparece en el listado.</div>
    </div>
  </div>

  <div class="card" style="margin-top:12px;">
    <table>
      <thead>
        <tr>
          <th class="nowrap">Producto</th>
          <th>Categoría</th>
          <th class="nowrap">Talle</th>
          <th>Color</th>
          <th>SKU</th>
          <th class="num">Precio</th>
          <th class="num">Stock</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="7" class="muted">Sin resultados.</td></tr>
      <?php else:
        foreach ($rows as $r):
          $cls = $r['stock'] == 0 ? 'zero' : ($r['stock'] <= $LOW_STOCK_LEVEL ? 'low' : '');
      ?>
        <tr class="<?=$cls?>">
          <td>
            #<?=$r['product_id']?> · <?=htmlspecialchars($r['name'])?>
            <div class="muted">Variante ID: <?=$r['variant_id']?></div>
          </td>
          <td><?=htmlspecialchars($r['kind'])?></td>
          <td><?=htmlspecialchars($r['size'] ?: 'U')?></td>
          <td><?=htmlspecialchars($r['color'] ?: '(sin color)')?></td>
          <td class="sku"><?=htmlspecialchars($r['sku'])?></td>
          <td class="num">ARS <?=number_format((float)$r['price'], 0, ',', '.')?></td>
          <td class="num">
            <form method="post" action="" style="display:flex; gap:6px; align-items:center;">
              <input type="hidden" name="id" value="<?=$r['variant_id']?>">
              <input class="stk" type="number" name="stock" min="0" step="1" value="<?=$r['stock']?>">
              <input type="hidden" name="reason" value="adjust">
              <button class="btn" type="submit" name="update" value="1">Guardar</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>

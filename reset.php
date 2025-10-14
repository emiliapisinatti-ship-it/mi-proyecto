<?php
// reset.php
declare(strict_types=1);
session_start();
require __DIR__.'/api/db.php';

const DEV_DEBUG = true;     // mostrar motivo si el link no vale (para debug)
const TTL_HOURS = 24;       // vigencia del enlace

$email = strtolower(trim($_GET['email'] ?? ''));
$token = trim($_GET['token'] ?? '');

$invalid_reason = '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $token === '') {
  $invalid_reason = 'Parámetros faltantes.';
} else {
  // Buscar token para ese email
  $st = $pdo->prepare("SELECT id, email, token, created_at
                       FROM password_resets
                       WHERE email = ? AND token = ?
                       LIMIT 1");
  $st->execute([$email, $token]);
  $row = $st->fetch(PDO::FETCH_ASSOC);

  if (!$row) {
    $invalid_reason = 'Token inexistente (posible truncado o ya borrado).';
  } else {
    // Vigencia
    $created = strtotime($row['created_at'] ?? '1970-01-01 00:00:00');
    if ($created < time() - TTL_HOURS * 3600) {
      $invalid_reason = 'Token vencido.';
    }
  }
}

if ($invalid_reason && $_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo 'El enlace de recuperación no es válido o ya fue utilizado.';
  if (DEV_DEBUG) echo '<br><small>Motivo: '.htmlspecialchars($invalid_reason).'</small>';
  echo '<p><a href="request_reset.php">Generar un nuevo enlace</a></p>';
  exit;
}

$msg = $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $p1 = $_POST['password'] ?? '';
  $p2 = $_POST['password2'] ?? '';
  if ($p1 === '' || $p2 === '') {
    $err = 'La contraseña no puede estar vacía';
  } elseif ($p1 !== $p2) {
    $err = 'Las contraseñas no coinciden';
  } elseif (strlen($p1) < 6) {
    $err = 'Mínimo 6 caracteres';
  } else {
    // Actualizar hash en customers
    $hash = password_hash($p1, PASSWORD_DEFAULT);
    $upd  = $pdo->prepare("UPDATE customers SET password_hash = :h WHERE email = :e");
    $upd->execute([':h'=>$hash, ':e'=>$email]);

    // Borrar todos los tokens de ese email (invalidar enlaces viejos)
    $pdo->prepare("DELETE FROM password_resets WHERE email=?")->execute([$email]);

    $msg = '¡Contraseña actualizada! Ya podés iniciar sesión.';
  }
}
?>
<!doctype html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Restablecer contraseña</title>
</head><body>
<h1>Restablecer contraseña</h1>

<?php if ($msg): ?>
  <p style="background:#e6ffed;padding:8px;border:1px solid #b7ebc6;"><?=htmlspecialchars($msg)?></p>
  <p><a href="cliente_login.php">Ir a iniciar sesión</a></p>
<?php else: ?>
  <?php if ($err): ?><p style="color:#d00;"><?=htmlspecialchars($err)?></p><?php endif; ?>
  <form method="post">
    <p>Email: <strong><?=htmlspecialchars($email)?></strong></p>
    <label>Nueva contraseña
      <input type="password" name="password" required>
    </label><br>
    <label>Repetir contraseña
      <input type="password" name="password2" required>
    </label><br>
    <button type="submit">Guardar</button>
  </form>
<?php endif; ?>
</body></html>

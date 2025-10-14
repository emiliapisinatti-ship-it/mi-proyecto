<?php
// request_reset.php
declare(strict_types=1);
session_start();
require __DIR__.'/api/db.php';

const DEV_SHOW_LINK = true; // poner false en producción

$msg = $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = strtolower(trim($_POST['email'] ?? ''));
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $err = 'Email inválido';
  } else {
    // ¿Existe el cliente?
    $st = $pdo->prepare("SELECT id FROM customers WHERE email=? LIMIT 1");
    $st->execute([$email]);
    $cust = $st->fetch();

    // Siempre respondemos "ok" aunque el email no exista (por seguridad)
    // Pero si existe, creamos token.
    if ($cust) {
      // Invalidar tokens anteriores de ese email
      $pdo->prepare("DELETE FROM password_resets WHERE email=?")->execute([$email]);

      // Generar token seguro (64 chars)
      $token = bin2hex(random_bytes(32));

      $ins = $pdo->prepare("INSERT INTO password_resets (email, token) VALUES (?, ?)");
      $ins->execute([$email, $token]);

      $link = sprintf('%sreset.php?email=%s&token=%s',
        rtrim(dirname($_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']), '/').'/',
        urlencode($email),
        urlencode($token)
      );
    }
    $msg = 'Si el email existe, te enviamos un enlace para restablecer la contraseña.';
  }
}
?>
<!doctype html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Recuperar contraseña</title>
</head><body>
<h1>Recuperar contraseña</h1>

<?php if ($msg): ?>
  <p style="background:#e6ffed;padding:8px;border:1px solid #b7ebc6;"><?=htmlspecialchars($msg)?></p>
  <?php if (DEV_SHOW_LINK && !empty($link)): ?>
    <p><strong>(Dev) Enlace:</strong><br>
      <a href="<?=htmlspecialchars($link)?>"><?=htmlspecialchars($link)?></a>
    </p>
  <?php endif; ?>
<?php endif; ?>

<?php if ($err): ?><p style="color:#d00;"><?=htmlspecialchars($err)?></p><?php endif; ?>

<form method="post">
  <label>Email <input type="email" name="email" required></label>
  <button type="submit">Enviar enlace</button>
</form>
<p><a href="cliente_login.php">Volver a iniciar sesión</a></p>
</body></html>

<?php
declare(strict_types=1);

session_set_cookie_params([
  'lifetime' => 0,
  'path' => '/',
  'domain' => 'localhost',
  'secure' => false,
  'httponly' => true,
  'samesite' => 'Lax'
]);
session_start();

require __DIR__.'/api/db.php';
require __DIR__.'/config.local.php';

if (empty($_SESSION['admin_id'])) {
  header('Location: admin_login.php?k='.urlencode($ADMIN_GATE)); exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) { header('Location: admin_stock.php'); exit; }

// Despublicar el producto
$pdo->prepare("UPDATE products SET active=0 WHERE id=?")->execute([$id]);
// Opcional: dejar stock en 0 para API viejas
$pdo->prepare("UPDATE product_variants SET stock=0 WHERE product_id=?")->execute([$id]);

header('Location: admin_stock.php');

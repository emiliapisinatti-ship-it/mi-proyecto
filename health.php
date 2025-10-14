<?php
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');
session_start();

echo "Session admin_id: " . (isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : '(no)') . "\n";

try {
  require __DIR__.'/api/db.php';
  $n = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
  echo "DB OK. products=$n\n";
} catch (Throwable $e) {
  echo "DB ERROR: ".$e->getMessage()."\n";
}

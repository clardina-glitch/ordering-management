<?php
require_once __DIR__ . '/helpers.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('products.php');
}

require_csrf();

$id = parse_id($_POST['id'] ?? null);
if (!$id) {
    flash('error', 'Invalid product.');
    redirect('products.php');
}

$statement = db()->prepare('DELETE FROM products WHERE id = ?');
$statement->execute([$id]);

flash('success', 'Product deleted.');
redirect('products.php');

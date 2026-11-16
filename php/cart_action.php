<?php
require_once __DIR__ . '/helpers.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('cart.php');
}

require_csrf();

$action = (string) ($_POST['action'] ?? '');
$productId = parse_id($_POST['product_id'] ?? null);
$_SESSION['cart'] = $_SESSION['cart'] ?? [];

if ($action === 'add') {
    $quantity = parse_quantity($_POST['quantity'] ?? null);

    if (!$productId || !$quantity) {
        flash('error', 'Choose a valid product and quantity.');
        redirect('products.php');
    }

    $statement = db()->prepare('SELECT id, name, stock FROM products WHERE id = ?');
    $statement->execute([$productId]);
    $product = $statement->fetch();

    if (!$product) {
        flash('error', 'That product is no longer available.');
        redirect('products.php');
    }

    if ((int) $product['stock'] < 1) {
        flash('error', $product['name'] . ' is out of stock.');
        redirect('products.php');
    }

    $existing = (int) ($_SESSION['cart'][$productId] ?? 0);
    $nextQuantity = min($existing + $quantity, (int) $product['stock']);
    $_SESSION['cart'][$productId] = $nextQuantity;

    if ($nextQuantity < $existing + $quantity) {
        flash('info', 'Only ' . (int) $product['stock'] . ' of ' . $product['name'] . ' are available, so your quantity was adjusted.');
    } else {
        flash('success', $product['name'] . ' added to your order.');
    }

    redirect('products.php');
}

if ($action === 'update') {
    if (!$productId) {
        flash('error', 'Invalid product.');
        redirect('cart.php');
    }

    $rawQuantity = $_POST['quantity'] ?? '';
    if ($rawQuantity === '' || filter_var($rawQuantity, FILTER_VALIDATE_INT) === false) {
        flash('error', 'Enter a valid quantity.');
        redirect('cart.php');
    }

    $quantity = (int) $rawQuantity;
    if ($quantity < 0 || $quantity > 9999) {
        flash('error', 'Enter a valid quantity.');
        redirect('cart.php');
    }

    if ($quantity === 0) {
        unset($_SESSION['cart'][$productId]);
        flash('info', 'Item removed from your order.');
        redirect('cart.php');
    }

    $statement = db()->prepare('SELECT name, stock FROM products WHERE id = ?');
    $statement->execute([$productId]);
    $product = $statement->fetch();

    if (!$product) {
        unset($_SESSION['cart'][$productId]);
        flash('info', 'That product was removed from your order.');
        redirect('cart.php');
    }

    $nextQuantity = min($quantity, (int) $product['stock']);
    $_SESSION['cart'][$productId] = $nextQuantity;

    if ($nextQuantity < $quantity) {
        flash('info', 'Only ' . (int) $product['stock'] . ' of ' . $product['name'] . ' are available.');
    }

    redirect('cart.php');
}

if ($action === 'remove') {
    if ($productId) {
        unset($_SESSION['cart'][$productId]);
    }

    flash('info', 'Item removed from your order.');
    redirect('cart.php');
}

flash('error', 'Invalid cart action.');
redirect('cart.php');

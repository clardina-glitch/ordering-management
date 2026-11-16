<?php
require_once __DIR__ . '/helpers.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('cart.php');
}

require_csrf();

$entries = [];
foreach (($_SESSION['cart'] ?? []) as $id => $quantity) {
    $id = (int) $id;
    $quantity = (int) $quantity;

    if ($id > 0 && $quantity > 0) {
        $entries[] = ['id' => $id, 'quantity' => $quantity];
    }
}

if (count($entries) === 0) {
    flash('error', 'Your order is empty.');
    redirect('cart.php');
}

$pdo = db();

try {
    $pdo->beginTransaction();

    $ids = array_map(function ($entry) {
        return $entry['id'];
    }, $entries);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $statement = $pdo->prepare("SELECT id, name, price, stock FROM products WHERE id IN ($placeholders) FOR UPDATE");
    $statement->execute($ids);

    $products = [];
    foreach ($statement->fetchAll() as $product) {
        $products[(int) $product['id']] = $product;
    }

    $total = 0.0;
    $lines = [];

    foreach ($entries as $entry) {
        if (!isset($products[$entry['id']])) {
            throw new DomainException('One of the products in your order is no longer available.');
        }

        $product = $products[$entry['id']];

        if ((int) $product['stock'] < $entry['quantity']) {
            throw new DomainException('Only ' . (int) $product['stock'] . ' of ' . $product['name'] . ' are available.');
        }

        $price = (float) $product['price'];
        $subtotal = round($price * $entry['quantity'], 2);
        $total += $subtotal;

        $lines[] = [
            'product_id' => (int) $product['id'],
            'name' => $product['name'],
            'price' => $price,
            'quantity' => $entry['quantity'],
            'subtotal' => $subtotal,
        ];
    }

    $total = round($total, 2);
    $userId = (int) current_user()['id'];

    $statement = $pdo->prepare('INSERT INTO orders (user_id, total, status) VALUES (?, ?, ?)');
    $statement->execute([$userId, $total, 'pending']);
    $orderId = (int) $pdo->lastInsertId();

    $itemStatement = $pdo->prepare(
        'INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, subtotal)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stockStatement = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?');

    foreach ($lines as $line) {
        $itemStatement->execute([
            $orderId,
            $line['product_id'],
            $line['name'],
            $line['price'],
            $line['quantity'],
            $line['subtotal'],
        ]);

        $stockStatement->execute([$line['quantity'], $line['product_id'], $line['quantity']]);

        if ($stockStatement->rowCount() === 0) {
            throw new DomainException('Not enough stock for ' . $line['name'] . '.');
        }
    }

    $pdo->commit();

    $_SESSION['cart'] = [];
    flash('success', 'Order #' . $orderId . ' placed successfully.');
    redirect('order.php?id=' . $orderId);
} catch (DomainException $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    flash('error', $error->getMessage());
    redirect('cart.php');
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    throw $error;
}

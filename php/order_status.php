<?php
require_once __DIR__ . '/helpers.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('orders.php');
}

require_csrf();

$id = parse_id($_GET['id'] ?? null);
$status = (string) ($_POST['status'] ?? '');
$statuses = ['pending', 'completed', 'cancelled'];

if (!$id || !in_array($status, $statuses, true)) {
    flash('error', 'Invalid order status.');
    redirect('orders.php');
}

$pdo = db();

try {
    $pdo->beginTransaction();

    $statement = $pdo->prepare('SELECT id, status FROM orders WHERE id = ? FOR UPDATE');
    $statement->execute([$id]);
    $order = $statement->fetch();

    if (!$order) {
        $pdo->rollBack();
        flash('error', 'Order not found.');
        redirect('orders.php');
    }

    $currentStatus = $order['status'];

    if ($currentStatus === 'cancelled' && $status !== 'cancelled') {
        $pdo->rollBack();
        flash('error', 'Cancelled orders cannot be reopened. Create a new order instead.');
        redirect('order.php?id=' . $id);
    }

    if ($currentStatus !== $status) {
        $statement = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
        $statement->execute([$status, $id]);

        if ($status === 'cancelled') {
            $statement = $pdo->prepare(
                'SELECT product_id, quantity FROM order_items WHERE order_id = ? AND product_id IS NOT NULL'
            );
            $statement->execute([$id]);
            $items = $statement->fetchAll();

            $restock = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
            foreach ($items as $item) {
                $restock->execute([(int) $item['quantity'], (int) $item['product_id']]);
            }
        }

        flash('success', 'Order #' . $id . ' marked as ' . $status . '.');
    }

    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    throw $error;
}

redirect('order.php?id=' . $id);

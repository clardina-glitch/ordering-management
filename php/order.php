<?php
require_once __DIR__ . '/helpers.php';
require_login();

$user = current_user();
$isAdmin = $user['role'] === 'admin';
$id = parse_id($_GET['id'] ?? null);

if (!$id) {
    render_message_page(404, 'Order not found', 'The order you are looking for does not exist.', '📦');
}

$statement = db()->prepare(
    'SELECT o.*, u.name AS customer_name, u.email AS customer_email
     FROM orders o
     JOIN users u ON u.id = o.user_id
     WHERE o.id = ?'
);
$statement->execute([$id]);
$order = $statement->fetch();

if (!$order) {
    render_message_page(404, 'Order not found', 'The order you are looking for does not exist.', '📦');
}

if (!$isAdmin && (int) $order['user_id'] !== (int) $user['id']) {
    flash('error', 'You do not have permission to view that order.');
    redirect('orders.php');
}

$statement = db()->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC');
$statement->execute([$id]);
$items = $statement->fetchAll();

$pageTitle = 'Order #' . $order['id'];
include __DIR__ . '/partials/header.php';
?>
<main class="container page">
  <div class="page-head">
    <div>
      <p class="eyebrow">Order details</p>
      <h1>Order #<?= (int) $order['id'] ?></h1>
      <p class="muted">Placed on <?= e(format_date($order['created_at'])) ?></p>
    </div>
    <a href="orders.php" class="btn btn-ghost">Back to orders</a>
  </div>

  <div class="order-grid">
    <section class="card order-main">
      <div class="order-meta">
        <div>
          <span class="meta-label">Status</span>
          <span class="status status-<?= e($order['status']) ?>"><?= e($order['status']) ?></span>
        </div>
        <div>
          <span class="meta-label">Total</span>
          <strong class="order-total"><?= money($order['total']) ?></strong>
        </div>
        <?php if ($isAdmin) { ?>
          <div>
            <span class="meta-label">Customer</span>
            <strong><?= e($order['customer_name']) ?></strong>
            <small class="muted"><?= e($order['customer_email']) ?></small>
          </div>
        <?php } ?>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Product</th>
              <th>Unit price</th>
              <th>Qty</th>
              <th>Subtotal</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($items as $item) { ?>
              <tr>
                <td><?= e($item['product_name']) ?></td>
                <td><?= money($item['unit_price']) ?></td>
                <td><?= (int) $item['quantity'] ?></td>
                <td><?= money($item['subtotal']) ?></td>
              </tr>
            <?php } ?>
          </tbody>
          <tfoot>
            <tr>
              <th colspan="3">Order total</th>
              <th><?= money($order['total']) ?></th>
            </tr>
          </tfoot>
        </table>
      </div>
    </section>

    <aside class="card order-side">
      <h2>Need something else?</h2>
      <p class="muted">Continue shopping to add more products to a new order.</p>
      <a href="products.php" class="btn btn-block">Browse products</a>

      <?php if ($isAdmin) { ?>
        <hr>
        <h2>Update status</h2>
        <?php if ($order['status'] === 'cancelled') { ?>
          <p class="muted">Cancelled orders cannot be reopened. Stock has been returned to the catalogue.</p>
        <?php } else { ?>
          <form action="order_status.php?id=<?= (int) $order['id'] ?>" method="post" class="stack">
            <?= csrf_field() ?>
            <div class="field">
              <label for="status">Order status</label>
              <select id="status" name="status">
                <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="completed" <?= $order['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                <option value="cancelled">Cancelled (restocks items)</option>
              </select>
            </div>
            <button type="submit" class="btn btn-block">Save status</button>
          </form>
        <?php } ?>
      <?php } ?>
    </aside>
  </div>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>

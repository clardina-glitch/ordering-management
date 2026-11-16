<?php
require_once __DIR__ . '/helpers.php';
require_login();

$user = current_user();
$isAdmin = $user['role'] === 'admin';
$q = query_text('q');
$conditions = [];
$params = [];

if (!$isAdmin) {
    $conditions[] = 'o.user_id = ?';
    $params[] = (int) $user['id'];
}

if ($q !== '') {
    $like = '%' . $q . '%';
    $search = '(u.name LIKE ? OR u.email LIKE ? OR o.status LIKE ?
        OR EXISTS (SELECT 1 FROM order_items oi2 WHERE oi2.order_id = o.id AND oi2.product_name LIKE ?)';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;

    if (preg_match('/^\d+$/', $q)) {
        $search .= ' OR o.id = ?';
        $params[] = (int) $q;
    }

    $search .= ')';
    $conditions[] = $search;
}

$sql = 'SELECT o.id, o.total, o.status, o.created_at, u.name AS customer_name,
            (SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi
             WHERE oi.order_id = o.id) AS item_count
        FROM orders o
        JOIN users u ON u.id = o.user_id';

if (count($conditions) > 0) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}

$sql .= ' ORDER BY o.created_at DESC, o.id DESC LIMIT 100';

$statement = db()->prepare($sql);
$statement->execute($params);
$orders = $statement->fetchAll();

$pageTitle = $isAdmin ? 'All orders' : 'My orders';
include __DIR__ . '/partials/header.php';
?>
<main class="container page">
  <div class="page-head">
    <div>
      <p class="eyebrow"><?= $isAdmin ? 'Admin' : 'History' ?></p>
      <h1><?= $isAdmin ? 'All customer orders' : 'My orders' ?></h1>
      <p class="muted">
        <?= $isAdmin ? 'Search orders and update their status.' : 'Every order you have placed, newest first.' ?>
      </p>
    </div>
    <a href="products.php" class="btn">Browse products</a>
  </div>

  <form action="orders.php" method="get" class="search-form card">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search by order number, customer, product or status…" aria-label="Search orders">
    <button type="submit" class="btn">Search</button>
    <?php if ($q !== '') { ?><a href="orders.php" class="btn btn-ghost">Clear</a><?php } ?>
  </form>

  <p class="result-count muted">
    <?= count($orders) ?> order<?= count($orders) === 1 ? '' : 's' ?><?= $q !== '' ? ' matching “' . e($q) . '”' : '' ?>
  </p>

  <?php if (count($orders) === 0) { ?>
    <div class="empty-state card">
      <span class="empty-icon">📦</span>
      <h3>No orders found</h3>
      <p class="muted"><?= $q !== '' ? 'Try a different search term.' : 'Orders will show up here once placed.' ?></p>
      <?php if ($q !== '') { ?>
        <a href="orders.php" class="btn btn-small">Show all orders</a>
      <?php } else { ?>
        <a href="products.php" class="btn btn-small">Start an order</a>
      <?php } ?>
    </div>
  <?php } else { ?>
    <div class="table-wrap card">
      <table>
        <thead>
          <tr>
            <th>Order</th>
            <th>Date</th>
            <?php if ($isAdmin) { ?><th>Customer</th><?php } ?>
            <th>Items</th>
            <th>Total</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($orders as $order) { ?>
            <tr>
              <td><a class="strong-link" href="order.php?id=<?= (int) $order['id'] ?>">#<?= (int) $order['id'] ?></a></td>
              <td class="muted"><?= e(format_date($order['created_at'])) ?></td>
              <?php if ($isAdmin) { ?><td><?= e($order['customer_name']) ?></td><?php } ?>
              <td><?= (int) $order['item_count'] ?></td>
              <td><?= money($order['total']) ?></td>
              <td><span class="status status-<?= e($order['status']) ?>"><?= e($order['status']) ?></span></td>
              <td class="cell-action"><a href="order.php?id=<?= (int) $order['id'] ?>" class="btn btn-ghost btn-small">View</a></td>
            </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
  <?php } ?>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>

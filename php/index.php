<?php
require_once __DIR__ . '/helpers.php';
require_login();

$user = current_user();
$isAdmin = $user['role'] === 'admin';

if ($isAdmin) {
    $productCount = (int) db()->query('SELECT COUNT(*) FROM products')->fetchColumn();
    $customerCount = (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
    $orderCount = (int) db()->query('SELECT COUNT(*) FROM orders')->fetchColumn();
    $revenue = (float) db()->query("SELECT COALESCE(SUM(total), 0) FROM orders WHERE status <> 'cancelled'")->fetchColumn();
    $lowStock = (int) db()->query('SELECT COUNT(*) FROM products WHERE stock < 10')->fetchColumn();

    $stats = [
        ['label' => 'Products', 'value' => $productCount, 'hint' => $lowStock . ' low on stock'],
        ['label' => 'Customers', 'value' => $customerCount, 'hint' => 'Registered accounts'],
        ['label' => 'Orders', 'value' => $orderCount, 'hint' => 'All time'],
        ['label' => 'Revenue', 'value' => money($revenue), 'hint' => 'Excludes cancelled'],
    ];

    $recentOrders = db()->query(
        'SELECT o.id, o.total, o.status, o.created_at, u.name AS customer_name
         FROM orders o
         JOIN users u ON u.id = o.user_id
         ORDER BY o.created_at DESC, o.id DESC
         LIMIT 6'
    )->fetchAll();
} else {
    $statement = db()->prepare('SELECT COUNT(*) FROM orders WHERE user_id = ?');
    $statement->execute([(int) $user['id']]);
    $orderCount = (int) $statement->fetchColumn();

    $statement = db()->prepare("SELECT COALESCE(SUM(total), 0) FROM orders WHERE user_id = ? AND status <> 'cancelled'");
    $statement->execute([(int) $user['id']]);
    $spent = (float) $statement->fetchColumn();

    $statement = db()->prepare(
        "SELECT COALESCE(SUM(oi.quantity), 0)
         FROM order_items oi
         JOIN orders o ON o.id = oi.order_id
         WHERE o.user_id = ? AND o.status <> 'cancelled'"
    );
    $statement->execute([(int) $user['id']]);
    $items = (int) $statement->fetchColumn();

    $statement = db()->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = 'pending'");
    $statement->execute([(int) $user['id']]);
    $pending = (int) $statement->fetchColumn();

    $productCount = (int) db()->query('SELECT COUNT(*) FROM products')->fetchColumn();

    $stats = [
        ['label' => 'My orders', 'value' => $orderCount, 'hint' => $pending . ' pending'],
        ['label' => 'Items ordered', 'value' => $items, 'hint' => 'All time'],
        ['label' => 'Total spent', 'value' => money($spent), 'hint' => 'Excludes cancelled'],
        ['label' => 'Available products', 'value' => $productCount, 'hint' => 'Browse the catalogue'],
    ];

    $statement = db()->prepare(
        'SELECT o.id, o.total, o.status, o.created_at, u.name AS customer_name
         FROM orders o
         JOIN users u ON u.id = o.user_id
         WHERE o.user_id = ?
         ORDER BY o.created_at DESC, o.id DESC
         LIMIT 6'
    );
    $statement->execute([(int) $user['id']]);
    $recentOrders = $statement->fetchAll();
}

$firstName = explode(' ', trim($user['name']))[0];
$pageTitle = 'Dashboard';
include __DIR__ . '/partials/header.php';
?>
<main class="container page">
  <div class="page-head">
    <div>
      <p class="eyebrow">Overview</p>
      <h1>Welcome back, <?= e($firstName) ?></h1>
      <p class="muted">
        <?= $isAdmin
            ? 'Manage the catalogue and keep an eye on every customer order.'
            : 'Pick your favourite products and place an order in a few clicks.' ?>
      </p>
    </div>
    <div class="page-actions">
      <a href="products.php" class="btn">Browse products</a>
      <?php if ($isAdmin) { ?>
        <a href="product_form.php" class="btn btn-ghost">Add product</a>
      <?php } else { ?>
        <a href="orders.php" class="btn btn-ghost">My orders</a>
      <?php } ?>
    </div>
  </div>

  <section class="stats-grid">
    <?php foreach ($stats as $stat) { ?>
      <article class="stat-card">
        <span class="stat-label"><?= e($stat['label']) ?></span>
        <strong class="stat-value"><?= e($stat['value']) ?></strong>
        <span class="stat-hint"><?= e($stat['hint']) ?></span>
      </article>
    <?php } ?>
  </section>

  <div class="section-head">
    <div>
      <h2><?= $isAdmin ? 'Latest orders' : 'Recent orders' ?></h2>
      <p class="muted"><?= $isAdmin ? 'Newest customer activity across the store.' : 'Your latest purchases.' ?></p>
    </div>
    <a href="orders.php" class="link-arrow">View all →</a>
  </div>

  <?php if (count($recentOrders) === 0) { ?>
    <div class="empty-state card">
      <span class="empty-icon">🛒</span>
      <h3>No orders yet</h3>
      <p class="muted">
        <?= $isAdmin ? 'Customer orders will appear here.' : 'Head to the catalogue and place your first order.' ?>
      </p>
      <a href="products.php" class="btn btn-small">Browse products</a>
    </div>
  <?php } else { ?>
    <div class="table-wrap card">
      <table>
        <thead>
          <tr>
            <th>Order</th>
            <?php if ($isAdmin) { ?><th>Customer</th><?php } ?>
            <th>Date</th>
            <th>Total</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentOrders as $order) { ?>
            <tr>
              <td><a class="strong-link" href="order.php?id=<?= (int) $order['id'] ?>">#<?= (int) $order['id'] ?></a></td>
              <?php if ($isAdmin) { ?><td><?= e($order['customer_name']) ?></td><?php } ?>
              <td class="muted"><?= e(format_date($order['created_at'])) ?></td>
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

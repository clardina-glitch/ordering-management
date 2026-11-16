<?php
require_once __DIR__ . '/helpers.php';
require_login();

$user = current_user();
$isAdmin = $user['role'] === 'admin';
$q = query_text('q');

if ($q !== '') {
    $statement = db()->prepare(
        'SELECT * FROM products WHERE name LIKE ? OR description LIKE ? ORDER BY name ASC'
    );
    $statement->execute(['%' . $q . '%', '%' . $q . '%']);
    $products = $statement->fetchAll();
} else {
    $products = db()->query('SELECT * FROM products ORDER BY name ASC')->fetchAll();
}

$pageTitle = 'Products';
include __DIR__ . '/partials/header.php';
?>
<main class="container page">
  <div class="page-head">
    <div>
      <p class="eyebrow">Catalogue</p>
      <h1>Available products</h1>
      <p class="muted">Choose a product, set the quantity and add it to your order.</p>
    </div>
    <?php if ($isAdmin) { ?>
      <div class="page-actions">
        <a href="product_form.php" class="btn">Add product</a>
      </div>
    <?php } ?>
  </div>

  <form action="products.php" method="get" class="search-form card">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search products by name or description…" aria-label="Search products">
    <button type="submit" class="btn">Search</button>
    <?php if ($q !== '') { ?><a href="products.php" class="btn btn-ghost">Clear</a><?php } ?>
  </form>

  <p class="result-count muted">
    <?= count($products) ?> product<?= count($products) === 1 ? '' : 's' ?>
    <?= $q !== '' ? 'matching “' . e($q) . '”' : 'in the catalogue' ?>
  </p>

  <?php if (count($products) === 0) { ?>
    <div class="empty-state card">
      <span class="empty-icon">🔍</span>
      <h3>No products found</h3>
      <p class="muted">Try a different search term.</p>
      <a href="products.php" class="btn btn-small">Show all products</a>
    </div>
  <?php } else { ?>
    <section class="product-grid">
      <?php foreach ($products as $product) { ?>
        <article class="product-card card">
          <div class="product-thumb"><?= e($product['image']) ?></div>
          <div class="product-body">
            <div class="product-title-row">
              <h3><?= e($product['name']) ?></h3>
              <span class="price"><?= money($product['price']) ?></span>
            </div>
            <p class="muted product-desc"><?= e($product['description'] ?: 'No description provided.') ?></p>

            <?php if ((int) $product['stock'] < 1) { ?>
              <span class="badge badge-out">Out of stock</span>
            <?php } elseif ((int) $product['stock'] < 10) { ?>
              <span class="badge badge-warn">Only <?= (int) $product['stock'] ?> left</span>
            <?php } else { ?>
              <span class="badge badge-ok"><?= (int) $product['stock'] ?> in stock</span>
            <?php } ?>

            <form action="cart_action.php" method="post" class="add-form">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="add">
              <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
              <label class="qty">
                <span>Qty</span>
                <input type="number" name="quantity" value="1" min="1" max="<?= (int) $product['stock'] ?>" <?= (int) $product['stock'] < 1 ? 'disabled' : '' ?>>
              </label>
              <button type="submit" class="btn btn-small" <?= (int) $product['stock'] < 1 ? 'disabled' : '' ?>>Add to order</button>
            </form>

            <?php if ($isAdmin) { ?>
              <div class="admin-actions">
                <a href="product_form.php?id=<?= (int) $product['id'] ?>" class="btn btn-ghost btn-small">Edit</a>
                <form action="product_delete.php" method="post" data-confirm="Delete this product? This cannot be undone.">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-small">Delete</button>
                </form>
              </div>
            <?php } ?>
          </div>
        </article>
      <?php } ?>
    </section>
  <?php } ?>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>

<?php
require_once __DIR__ . '/helpers.php';
require_admin();

$rawId = $_GET['id'] ?? null;
$id = $rawId !== null ? parse_id($rawId) : null;
$isEdit = $id !== null;

if ($rawId !== null && $id === null) {
    render_message_page(404, 'Product not found', 'That product does not exist or may have been deleted.', '🔍');
}

$product = ['name' => '', 'description' => '', 'price' => '', 'stock' => '', 'image' => '📦'];
$errors = [];

if ($isEdit) {
    $statement = db()->prepare('SELECT * FROM products WHERE id = ?');
    $statement->execute([$id]);
    $existing = $statement->fetch();

    if (!$existing) {
        render_message_page(404, 'Product not found', 'That product does not exist or may have been deleted.', '🔍');
    }

    $product = $existing;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $product = [
        'name' => post_text('name', 120),
        'description' => post_text('description', 1000),
        'price' => trim((string) ($_POST['price'] ?? '')),
        'stock' => trim((string) ($_POST['stock'] ?? '')),
        'image' => post_text('image', 16) ?: '📦',
    ];

    $price = parse_price($product['price']);
    $stock = parse_stock($product['stock']);

    if (function_exists('mb_strlen') ? mb_strlen($product['name']) < 2 : strlen($product['name']) < 2) {
        $errors[] = 'Product name must be at least 2 characters.';
    }
    if ($price === null) {
        $errors[] = 'Price must be a number between 0 and 99,999,999.99.';
    }
    if ($stock === null) {
        $errors[] = 'Stock must be a whole number of 0 or more.';
    }

    if (count($errors) === 0) {
        if ($isEdit) {
            $statement = db()->prepare(
                'UPDATE products SET name = ?, description = ?, price = ?, stock = ?, image = ? WHERE id = ?'
            );
            $statement->execute([$product['name'], $product['description'], $price, $stock, $product['image'], $id]);
            flash('success', 'Product updated.');
        } else {
            $statement = db()->prepare(
                'INSERT INTO products (name, description, price, stock, image) VALUES (?, ?, ?, ?, ?)'
            );
            $statement->execute([$product['name'], $product['description'], $price, $stock, $product['image']]);
            flash('success', 'Product added.');
        }

        redirect('products.php');
    }
}

$pageTitle = $isEdit ? 'Edit product' : 'Add product';
if (count($errors) > 0) {
    http_response_code(400);
}
include __DIR__ . '/partials/header.php';
?>
<main class="container page narrow">
  <div class="page-head">
    <div>
      <p class="eyebrow">Admin</p>
      <h1><?= $isEdit ? 'Edit product' : 'Add a new product' ?></h1>
      <p class="muted"><?= $isEdit ? 'Update the details shown to customers.' : 'Fill in the details to publish a product to the catalogue.' ?></p>
    </div>
    <a href="products.php" class="btn btn-ghost">Back to products</a>
  </div>

  <?php if (count($errors) > 0) { ?>
    <div class="alert alert-error">
      <ul class="alert-list">
        <?php foreach ($errors as $message) { ?><li><?= e($message) ?></li><?php } ?>
      </ul>
    </div>
  <?php } ?>

  <form action="product_form.php<?= $isEdit ? '?id=' . $id : '' ?>" method="post" class="card form-card stack">
    <?= csrf_field() ?>
    <div class="field">
      <label for="name">Product name</label>
      <input id="name" type="text" name="name" value="<?= e($product['name']) ?>" maxlength="120" required>
    </div>

    <div class="form-row">
      <div class="field">
        <label for="price">Price (USD)</label>
        <input id="price" type="number" name="price" value="<?= e($product['price']) ?>" min="0" step="0.01" required>
      </div>
      <div class="field">
        <label for="stock">Stock quantity</label>
        <input id="stock" type="number" name="stock" value="<?= e($product['stock']) ?>" min="0" step="1" required>
      </div>
    </div>

    <div class="field">
      <label for="image">Product icon</label>
      <input id="image" type="text" name="image" value="<?= e($product['image']) ?>" maxlength="16">
      <small class="hint">Use a single emoji, for example ☕ or 🧊.</small>
    </div>

    <div class="field">
      <label for="description">Description</label>
      <textarea id="description" name="description" rows="4" maxlength="1000"><?= e($product['description']) ?></textarea>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn"><?= $isEdit ? 'Save changes' : 'Create product' ?></button>
      <a href="products.php" class="btn btn-ghost">Cancel</a>
    </div>
  </form>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>

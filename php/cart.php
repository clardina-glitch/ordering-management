<?php
require_once __DIR__ . '/helpers.php';
require_login();

$cart = load_cart();

$cleanCart = [];
foreach ($cart['items'] as $item) {
    $cleanCart[$item['id']] = $item['quantity'];
}
$_SESSION['cart'] = $cleanCart;

$pageTitle = 'Your order';
include __DIR__ . '/partials/header.php';
?>
<main class="container page">
  <div class="page-head">
    <div>
      <p class="eyebrow">Checkout</p>
      <h1>Your order</h1>
      <p class="muted">Review quantities, then place your order.</p>
    </div>
    <a href="products.php" class="btn btn-ghost">Continue shopping</a>
  </div>

  <?php if (count($cart['items']) === 0) { ?>
    <div class="empty-state card">
      <span class="empty-icon">🛒</span>
      <h3>Your order is empty</h3>
      <p class="muted">Add a few products from the catalogue to get started.</p>
      <a href="products.php" class="btn">Browse products</a>
    </div>
  <?php } else { ?>
    <div class="cart-layout">
      <section class="table-wrap card responsive-table">
        <table>
          <thead>
            <tr>
              <th>Product</th>
              <th>Price</th>
              <th>Quantity</th>
              <th>Subtotal</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($cart['items'] as $item) { ?>
              <tr>
                <td data-label="Product">
                  <span class="cart-product">
                    <span class="cart-thumb"><?= e($item['image']) ?></span>
                    <span>
                      <strong><?= e($item['name']) ?></strong>
                      <small class="muted"><?= (int) $item['stock'] ?> available</small>
                    </span>
                  </span>
                </td>
                <td data-label="Price"><?= money($item['price']) ?></td>
                <td data-label="Quantity">
                  <form action="cart_action.php" method="post" class="qty-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="product_id" value="<?= (int) $item['id'] ?>">
                    <input type="number" name="quantity" value="<?= (int) $item['quantity'] ?>" min="0" max="<?= (int) $item['stock'] ?>" aria-label="Quantity for <?= e($item['name']) ?>">
                    <button type="submit" class="btn btn-ghost btn-small">Update</button>
                  </form>
                </td>
                <td data-label="Subtotal" class="subtotal"><?= money($item['subtotal']) ?></td>
                <td class="cell-action">
                  <form action="cart_action.php" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="remove">
                    <input type="hidden" name="product_id" value="<?= (int) $item['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-small">Remove</button>
                  </form>
                </td>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </section>

      <aside class="summary card">
        <h2>Order summary</h2>
        <dl class="summary-list">
          <div><dt>Items</dt><dd><?= (int) $cart['count'] ?></dd></div>
          <div><dt>Subtotal</dt><dd><?= money($cart['total']) ?></dd></div>
          <div class="summary-total"><dt>Total</dt><dd><?= money($cart['total']) ?></dd></div>
        </dl>
        <form action="checkout.php" method="post">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-block">Place order</button>
        </form>
        <p class="hint">Stock is checked again when your order is placed.</p>
      </aside>
    </div>
  <?php } ?>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>

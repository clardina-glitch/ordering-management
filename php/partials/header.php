<?php
$currentUser = current_user();
$flash = pull_flash();
$cartCount = cart_count();
$currentFile = basename($_SERVER['PHP_SELF']);
$productPages = ['products.php', 'product_form.php'];
$orderPages = ['orders.php', 'order.php'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle ?? 'Orderly') ?> · Orderly</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="site-header">
  <div class="container nav">
    <a class="brand" href="<?= $currentUser ? 'index.php' : 'login.php' ?>">
      <span class="brand-mark">O</span>
      <span class="brand-text">Orderly<small>Product ordering</small></span>
    </a>

    <nav class="nav-links">
      <?php if ($currentUser) { ?>
        <a href="index.php" class="<?= $currentFile === 'index.php' ? 'active' : '' ?>">Dashboard</a>
        <a href="products.php" class="<?= in_array($currentFile, $productPages, true) ? 'active' : '' ?>">Products</a>
        <a href="cart.php" class="<?= $currentFile === 'cart.php' ? 'active' : '' ?>">
          Cart
          <?php if ($cartCount > 0) { ?><span class="cart-badge"><?= $cartCount ?></span><?php } ?>
        </a>
        <a href="orders.php" class="<?= in_array($currentFile, $orderPages, true) ? 'active' : '' ?>">
          <?= $currentUser['role'] === 'admin' ? 'All orders' : 'My orders' ?>
        </a>
        <?php if ($currentUser['role'] === 'admin') { ?>
          <a href="product_form.php" class="<?= $currentFile === 'product_form.php' ? 'active' : '' ?>">Add product</a>
        <?php } ?>
      <?php } else { ?>
        <a href="login.php" class="<?= $currentFile === 'login.php' ? 'active' : '' ?>">Log in</a>
        <a href="register.php" class="btn btn-small">Create account</a>
      <?php } ?>
    </nav>

    <?php if ($currentUser) { ?>
      <div class="nav-user">
        <span class="user-chip">
          <span class="avatar"><?= e(strtoupper(substr($currentUser['name'], 0, 1))) ?></span>
          <span class="user-meta">
            <strong><?= e($currentUser['name']) ?></strong>
            <small><?= $currentUser['role'] === 'admin' ? 'Administrator' : 'Customer' ?></small>
          </span>
        </span>
        <form action="logout.php" method="post">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-ghost btn-small">Log out</button>
        </form>
      </div>
    <?php } ?>
  </div>

  <?php if ($flash) { ?>
    <div class="container">
      <div class="alert alert-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
    </div>
  <?php } ?>
</header>

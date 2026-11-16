<?php
require_once __DIR__ . '/helpers.php';

if (current_user()) {
    redirect('index.php');
}

$email = '';
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $email = strtolower(clean_text($_POST['email'] ?? '', 190));
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Email and password are required.';
    } else {
        $statement = db()->prepare('SELECT * FROM users WHERE email = ?');
        $statement->execute([$email]);
        $user = $statement->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            flash('success', 'Welcome back, ' . $user['name'] . '.');
            redirect('index.php');
        }

        $error = 'Invalid email or password.';
    }
}

$pageTitle = 'Log in';
if ($error) {
    http_response_code(400);
}
include __DIR__ . '/partials/header.php';
?>
<main class="container page auth-page">
  <section class="auth-card card">
    <div class="auth-intro">
      <p class="eyebrow">Welcome back</p>
      <h1>Log in to Orderly</h1>
      <p class="muted">Browse the catalogue, build an order and track your purchases.</p>
    </div>

    <?php if ($error) { ?>
      <div class="alert alert-error"><?= e($error) ?></div>
    <?php } ?>

    <form action="login.php" method="post" class="stack">
      <?= csrf_field() ?>
      <div class="field">
        <label for="email">Email address</label>
        <input id="email" type="email" name="email" value="<?= e($email) ?>" autocomplete="email" required autofocus>
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input id="password" type="password" name="password" autocomplete="current-password" required>
      </div>
      <button type="submit" class="btn btn-block">Log in</button>
    </form>

    <p class="auth-switch muted">New here? <a href="register.php">Create an account</a></p>

    <div class="demo-box">
      <strong>Demo accounts</strong>
      <span>Admin: admin@example.com / admin123</span>
      <span>Customer: customer@example.com / customer123</span>
    </div>
  </section>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>

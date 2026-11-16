<?php
require_once __DIR__ . '/helpers.php';

if (current_user()) {
    redirect('index.php');
}

$values = ['name' => '', 'email' => ''];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $values['name'] = clean_text($_POST['name'] ?? '', 80);
    $values['email'] = strtolower(clean_text($_POST['email'] ?? '', 190));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirmPassword'] ?? '');

    if (strlen($values['name']) < 2) {
        $errors[] = 'Name must be at least 2 characters.';
    }
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (count($errors) === 0) {
        try {
            $statement = db()->prepare(
                'INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)'
            );
            $statement->execute([
                $values['name'],
                $values['email'],
                password_hash($password, PASSWORD_DEFAULT),
                'customer',
            ]);

            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) db()->lastInsertId();
            flash('success', 'Welcome! Your account is ready.');
            redirect('index.php');
        } catch (PDOException $error) {
            if ($error->getCode() === '23000') {
                $errors[] = 'An account with that email already exists.';
            } else {
                throw $error;
            }
        }
    }
}

$pageTitle = 'Create account';
if (count($errors) > 0) {
    http_response_code(400);
}
include __DIR__ . '/partials/header.php';
?>
<main class="container page auth-page">
  <section class="auth-card card">
    <div class="auth-intro">
      <p class="eyebrow">Get started</p>
      <h1>Create your account</h1>
      <p class="muted">Sign up as a customer to place and track orders.</p>
    </div>

    <?php if (count($errors) > 0) { ?>
      <div class="alert alert-error">
        <ul class="alert-list">
          <?php foreach ($errors as $message) { ?><li><?= e($message) ?></li><?php } ?>
        </ul>
      </div>
    <?php } ?>

    <form action="register.php" method="post" class="stack">
      <?= csrf_field() ?>
      <div class="field">
        <label for="name">Full name</label>
        <input id="name" type="text" name="name" value="<?= e($values['name']) ?>" maxlength="80" autocomplete="name" required>
      </div>
      <div class="field">
        <label for="email">Email address</label>
        <input id="email" type="email" name="email" value="<?= e($values['email']) ?>" maxlength="190" autocomplete="email" required>
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input id="password" type="password" name="password" minlength="6" autocomplete="new-password" required>
        <small class="hint">Use at least 6 characters.</small>
      </div>
      <div class="field">
        <label for="confirmPassword">Confirm password</label>
        <input id="confirmPassword" type="password" name="confirmPassword" minlength="6" autocomplete="new-password" required>
      </div>
      <button type="submit" class="btn btn-block">Create account</button>
    </form>

    <p class="auth-switch muted">Already registered? <a href="login.php">Log in</a></p>
  </section>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>

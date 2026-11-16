<?php

require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => $secure,
    ]);

    $sessionConfig = app_config();
    session_name($sessionConfig['session_name'] ?? 'ordering_sid');
    session_start();
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function pull_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return $flash;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function require_csrf(): void
{
    $token = isset($_POST['csrf']) ? (string) $_POST['csrf'] : '';
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) {
        render_message_page(400, 'Invalid request', 'Your session expired or the form token is invalid. Please try again.', '⚠️');
    }
}

function current_user(): ?array
{
    static $loaded = false;
    static $user = null;

    if ($loaded) {
        return $user;
    }

    $loaded = true;

    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $statement = db()->prepare('SELECT id, name, email, role FROM users WHERE id = ?');
    $statement->execute([(int) $_SESSION['user_id']]);
    $row = $statement->fetch();

    if (!$row) {
        unset($_SESSION['user_id']);
        return null;
    }

    $user = $row;

    return $user;
}

function require_login(): void
{
    if (!current_user()) {
        flash('error', 'Please log in to continue.');
        redirect('login.php');
    }
}

function require_admin(): void
{
    require_login();

    if (current_user()['role'] !== 'admin') {
        flash('error', 'You do not have permission to do that.');
        redirect('index.php');
    }
}

function money($value): string
{
    return '$' . number_format((float) $value, 2);
}

function format_date(?string $value): string
{
    $timestamp = $value ? strtotime($value) : false;

    return $timestamp ? date('M j, Y, g:i A', $timestamp) : (string) $value;
}

function clean_text(?string $value, int $maxLength): string
{
    $text = trim((string) $value);

    return function_exists('mb_substr') ? mb_substr($text, 0, $maxLength) : substr($text, 0, $maxLength);
}

function post_text(string $key, int $maxLength = 1000): string
{
    return clean_text($_POST[$key] ?? '', $maxLength);
}

function query_text(string $key, int $maxLength = 100): string
{
    return clean_text($_GET[$key] ?? '', $maxLength);
}

function parse_id($value): ?int
{
    if (filter_var($value, FILTER_VALIDATE_INT) === false) {
        return null;
    }

    $id = (int) $value;

    return $id > 0 ? $id : null;
}

function parse_quantity($value, int $max = 9999): ?int
{
    if (filter_var($value, FILTER_VALIDATE_INT) === false) {
        return null;
    }

    $quantity = (int) $value;

    return ($quantity >= 1 && $quantity <= $max) ? $quantity : null;
}

function parse_price($value): ?float
{
    if (!is_numeric($value)) {
        return null;
    }

    $price = (float) $value;

    if ($price < 0 || $price > 99999999.99) {
        return null;
    }

    return round($price, 2);
}

function parse_stock($value): ?int
{
    if (filter_var($value, FILTER_VALIDATE_INT) === false) {
        return null;
    }

    $stock = (int) $value;

    return ($stock >= 0 && $stock <= 1000000) ? $stock : null;
}

function cart_count(): int
{
    $count = 0;

    foreach (($_SESSION['cart'] ?? []) as $quantity) {
        $count += max(0, (int) $quantity);
    }

    return $count;
}

function load_cart(): array
{
    $raw = $_SESSION['cart'] ?? [];
    $ids = [];

    foreach ($raw as $id => $quantity) {
        $id = (int) $id;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }

    if (count($ids) === 0) {
        return ['items' => [], 'total' => 0.0, 'count' => 0];
    }

    $ids = array_values($ids);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $statement = db()->prepare("SELECT id, name, price, stock, image FROM products WHERE id IN ($placeholders)");
    $statement->execute($ids);

    $products = [];
    foreach ($statement->fetchAll() as $product) {
        $products[(int) $product['id']] = $product;
    }

    $items = [];
    $total = 0.0;
    $count = 0;

    foreach ($ids as $id) {
        if (!isset($products[$id])) {
            continue;
        }

        $product = $products[$id];
        $quantity = max(0, min((int) ($raw[$id] ?? 0), (int) $product['stock']));

        if ($quantity < 1) {
            continue;
        }

        $price = (float) $product['price'];
        $subtotal = round($price * $quantity, 2);

        $items[] = [
            'id' => $id,
            'name' => $product['name'],
            'price' => $price,
            'quantity' => $quantity,
            'stock' => (int) $product['stock'],
            'image' => $product['image'],
            'subtotal' => $subtotal,
        ];

        $total += $subtotal;
        $count += $quantity;
    }

    return ['items' => $items, 'total' => round($total, 2), 'count' => $count];
}

function render_message_page(int $status, string $title, string $message, string $icon = '⚠️'): void
{
    http_response_code($status);
    $pageTitle = $title;
    include __DIR__ . '/partials/header.php';
    echo '<main class="container page"><div class="empty-state card">';
    echo '<span class="empty-icon">' . e($icon) . '</span>';
    echo '<h1>' . e($title) . '</h1>';
    echo '<p class="muted">' . e($message) . '</p>';
    $target = current_user() ? 'index.php' : 'login.php';
    $label = current_user() ? 'Go to dashboard' : 'Go to login';
    echo '<a class="btn" href="' . $target . '">' . $label . '</a>';
    echo '</div></main>';
    include __DIR__ . '/partials/footer.php';
    exit;
}

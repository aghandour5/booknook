<?php
declare(strict_types=1);

session_start();

$DB_HOST = "127.0.0.1";
$DB_NAME = "booknook";
$DB_USER = "root";
$DB_PASS = "";

$pdo = new PDO(
  "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
  $DB_USER,
  $DB_PASS,
  [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]
);

function h(?string $s): string {
  return htmlspecialchars($s ?? "", ENT_QUOTES, "UTF-8");
}

function me(): ?array {
  return $_SESSION["user"] ?? null;
}

function is_admin(): bool {
  $u = me();
  return $u && (($u["role"] ?? "user") === "admin");
}

function require_login(): void {
  if (!me()) { header("Location: auth.php?p=login"); exit; }
}

function require_admin(): void {
  if (!is_admin()) { http_response_code(403); exit("Access denied"); }
}

function csrf_token(): string {
  if (empty($_SESSION["csrf"])) $_SESSION["csrf"] = bin2hex(random_bytes(32));
  return $_SESSION["csrf"];
}

function csrf_check(): void {
  if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $t = $_POST["csrf"] ?? "";
    if (!$t || !hash_equals($_SESSION["csrf"] ?? "", $t)) {
      http_response_code(400);
      exit("Bad CSRF token");
    }
  }
}

function redirect(string $url): void {
  header("Location: {$url}");
  exit;
}

function active(string $file): string {
  return basename($_SERVER["PHP_SELF"]) === $file ? "active" : "";
}

function layout_header(string $title): void { ?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title><?= h($title) ?></title>
  <link rel="stylesheet" href="assets/app.css?v=3">
</head>
<body id="top">
  <div class="nav">
    <div class="container nav-inner">
      <a class="brand" href="index.php">
        <span class="diamond"></span>
        <span>Book</span>
      </a>

      <div class="nav-links">
        <a class="<?= active('index.php') ?>" href="index.php">HOME</a>
        <?php if (is_admin()): ?>
          <!-- No other links for admin as per instruction -->
        <?php elseif (me()): ?>
          <a class="<?= active('books.php') ?>" href="books.php">BOOKS</a>
          <a class="<?= active('about.php') ?>" href="about.php">ABOUT</a>
        <?php else: /* Logged out */ ?>
          <a class="<?= active('about.php') ?>" href="about.php">ABOUT</a>
        <?php endif; ?>
      </div>

      <div class="nav-right">
        <?php if (is_admin()): ?>
          <a class="btn" href="dashboard.php">Dashboard</a>
          <a class="btn" href="auth.php?p=logout">Logout</a>
        <?php elseif (me()): ?>
          <span class="nav-user">Hi, <?= h(me()["username"] ?? "") ?></span>
          <a class="btn" href="auth.php?p=logout">Logout</a>
        <?php else: /* Logged out */ ?>
          <a class="btn" href="auth.php?p=login">Login</a>
          <a class="btn btn-primary" href="auth.php?p=register">Register</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="page">
    <div class="container">
<?php }

function layout_footer(): void { ?>
    </div>

    <div class="footer">
      <div class="container footer-inner">
        <div>
          <h3>Follow Me</h3>
          <div class="socials">
            <!-- Facebook -->
            <a href="#" title="Facebook">
              <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M22 12a10 10 0 1 0-11.5 9.9v-7H8v-3h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.4h-1.2c-1.2 0-1.6.8-1.6 1.6V12H17l-.4 3h-2.6v7A10 10 0 0 0 22 12z"/></svg>
            </a>
            <!-- LinkedIn -->
            <a href="#" title="LinkedIn">
              <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4.98 3.5A2.5 2.5 0 1 1 5 8.5a2.5 2.5 0 0 1-.02-5zM3 9h4v12H3V9zm7 0h3.8v1.6h.1c.5-.9 1.8-1.9 3.7-1.9 4 0 4.7 2.6 4.7 6V21h-4v-5.3c0-1.3 0-3-1.8-3s-2.1 1.4-2.1 2.9V21h-4V9z"/></svg>
            </a>
            <!-- Instagram -->
            <a href="#" title="Instagram">
              <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 2h10a5 5 0 0 1 5 5v10a5 5 0 0 1-5 5H7a5 5 0 0 1-5-5V7a5 5 0 0 1 5-5zm10 2H7a3 3 0 0 0-3 3v10a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3zm-5 3.5A4.5 4.5 0 1 1 7.5 12 4.5 4.5 0 0 1 12 7.5zm0 2A2.5 2.5 0 1 0 14.5 12 2.5 2.5 0 0 0 12 9.5zM17.8 6.2a1 1 0 1 1-1 1 1 1 0 0 1 1-1z"/></svg>
            </a>
            <!-- Behance -->
            <a href="#" title="Behance">
              <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M8.3 11.4H5.9v3.1h2.6c1.1 0 1.8-.5 1.8-1.6 0-1-.6-1.5-2-1.5zM8 7.3H5.9V10H8c1 0 1.7-.4 1.7-1.3S9.1 7.3 8 7.3zM21 11.4c0-2.6-1.5-4.4-4.2-4.4-2.6 0-4.4 1.9-4.4 4.8 0 3 1.8 4.8 4.6 4.8 2.2 0 3.5-1 3.9-2.4h-2.1c-.3.6-.9 1-1.8 1-1.2 0-2-.8-2.1-2h6.1v-.8zM15 11.7c.1-1.1.8-1.8 1.8-1.8 1.1 0 1.7.7 1.8 1.8H15zM14.5 6h4v1.2h-4V6zM3 5h5.4c2 0 3.3 1.1 3.3 2.8 0 1.2-.6 2-1.6 2.4 1.4.4 2.2 1.5 2.2 3 0 2.1-1.6 3.4-4 3.4H3V5z"/></svg>
            </a>
          </div>
        </div>

        <div class="muted footer-text">
          A simple book community: track what you read, rate it, and discuss with others.
        </div>
      </div>
    </div>

    <a class="scroll-top" href="#top" title="Back to top">↑</a>
  </div>
</body>
</html>
<?php }
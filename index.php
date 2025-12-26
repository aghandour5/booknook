<?php
declare(strict_types=1);

require __DIR__ . "/config.php";

$error = null;
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $pass = $_POST["password"] ?? "";

    $st = $pdo->prepare("SELECT id,username,email,password_hash,role FROM users WHERE email=? LIMIT 1");
    $st->execute([$email]);
    $row = $st->fetch();

    if (!$row || !password_verify($pass, $row["password_hash"])) {
        $error = "Wrong email or password.";
    } else {
        unset($row["password_hash"]);
        $_SESSION["user"] = $row;
        redirect("books.php");
    }
}


layout_header("Home");
?>

<div class="hero">
  <div>
    <div class="kicker">CREATOR: YOUR NAME</div>
    <h1 class="h1">BOOK NOTES<br>PROJECT</h1>

    <p>
      Track the books you read, add your ratings, and share thoughts with the community.
      Use ISBN to fetch covers and author photos automatically.
    </p>

    <div class="hero-actions">
      <?php if (me()): ?>
        <a class="btn btn-primary" href="books.php">My Books</a>
        <a class="btn" href="library.php">Community</a>
      <?php else: ?>
        <form method="post" action="index.php">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <?php if ($error): ?>
                <div class="muted error-message"><?= h($error) ?></div>
            <?php endif; ?>
            <label>Email</label>
            <input name="email" type="email" required>

            <label>Password</label>
            <input name="password" type="password" required>

            <button class="btn btn-primary" type="submit">Login</button>
            <a class="btn" href="auth.php?p=register">Create an account</a>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <div class="hero-book-wrap">
    <!-- Put your book image here: assets/book.png (or any png) -->
    <img class="hero-book" src="assets/book.png" alt="Book">
  </div>
</div>

<div class="spacer-18"></div>

<div class="section-head">
  <h2 class="h2">BOOKS I’VE READ</h2>
  <div class="section-sub">
    Here you’ll find books added by the community. Go to BOOKS to explore, or MY BOOKS to add your own.
  </div>
</div>

<?php layout_footer(); ?>

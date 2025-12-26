<?php
declare(strict_types=1);

require __DIR__ . "/config.php";
csrf_check();

$p = $_GET["p"] ?? "login";
if ($p === "logout") { session_destroy(); redirect("index.php"); }

$error = null;

if ($p === "register" && $_SERVER["REQUEST_METHOD"] === "POST") {
  $username = trim($_POST["username"] ?? "");
  $email = trim($_POST["email"] ?? "");
  $pass = $_POST["password"] ?? "";

  if ($username === "" || $email === "" || $pass === "") {
    $error = "All fields are required.";
  } else {
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    try {
      $st = $pdo->prepare("INSERT INTO users(username,email,password_hash) VALUES (?,?,?)");
      $st->execute([$username, $email, $hash]);
      redirect("auth.php?p=login");
    } catch (Throwable $e) {
      $error = "Username/email already used.";
    }
  }
}

if ($p === "login" && $_SERVER["REQUEST_METHOD"] === "POST") {
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
    redirect("index.php");
  }
}

layout_header($p === "register" ? "Register" : "Login");
?>

<div class="section-head">
  <h2 class="h2"><?= $p === "register" ? "REGISTER" : "LOGIN" ?></h2>
  <div class="section-sub"><?= $p === "register" ? "Create a new account." : "Welcome back." ?></div>
</div>

<div class="panel">
  <?php if ($error): ?>
    <div class="muted error-message"><?= h($error) ?></div>
  <?php endif; ?>

  <?php if ($p === "register"): ?>
    <form method="post" action="auth.php?p=register">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

      <div class="form-grid">
        <div>
          <label>Username</label>
          <input name="username" required>
        </div>
        <div>
          <label>Email</label>
          <input name="email" type="email" required>
        </div>
      </div>

      <div class="mt-14">
        <label>Password</label>
        <input name="password" type="password" required>
      </div>

      <div class="btn-row mt-16">
        <button class="btn btn-primary" type="submit">Create account</button>
        <a class="btn" href="auth.php?p=login">I already have an account</a>
      </div>
    </form>
  <?php else: ?>
    <form method="post" action="auth.php?p=login">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

      <div class="mt-10">
        <label>Email</label>
        <input name="email" type="email" required>
      </div>

      <div class="mt-14">
        <label>Password</label>
        <input name="password" type="password" required>
      </div>

      <div class="btn-row mt-16">
        <button class="btn btn-primary" type="submit">Login</button>
        <a class="btn" href="auth.php?p=register">Create an account</a>
      </div>
    </form>
  <?php endif; ?>
</div>

<?php layout_footer(); ?>
<?php
declare(strict_types=1);

require __DIR__ . "/config.php";
require __DIR__ . "/openlibrary.php";

csrf_check();
require_admin();

$tab = $_GET["tab"] ?? "users";
$action = $_GET["action"] ?? "";

/* --- Actions --- */
if ($action === "delete_user") {
  if ($_SERVER["REQUEST_METHOD"] !== "POST") redirect("dashboard.php?tab=users");
  $id = (int)($_POST["id"] ?? 0);
  if ($id > 0 && $id !== (int)me()["id"]) {
    $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
  }
  redirect("dashboard.php?tab=users");
}

if ($action === "delete_book") {
  if ($_SERVER["REQUEST_METHOD"] !== "POST") redirect("dashboard.php?tab=books");
  $id = (int)($_POST["id"] ?? 0);
  if ($id > 0) $pdo->prepare("DELETE FROM books WHERE id=?")->execute([$id]);
  redirect("dashboard.php?tab=books");
}

if ($action === "delete_comment") {
  if ($_SERVER["REQUEST_METHOD"] !== "POST") redirect("dashboard.php?tab=books");
  $id = (int)($_POST["id"] ?? 0);
  if ($id > 0) $pdo->prepare("DELETE FROM comments WHERE id=?")->execute([$id]);
  redirect("dashboard.php?tab=books");
}

if ($action === "add_book") {
  if ($_SERVER["REQUEST_METHOD"] !== "POST") redirect("dashboard.php?tab=books");

  $userId = (int)($_POST["user_id"] ?? 0);
  $isbn = trim($_POST["isbn"] ?? "");
  $title = trim($_POST["title"] ?? "");
  $author = trim($_POST["author"] ?? "");
  $rating = (int)($_POST["rating"] ?? 5);
  $description = trim($_POST["description"] ?? "");
  $date_read = trim($_POST["date_read"] ?? "");

  if ($rating < 1 || $rating > 5) $rating = 5;

  $u = $pdo->prepare("SELECT id FROM users WHERE id=? LIMIT 1");
  $u->execute([$userId]);
  if (!$u->fetch()) redirect("dashboard.php?tab=books");

  $enrich = fetch_openlibrary_by_isbn($isbn);
  if ($title === "" && !empty($enrich["title"])) $title = $enrich["title"];
  if ($author === "" && !empty($enrich["author"])) $author = $enrich["author"];

  $isbnNorm = normalize_isbn($isbn);
  if ($isbnNorm === "" || $title === "" || $author === "") redirect("dashboard.php?tab=books");

  $pdo->prepare("
    INSERT INTO books(user_id,isbn,title,author,rating,description,date_read,cover_url,author_olid,author_photo_url)
    VALUES (?,?,?,?,?,?,?,?,?,?)
  ")->execute([
    $userId,
    $isbnNorm,
    $title,
    $author,
    $rating,
    $description !== "" ? $description : null,
    $date_read !== "" ? $date_read : null,
    $enrich["cover_url"] ?? null,
    $enrich["author_olid"] ?? null,
    $enrich["author_photo_url"] ?? null
  ]);

  redirect("dashboard.php?tab=books");
}

layout_header("Admin");

/* --- Data --- */
$users = $pdo->query("SELECT id,username,email,role,created_at FROM users ORDER BY created_at DESC")->fetchAll();

$books = $pdo->query("
  SELECT b.id,b.title,b.author,b.created_at,u.username
  FROM books b JOIN users u ON u.id=b.user_id
  ORDER BY b.created_at DESC
  LIMIT 200
")->fetchAll();

$comments = $pdo->query("
  SELECT c.id,c.content,c.created_at,u.username,b.title
  FROM comments c
  JOIN users u ON u.id=c.user_id
  JOIN books b ON b.id=c.book_id
  ORDER BY c.created_at DESC
  LIMIT 200
")->fetchAll();
?>

<div class="topbar">
  <h1 class="page-title">Admin</h1>
  <div class="btn-row">
    <a class="btn <?= $tab==="users" ? "btn-primary" : "" ?>" href="dashboard.php?tab=users">Users</a>
    <a class="btn <?= $tab==="books" ? "btn-primary" : "" ?>" href="dashboard.php?tab=books">Books</a>
  </div>
</div>

<div class="panel">
  <?php if ($tab === "users"): ?>
    <table class="table">
      <thead>
        <tr>
          <th>User</th>
          <th>Email</th>
          <th>Role</th>
          <th>Joined</th>
          <th class="text-right">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $x): ?>
          <tr>
            <td><b><?= h($x["username"]) ?></b></td>
            <td><?= h($x["email"]) ?></td>
            <td><?= h($x["role"]) ?></td>
            <td><?= h($x["created_at"]) ?></td>
            <td class="text-right">
              <form method="post" action="dashboard.php?tab=users&action=delete_user" class="delete-form" onsubmit="return confirm('Delete this user?')">
                <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= (int)$x["id"] ?>">
                <button class="btn btn-danger" <?= ((int)$x["id"] === (int)me()["id"]) ? "disabled" : "" ?>>Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

  <?php else: ?>
    <div class="notice add-book-notice">
      <b>Add Book (Admin)</b>
      <div class="muted add-book-muted">Add a book for any user. ISBN will fetch title/author/cover when available.</div>

      <form method="post" action="dashboard.php?tab=books&action=add_book" class="add-book-form">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

        <div class="form-grid">
          <div>
            <label>User</label>
            <select name="user_id" required>
              <?php foreach ($users as $u): ?>
                <option value="<?= (int)$u["id"] ?>"><?= h($u["username"]) ?> (<?= h($u["email"]) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label>Rating</label>
            <select name="rating">
              <option>5</option><option>4</option><option>3</option><option>2</option><option>1</option>
            </select>
          </div>
          <div>
            <label>ISBN</label>
            <input name="isbn" required placeholder="9780140328721">
          </div>
          <div>
            <label>Date read (optional)</label>
            <input type="date" name="date_read">
          </div>
          <div>
            <label>Title (optional if ISBN works)</label>
            <input name="title">
          </div>
          <div>
            <label>Author (optional if ISBN works)</label>
            <input name="author">
          </div>
          <div class="form-grid-full-width">
            <label>Description (optional)</label>
            <input name="description">
          </div>
        </div>

        <div class="btn-row mt-12">
          <button class="btn btn-primary" type="submit">Add Book</button>
        </div>
      </form>
    </div>

    <h3 class="books-table-title">Books</h3>
    <table class="table">
      <thead>
        <tr>
          <th>Title</th>
          <th>Author</th>
          <th>Added By</th>
          <th>Created</th>
          <th class="text-right">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($books as $x): ?>
          <tr>
            <td><b><?= h($x["title"]) ?></b></td>
            <td><?= h($x["author"]) ?></td>
            <td><?= h($x["username"]) ?></td>
            <td><?= h($x["created_at"]) ?></td>
            <td class="text-right">
              <form method="post" action="dashboard.php?tab=books&action=delete_book" class="delete-form" onsubmit="return confirm('Delete this book?')">
                <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= (int)$x["id"] ?>">
                <button class="btn btn-danger">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="hr"></div>

    <h3 class="comments-table-title">Comments</h3>
    <?php foreach ($comments as $c): ?>
      <div class="notice comment">
        <div class="book-meta"><?= h($c["username"]) ?> on “<?= h($c["title"]) ?>” • <?= h($c["created_at"]) ?></div>
        <div class="comment-content"><?= h($c["content"]) ?></div>
        <form method="post" action="dashboard.php?tab=books&action=delete_comment" class="delete-comment-form" onsubmit="return confirm('Delete this comment?')">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="id" value="<?= (int)$c["id"] ?>">
          <button class="btn">Delete Comment</button>
        </form>
      </div>
    <?php endforeach; ?>

  <?php endif; ?>
</div>

<?php layout_footer(); ?>

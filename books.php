<?php
declare(strict_types=1);

require __DIR__ . "/config.php";
require __DIR__ . "/openlibrary.php";

csrf_check();

$action = $_GET["action"] ?? "";
$id = (int)($_GET["id"] ?? 0);

/* ---------- Actions ---------- */
if ($action === "add") {
  require_login();
  if ($_SERVER["REQUEST_METHOD"] !== "POST") redirect("books.php");

  $isbn = trim($_POST["isbn"] ?? "");
  $title = trim($_POST["title"] ?? "");
  $author = trim($_POST["author"] ?? "");
  $rating = (int)($_POST["rating"] ?? 5);
  $description = trim($_POST["description"] ?? "");
  $date_read = trim($_POST["date_read"] ?? "");

  if ($rating < 1 || $rating > 5) $rating = 5;

  $enrich = fetch_openlibrary_by_isbn($isbn);
  if ($title === "" && !empty($enrich["title"])) $title = $enrich["title"];
  if ($author === "" && !empty($enrich["author"])) $author = $enrich["author"];

  $isbnNorm = normalize_isbn($isbn);

  if ($isbnNorm === "" || $title === "" || $author === "") {
    redirect("books.php?error=1");
  }

  $pdo->prepare("
    INSERT INTO books(user_id,isbn,title,author,rating,description,date_read,cover_url,author_olid,author_photo_url)
    VALUES (?,?,?,?,?,?,?,?,?,?)
  ")->execute([
    (int)me()["id"],
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

  redirect("books.php");
}

if ($action === "delete") {
  require_login();
  if ($_SERVER["REQUEST_METHOD"] !== "POST") redirect("books.php");

  $bookId = (int)($_POST["id"] ?? 0);
  if ($bookId <= 0) redirect("books.php");

  if (is_admin()) {
    $pdo->prepare("DELETE FROM books WHERE id=?")->execute([$bookId]);
  } else {
    $pdo->prepare("DELETE FROM books WHERE id=? AND user_id=?")->execute([$bookId, (int)me()["id"]]);
  }

  redirect("books.php");
}

if ($action === "comment") {
  require_login();
  if ($_SERVER["REQUEST_METHOD"] !== "POST") redirect("index.php");

  $bookId = (int)($_POST["book_id"] ?? 0);
  $content = trim($_POST["content"] ?? "");

  if ($bookId > 0 && $content !== "") {
    $pdo->prepare("INSERT INTO comments(book_id,user_id,content) VALUES (?,?,?)")
      ->execute([$bookId, (int)me()["id"], $content]);
  }

  redirect("books.php?id=" . $bookId);
}

/* ---------- Book Details Page ---------- */
if ($id > 0) {
  $st = $pdo->prepare("
    SELECT b.*, u.username
    FROM books b
    JOIN users u ON u.id=b.user_id
    WHERE b.id=?
    LIMIT 1
  ");
  $st->execute([$id]);
  $b = $st->fetch();

  if (!$b) {
    layout_header("Book Not Found");
    echo "<div class='notice'>Book not found.</div>";
    layout_footer();
    exit;
  }

  $st = $pdo->prepare("
    SELECT c.*, u.username
    FROM comments c
    JOIN users u ON u.id=c.user_id
    WHERE c.book_id=?
    ORDER BY c.created_at ASC
  ");
  $st->execute([$id]);
  $comments = $st->fetchAll();

  layout_header($b["title"]);
  ?>
  <div class="topbar">
    <h1 class="page-title"><?= h($b["title"]) ?></h1>
    <div class="btn-row">
      <a class="btn" href="index.php">Back</a>
      <?php if (me()): ?><a class="btn btn-primary" href="books.php">My Books</a><?php endif; ?>
    </div>
  </div>

  <div class="panel">
    <div class="form-grid">
      <div>
        <div class="book-card book-card-details">
          <div class="book-cover book-cover-details">
            <?php if (!empty($b["cover_url"])): ?>
              <img src="<?= h($b["cover_url"]) ?>" alt="">
            <?php else: ?>
              <div class="no-cover">No cover</div>
            <?php endif; ?>
          </div>
          <div class="book-body">
            <div class="book-author book-author-details"><?= h($b["author"]) ?></div>
            <div class="book-meta">
              By <?= h($b["username"]) ?> • Rating: <?= (int)$b["rating"] ?>/5<br>
              ISBN: <?= h($b["isbn"]) ?><br>
              Date read: <?= h($b["date_read"] ?? "—") ?>
            </div>
          </div>
        </div>
      </div>

      <div>
        <h3 class="section-title">Review</h3>
        <div class="notice comment-content"><?= h($b["description"] ?? "No review provided.") ?></div>

        <div class="hr"></div>

        <h3 class="section-title">
          Comments (<?= count($comments) ?>)
        </h3>

        <?php if (count($comments) === 0): ?>
          <div class="notice">No comments yet.</div>
        <?php else: ?>
          <?php foreach ($comments as $c): ?>
            <div class="notice comment">
              <div class="book-meta"><?= h($c["username"]) ?> • <?= h($c["created_at"]) ?></div>
              <div class="comment-content"><?= h($c["content"]) ?></div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <?php if (me()): ?>
          <div class="hr"></div>
          <form method="post" action="books.php?action=comment">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="book_id" value="<?= (int)$b["id"] ?>">

            <label>Add a comment</label>
            <textarea name="content" rows="4" required placeholder="Write your comment..."></textarea>

            <div class="btn-row mt-12">
              <button class="btn btn-primary" type="submit">Post Comment</button>
            </div>
          </form>
        <?php else: ?>
          <div class="notice">Please <a href="auth.php?p=login">login</a> to comment.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php
  layout_footer();
  exit;
}

/* ---------- My Books Page ---------- */
require_login();

$error = isset($_GET["error"]);

$st = $pdo->prepare("SELECT * FROM books WHERE user_id=? ORDER BY created_at DESC");
$st->execute([(int)me()["id"]]);
$myBooks = $st->fetchAll();

layout_header("My Books");
?>

<div class="topbar">
  <h1 class="page-title">My Books</h1>
</div>

<div class="panel mb-16">
  <?php if ($error): ?>
    <div class="notice error error-notice">
      Please provide ISBN and at least Title/Author (if Open Library can’t fill them).
    </div>
  <?php endif; ?>

  <form method="post" action="books.php?action=add">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

    <div class="form-grid">
      <div>
        <label>ISBN</label>
        <input name="isbn" required placeholder="9780140328721">
      </div>
      <div>
        <label>Rating</label>
        <select name="rating">
          <option>5</option><option>4</option><option>3</option><option>2</option><option>1</option>
        </select>
      </div>
      <div>
        <label>Title (optional if ISBN works)</label>
        <input name="title">
      </div>
      <div>
        <label>Author (optional if ISBN works)</label>
        <input name="author">
      </div>
      <div>
        <label>Date read (optional)</label>
        <input type="date" name="date_read">
      </div>
      <div>
        <label>Review / Description</label>
        <input name="description" placeholder="Short thoughts (optional)">
      </div>
    </div>

    <div class="btn-row mt-14">
      <button class="btn btn-primary" type="submit">Add Book</button>
    </div>
  </form>
</div>

<div class="panel">
  <?php if (count($myBooks) === 0): ?>
    <div class="notice">No books yet. Add your first one above.</div>
  <?php else: ?>
    <div class="book-grid">
      <?php foreach ($myBooks as $b): ?>
        <article class="book-card">
          <div class="book-cover">
            <?php if (!empty($b["cover_url"])): ?>
              <img src="<?= h($b["cover_url"]) ?>" alt="">
            <?php else: ?>
              <div class="no-cover">No cover</div>
            <?php endif; ?>
          </div>
          <div class="book-body">
            <h3 class="book-title"><a href="books.php?id=<?= (int)$b["id"] ?>"><?= h($b["title"]) ?></a></h3>
            <p class="book-author"><?= h($b["author"]) ?></p>
            <div class="book-meta">Rating: <?= (int)$b["rating"] ?>/5 • ISBN: <?= h($b["isbn"]) ?></div>

            <form method="post" action="books.php?action=delete" onsubmit="return confirm('Delete this book?')" class="mt-12">
              <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="id" value="<?= (int)$b["id"] ?>">
              <button class="btn btn-danger" type="submit">Delete</button>
            </form>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php layout_footer(); ?>

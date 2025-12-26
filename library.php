<?php
declare(strict_types=1);

require __DIR__ . "/config.php";

layout_header("Community Library");

$sort = $_GET["sort"] ?? "date";
$orderSql = "b.created_at DESC";
if ($sort === "rating") $orderSql = "b.rating DESC, b.created_at DESC";

$st = $pdo->query("
  SELECT b.*, u.username
  FROM books b
  JOIN users u ON u.id=b.user_id
  ORDER BY {$orderSql}
  LIMIT 200
");
$books = $st->fetchAll();
?>

<div class="section-head">
  <h2 class="h2">COMMUNITY LIBRARY</h2>
  <div class="section-sub">
    Browse what others are reading. Sort by newest or best rating.
  </div>

  <div class="sort-row">
    <span class="pill">Sort by:</span>
    <form method="get">
      <select name="sort" onchange="this.form.submit()">
        <option value="date" <?= $sort==="date" ? "selected" : "" ?>>Newest</option>
        <option value="rating" <?= $sort==="rating" ? "selected" : "" ?>>Best</option>
      </select>
    </form>
  </div>
</div>

<div class="surface library-surface">
  <?php if (count($books) === 0): ?>
    <div class="muted"><b>No books yet.</b> Add one from MY BOOKS.</div>
  <?php else: ?>
    <div class="book-grid">
      <?php foreach ($books as $b): ?>
        <article class="book-card">
          <div class="book-cover">
            <?php if (!empty($b["cover_url"])): ?>
              <img src="<?= h($b["cover_url"]) ?>" alt="">
            <?php else: ?>
              <img src="assets/placeholder.jpg" alt="" onerror="this.style.display='none'">
            <?php endif; ?>
          </div>
          <div class="book-body">
            <h3 class="book-title">
              <a href="books.php?id=<?= (int)$b["id"] ?>"><?= h($b["title"]) ?></a>
            </h3>
            <div class="book-meta">
              Author: <?= h($b["author"]) ?><br>
              Rating: <?= (int)$b["rating"] ?>/5<br>
              By <?= h($b["username"]) ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php layout_footer(); ?>

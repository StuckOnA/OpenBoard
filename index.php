<?php
// index.php - the feed: all posts, with keyword search, tag filter and three sort modes.
require 'db.php';

$q    = get_str('q');
$tag  = get_str('tag');
$sort = get_str('sort');
if ($sort === 'popular') {
    $sort = 'top';                                      // old Part 1 links keep working
}
if (!in_array($sort, ['new', 'top', 'hot'], true)) {    // whitelist: only these three can reach the SQL
    $sort = 'hot';                                      // default mode (change to 'new' if you prefer)
}

$where  = [];
$params = [];
if ($q !== '') {
    // addcslashes makes a typed % or _ match literally instead of acting as a wildcard.
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $where[] = '(p.title LIKE :q1 OR p.body LIKE :q2)';
    $params[':q1'] = $like;
    $params[':q2'] = $like;
}
if ($tag !== '') {
    $where[] = 'EXISTS (SELECT 1 FROM post_tags pt JOIN tags t ON t.id = pt.tag_id
                        WHERE pt.post_id = p.id AND t.name = :tag)';
    $params[':tag'] = $tag;
}

// HOT RANKING:  hot = sign(s) * log10(|s| + 1) + t / 45000
//   s = net score (upvotes - downvotes), t = creation time in seconds since 1970.
//   45000 seconds = 12.5 hours: every tenfold increase in (|s| + 1) is worth 12.5 hours of age.
//   s = 0 gives 0 (ranked by age only); |s| + 1 is never 0, so no log(0) and no division by zero.
//   The hot value is used only for ordering. It is never stored or shown.
$net = 'COALESCE(vs.net_score, 0)';
$hot = "SIGN($net) * LOG10(ABS($net) + 1) + UNIX_TIMESTAMP(p.created_at) / 45000";

$orders = [   // every mode ends with created_at, id so equal values never reshuffle between requests
    'new' => 'p.created_at DESC, p.id DESC',
    'top' => 'score DESC, p.created_at DESC, p.id DESC',
    'hot' => "$hot DESC, p.created_at DESC, p.id DESC",
];

$sql = POST_LIST_SQL;
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY ' . $orders[$sort] . ' LIMIT 50';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$posts = $stmt->fetchAll();

$headings = ['new' => 'Newest posts', 'top' => 'Top posts', 'hot' => 'Hot posts'];
$pageTitle = 'Home';
require 'header.php';
?>
<h1 class="h3 mb-3"><?= e($headings[$sort]) ?></h1>

<!-- GET form: the search box, the dropdown and the hidden tag are all sent together,
     so changing the sort keeps the current search and tag filter. -->
<form class="row g-2 mb-3" method="get" action="index.php">
  <div class="col-12 col-md-6">
    <input type="search" name="q" class="form-control" placeholder="Search titles and text..."
           value="<?= e($q) ?>">
  </div>
  <div class="col-6 col-md-3">
    <select name="sort" class="form-select">
      <option value="hot" <?= $sort === 'hot' ? 'selected' : '' ?>>Hot</option>
      <option value="new" <?= $sort === 'new' ? 'selected' : '' ?>>New</option>
      <option value="top" <?= $sort === 'top' ? 'selected' : '' ?>>Top</option>
    </select>
  </div>
  <div class="col-6 col-md-3"><button class="btn btn-primary w-100">Apply</button></div>
  <?php if ($tag !== ''): ?><input type="hidden" name="tag" value="<?= e($tag) ?>"><?php endif; ?>
</form>

<?php if ($tag !== ''): ?>
  <p>Showing posts tagged <span class="badge text-bg-secondary">#<?= e($tag) ?></span>
     <a href="index.php?<?= e(http_build_query(array_filter(['q' => $q, 'sort' => $sort]))) ?>">clear tag</a></p>
<?php endif; ?>

<?php if (count($posts) === 0): ?>
  <div class="alert alert-info">
    <?php if ($q !== '' || $tag !== ''): ?>
      No posts match your search. <a href="index.php">Show all posts</a>.
    <?php else: ?>
      No posts yet. <a href="create.php">Create the first one!</a>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php foreach ($posts as $post): ?>
  <?php include 'post_card.php'; ?>
<?php endforeach; ?>

<?php require 'footer.php'; ?>

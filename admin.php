<?php
// admin.php - admin dashboard: statistics plus a paged list of users, posts or comments.
require 'db.php';
require_admin();   // checks the role stored in the database, on every request

$stats = [
    'users'    => (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'posts'    => (int)$pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn(),
    'comments' => (int)$pdo->query('SELECT COUNT(*) FROM comments')->fetchColumn(),
];

$view = get_str('view');
if (!in_array($view, ['users', 'posts', 'comments'], true)) {   // whitelist
    $view = 'posts';
}

$perPage = 20;
$pages   = max(1, (int)ceil($stats[$view] / $perPage));
$page    = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
$page    = ($page && $page > 0) ? min($page, $pages) : 1;
$offset  = ($page - 1) * $perPage;   // plain integers computed here, so they are safe inside the SQL

if ($view === 'users') {
    // Only non-sensitive columns: the password hash is never selected.
    $sql = "SELECT id, username, role, created_at FROM users ORDER BY id LIMIT $perPage OFFSET $offset";
} elseif ($view === 'posts') {
    $sql = "SELECT p.id, p.title, p.created_at, p.user_id, u.username
            FROM posts p JOIN users u ON u.id = p.user_id
            ORDER BY p.created_at DESC, p.id DESC LIMIT $perPage OFFSET $offset";
} else {
    $sql = "SELECT c.id, c.body, c.created_at, c.post_id, c.user_id, u.username, p.title
            FROM comments c
            JOIN users u ON u.id = c.user_id
            JOIN posts p ON p.id = c.post_id
            ORDER BY c.created_at DESC, c.id DESC LIMIT $perPage OFFSET $offset";
}
$rows = $pdo->query($sql)->fetchAll();

$pageTitle = 'Admin';
require 'header.php';
?>
<h1 class="h3 mb-3">Admin dashboard</h1>

<div class="row g-3 mb-4">
  <?php foreach ($stats as $label => $count): ?>
    <div class="col-4"><div class="card text-center"><div class="card-body py-2">
      <div class="fs-4 fw-bold"><?= $count ?></div>
      <div class="small text-muted text-capitalize"><?= e($label) ?></div>
    </div></div></div>
  <?php endforeach; ?>
</div>

<ul class="nav nav-pills mb-3">
  <?php foreach (['users', 'posts', 'comments'] as $v): ?>
    <li class="nav-item">
      <a class="nav-link text-capitalize <?= $view === $v ? 'active' : '' ?>" href="admin.php?view=<?= $v ?>"><?= $v ?></a>
    </li>
  <?php endforeach; ?>
</ul>

<?php if (!$rows): ?>
  <div class="alert alert-info">Nothing to show here.</div>
<?php else: ?>
<div class="table-responsive">
<table class="table table-sm table-striped align-middle bg-white">
  <?php if ($view === 'users'): ?>
    <thead><tr><th>Username</th><th>Role</th><th>Joined</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><a href="profile.php?id=<?= (int)$r['id'] ?>">@<?= e($r['username']) ?></a></td>
        <td><span class="badge <?= $r['role'] === 'admin' ? 'text-bg-warning' : 'text-bg-secondary' ?>"><?= e($r['role']) ?></span></td>
        <td><?= e($r['created_at']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>

  <?php elseif ($view === 'posts'): ?>
    <thead><tr><th>Title</th><th>Author</th><th>Created</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><a href="post.php?id=<?= (int)$r['id'] ?>"><?= e(mb_strimwidth($r['title'], 0, 50, '...')) ?></a></td>
        <td><a href="profile.php?id=<?= (int)$r['user_id'] ?>">@<?= e($r['username']) ?></a></td>
        <td><?= e($r['created_at']) ?></td>
        <td>
          <form method="post" action="actions.php" onsubmit="return confirm('Delete this post and its comments?');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="admin_delete_post">
            <input type="hidden" name="post_id" value="<?= (int)$r['id'] ?>">
            <button class="btn btn-outline-danger btn-sm">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>

  <?php else: ?>
    <thead><tr><th>Comment</th><th>Author</th><th>On post</th><th>Created</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e(mb_strimwidth($r['body'], 0, 60, '...')) ?></td>
        <td><a href="profile.php?id=<?= (int)$r['user_id'] ?>">@<?= e($r['username']) ?></a></td>
        <td><a href="post.php?id=<?= (int)$r['post_id'] ?>#comments"><?= e(mb_strimwidth($r['title'], 0, 30, '...')) ?></a></td>
        <td><?= e($r['created_at']) ?></td>
        <td>
          <form method="post" action="actions.php" onsubmit="return confirm('Delete this comment?');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="admin_delete_comment">
            <input type="hidden" name="comment_id" value="<?= (int)$r['id'] ?>">
            <button class="btn btn-outline-danger btn-sm">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  <?php endif; ?>
</table>
</div>
<?php endif; ?>

<?php if ($pages > 1): ?>
  <nav><ul class="pagination pagination-sm">
    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
      <a class="page-link" href="admin.php?view=<?= $view ?>&amp;page=<?= $page - 1 ?>">Previous</a></li>
    <li class="page-item disabled"><span class="page-link">Page <?= $page ?> of <?= $pages ?></span></li>
    <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
      <a class="page-link" href="admin.php?view=<?= $view ?>&amp;page=<?= $page + 1 ?>">Next</a></li>
  </ul></nav>
<?php endif; ?>

<?php require 'footer.php'; ?>

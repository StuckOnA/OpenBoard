<?php
// profile.php - a user's public page: username, join date and their posts.
require 'db.php';

$id      = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$profile = false;
$posts   = [];

if ($id) {
    $stmt = $pdo->prepare('SELECT id, username, created_at FROM users WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $profile = $stmt->fetch();
}
if ($profile) {
    $stmt = $pdo->prepare(POST_LIST_SQL . ' WHERE p.user_id = :id ORDER BY p.created_at DESC, p.id DESC');
    $stmt->execute([':id' => $profile['id']]);
    $posts = $stmt->fetchAll();
} else {
    http_response_code(404);
}

$pageTitle = $profile ? '@' . $profile['username'] : 'User not found';
require 'header.php';
?>
<?php if (!$profile): ?>
  <div class="alert alert-warning">That user doesn't exist.</div>
  <a href="index.php">&larr; Back to all posts</a>
<?php else: ?>
  <h1 class="h3">@<?= e($profile['username']) ?></h1>
  <p class="text-muted">Joined <?= e($profile['created_at']) ?> &middot; <?= count($posts) ?> post(s)</p>

  <?php if (count($posts) === 0): ?>
    <div class="alert alert-info">This user hasn't posted yet.</div>
  <?php endif; ?>
  <?php foreach ($posts as $post): ?>
    <?php include 'post_card.php'; ?>
  <?php endforeach; ?>
<?php endif; ?>
<?php require 'footer.php'; ?>

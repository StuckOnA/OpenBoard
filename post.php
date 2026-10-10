<?php
// post.php - one post: details, voting, comments, share link.
require 'db.php';

$user = current_user();
$id   = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$post = false;

if ($id) {
    $stmt = $pdo->prepare(POST_LIST_SQL . ' WHERE p.id = :id');
    $stmt->execute([':id' => $id]);
    $post = $stmt->fetch();
}
if (!$post) {
    http_response_code(404);
}

$myVote   = 0;    // the logged-in user's current vote: 1, -1 or 0 (none)
$comments = [];
if ($post) {
    if ($user) {
        $stmt = $pdo->prepare('SELECT vote_value FROM votes WHERE user_id = :u AND post_id = :p');
        $stmt->execute([':u' => $user['id'], ':p' => $post['id']]);
        $myVote = (int)$stmt->fetchColumn();
    }
    $stmt = $pdo->prepare(
        'SELECT c.id, c.user_id, c.body, c.created_at, u.username
         FROM comments c JOIN users u ON u.id = c.user_id
         WHERE c.post_id = :id ORDER BY c.created_at, c.id'
    );
    $stmt->execute([':id' => $post['id']]);
    $comments = $stmt->fetchAll();

    $isOwner  = $user && $user['id'] === (int)$post['user_id'];
    $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $shareUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . strtok($_SERVER['REQUEST_URI'], '?') . '?id=' . $post['id'];
}

$pageTitle = $post ? $post['title'] : 'Post not found';
require 'header.php';
?>
<?php if (!$post): ?>
  <div class="alert alert-warning">That post doesn't exist.</div>
  <a href="index.php">&larr; Back to all posts</a>
<?php else: ?>
  <article class="card mb-3">
    <div class="card-body">
      <h1 class="h3"><?= e($post['title']) ?></h1>
      <p class="text-muted small mb-2">
        by <a href="profile.php?id=<?= (int)$post['user_id'] ?>">@<?= e($post['username']) ?></a>
        &middot; <?= e($post['created_at']) ?>
      </p>
      <?php if ($post['tags']): ?>
        <div class="mb-2">
          <?php foreach (explode(',', $post['tags']) as $tagName): ?>
            <a class="badge text-bg-secondary text-decoration-none"
               href="index.php?tag=<?= urlencode($tagName) ?>">#<?= e($tagName) ?></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <hr>
      <p><?= nl2br(e($post['body'])) ?></p>
      <hr>

      <?php if ($user): ?>
        <form method="post" action="actions.php" class="d-flex align-items-center gap-2">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="vote">
          <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
          <!-- Clicking the active button sends 0, which removes the vote. -->
          <button name="value" value="<?= $myVote === 1 ? 0 : 1 ?>"
                  class="btn btn-sm <?= $myVote === 1 ? 'btn-success' : 'btn-outline-success' ?>">&#9650; Upvote</button>
          <span class="fw-bold">Score: <?= (int)$post['score'] ?></span>
          <button name="value" value="<?= $myVote === -1 ? 0 : -1 ?>"
                  class="btn btn-sm <?= $myVote === -1 ? 'btn-danger' : 'btn-outline-danger' ?>">&#9660; Downvote</button>
        </form>
      <?php else: ?>
        <span class="fw-bold">Score: <?= (int)$post['score'] ?></span>
        <span class="text-muted small">&middot; <a href="login.php">Log in</a> to vote.</span>
      <?php endif; ?>

      <?php if ($isOwner): ?>
        <div class="mt-3 d-flex gap-2">
          <a class="btn btn-sm btn-outline-primary" href="create.php?id=<?= (int)$post['id'] ?>">Edit</a>
          <form method="post" action="actions.php" onsubmit="return confirm('Delete this post?');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_post">
            <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
            <button class="btn btn-sm btn-outline-danger">Delete</button>
          </form>
        </div>
      <?php endif; ?>

      <div class="input-group input-group-sm mt-3">
        <input type="text" class="form-control" id="share-url" value="<?= e($shareUrl) ?>" readonly>
        <button type="button" class="btn btn-outline-secondary" id="copy-btn">Copy link</button>
      </div>
    </div>
  </article>

  <h2 class="h5" id="comments"><?= count($comments) ?> comment(s)</h2>

  <?php if (count($comments) === 0): ?>
    <p class="text-muted">No comments yet.</p>
  <?php endif; ?>
  <?php foreach ($comments as $c): ?>
    <div class="card mb-2"><div class="card-body py-2">
      <div class="small text-muted">
        <a href="profile.php?id=<?= (int)$c['user_id'] ?>">@<?= e($c['username']) ?></a>
        &middot; <?= e($c['created_at']) ?>
      </div>
      <div><?= nl2br(e($c['body'])) ?></div>
      <?php if ($user && $user['id'] === (int)$c['user_id']): ?>
        <form method="post" action="actions.php" class="mt-1">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete_comment">
          <input type="hidden" name="comment_id" value="<?= (int)$c['id'] ?>">
          <button class="btn btn-link btn-sm text-danger p-0">Delete</button>
        </form>
      <?php endif; ?>
    </div></div>
  <?php endforeach; ?>

  <?php if ($user): ?>
    <form method="post" action="actions.php" class="mt-3">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="comment">
      <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
      <textarea name="body" class="form-control mb-2" rows="3" maxlength="2000"
                placeholder="Write a comment..."></textarea>
      <button class="btn btn-primary btn-sm">Add comment</button>
    </form>
  <?php else: ?>
    <p class="mt-3"><a href="login.php">Log in</a> to comment.</p>
  <?php endif; ?>

  <a class="d-inline-block mt-3" href="index.php">&larr; Back to all posts</a>

  <script>
  // Copies the share link. Falls back to selecting it if the clipboard API is unavailable.
  document.getElementById('copy-btn').addEventListener('click', function () {
    var input = document.getElementById('share-url'), btn = this;
    input.select();
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(input.value).then(function () { btn.textContent = 'Copied!'; });
    } else {
      document.execCommand('copy');
      btn.textContent = 'Copied (or press Ctrl+C)';
    }
  });
  </script>
<?php endif; ?>

<?php require 'footer.php'; ?>

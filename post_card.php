<?php // post_card.php - one post in a list. Expects $post (a row from POST_LIST_SQL). ?>
<div class="card mb-3">
  <div class="card-body d-flex gap-3">
    <div class="text-center" style="min-width: 48px;">
      <div class="fs-5 fw-bold"><?= (int)$post['score'] ?></div>
      <div class="small text-muted">score</div>
    </div>
    <div class="flex-grow-1">
      <h2 class="h5 mb-1">
        <a class="text-decoration-none" href="post.php?id=<?= (int)$post['id'] ?>"><?= e($post['title']) ?></a>
      </h2>
      <p class="mb-2 text-body-secondary"><?= e(mb_strimwidth($post['body'], 0, 150, '...')) ?></p>
      <?php if ($post['tags']): ?>
        <div class="mb-2">
          <?php foreach (explode(',', $post['tags']) as $tagName): ?>
            <a class="badge text-bg-secondary text-decoration-none"
               href="index.php?tag=<?= urlencode($tagName) ?>">#<?= e($tagName) ?></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <div class="small text-muted">
        by <a href="profile.php?id=<?= (int)$post['user_id'] ?>">@<?= e($post['username']) ?></a>
        &middot; <?= e($post['created_at']) ?>
        &middot; <?= (int)$post['comment_count'] ?> comment(s)
      </div>
    </div>
  </div>
</div>

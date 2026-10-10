<?php
// create.php - create a new post, or edit one when called as create.php?id=5.
require 'db.php';
$user = require_login();

$editId = null;
$title = $body = $tagsRaw = '';
$errors = [];

if (isset($_GET['id'])) {                       // edit mode
    $editId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$editId) {
        http_response_code(404);
        exit('Post not found.');
    }
    $stmt = $pdo->prepare('SELECT user_id, title, body FROM posts WHERE id = :id');
    $stmt->execute([':id' => $editId]);
    $existing = $stmt->fetch();
    if (!$existing) {
        http_response_code(404);
        exit('Post not found.');
    }
    if ((int)$existing['user_id'] !== $user['id']) {   // ownership check, from the SESSION user
        http_response_code(403);
        exit('You can only edit your own posts.');
    }
    $title = $existing['title'];
    $body  = $existing['body'];
    $stmt = $pdo->prepare('SELECT t.name FROM post_tags pt JOIN tags t ON t.id = pt.tag_id
                           WHERE pt.post_id = :id ORDER BY t.name');
    $stmt->execute([':id' => $editId]);
    $tagsRaw = implode(', ', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $title   = post_str('title');
    $body    = post_str('body');
    $tagsRaw = post_str('tags');

    if ($title === '') {
        $errors[] = 'Title is required.';
    } elseif (mb_strlen($title) > 200) {
        $errors[] = 'Title must be 200 characters or fewer.';
    }
    if ($body === '') {
        $errors[] = 'Body is required.';
    } elseif (mb_strlen($body) > 10000) {
        $errors[] = 'Body must be 10,000 characters or fewer.';
    }

    // Turn "PHP, mysql, php" into ['php', 'mysql'] (lowercase, unique, validated).
    $tags = [];
    foreach (explode(',', $tagsRaw) as $t) {
        $t = strtolower(trim($t));
        if ($t === '') {
            continue;
        }
        if (!preg_match('/^[a-z0-9-]{1,30}$/', $t)) {
            $errors[] = 'Invalid tag "' . $t . '": use 1-30 letters, numbers or hyphens.';
        } else {
            $tags[$t] = $t;
        }
    }
    if (count($tags) > 5) {
        $errors[] = 'Use at most 5 tags.';
    }

    if (!$errors) {
        // A transaction makes the post and its tags succeed or fail together.
        $pdo->beginTransaction();
        try {
            if ($editId) {
                $stmt = $pdo->prepare('UPDATE posts SET title = :t, body = :b WHERE id = :id AND user_id = :u');
                $stmt->execute([':t' => $title, ':b' => $body, ':id' => $editId, ':u' => $user['id']]);
                $postId = $editId;
                $pdo->prepare('DELETE FROM post_tags WHERE post_id = :id')->execute([':id' => $postId]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO posts (user_id, title, body) VALUES (:u, :t, :b)');
                $stmt->execute([':u' => $user['id'], ':t' => $title, ':b' => $body]);
                $postId = (int)$pdo->lastInsertId();
            }

            $addTag  = $pdo->prepare('INSERT IGNORE INTO tags (name) VALUES (:n)');         // reuse if it exists
            $findTag = $pdo->prepare('SELECT id FROM tags WHERE name = :n');
            $link    = $pdo->prepare('INSERT IGNORE INTO post_tags (post_id, tag_id) VALUES (:p, :t)');
            foreach ($tags as $name) {
                $addTag->execute([':n' => $name]);
                $findTag->execute([':n' => $name]);
                $link->execute([':p' => $postId, ':t' => $findTag->fetchColumn()]);
            }
            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('Save post failed: ' . $e->getMessage());
            $errors[] = 'Could not save the post. Please try again.';
        }
        if (!$errors) {
            $_SESSION['flash'] = ['success', $editId ? 'Post updated.' : 'Post created.'];
            redirect('post.php?id=' . $postId);
        }
    }
}

$pageTitle = $editId ? 'Edit post' : 'New post';
require 'header.php';
?>
<h1 class="h3 mb-4"><?= $editId ? 'Edit post' : 'Create a post' ?></h1>

<?php if ($errors): ?>
  <div class="alert alert-danger"><ul class="mb-0">
    <?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
  </ul></div>
<?php endif; ?>

<form method="post" action="create.php<?= $editId ? '?id=' . (int)$editId : '' ?>">
  <?= csrf_field() ?>
  <div class="mb-3">
    <label for="title" class="form-label">Title</label>
    <input type="text" class="form-control" id="title" name="title" maxlength="200" value="<?= e($title) ?>">
  </div>
  <div class="mb-3">
    <label for="body" class="form-label">Body</label>
    <textarea class="form-control" id="body" name="body" rows="8"><?= e($body) ?></textarea>
  </div>
  <div class="mb-3">
    <label for="tags" class="form-label">Tags (optional)</label>
    <input type="text" class="form-control" id="tags" name="tags" value="<?= e($tagsRaw) ?>"
           placeholder="php, mysql, beginner">
    <div class="form-text">Comma-separated, up to 5. Letters, numbers and hyphens only.</div>
  </div>
  <button class="btn btn-primary"><?= $editId ? 'Save changes' : 'Publish' ?></button>
  <a class="btn btn-link" href="index.php">Cancel</a>
</form>

<?php require 'footer.php'; ?>

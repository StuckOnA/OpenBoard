<?php
// header.php - top of every page: <head>, navbar, flash message.
// The including page sets $pageTitle first.
$user  = current_user();
$flash = $_SESSION['flash'] ?? null;   // one-time message, e.g. ['success', 'Post created.']
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(($pageTitle ?? 'Home') . ' - OpenBoard') ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-dark bg-dark mb-4">
  <div class="container flex-wrap gap-2" style="max-width: 800px;">
    <a class="navbar-brand" href="index.php">OpenBoard</a>
    <div class="d-flex flex-wrap align-items-center gap-2">
      <?php if ($user): ?>
        <?php if (is_admin()): ?>
          <a class="btn btn-warning btn-sm" href="admin.php">Admin</a>
        <?php endif; ?>
        <a class="btn btn-primary btn-sm" href="create.php">New Post</a>
        <a class="btn btn-outline-light btn-sm" href="profile.php?id=<?= (int)$user['id'] ?>">@<?= e($user['username']) ?></a>
        <form method="post" action="actions.php" class="d-inline">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="logout">
          <button class="btn btn-outline-secondary btn-sm">Log out</button>
        </form>
      <?php else: ?>
        <a class="btn btn-outline-light btn-sm" href="login.php">Log in</a>
        <a class="btn btn-primary btn-sm" href="register.php">Register</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<div class="container" style="max-width: 800px;">
<?php if ($flash): ?>
  <div class="alert alert-<?= e($flash[0]) ?>"><?= e($flash[1]) ?></div>
<?php endif; ?>
